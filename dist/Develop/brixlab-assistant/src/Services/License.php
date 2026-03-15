<?php
namespace BrixlabAssistant\Services;

defined('ABSPATH') || exit;

/**
 * License Service — handles license validation and management via the server API.
 *
 * License sharing: if this plugin's own key is empty, falls back to
 * Theme Builder's key (brixte_license_key) as a convenience.
 */
class License
{
    const OPTION_LICENSE_KEY = 'brixlab_assistant_license_key';
    const OPTION_LICENSE_DATA = 'brixlab_assistant_license_data';
    const OPTION_LICENSE_VALIDATED_AT = 'brixlab_assistant_license_validated_at';
    const CACHE_DURATION = 86400;

    /** @var self|null */
    private static $instance = null;

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register(): void
    {
        add_action('wp_ajax_brixlab_assistant_activate_license', array($this, 'ajaxActivateLicense'));
        add_action('wp_ajax_brixlab_assistant_deactivate_license', array($this, 'ajaxDeactivateLicense'));
        add_action('wp_ajax_brixlab_assistant_check_license', array($this, 'ajaxCheckLicense'));
    }

    private function getApiBase(): string
    {
        return defined('BRIXLAB_ASSISTANT_API_BASE') ? BRIXLAB_ASSISTANT_API_BASE : '';
    }

    public function isActive(): bool
    {
        $data = $this->getLicenseData();
        if (empty($data) || empty($data['status'])) {
            return false;
        }

        if ($data['status'] !== 'active') {
            return false;
        }

        $validatedAt = (int) get_option(self::OPTION_LICENSE_VALIDATED_AT, 0);
        $now = time();

        if ($now - $validatedAt > self::CACHE_DURATION) {
            $licenseKey = $this->getLicenseKey();
            if ($licenseKey) {
                $freshData = $this->validateWithServer($licenseKey);
                if ($freshData) {
                    $this->saveLicenseData($freshData);
                    return $freshData['status'] === 'active';
                }
            }
            return false;
        }

        return true;
    }

    /**
     * Get the stored license key (decrypted).
     * Falls back to Theme Builder's key if own key is empty.
     */
    public function getLicenseKey(): string
    {
        $encrypted = get_option(self::OPTION_LICENSE_KEY, '');
        if (!empty($encrypted)) {
            return $this->decrypt($encrypted);
        }

        // Fallback: try Theme Builder's license key
        $themeBuilderKey = get_option('brixte_license_key', '');
        if (!empty($themeBuilderKey)) {
            return $this->decrypt($themeBuilderKey);
        }

        return '';
    }

    public function getLicenseData(): array
    {
        $data = get_option(self::OPTION_LICENSE_DATA, array());
        if (is_array($data) && !empty($data)) {
            return $data;
        }

        // Fallback: try Theme Builder's license data
        $themeBuilderData = get_option('brixte_license_data', array());
        if (is_array($themeBuilderData) && !empty($themeBuilderData)) {
            return $themeBuilderData;
        }

        return array();
    }

    public function fetchStatus(): ?array
    {
        $licenseKey = $this->getLicenseKey();
        if (empty($licenseKey)) {
            return null;
        }

        $apiBase = $this->getApiBase();
        if (empty($apiBase)) {
            return null;
        }

        $response = wp_remote_get($apiBase . '/license/status?license_key=' . rawurlencode($licenseKey), array(
            'timeout' => 30,
            'headers' => array('Accept' => 'application/json'),
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $rawBody = wp_remote_retrieve_body($response);
        $body = brixlab_assistant_sanitize_deep(json_decode($rawBody, true));

        $subscription = isset($body['subscription']) ? $body['subscription'] : array();
        $usage = isset($body['usage']) ? $body['usage'] : array();
        $activations = isset($body['activations']) ? $body['activations'] : array();
        $errorCode = isset($body['error']) ? $body['error'] : null;
        $subscriptionStatus = isset($subscription['status']) ? $subscription['status'] : null;

        if ($subscriptionStatus === 'active') {
            $status = 'active';
        } elseif ($errorCode === 'USAGE_LIMIT_EXCEEDED') {
            $status = 'active';
        } elseif (isset($body['valid']) && $body['valid']) {
            $status = 'active';
        } else {
            $status = 'inactive';
        }

        $existingData = $this->getLicenseData();

        $data = array(
            'valid' => $status === 'active',
            'status' => $status,
            'plan' => isset($subscription['plan']) ? $subscription['plan'] : (isset($existingData['plan']) ? $existingData['plan'] : ''),
            'customer_email' => isset($existingData['customer_email']) ? $existingData['customer_email'] : '',
            'customer_name' => isset($existingData['customer_name']) ? $existingData['customer_name'] : '',
            'product_name' => isset($existingData['product_name']) ? $existingData['product_name'] : '',
            'variant_name' => isset($existingData['variant_name']) ? $existingData['variant_name'] : '',
            'expires_at' => isset($subscription['currentPeriodEnd']) ? $subscription['currentPeriodEnd'] : (isset($existingData['expires_at']) ? $existingData['expires_at'] : null),
            'activations_count' => isset($activations['current']) ? (int) $activations['current'] : 0,
            'activation_limit' => isset($activations['limit']) ? (int) $activations['limit'] : 0,
            'usage_current' => isset($usage['current']) ? (int) $usage['current'] : 0,
            'usage_limit' => isset($usage['limit']) ? (int) $usage['limit'] : 0,
            'usage_remaining' => isset($usage['remaining']) ? (int) $usage['remaining'] : 0,
            'usage_limit_exceeded' => $errorCode === 'USAGE_LIMIT_EXCEEDED',
        );

        if ($errorCode) {
            $data['error_code'] = $errorCode;
            $data['error_message'] = isset($body['message']) ? $body['message'] : '';
        }

        $this->saveLicenseData($data);

        return $data;
    }

    public function activate(string $licenseKey): array
    {
        $licenseKey = trim($licenseKey);
        if (empty($licenseKey)) {
            return array('success' => false, 'message' => __('License key is required.', 'brixlab-assistant'));
        }

        $activated = $this->activateOnServer($licenseKey);
        if (!$activated['success']) {
            return $activated;
        }

        $subscription = isset($activated['subscription']) ? $activated['subscription'] : array();
        $activations = isset($activated['activations']) ? $activated['activations'] : array();

        $data = array(
            'valid' => true,
            'status' => 'active',
            'plan' => isset($subscription['plan']) ? $subscription['plan'] : '',
            'customer_email' => isset($subscription['customerEmail']) ? $subscription['customerEmail'] : '',
            'customer_name' => isset($subscription['customerName']) ? $subscription['customerName'] : '',
            'product_name' => isset($subscription['productName']) ? $subscription['productName'] : '',
            'variant_name' => isset($subscription['variantName']) ? $subscription['variantName'] : '',
            'expires_at' => isset($subscription['expiresAt']) ? $subscription['expiresAt'] : null,
            'activations_count' => isset($activations['current']) ? (int) $activations['current'] : 0,
            'activation_limit' => isset($activations['limit']) ? (int) $activations['limit'] : 0,
        );

        update_option(self::OPTION_LICENSE_KEY, $this->encrypt($licenseKey));
        $this->saveLicenseData($data);

        return array('success' => true, 'message' => __('License activated successfully!', 'brixlab-assistant'), 'data' => $data);
    }

    public function deactivate(): array
    {
        $licenseKey = $this->getLicenseKey();
        if ($licenseKey) {
            $this->deactivateOnServer($licenseKey);
        }

        delete_option(self::OPTION_LICENSE_KEY);
        delete_option(self::OPTION_LICENSE_DATA);
        delete_option(self::OPTION_LICENSE_VALIDATED_AT);

        return array('success' => true, 'message' => __('License deactivated.', 'brixlab-assistant'));
    }

    private function validateWithServer(string $licenseKey): ?array
    {
        $apiBase = $this->getApiBase();
        if (empty($apiBase)) {
            return null;
        }

        $response = wp_remote_post($apiBase . '/license/validate', array(
            'timeout' => 30,
            'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
            'body' => wp_json_encode(array('license_key' => $licenseKey)),
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = brixlab_assistant_sanitize_deep(json_decode(wp_remote_retrieve_body($response), true));

        if ($statusCode >= 400 || (isset($body['valid']) && !$body['valid'])) {
            return null;
        }

        $subscription = isset($body['subscription']) ? $body['subscription'] : array();
        $activations = isset($body['activations']) ? $body['activations'] : array();

        return array(
            'valid' => isset($body['valid']) ? (bool) $body['valid'] : true,
            'status' => 'active',
            'plan' => isset($subscription['plan']) ? $subscription['plan'] : '',
            'customer_email' => isset($subscription['customerEmail']) ? $subscription['customerEmail'] : '',
            'customer_name' => isset($subscription['customerName']) ? $subscription['customerName'] : '',
            'product_name' => isset($subscription['productName']) ? $subscription['productName'] : '',
            'variant_name' => isset($subscription['variantName']) ? $subscription['variantName'] : '',
            'expires_at' => isset($subscription['expiresAt']) ? $subscription['expiresAt'] : null,
            'activations_count' => isset($activations['current']) ? (int) $activations['current'] : 0,
            'activation_limit' => isset($activations['limit']) ? (int) $activations['limit'] : 0,
        );
    }

    private function activateOnServer(string $licenseKey): array
    {
        $apiBase = $this->getApiBase();
        if (empty($apiBase)) {
            return array('success' => false, 'message' => __('Server API URL is not configured.', 'brixlab-assistant'));
        }

        $instanceName = $this->getInstanceName();

        $response = wp_remote_post($apiBase . '/license/activate', array(
            'timeout' => 30,
            'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
            'body' => wp_json_encode(array('license_key' => $licenseKey, 'instance_name' => $instanceName)),
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => __('Connection error: ', 'brixlab-assistant') . $response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = brixlab_assistant_sanitize_deep(json_decode(wp_remote_retrieve_body($response), true));

        if ($statusCode >= 400 || (isset($body['success']) && !$body['success'])) {
            $error = isset($body['error']) ? $body['error'] : __('Activation failed', 'brixlab-assistant');

            if (strpos($error, 'limit') !== false || strpos($error, 'maximum') !== false) {
                $error = __('License activation limit reached. Please deactivate the license from another site first.', 'brixlab-assistant');
            } elseif (strpos($error, 'invalid') !== false || strpos($error, 'not found') !== false) {
                $error = __('Invalid license key. Please check your key and try again.', 'brixlab-assistant');
            } elseif (strpos($error, 'expired') !== false) {
                $error = __('This license has expired.', 'brixlab-assistant');
            } elseif (strpos($error, 'disabled') !== false) {
                $error = __('This license has been disabled.', 'brixlab-assistant');
            }

            return array('success' => false, 'message' => $error);
        }

        $subscription = isset($body['subscription']) ? $body['subscription'] : array();
        $activations = isset($body['activations']) ? $body['activations'] : array();

        return array('success' => true, 'subscription' => $subscription, 'activations' => $activations);
    }

    private function deactivateOnServer(string $licenseKey): bool
    {
        $apiBase = $this->getApiBase();
        if (empty($apiBase)) {
            return false;
        }

        $response = wp_remote_post($apiBase . '/license/deactivate', array(
            'timeout' => 30,
            'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
            'body' => wp_json_encode(array('license_key' => $licenseKey)),
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
    }

    private function getInstanceName(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        return $host ? $host : 'brixlab-assistant-site';
    }

    private function saveLicenseData(array $data): void
    {
        update_option(self::OPTION_LICENSE_DATA, $data);
        update_option(self::OPTION_LICENSE_VALIDATED_AT, time());
    }

    private function encrypt(string $value): string
    {
        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $value): string
    {
        $key = $this->getEncryptionKey();
        $data = base64_decode($value);
        if (strlen($data) < 16) {
            return '';
        }
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        return $decrypted ? $decrypted : '';
    }

    private function getEncryptionKey(): string
    {
        $authKey = defined('AUTH_KEY') ? AUTH_KEY : 'wpbuilder-default-key';
        return hash('sha256', $authKey . 'wpbuilder_license', true);
    }

    // ─── AJAX Handlers ───

    public function ajaxActivateLicense(): void
    {
        check_ajax_referer('brixlab_assistant_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'brixlab-assistant'), 403);
        }

        $licenseKey = isset($_POST['license_key'])
            ? sanitize_text_field(wp_unslash($_POST['license_key']))
            : '';

        $result = $this->activate($licenseKey);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public function ajaxDeactivateLicense(): void
    {
        check_ajax_referer('brixlab_assistant_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'brixlab-assistant'), 403);
        }

        $result = $this->deactivate();
        wp_send_json_success($result);
    }

    public function ajaxCheckLicense(): void
    {
        check_ajax_referer('brixlab_assistant_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'brixlab-assistant'), 403);
        }

        wp_send_json_success(array(
            'active' => $this->isActive(),
            'data' => $this->getLicenseData(),
        ));
    }
}
