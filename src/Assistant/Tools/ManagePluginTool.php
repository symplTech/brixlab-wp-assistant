<?php
namespace BrixlabAssistant\Assistant\Tools;

defined('ABSPATH') || exit;

use BrixlabAssistant\Assistant\AbstractAssistantTool;

class ManagePluginTool extends AbstractAssistantTool
{
    public function getName(): string
    {
        return 'manage_plugin';
    }

    public function getDescription(): string
    {
        return 'Activate or deactivate a WordPress plugin. Provide the plugin file path (e.g. "akismet/akismet.php") and the desired action.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'plugin' => [
                    'type'        => 'string',
                    'description' => 'Plugin file path relative to the plugins directory (e.g. "akismet/akismet.php").',
                ],
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['activate', 'deactivate'],
                    'description' => 'Whether to activate or deactivate the plugin.',
                ],
            ],
            'required' => ['plugin', 'action'],
        ];
    }

    public function preview(array $params): array
    {
        $plugin = $params['plugin'];
        $action = $params['action'];

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $is_active     = is_plugin_active($plugin);
        $current_state = $is_active ? 'active' : 'inactive';
        $new_state     = $action === 'activate' ? 'active' : 'inactive';

        return [
            'title'   => ucfirst($action) . ' plugin: ' . $plugin,
            'changes' => [
                ['type' => 'update', 'label' => $plugin, 'from' => $current_state, 'to' => $new_state],
            ],
        ];
    }

    public function execute(array $params): array
    {
        $plugin = $params['plugin'];
        $action = $params['action'];

        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ($action === 'activate') {
            $result = activate_plugin($plugin);

            if (is_wp_error($result)) {
                return ['success' => false, 'message' => 'Failed to activate plugin: ' . $result->get_error_message()];
            }

            return [
                'success' => true,
                'message' => 'Plugin "' . $plugin . '" activated successfully.',
                'link'    => ['url' => admin_url('plugins.php'), 'label' => 'View plugins'],
            ];
        }

        deactivate_plugins($plugin);

        return [
            'success' => true,
            'message' => 'Plugin "' . $plugin . '" deactivated successfully.',
            'link'    => ['url' => admin_url('plugins.php'), 'label' => 'View plugins'],
        ];
    }
}
