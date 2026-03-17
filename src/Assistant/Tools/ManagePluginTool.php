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
        return 'Activate or deactivate a WordPress plugin. You can provide either the plugin file path (e.g. "akismet/akismet.php") OR the plugin display name (e.g. "Akismet") — an exact file path is not required.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'plugin' => [
                    'type'        => 'string',
                    'description' => 'Plugin file path (e.g. "akismet/akismet.php") OR the plugin display name (e.g. "Akismet", "WooCommerce"). If a display name is given, the plugin file will be resolved automatically.',
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

    /**
     * Resolve a plugin file path from a display name or return as-is if already a path.
     */
    private function resolvePluginFile(string $plugin): ?string
    {
        // If it looks like a file path (contains / or .php), use as-is
        if (strpos($plugin, '/') !== false || strpos($plugin, '.php') !== false) {
            return $plugin;
        }

        // Otherwise, search by display name
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $search = strtolower(trim($plugin));

        // Exact match first
        foreach ($all_plugins as $file => $data) {
            if (strtolower($data['Name']) === $search) {
                return $file;
            }
        }

        // Partial match
        foreach ($all_plugins as $file => $data) {
            if (strpos(strtolower($data['Name']), $search) !== false) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Get the display name for a plugin file.
     */
    private function getPluginName(string $plugin_file): string
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        if (isset($all_plugins[$plugin_file])) {
            return $all_plugins[$plugin_file]['Name'];
        }

        return $plugin_file;
    }

    public function preview(array $params): array
    {
        $plugin_input = $params['plugin'];
        $action = $params['action'];

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = $this->resolvePluginFile($plugin_input);

        if (!$plugin_file) {
            return [
                'title'   => ucfirst($action) . ' plugin: ' . $plugin_input,
                'changes' => [['type' => 'error', 'label' => 'Not found', 'from' => $plugin_input, 'to' => 'Plugin not found on this site']],
            ];
        }

        $display_name  = $this->getPluginName($plugin_file);
        $is_active     = is_plugin_active($plugin_file);
        $current_state = $is_active ? 'active' : 'inactive';
        $new_state     = $action === 'activate' ? 'active' : 'inactive';

        return [
            'title'   => ucfirst($action) . ' plugin: ' . $display_name,
            'changes' => [
                ['type' => 'update', 'label' => $display_name, 'from' => $current_state, 'to' => $new_state],
            ],
        ];
    }

    public function execute(array $params): array
    {
        $plugin_input = $params['plugin'];
        $action = $params['action'];

        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = $this->resolvePluginFile($plugin_input);

        if (!$plugin_file) {
            return ['success' => false, 'message' => 'Plugin "' . $plugin_input . '" not found on this site.'];
        }

        $display_name = $this->getPluginName($plugin_file);

        if ($action === 'activate') {
            $result = activate_plugin($plugin_file);

            if (is_wp_error($result)) {
                return ['success' => false, 'message' => 'Failed to activate ' . $display_name . ': ' . $result->get_error_message()];
            }

            return [
                'success' => true,
                'message' => $display_name . ' activated successfully.',
                'link'    => ['url' => admin_url('plugins.php'), 'label' => 'View plugins'],
            ];
        }

        deactivate_plugins($plugin_file);

        return [
            'success' => true,
            'message' => $display_name . ' deactivated successfully.',
            'link'    => ['url' => admin_url('plugins.php'), 'label' => 'View plugins'],
        ];
    }
}
