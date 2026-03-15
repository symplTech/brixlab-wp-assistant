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
        return 'update_option';
    }

    public function getDescription(): string
    {
        return 'Update a WordPress site setting. Only a predefined set of safe options can be changed: site title, tagline, timezone, date/time format, permalink structure, posts per page, and default comment status.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'option_name' => [
                    'type'        => 'string',
                    'enum'        => self::ALLOWED_OPTIONS,
                    'description' => 'The WordPress option name to update.',
                ],
                'value' => [
                    'type'        => 'string',
                    'description' => 'The new value for the option.',
                ],
            ],
            'required' => ['option_name', 'value'],
        ];
    }

    public function preview(array $params): array
    {
        $option_name = $params['option_name'];
        $new_value   = $params['value'];
        $current     = get_option($option_name, '');

        return [
            'title'   => 'Update ' . $option_name,
            'changes' => [
                ['type' => 'update', 'label' => $option_name, 'from' => $current, 'to' => $new_value],
            ],
        ];
    }

    public function execute(array $params): array
    {
        $option_name = $params['option_name'];
        $new_value   = $params['value'];

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
