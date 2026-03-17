<?php
/**
 * Plugin Name: BrixLab Assistant
 * Description: AI-powered WordPress assistant — manage your site with natural language, powered by tool-based AI.
 * Version: 0.1.0
 * Author: BrixLab
 * Author URI: https://brixlab.dev
 * Text Domain: brixlab-assistant
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

// Constants
if (!defined('BRIXLAB_ASSISTANT_PLUGIN_FILE')) {
    define('BRIXLAB_ASSISTANT_PLUGIN_FILE', __FILE__);
}
if (!defined('BRIXLAB_ASSISTANT_DIR')) {
    define('BRIXLAB_ASSISTANT_DIR', plugin_dir_path(__FILE__));
}
if (!defined('BRIXLAB_ASSISTANT_URL')) {
    define('BRIXLAB_ASSISTANT_URL', plugin_dir_url(__FILE__));
}
if (!defined('BRIXLAB_ASSISTANT_VERSION')) {
    define('BRIXLAB_ASSISTANT_VERSION', '0.1.0');
}
if (!defined('BRIXLAB_ASSISTANT_API_BASE')) {
    define('BRIXLAB_ASSISTANT_API_BASE', defined('BRIXTE_API_BASE') ? BRIXTE_API_BASE : 'http://localhost:50444');
}

// Class loader
require_once BRIXLAB_ASSISTANT_DIR . 'src/Plugin.php';
require_once BRIXLAB_ASSISTANT_DIR . 'src/Services/License.php';
require_once BRIXLAB_ASSISTANT_DIR . 'src/Services/Settings.php';
require_once BRIXLAB_ASSISTANT_DIR . 'src/Services/AssistantService.php';

// Assistant tool system
require_once BRIXLAB_ASSISTANT_DIR . 'src/Assistant/AbstractAssistantTool.php';
require_once BRIXLAB_ASSISTANT_DIR . 'src/Assistant/CallbackTool.php';
require_once BRIXLAB_ASSISTANT_DIR . 'src/Assistant/AssistantToolRegistry.php';
require_once BRIXLAB_ASSISTANT_DIR . 'src/Assistant/Tools/UpdateOptionTool.php';
require_once BRIXLAB_ASSISTANT_DIR . 'src/Assistant/Tools/ManagePluginTool.php';
require_once BRIXLAB_ASSISTANT_DIR . 'src/Assistant/Tools/ManageMenuTool.php';
require_once BRIXLAB_ASSISTANT_DIR . 'src/Assistant/Tools/ManagePostTool.php';
require_once BRIXLAB_ASSISTANT_DIR . 'src/Assistant/Tools/ManageUserTool.php';

/**
 * Recursively sanitize a value decoded from JSON.
 *
 * @param mixed $value Decoded JSON value.
 * @return mixed Sanitized value.
 */
function brixlab_assistant_sanitize_deep( $value ) {
    if ( is_array( $value ) ) {
        return array_map( 'brixlab_assistant_sanitize_deep', $value );
    }
    if ( is_string( $value ) ) {
        return sanitize_text_field( $value );
    }
    return $value;
}

/**
 * Register a tool with the AI Assistant using a simple array config.
 *
 * This is the easiest way for third-party plugins to add tools without
 * extending AbstractAssistantTool. Call this at any time — the tool will
 * be picked up when the registry initializes (on first AJAX request).
 *
 * Example:
 *
 *   brixlab_assistant_register_tool([
 *       'name'        => 'send_notification',
 *       'description' => 'Send an email notification to the site admin.',
 *       'parameters'  => [
 *           'type'       => 'object',
 *           'properties' => [
 *               'subject' => ['type' => 'string', 'description' => 'Email subject.'],
 *               'message' => ['type' => 'string', 'description' => 'Email body.'],
 *           ],
 *           'required' => ['subject', 'message'],
 *       ],
 *       'preview' => function(array $params) {
 *           return [
 *               'title'   => 'Send notification',
 *               'changes' => [
 *                   ['type' => 'create', 'label' => 'Email', 'to' => $params['subject']],
 *               ],
 *           ];
 *       },
 *       'execute' => function(array $params) {
 *           wp_mail(get_option('admin_email'), $params['subject'], $params['message']);
 *           return ['success' => true, 'message' => 'Notification sent.'];
 *       },
 *   ]);
 *
 * @param array $config Tool configuration (name, description, parameters, preview, execute).
 */
function brixlab_assistant_register_tool( array $config ) {
    add_action('brixlab_register_assistant_tools', function( $registry ) use ( $config ) {
        $registry->register( new \BrixlabAssistant\Assistant\CallbackTool( $config ) );
    });
}

// Bootstrap
add_action('plugins_loaded', function(){
    \BrixlabAssistant\Plugin::instance()->boot();
}, 10);
