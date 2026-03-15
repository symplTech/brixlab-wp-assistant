<?php
/**
 * License Settings Page Template
 */
defined('ABSPATH') || exit;

$license = \BrixlabAssistant\Services\License::instance();

if ($license->getLicenseKey()) {
    $license->fetchStatus();
}

$isActive = $license->isActive();
$data = $license->getLicenseData();
$nonce = wp_create_nonce('brixlab_assistant_license_nonce');
?>

<div class="wrap wpb-license-wrap">
    <h1><?php esc_html_e('BrixLab Assistant License', 'brixlab-assistant'); ?></h1>

    <div class="wpb-license-card">
        <div class="wpb-license-status <?php echo esc_attr($isActive ? 'active' : 'inactive'); ?>">
            <span class="status-indicator"></span>
            <span class="status-text">
                <?php echo $isActive
                    ? esc_html__('License Active', 'brixlab-assistant')
                    : esc_html__('License Inactive', 'brixlab-assistant');
                ?>
            </span>
        </div>

        <?php if ($isActive && !empty($data)) : ?>
            <div class="wpb-license-info">
                <?php
                $licenseKey = $license->getLicenseKey();
                if (!empty($licenseKey)) :
                    $lastFive = substr($licenseKey, -5);
                ?>
                    <p><strong><?php esc_html_e('License:', 'brixlab-assistant'); ?></strong> <?php echo esc_html('•••••-' . $lastFive); ?></p>
                <?php endif; ?>
                <?php if (!empty($data['product_name'])) : ?>
                    <p><strong><?php esc_html_e('Product:', 'brixlab-assistant'); ?></strong> <?php echo esc_html($data['product_name']); ?></p>
                <?php endif; ?>
                <?php if (!empty($data['variant_name'])) : ?>
                    <p><strong><?php esc_html_e('Plan:', 'brixlab-assistant'); ?></strong> <?php echo esc_html($data['variant_name']); ?></p>
                <?php endif; ?>
                <?php if (!empty($data['customer_email'])) : ?>
                    <p><strong><?php esc_html_e('Registered to:', 'brixlab-assistant'); ?></strong> <?php echo esc_html($data['customer_email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($data['expires_at'])) : ?>
                    <p><strong><?php esc_html_e('Expires:', 'brixlab-assistant'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($data['expires_at']))); ?></p>
                <?php endif; ?>
                <?php if (!empty($data['activation_limit']) && $data['activation_limit'] > 0) : ?>
                    <p><strong><?php esc_html_e('Activations:', 'brixlab-assistant'); ?></strong>
                        <?php
                        $count = isset($data['activations_count']) ? (int) $data['activations_count'] : 0;
                        echo esc_html($count . ' / ' . $data['activation_limit']);
                        ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($data['usage_limit']) && $data['usage_limit'] > 0) : ?>
                    <p><strong><?php esc_html_e('Usage:', 'brixlab-assistant'); ?></strong>
                        <?php
                        $usage_current = isset($data['usage_current']) ? (int) $data['usage_current'] : 0;
                        echo esc_html($usage_current . ' / ' . $data['usage_limit']);
                        ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($data['usage_limit_exceeded'])) : ?>
                <div class="wpb-license-warning">
                    <strong><?php esc_html_e('Usage Limit Reached', 'brixlab-assistant'); ?></strong>
                    <p><?php esc_html_e('You have reached your usage limit for this billing period. Please upgrade your plan or wait for the next billing cycle.', 'brixlab-assistant'); ?></p>
                </div>
            <?php endif; ?>

            <form id="wpb-deactivate-form">
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                <button type="submit" class="button button-secondary">
                    <?php esc_html_e('Deactivate License', 'brixlab-assistant'); ?>
                </button>
            </form>
        <?php else : ?>
            <form id="wpb-activate-form">
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                <p>
                    <label for="wpb-license-key"><?php esc_html_e('License Key', 'brixlab-assistant'); ?></label>
                    <input type="text" id="wpb-license-key" name="license_key" class="regular-text" placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX">
                </p>
                <p>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Activate License', 'brixlab-assistant'); ?>
                    </button>
                </p>
            </form>

            <p class="description">
                <?php
                printf(
                    esc_html__('Enter your license key to activate BrixLab Assistant. Don\'t have a license? %1$sPurchase one here%2$s.', 'brixlab-assistant'),
                    '<a href="https://brixlab.dev/pricing" target="_blank" rel="noopener">',
                    '</a>'
                );
                ?>
            </p>
        <?php endif; ?>

        <div id="wpb-license-message" style="display:none;"></div>
    </div>
</div>
