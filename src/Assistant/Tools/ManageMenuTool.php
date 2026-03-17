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
        return 'Manage WordPress navigation menus. Can create, update (rename), delete a menu, add items to a menu, and remove items from a menu. For existing menus, provide either menu_id OR menu_name — an ID is not required. Menu items can be custom links, existing pages, posts, or taxonomy terms (categories, tags). When adding pages/posts/terms, provide either object_id OR object_name — the item will be looked up by title if no ID is given.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['create', 'update', 'delete', 'add_items', 'remove_items'],
                    'description' => 'The action to perform.',
                ],
                'menu_name' => [
                    'type'        => 'string',
                    'description' => 'Name of the menu. For "create": the new menu name. For other actions: used to find the menu if menu_id is not provided. For "update": also sets the new name if you want to rename.',
                ],
                'new_menu_name' => [
                    'type'        => 'string',
                    'description' => 'New name for the menu (only for "update" action when renaming). If omitted, menu_name is used as the new name.',
                ],
                'menu_id' => [
                    'type'        => 'integer',
                    'description' => 'ID of the existing menu. Optional — the menu can be found by menu_name instead.',
                ],
                'items' => [
                    'type'        => 'array',
                    'description' => 'Menu items to add (for "add_items" action).',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'title' => [
                                'type'        => 'string',
                                'description' => 'Menu item label. For pages/posts/terms this overrides the default title. Required for custom links.',
                            ],
                            'url' => [
                                'type'        => 'string',
                                'description' => 'Menu item URL. Required for custom links, auto-resolved for pages/posts/terms.',
                            ],
                            'object_type' => [
                                'type'        => 'string',
                                'enum'        => ['custom', 'page', 'post', 'category', 'post_tag'],
                                'description' => 'Type of menu item. Defaults to "custom".',
                            ],
                            'object_id' => [
                                'type'        => 'integer',
                                'description' => 'ID of the page, post, or term. Optional — if not provided, object_name is used to look it up.',
                            ],
                            'object_name' => [
                                'type'        => 'string',
                                'description' => 'Title/name of the page, post, or term to link to. Used when object_id is not provided.',
                            ],
                            'parent_item_id' => [
                                'type'        => 'integer',
                                'description' => 'Menu item ID of the parent (for nested/dropdown items).',
                            ],
                        ],
                    ],
                ],
                'item_ids' => [
                    'type'        => 'array',
                    'description' => 'Menu item IDs to remove (for "remove_items" action).',
                    'items'       => ['type' => 'integer'],
                ],
                'item_names' => [
                    'type'        => 'array',
                    'description' => 'Menu item titles to remove (for "remove_items" action). Used when item_ids are not known — matches by title within the menu.',
                    'items'       => ['type' => 'string'],
                ],
                'location' => [
                    'type'        => 'string',
                    'description' => 'Theme menu location slug to assign the menu to.',
                ],
            ],
            'required' => ['action'],
        ];
    }

    // ─── Resolvers ───

    /**
     * Resolve a nav menu by ID or name.
     */
    private function resolveMenu(array $params): ?\WP_Term
    {
        if (!empty($params['menu_id'])) {
            $menu = wp_get_nav_menu_object((int) $params['menu_id']);
            return $menu ?: null;
        }

        if (!empty($params['menu_name'])) {
            $menu = wp_get_nav_menu_object($params['menu_name']);
            return $menu ?: null;
        }

        return null;
    }

    /**
     * Resolve a post/page by name for menu item linking.
     */
    private function resolvePostByName(string $name, string $post_type = 'page'): ?\WP_Post
    {
        $found = get_posts([
            'title'          => $name,
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ]);
        return !empty($found) ? $found[0] : null;
    }

    /**
     * Resolve a taxonomy term by name.
     */
    private function resolveTermByName(string $name, string $taxonomy): ?\WP_Term
    {
        $term = get_term_by('name', $name, $taxonomy);
        return $term && !is_wp_error($term) ? $term : null;
    }

    /**
     * Resolve menu item IDs from item_names within a menu.
     */
    private function resolveItemIdsByName(int $menu_id, array $names): array
    {
        $menu_items = wp_get_nav_menu_items($menu_id);
        if (!$menu_items) return [];

        $ids = [];
        $names_lower = array_map('strtolower', $names);

        foreach ($menu_items as $item) {
            if (in_array(strtolower($item->title), $names_lower, true)) {
                $ids[] = $item->ID;
            }
        }

        return $ids;
    }

    private function describeMenuIdentifier(array $params): string
    {
        if (!empty($params['menu_id'])) return '#' . $params['menu_id'];
        if (!empty($params['menu_name'])) return '"' . $params['menu_name'] . '"';
        return '(unknown)';
    }

    // ─── Preview ───

    public function preview(array $params): array
    {
        $action = $params['action'];

        if ($action === 'create') {
            $menu_name = isset($params['menu_name']) ? $params['menu_name'] : 'Untitled Menu';
            $changes   = [['type' => 'create', 'label' => 'Menu', 'from' => '', 'to' => $menu_name]];

            if (!empty($params['items'])) {
                foreach ($params['items'] as $item) {
                    $changes[] = ['type' => 'create', 'label' => 'Menu item', 'from' => '', 'to' => $this->describeItem($item)];
                }
            }

            if (!empty($params['location'])) {
                $changes[] = ['type' => 'update', 'label' => 'Theme location', 'from' => '', 'to' => $params['location']];
            }

            return ['title' => 'Create menu: ' . $menu_name, 'changes' => $changes];
        }

        $menu = $this->resolveMenu($params);

        if ($action === 'update') {
            $current_name = $menu ? $menu->name : $this->describeMenuIdentifier($params);
            $changes = [];

            $new_name = !empty($params['new_menu_name']) ? $params['new_menu_name'] : (!empty($params['menu_name']) && $menu && $params['menu_name'] !== $menu->name ? $params['menu_name'] : '');
            if ($new_name) {
                $changes[] = ['type' => 'update', 'label' => 'Menu name', 'from' => $current_name, 'to' => $new_name];
            }
            if (!empty($params['location'])) {
                $changes[] = ['type' => 'update', 'label' => 'Theme location', 'from' => '', 'to' => $params['location']];
            }

            return ['title' => 'Update menu: ' . $current_name, 'changes' => $changes];
        }

        if ($action === 'delete') {
            $name = $menu ? $menu->name : $this->describeMenuIdentifier($params);
            return [
                'title'   => 'Delete menu: ' . $name,
                'changes' => [['type' => 'delete', 'label' => 'Menu', 'from' => $name, 'to' => 'deleted']],
            ];
        }

        if ($action === 'add_items') {
            $menu_name = $menu ? $menu->name : $this->describeMenuIdentifier($params);
            $changes = [];

            if (!empty($params['items'])) {
                foreach ($params['items'] as $item) {
                    $changes[] = ['type' => 'create', 'label' => 'Menu item', 'from' => '', 'to' => $this->describeItem($item)];
                }
            }

            return ['title' => 'Add items to ' . $menu_name, 'changes' => $changes];
        }

        // remove_items
        $menu_name = $menu ? $menu->name : $this->describeMenuIdentifier($params);
        $changes = [];

        // Resolve by IDs
        $item_ids = isset($params['item_ids']) ? $params['item_ids'] : [];
        foreach ($item_ids as $item_id) {
            $post = get_post((int) $item_id);
            $label = $post ? $post->post_title : '#' . $item_id;
            $changes[] = ['type' => 'delete', 'label' => 'Menu item', 'from' => $label, 'to' => 'removed'];
        }

        // Resolve by names
        if ($menu && !empty($params['item_names'])) {
            $resolved_ids = $this->resolveItemIdsByName($menu->term_id, $params['item_names']);
            foreach ($resolved_ids as $item_id) {
                $post = get_post($item_id);
                $label = $post ? $post->post_title : '#' . $item_id;
                $changes[] = ['type' => 'delete', 'label' => 'Menu item', 'from' => $label, 'to' => 'removed'];
            }
            // Show names that couldn't be resolved
            $resolved_titles = [];
            foreach ($resolved_ids as $rid) {
                $p = get_post($rid);
                if ($p) $resolved_titles[] = strtolower($p->post_title);
            }
            foreach ($params['item_names'] as $name) {
                if (!in_array(strtolower($name), $resolved_titles, true)) {
                    $changes[] = ['type' => 'error', 'label' => 'Not found', 'from' => $name, 'to' => 'Item not found in menu'];
                }
            }
        }

        return ['title' => 'Remove items from ' . $menu_name, 'changes' => $changes];
    }

    // ─── Execute ───

    public function execute(array $params): array
    {
        $action = $params['action'];

        if ($action === 'create') {
            return $this->executeCreate($params);
        }

        // All other actions need an existing menu
        $menu = $this->resolveMenu($params);
        if (!$menu && $action !== 'create') {
            return ['success' => false, 'message' => 'Menu ' . $this->describeMenuIdentifier($params) . ' not found.'];
        }

        if ($action === 'update') return $this->executeUpdate($menu, $params);
        if ($action === 'delete') return $this->executeDelete($menu);
        if ($action === 'add_items') return $this->executeAddItems($menu, $params);
        if ($action === 'remove_items') return $this->executeRemoveItems($menu, $params);

        return ['success' => false, 'message' => 'Unknown action: ' . $action];
    }

    private function executeCreate(array $params): array
    {
        $menu_name = isset($params['menu_name']) ? $params['menu_name'] : 'Untitled Menu';
        $menu_id   = wp_create_nav_menu($menu_name);

        if (is_wp_error($menu_id)) {
            return ['success' => false, 'message' => 'Failed to create menu: ' . $menu_id->get_error_message()];
        }

        $added = $this->addItemsToMenu($menu_id, isset($params['items']) ? $params['items'] : []);

        if (!empty($params['location'])) {
            $this->assignLocation($menu_id, $params['location']);
        }

        return [
            'success' => true,
            'message' => 'Menu "' . $menu_name . '" created' . ($added > 0 ? ' with ' . $added . ' item(s)' : '') . '.',
            'link'    => ['url' => admin_url('nav-menus.php?action=edit&menu=' . $menu_id), 'label' => 'Edit menu'],
        ];
    }

    private function executeUpdate(\WP_Term $menu, array $params): array
    {
        $new_name = !empty($params['new_menu_name']) ? $params['new_menu_name'] : '';
        if (!$new_name && !empty($params['menu_name']) && $params['menu_name'] !== $menu->name) {
            $new_name = $params['menu_name'];
        }

        if ($new_name) {
            $result = wp_update_nav_menu_object($menu->term_id, ['menu-name' => $new_name]);
            if (is_wp_error($result)) {
                return ['success' => false, 'message' => 'Failed to rename menu: ' . $result->get_error_message()];
            }
        }

        if (!empty($params['location'])) {
            $this->assignLocation($menu->term_id, $params['location']);
        }

        $display_name = $new_name ?: $menu->name;

        return [
            'success' => true,
            'message' => 'Menu "' . $display_name . '" updated.',
            'link'    => ['url' => admin_url('nav-menus.php?action=edit&menu=' . $menu->term_id), 'label' => 'Edit menu'],
        ];
    }

    private function executeDelete(\WP_Term $menu): array
    {
        $name = $menu->name;
        $result = wp_delete_nav_menu($menu->term_id);

        if (is_wp_error($result) || !$result) {
            return ['success' => false, 'message' => 'Failed to delete menu "' . $name . '".'];
        }

        return ['success' => true, 'message' => 'Menu "' . $name . '" deleted.'];
    }

    private function executeAddItems(\WP_Term $menu, array $params): array
    {
        $items = isset($params['items']) ? $params['items'] : [];
        if (empty($items)) {
            return ['success' => false, 'message' => 'No items provided to add.'];
        }

        $added = $this->addItemsToMenu($menu->term_id, $items);

        return [
            'success' => true,
            'message' => $added . ' item(s) added to menu "' . $menu->name . '".',
            'link'    => ['url' => admin_url('nav-menus.php?action=edit&menu=' . $menu->term_id), 'label' => 'Edit menu'],
        ];
    }

    private function executeRemoveItems(\WP_Term $menu, array $params): array
    {
        $item_ids = isset($params['item_ids']) ? $params['item_ids'] : [];

        // Also resolve names to IDs
        if (!empty($params['item_names'])) {
            $resolved = $this->resolveItemIdsByName($menu->term_id, $params['item_names']);
            $item_ids = array_merge($item_ids, $resolved);
        }

        $item_ids = array_unique(array_map('intval', $item_ids));

        if (empty($item_ids)) {
            return ['success' => false, 'message' => 'No matching menu items found to remove.'];
        }

        $removed = 0;
        foreach ($item_ids as $item_id) {
            if (wp_delete_post($item_id, true)) {
                $removed++;
            }
        }

        return [
            'success' => true,
            'message' => $removed . ' item(s) removed from menu "' . $menu->name . '".',
            'link'    => ['url' => admin_url('nav-menus.php?action=edit&menu=' . $menu->term_id), 'label' => 'Edit menu'],
        ];
    }

    // ─── Helpers ───

    private function addItemsToMenu(int $menu_id, array $items): int
    {
        $added = 0;

        foreach ($items as $item) {
            $object_type = isset($item['object_type']) ? $item['object_type'] : 'custom';
            $object_id   = isset($item['object_id']) ? (int) $item['object_id'] : 0;
            $object_name = isset($item['object_name']) ? $item['object_name'] : '';
            $parent_id   = isset($item['parent_item_id']) ? (int) $item['parent_item_id'] : 0;

            $menu_item_data = [
                'menu-item-status'    => 'publish',
                'menu-item-parent-id' => $parent_id,
            ];

            if ($object_type === 'custom') {
                $menu_item_data['menu-item-type']  = 'custom';
                $menu_item_data['menu-item-title'] = isset($item['title']) ? $item['title'] : '';
                $menu_item_data['menu-item-url']   = isset($item['url']) ? $item['url'] : '#';
            } elseif ($object_type === 'page' || $object_type === 'post') {
                // Resolve by name if no ID
                if (!$object_id && $object_name) {
                    $post = $this->resolvePostByName($object_name, $object_type);
                    if ($post) $object_id = $post->ID;
                }
                $post = $object_id ? get_post($object_id) : null;
                if (!$post) continue;

                $menu_item_data['menu-item-type']      = 'post_type';
                $menu_item_data['menu-item-object']     = $post->post_type;
                $menu_item_data['menu-item-object-id']  = $object_id;
                $menu_item_data['menu-item-title']      = isset($item['title']) ? $item['title'] : $post->post_title;
                $menu_item_data['menu-item-url']        = get_permalink($object_id);
            } else {
                // Taxonomy term — resolve by name if no ID
                if (!$object_id && $object_name) {
                    $term = $this->resolveTermByName($object_name, $object_type);
                    if ($term) $object_id = $term->term_id;
                }
                $term = $object_id ? get_term($object_id, $object_type) : null;
                if (!$term || is_wp_error($term)) continue;

                $menu_item_data['menu-item-type']      = 'taxonomy';
                $menu_item_data['menu-item-object']     = $object_type;
                $menu_item_data['menu-item-object-id']  = $object_id;
                $menu_item_data['menu-item-title']      = isset($item['title']) ? $item['title'] : $term->name;
                $menu_item_data['menu-item-url']        = get_term_link($term);
            }

            $result = wp_update_nav_menu_item($menu_id, 0, $menu_item_data);
            if (!is_wp_error($result)) {
                $added++;
            }
        }

        return $added;
    }

    private function assignLocation(int $menu_id, string $location): void
    {
        $locations = get_theme_mod('nav_menu_locations', []);
        $locations[$location] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);
    }

    private function describeItem(array $item): string
    {
        $object_type = isset($item['object_type']) ? $item['object_type'] : 'custom';
        $title = isset($item['title']) ? $item['title'] : '';
        $object_id = isset($item['object_id']) ? (int) $item['object_id'] : 0;
        $object_name = isset($item['object_name']) ? $item['object_name'] : '';

        if ($object_type === 'custom') {
            $url = isset($item['url']) ? $item['url'] : '#';
            return ($title ?: 'Custom link') . ' (' . $url . ')';
        }

        if ($object_type === 'page' || $object_type === 'post') {
            if (!$object_id && $object_name) {
                $post = $this->resolvePostByName($object_name, $object_type);
                if ($post) $object_id = $post->ID;
            }
            $post = $object_id ? get_post($object_id) : null;
            $resolved_title = $title ?: $object_name ?: ($post ? $post->post_title : '#' . $object_id);
            $type_obj = $post ? get_post_type_object($post->post_type) : null;
            $type_label = $type_obj ? $type_obj->labels->singular_name : ucfirst($object_type);
            return $resolved_title . ' [' . $type_label . ']';
        }

        // Taxonomy
        if (!$object_id && $object_name) {
            $term = $this->resolveTermByName($object_name, $object_type);
            if ($term) $object_id = $term->term_id;
        }
        $term = $object_id ? get_term($object_id, $object_type) : null;
        $resolved_title = $title ?: $object_name ?: ($term && !is_wp_error($term) ? $term->name : '#' . $object_id);
        $tax_label = $object_type === 'category' ? 'Category' : ($object_type === 'post_tag' ? 'Tag' : ucfirst($object_type));
        return $resolved_title . ' [' . $tax_label . ']';
    }
}
