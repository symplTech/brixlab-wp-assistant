<?php
namespace BrixlabAssistant;

defined('ABSPATH') || exit;

use BrixlabAssistant\Services\License;
use BrixlabAssistant\Services\Settings;
use BrixlabAssistant\Services\AssistantService;
use BrixlabAssistant\Assistant\AssistantToolRegistry;

class Plugin
{
    private static $instance;

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot()
    {
        // License management
        License::instance()->register();

        // Settings page
        Settings::instance()->register();

        // Assistant chat service
        $assistant = new AssistantService();
        $assistant->register();

        // Tool registry: lazy-initialized on first use (when AJAX fires).
        // This ensures all plugins have had a chance to hook into
        // 'brixlab_register_assistant_tools' before the registry collects tools.
        // No explicit init() call needed here.

        // Admin menu
        add_action('admin_menu', array($this, 'addAdminMenu'));
    }

    /**
     * Register the top-level admin menu and sub-pages.
     */
    public function addAdminMenu()
    {
        // Top-level menu
        add_menu_page(
            __('BrixLab Assistant', 'brixlab-assistant'),
            __('BrixLab Assistant', 'brixlab-assistant'),
            'manage_options',
            'brixlab-assistant',
            array(Settings::instance(), 'renderSettingsPage'),
            'dashicons-format-chat',
            80
        );

        // Settings sub-page (same as top-level)
        add_submenu_page(
            'brixlab-assistant',
            __('Settings', 'brixlab-assistant'),
            __('Settings', 'brixlab-assistant'),
            'manage_options',
            'brixlab-assistant',
            array(Settings::instance(), 'renderSettingsPage')
        );

        // Tools sub-page
        add_submenu_page(
            'brixlab-assistant',
            __('Tools', 'brixlab-assistant'),
            __('Tools', 'brixlab-assistant'),
            'manage_options',
            'brixlab-assistant-tools',
            array($this, 'renderToolsPage')
        );

        // License sub-page
        add_submenu_page(
            'brixlab-assistant',
            __('License', 'brixlab-assistant'),
            __('License', 'brixlab-assistant'),
            'manage_options',
            'brixlab-assistant-license',
            array($this, 'renderLicensePage')
        );
    }

    /**
     * Render the license settings page.
     */
    public function renderLicensePage()
    {
        wp_enqueue_style(
            'brixlab-assistant-license',
            BRIXLAB_ASSISTANT_URL . 'assets/css/license-settings.css',
            array(),
            BRIXLAB_ASSISTANT_VERSION
        );
        wp_enqueue_script(
            'brixlab-assistant-license',
            BRIXLAB_ASSISTANT_URL . 'assets/js/license-settings.js',
            array('jquery'),
            BRIXLAB_ASSISTANT_VERSION,
            true
        );

        wp_localize_script('brixlab-assistant-license', 'brixlabAssistantLicenseL10n', array(
            'ajaxUrl'            => admin_url('admin-ajax.php'),
            'nonce'              => wp_create_nonce('brixlab_assistant_license_nonce'),
            'activating'         => __('Activating...', 'brixlab-assistant'),
            'activationFailed'   => __('Activation failed. Please try again.', 'brixlab-assistant'),
            'connectionError'    => __('Connection error. Please try again.', 'brixlab-assistant'),
            'checking'           => __('Checking...', 'brixlab-assistant'),
            'newVersion'         => __('A new version', 'brixlab-assistant'),
            'isAvailable'        => __('is available!', 'brixlab-assistant'),
            'pluginsUrl'         => admin_url('plugins.php'),
            'goToPlugins'        => __('Go to Plugins page to update', 'brixlab-assistant'),
            'latestVersion'      => __('You are running the latest version.', 'brixlab-assistant'),
            'updateCheckFailed'  => __('Update check failed.', 'brixlab-assistant'),
            'confirmDeactivate'  => __('Are you sure you want to deactivate this license?', 'brixlab-assistant'),
            'deactivating'       => __('Deactivating...', 'brixlab-assistant'),
            'failedToDeactivate' => __('Failed to deactivate. Please try again.', 'brixlab-assistant'),
        ));

        $view = BRIXLAB_ASSISTANT_DIR . 'views/license-settings.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('License', 'brixlab-assistant') . '</h1>';
            echo '<p>' . esc_html__('License settings view not found.', 'brixlab-assistant') . '</p></div>';
        }
    }

    /**
     * Render the tools overview page.
     */
    public function renderToolsPage()
    {
        $view = BRIXLAB_ASSISTANT_DIR . 'views/tools.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('Tools', 'brixlab-assistant') . '</h1>';
            echo '<p>' . esc_html__('Tools view not found.', 'brixlab-assistant') . '</p></div>';
        }
    }
}
