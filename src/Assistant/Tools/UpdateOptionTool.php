<?php
namespace BrixlabAssistant\Assistant\Tools;

defined('ABSPATH') || exit;

use BrixlabAssistant\Assistant\AbstractAssistantTool;

class UpdateOptionTool extends AbstractAssistantTool
{
    private const ALLOWED_OPTIONS = [
        'blogname',
        'blogdescription',
        'timezone_string',
        'date_format',
        'time_format',
        'permalink_structure',
        'posts_per_page',
        'default_comment_status',
    ];

    public function getName(): string
    {
        return 'manage_option';
    }

    public function getDescription(): string
    {
        return 'Read or update WordPress site settings. Use action "get" to read the current value, "get_all" to see all manageable settings at once, or "update" to change a value. Manageable options: site title (blogname), tagline (blogdescription), timezone, date/time format, permalink structure, posts per page, default comment status.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['get', 'get_all', 'update'],
                    'description' => 'The action: "get" reads one option, "get_all" reads all manageable options, "update" changes an option.',
                ],
                'option_name' => [
                    'type'        => 'string',
                    'enum'        => self::ALLOWED_OPTIONS,
                    'description' => 'The WordPress option name (required for "get" and "update").',
                ],
                'value' => [
                    'type'        => 'string',
                    'description' => 'The new value for the option (required for "update").',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function isReadOnly(array $params): bool
    {
        $action = isset($params['action']) ? $params['action'] : 'update';
        return in_array($action, ['get', 'get_all'], true);
    }

    public function preview(array $params): array
    {
        $action = isset($params['action']) ? $params['action'] : 'update';

        if ($action === 'get') {
            $option_name = isset($params['option_name']) ? $params['option_name'] : '';
            $current = get_option($option_name, '(not set)');
            return [
                'title'   => 'Read setting: ' . $option_name,
                'changes' => [['type' => 'update', 'label' => $option_name, 'from' => '', 'to' => (string) $current]],
            ];
        }

        if ($action === 'get_all') {
            $changes = [];
            foreach (self::ALLOWED_OPTIONS as $opt) {
                $val = get_option($opt, '(not set)');
                $changes[] = ['type' => 'update', 'label' => $opt, 'from' => '', 'to' => (string) $val];
            }
            return ['title' => 'Read all settings', 'changes' => $changes];
        }

        // update
        $option_name = isset($params['option_name']) ? $params['option_name'] : '';
        $new_value   = isset($params['value']) ? $params['value'] : '';
        $current     = get_option($option_name, '');

        return [
            'title'   => 'Update ' . $option_name,
            'changes' => [['type' => 'update', 'label' => $option_name, 'from' => (string) $current, 'to' => $new_value]],
        ];
    }

    public function execute(array $params): array
    {
        $action = isset($params['action']) ? $params['action'] : 'update';

        if ($action === 'get') {
            $option_name = isset($params['option_name']) ? $params['option_name'] : '';
            if (!in_array($option_name, self::ALLOWED_OPTIONS, true)) {
                return ['success' => false, 'message' => 'Option "' . $option_name . '" is not in the allowlist.'];
            }
            $value = get_option($option_name, '');
            return ['success' => true, 'message' => $option_name . ' = "' . $value . '"'];
        }

        if ($action === 'get_all') {
            $lines = [];
            foreach (self::ALLOWED_OPTIONS as $opt) {
                $val = get_option($opt, '(not set)');
                $lines[] = $opt . ': ' . $val;
            }
            return ['success' => true, 'message' => "Current settings:\n" . implode("\n", $lines)];
        }

        // update
        $option_name = isset($params['option_name']) ? $params['option_name'] : '';
        $new_value   = isset($params['value']) ? $params['value'] : '';

        if (!in_array($option_name, self::ALLOWED_OPTIONS, true)) {
            return ['success' => false, 'message' => 'Option "' . $option_name . '" is not in the allowlist.'];
        }

        update_option($option_name, $new_value);

        return [
            'success' => true,
            'message' => 'Option "' . $option_name . '" updated successfully.',
            'link'    => ['url' => admin_url('options-general.php'), 'label' => 'View settings'],
        ];
    }
}
