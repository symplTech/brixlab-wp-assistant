<?php
namespace BrixlabAssistant\Services;

defined('ABSPATH') || exit;

/**
 * Settings Service — manages assistant visibility and role permissions.
 */
class Settings
{
    const OPTION_ALLOWED_ROLES = 'brixlab_assistant_allowed_roles';
    const OPTION_SHOW_BUBBLE = 'brixlab_assistant_show_bubble';

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
        add_action('wp_ajax_brixlab_assistant_save_settings', array($this, 'ajaxSaveSettings'));
    }

    /**
     * Check if the current user can use the assistant.
     */
    public static function canUseAssistant(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $allowedRoles = get_option(self::OPTION_ALLOWED_ROLES, array('administrator'));
        if (!is_array($allowedRoles) || empty($allowedRoles)) {
            $allowedRoles = array('administrator');
        }

        $user = wp_get_current_user();
        foreach ($user->roles as $role) {
            if (in_array($role, $allowedRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the bubble visibility setting.
     *
     * @return string 'everywhere' or 'specific_pages'
     */
    public static function getShowBubble(): string
    {
        $value = get_option(self::OPTION_SHOW_BUBBLE, 'everywhere');
        return in_array($value, array('everywhere', 'specific_pages'), true) ? $value : 'everywhere';
    }

    /**
     * Get the allowed roles.
     *
     * @return array
     */
    public static function getAllowedRoles(): array
    {
        $roles = get_option(self::OPTION_ALLOWED_ROLES, array('administrator'));
        return is_array($roles) ? $roles : array('administrator');
    }

    /**
     * Render the settings page.
     */
    public function renderSettingsPage(): void
    {
        $view = BRIXLAB_ASSISTANT_DIR . 'views/settings.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('Settings', 'brixlab-assistant') . '</h1>';
            echo '<p>' . esc_html__('Settings view not found.', 'brixlab-assistant') . '</p></div>';
        }
    }

    /**
     * AJAX handler for saving settings.
     */
    public function ajaxSaveSettings(): void
    {
        check_ajax_referer('brixlab_assistant_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'brixlab-assistant'), 403);
        }

        // Allowed roles
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array of role slugs
        $roles_raw = isset($_POST['allowed_roles']) ? wp_unslash($_POST['allowed_roles']) : array();
        $allowed_roles = array();
        if (is_array($roles_raw)) {
            foreach ($roles_raw as $role) {
                $allowed_roles[] = sanitize_text_field($role);
            }
        }
        if (empty($allowed_roles)) {
            $allowed_roles = array('administrator');
        }
        update_option(self::OPTION_ALLOWED_ROLES, $allowed_roles);

        // Show bubble
        $show_bubble = isset($_POST['show_bubble']) ? sanitize_text_field(wp_unslash($_POST['show_bubble'])) : 'everywhere';
        if (!in_array($show_bubble, array('everywhere', 'specific_pages'), true)) {
            $show_bubble = 'everywhere';
        }
        update_option(self::OPTION_SHOW_BUBBLE, $show_bubble);

        wp_send_json_success(array('message' => __('Settings saved.', 'brixlab-assistant')));
    }
}
