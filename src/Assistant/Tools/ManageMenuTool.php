<?php
namespace BrixlabAssistant\Assistant\Tools;

defined('ABSPATH') || exit;

use BrixlabAssistant\Assistant\AbstractAssistantTool;

class ManageMenuTool extends AbstractAssistantTool
{
    public function getName(): string
    {
        return 'manage_menu';
    }

    public function getDescription(): string
    {
        return 'Create a WordPress navigation menu or add items to an existing menu. Can also assign a menu to a theme location.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['create', 'add_items'],
                    'description' => 'Whether to create a new menu or add items to an existing one.',
                ],
                'menu_name' => [
                    'type'        => 'string',
                    'description' => 'Name for the new menu (required for "create" action).',
                ],
                'menu_id' => [
                    'type'        => 'integer',
                    'description' => 'ID of the existing menu (required for "add_items" action).',
                ],
                'items' => [
                    'type'        => 'array',
                    'description' => 'Menu items to add.',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Menu item label.'],
                            'url'   => ['type' => 'string', 'description' => 'Menu item URL.'],
                            'type'  => ['type' => 'string', 'description' => 'Menu item type (default "custom").'],
                        ],
                        'required' => ['title', 'url'],
                    ],
                ],
                'location' => [
                    'type'        => 'string',
                    'description' => 'Theme menu location to assign the menu to (optional).',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function preview(array $params): array
    {
        $action = $params['action'];

        if ($action === 'create') {
            $menu_name = isset($params['menu_name']) ? $params['menu_name'] : 'Untitled Menu';
            $changes   = [['type' => 'create', 'label' => 'Menu', 'from' => '', 'to' => $menu_name]];

            if (!empty($params['items'])) {
                foreach ($params['items'] as $item) {
                    $changes[] = ['type' => 'create', 'label' => 'Menu item', 'from' => '', 'to' => $item['title'] . ' (' . $item['url'] . ')'];
                }
            }

            if (!empty($params['location'])) {
                $changes[] = ['type' => 'update', 'label' => 'Theme location', 'from' => '', 'to' => $params['location']];
            }

            return ['title' => 'Create menu: ' . $menu_name, 'changes' => $changes];
        }

        // add_items
        $menu_id = isset($params['menu_id']) ? $params['menu_id'] : 0;
        $changes = [];

        if (!empty($params['items'])) {
            foreach ($params['items'] as $item) {
                $changes[] = ['type' => 'create', 'label' => 'Menu item', 'from' => '', 'to' => $item['title'] . ' (' . $item['url'] . ')'];
            }
        }

        return ['title' => 'Add items to menu #' . $menu_id, 'changes' => $changes];
    }

    public function execute(array $params): array
    {
        $action = $params['action'];

        if ($action === 'create') {
            $menu_name = isset($params['menu_name']) ? $params['menu_name'] : 'Untitled Menu';
            $menu_id   = wp_create_nav_menu($menu_name);

            if (is_wp_error($menu_id)) {
                return ['success' => false, 'message' => 'Failed to create menu: ' . $menu_id->get_error_message()];
            }

            if (!empty($params['items'])) {
                foreach ($params['items'] as $item) {
                    wp_update_nav_menu_item($menu_id, 0, [
                        'menu-item-title'  => $item['title'],
                        'menu-item-url'    => $item['url'],
                        'menu-item-type'   => isset($item['type']) ? $item['type'] : 'custom',
                        'menu-item-status' => 'publish',
                    ]);
                }
            }

            if (!empty($params['location'])) {
                $locations = get_theme_mod('nav_menu_locations', []);
                $locations[$params['location']] = $menu_id;
                set_theme_mod('nav_menu_locations', $locations);
            }

            return [
                'success' => true,
                'message' => 'Menu "' . $menu_name . '" created successfully (ID: ' . $menu_id . ').',
                'link'    => ['url' => admin_url('nav-menus.php?action=edit&menu=' . $menu_id), 'label' => 'Edit menu'],
            ];
        }

        // add_items
        $menu_id = isset($params['menu_id']) ? (int) $params['menu_id'] : 0;

        if (!$menu_id) {
            return ['success' => false, 'message' => 'menu_id is required for add_items action.'];
        }

        if (empty($params['items'])) {
            return ['success' => false, 'message' => 'No items provided to add.'];
        }

        foreach ($params['items'] as $item) {
            wp_update_nav_menu_item($menu_id, 0, [
                'menu-item-title'  => $item['title'],
                'menu-item-url'    => $item['url'],
                'menu-item-type'   => isset($item['type']) ? $item['type'] : 'custom',
                'menu-item-status' => 'publish',
            ]);
        }

        return [
            'success' => true,
            'message' => count($params['items']) . ' item(s) added to menu #' . $menu_id . '.',
            'link'    => ['url' => admin_url('nav-menus.php?action=edit&menu=' . $menu_id), 'label' => 'Edit menu'],
        ];
    }
}
