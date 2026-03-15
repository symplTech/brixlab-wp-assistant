<?php
namespace BrixlabAssistant\Services;

defined('ABSPATH') || exit;

/**
 * AssistantService — AI assistant chat and tool execution endpoints + script enqueue.
 */
class AssistantService
{
    public function register()
    {
        add_action('wp_ajax_brixlab_assistant_chat', array($this, 'ajaxAssistantChat'));
        add_action('wp_ajax_brixlab_assistant_preview', array($this, 'ajaxAssistantPreview'));
        add_action('wp_ajax_brixlab_assistant_execute', array($this, 'ajaxAssistantExecute'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
    }

    /**
     * Enqueue assistant scripts and styles on admin pages.
     */
    public function enqueueScripts()
    {
        if (!Settings::canUseAssistant()) {
            return;
        }

        if (!License::instance()->isActive()) {
            return;
        }

        $version = defined('BRIXLAB_ASSISTANT_VERSION') ? BRIXLAB_ASSISTANT_VERSION : '0.1.0';

        wp_enqueue_script(
            'brixlab-assistant',
            BRIXLAB_ASSISTANT_URL . 'assets/js/assistant.js',
            array(),
            $version,
            true
        );

        wp_localize_script('brixlab-assistant', 'brixlabAssistant', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('brixlab_assistant_nonce'),
            'site_url' => site_url(),
        ));

        wp_enqueue_style(
            'brixlab-assistant',
            BRIXLAB_ASSISTANT_URL . 'assets/css/assistant.css',
            array(),
            $version
        );
    }

    /**
     * AJAX: Send a message to the AI assistant.
     */
    public function ajaxAssistantChat()
    {
        check_ajax_referer('brixlab_assistant_nonce', 'nonce');

        if (!Settings::canUseAssistant()) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        if (!License::instance()->isActive()) {
            wp_send_json_error(array('message' => 'Active license required.'), 403);
        }

        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if (empty($message)) {
            wp_send_json_error(array('message' => 'Message is required.'), 400);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string
        $history_raw = isset($_POST['history']) ? wp_unslash($_POST['history']) : '[]';
        $history     = brixlab_assistant_sanitize_deep(json_decode($history_raw, true));

        if (json_last_error() !== JSON_ERROR_NONE) {
            $history = array();
        }

        $registry = \BrixlabAssistant\Assistant\AssistantToolRegistry::instance();

        $site_context = array(
            'site_name'    => get_bloginfo('name'),
            'site_url'     => site_url(),
            'wp_version'   => get_bloginfo('version'),
            'active_theme' => get_template(),
            'admin_email'  => get_bloginfo('admin_email'),
        );

        $api_url  = BRIXLAB_ASSISTANT_API_BASE . '/assistant/chat';
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'X-License-Key' => License::instance()->getLicenseKey(),
            ),
            'body'    => wp_json_encode(array(
                'message'     => $message,
                'history'     => $history,
                'tools'       => $registry->getToolDefinitions(),
                'siteContext' => $site_context,
            )),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Failed to connect to server.'), 500);
        }

        $body        = brixlab_assistant_sanitize_deep(json_decode(wp_remote_retrieve_body($response), true));
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            $error_msg = isset($body['error']) ? $body['error'] : 'Assistant request failed';
            wp_send_json_error(array('message' => $error_msg), $status_code);
        }

        wp_send_json_success($body);
    }

    /**
     * AJAX: Preview a tool execution (read-only).
     */
    public function ajaxAssistantPreview()
    {
        check_ajax_referer('brixlab_assistant_nonce', 'nonce');

        if (!Settings::canUseAssistant()) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        if (!License::instance()->isActive()) {
            wp_send_json_error(array('message' => 'Active license required.'), 403);
        }

        $tool_name = isset($_POST['tool_name']) ? sanitize_text_field(wp_unslash($_POST['tool_name'])) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string
        $tool_input_raw = isset($_POST['tool_input']) ? wp_unslash($_POST['tool_input']) : '{}';
        $tool_input     = brixlab_assistant_sanitize_deep(json_decode($tool_input_raw, true));

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'Invalid tool_input JSON.'), 400);
        }

        $registry = \BrixlabAssistant\Assistant\AssistantToolRegistry::instance();
        $tool     = $registry->get($tool_name);

        if (!$tool) {
            wp_send_json_error(array('message' => 'Unknown tool: ' . $tool_name), 404);
        }

        $preview = $tool->preview($tool_input);
        wp_send_json_success($preview);
    }

    /**
     * AJAX: Execute a tool (side effects).
     */
    public function ajaxAssistantExecute()
    {
        check_ajax_referer('brixlab_assistant_nonce', 'nonce');

        if (!Settings::canUseAssistant()) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        if (!License::instance()->isActive()) {
            wp_send_json_error(array('message' => 'Active license required.'), 403);
        }

        $tool_name = isset($_POST['tool_name']) ? sanitize_text_field(wp_unslash($_POST['tool_name'])) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string
        $tool_input_raw = isset($_POST['tool_input']) ? wp_unslash($_POST['tool_input']) : '{}';
        $tool_input     = brixlab_assistant_sanitize_deep(json_decode($tool_input_raw, true));

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'Invalid tool_input JSON.'), 400);
        }

        $registry = \BrixlabAssistant\Assistant\AssistantToolRegistry::instance();
        $tool     = $registry->get($tool_name);

        if (!$tool) {
            wp_send_json_error(array('message' => 'Unknown tool: ' . $tool_name), 404);
        }

        $result = $tool->execute($tool_input);
        wp_send_json_success($result);
    }
}
