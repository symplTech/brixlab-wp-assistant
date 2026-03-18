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
        return 'Manage WordPress navigation menus. Actions: "list_menus" to see all menus, "list_items" to see items in a menu (with order numbers and hierarchy), "create" a menu, "update" (rename) a menu, "delete" a menu, "add_items" to a menu, "remove_items" from a menu. For existing menus, provide either menu_id OR menu_name — an ID is not required. Always use list_items before removing items so you can see the structure. For bulk removal, use remove_all (clear entire menu), remove_children_of (remove all descendants of an item), or remove_after (remove all items after a given order position). Menu items can be custom links, pages, posts, or terms — provide object_id OR object_name.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['list_menus', 'list_items', 'create', 'update', 'delete', 'add_items', 'remove_items'],
                    'description' => 'The action: "list_menus" shows all menus, "list_items" shows items in a menu with hierarchy, others modify menus.',
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
                'remove_all' => [
                    'type'        => 'boolean',
                    'description' => 'For "remove_items": remove ALL items from the menu.',
                ],
                'remove_children_of' => [
                    'type'        => 'integer',
                    'description' => 'For "remove_items": remove all children (and nested descendants) of this menu item ID.',
                ],
                'remove_after' => [
                    'type'        => 'integer',
                    'description' => 'For "remove_items": remove all items that appear after this menu item ID in the menu order (based on the list_items order numbers).',
                ],
                'location' => [
                    'type'        => 'string',
                    'description' => 'Theme menu location slug to assign the menu to.',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function isReadOnly(array $params): bool
    {
        $action = isset($params['action']) ? $params['action'] : '';
        return in_array($action, ['list_menus', 'list_items'], true);
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
        $menu_items = wp_get_nav_menu_items($menu_id, array('post_status' => 'any'));
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

    /**
     * Get the display title for a nav menu item post.
     * Nav menu items store their title in post meta, not post_title.
     */
    private function getMenuItemTitle(int $item_id): string
    {
        $post = get_post($item_id);
        if (!$post) {
            return '#' . $item_id;
        }

        // Try the nav menu item's stored title first
        if (!empty($post->post_title)) {
            return $post->post_title;
        }

        // Use wp_setup_nav_menu_item to populate the title from the linked object
        $nav_item = wp_setup_nav_menu_item($post);
        if (!empty($nav_item->title)) {
            return $nav_item->title;
        }

        // Last resort: check the linked object directly
        $object_id = (int) get_post_meta($item_id, '_menu_item_object_id', true);
        $object_type = get_post_meta($item_id, '_menu_item_type', true);

        if ($object_type === 'post_type' && $object_id) {
            $linked = get_post($object_id);
            if ($linked) return $linked->post_title;
        } elseif ($object_type === 'taxonomy' && $object_id) {
            $taxonomy = get_post_meta($item_id, '_menu_item_object', true);
            $term = get_term($object_id, $taxonomy);
            if ($term && !is_wp_error($term)) return $term->name;
        }

        return '#' . $item_id;
    }

    /**
     * Resolve all item IDs to remove based on the various removal parameters.
     * Supports: item_ids, item_names, remove_all, remove_children_of, remove_after.
     */
    private function resolveRemovalIds(\WP_Term $menu, array $params): array
    {
        $all_items = wp_get_nav_menu_items($menu->term_id, array('post_status' => 'any'));
        if (!$all_items) {
            return [];
        }

        // remove_all — every item in the menu
        if (!empty($params['remove_all'])) {
            return array_map(function ($item) { return $item->ID; }, $all_items);
        }

        // remove_children_of — all descendants of a specific item
        if (!empty($params['remove_children_of'])) {
            $parent_id = (int) $params['remove_children_of'];
            return $this->collectDescendants($all_items, $parent_id);
        }

        // remove_after — all items after a given item in menu_order
        if (!empty($params['remove_after'])) {
            $after_id = (int) $params['remove_after'];
            $found = false;
            $ids = [];
            foreach ($all_items as $item) {
                if ($found) {
                    $ids[] = $item->ID;
                }
                if ($item->ID === $after_id) {
                    $found = true;
                }
            }
            return $ids;
        }

        // Standard: item_ids + item_names
        $ids = [];
        if (!empty($params['item_ids'])) {
            $ids = array_map('intval', $params['item_ids']);
        }
        if (!empty($params['item_names'])) {
            $resolved = $this->resolveItemIdsByName($menu->term_id, $params['item_names']);
            $ids = array_merge($ids, $resolved);
        }

        return array_unique($ids);
    }

    /**
     * Collect all descendant item IDs of a given parent (recursive).
     */
    private function collectDescendants(array $all_items, int $parent_id): array
    {
        $ids = [];
        foreach ($all_items as $item) {
            if ((int) $item->menu_item_parent === $parent_id) {
                $ids[] = $item->ID;
                $ids = array_merge($ids, $this->collectDescendants($all_items, $item->ID));
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

    /**
     * Format a single menu item line with order, type, status, and ID.
     */
    private function formatItemLine(object $item, int $order, string $indent = ''): string
    {
        $type_info = '';
        if ($item->type === 'post_type') {
            $type_info = ' [' . ucfirst($item->object) . ']';
            // Check linked object status
            $linked = get_post($item->object_id);
            if ($linked && $linked->post_status !== 'publish') {
                $type_info .= ' (linked ' . $linked->post_status . ')';
            }
        } elseif ($item->type === 'taxonomy') {
            $type_info = ' [' . ucfirst($item->object) . ']';
        } elseif ($item->type === 'custom') {
            $type_info = ' [Custom: ' . $item->url . ']';
        }

        $status_info = '';
        $post = get_post($item->ID);
        if ($post && $post->post_status !== 'publish') {
            $status_info = ' {' . $post->post_status . '}';
        }

        return $indent . $order . '. ' . $item->title . $type_info . $status_info . ' (ID: ' . $item->ID . ')';
    }

    /**
     * Build a text representation of menu items with hierarchy.
     * Tracks visited items and appends orphans that the tree walk missed.
     */
    private function formatMenuItems(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        // Build parent → children map
        $children_map = [];
        $all_ids = [];
        foreach ($items as $item) {
            $parent = (int) $item->menu_item_parent;
            $children_map[$parent][] = $item;
            $all_ids[$item->ID] = true;
        }

        $lines = [];
        $visited = [];
        $order = 1;

        // Recursive walk
        $walk = function (int $parent_id, int $depth) use (&$walk, &$lines, &$visited, &$order, $children_map) {
            if (!isset($children_map[$parent_id])) {
                return;
            }
            foreach ($children_map[$parent_id] as $item) {
                if (isset($visited[$item->ID])) {
                    continue;
                }
                $visited[$item->ID] = true;
                $indent = str_repeat('  ', $depth);
                $lines[] = $this->formatItemLine($item, $order, $indent);
                $order++;
                $walk($item->ID, $depth + 1);
            }
        };

        // Start from top-level (parent = 0)
        $walk(0, 0);

        // Catch items whose parent is not 0 but also not in this menu (orphans)
        foreach ($items as $item) {
            if (isset($visited[$item->ID])) {
                continue;
            }
            $parent = (int) $item->menu_item_parent;
            $label = ($parent > 0 && !isset($all_ids[$parent])) ? '[orphaned] ' : '';
            $lines[] = $label . $this->formatItemLine($item, $order, '');
            $order++;
        }

        return $lines;
    }

    public function preview(array $params): array
    {
        $action = $params['action'];

        if ($action === 'list_menus') {
            $menus = wp_get_nav_menus();
            $locations = get_nav_menu_locations();
            $location_map = [];
            foreach ($locations as $loc => $menu_id) {
                $location_map[$menu_id][] = $loc;
            }

            $changes = [];
            foreach ($menus as $menu) {
                $locs = isset($location_map[$menu->term_id]) ? implode(', ', $location_map[$menu->term_id]) : 'none';
                $item_count = wp_get_nav_menu_items($menu->term_id, array('post_status' => 'any'));
                $count = $item_count ? count($item_count) : 0;
                $changes[] = ['type' => 'update', 'label' => $menu->name . ' (ID: ' . $menu->term_id . ')', 'from' => $count . ' items', 'to' => 'location: ' . $locs];
            }
            if (empty($changes)) {
                $changes[] = ['type' => 'update', 'label' => 'No menus', 'from' => '', 'to' => 'No navigation menus found'];
            }
            return ['title' => count($menus) . ' menu(s) found', 'changes' => $changes];
        }

        if ($action === 'list_items') {
            $menu = $this->resolveMenu($params);
            if (!$menu) {
                return ['title' => 'List menu items', 'changes' => [['type' => 'error', 'label' => 'Not found', 'from' => $this->describeMenuIdentifier($params), 'to' => 'Menu not found']]];
            }
            $items = wp_get_nav_menu_items($menu->term_id, array('post_status' => 'any'));
            $changes = [];
            if ($items) {
                $lines = $this->formatMenuItems($items);
                foreach ($lines as $line) {
                    $changes[] = ['type' => 'update', 'label' => 'Item', 'from' => '', 'to' => $line];
                }
            }
            if (empty($changes)) {
                $changes[] = ['type' => 'update', 'label' => 'Empty', 'from' => '', 'to' => 'Menu has no items'];
            }
            return ['title' => 'Items in ' . $menu->name, 'changes' => $changes];
        }

        if ($action === 'create') {
            $menu_name = isset($params['menu_name']) ? $params['menu_name'] : 'Untitled Menu';
            $changes   = [['type' => 'create', 'label' => $menu_name, 'from' => '', 'to' => 'New menu']];

            if (!empty($params['items'])) {
                foreach ($params['items'] as $item) {
                    $desc = $this->describeItem($item);
                    $item_title = isset($item['title']) ? $item['title'] : (isset($item['object_name']) ? $item['object_name'] : $desc);
                    $changes[] = ['type' => 'create', 'label' => $item_title, 'from' => '', 'to' => $desc];
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
                $changes[] = ['type' => 'update', 'label' => $current_name, 'from' => $current_name, 'to' => $new_name];
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
                'changes' => [['type' => 'delete', 'label' => $name, 'from' => $name, 'to' => 'deleted']],
            ];
        }

        if ($action === 'add_items') {
            $menu_name = $menu ? $menu->name : $this->describeMenuIdentifier($params);
            $changes = [];

            if (!empty($params['items'])) {
                foreach ($params['items'] as $item) {
                    $desc = $this->describeItem($item);
                    $item_title = isset($item['title']) ? $item['title'] : (isset($item['object_name']) ? $item['object_name'] : $desc);
                    $changes[] = ['type' => 'create', 'label' => $item_title, 'from' => '', 'to' => $desc];
                }
            }

            return ['title' => 'Add items to ' . $menu_name, 'changes' => $changes];
        }

        // remove_items
        $menu_name = $menu ? $menu->name : $this->describeMenuIdentifier($params);
        $changes = [];

        if ($menu) {
            $ids_to_remove = $this->resolveRemovalIds($menu, $params);
            foreach ($ids_to_remove as $item_id) {
                $item_title = $this->getMenuItemTitle($item_id);
                $changes[] = ['type' => 'delete', 'label' => $item_title, 'from' => $item_title, 'to' => 'removed'];
            }
            if (empty($ids_to_remove)) {
                $changes[] = ['type' => 'error', 'label' => 'No matches', 'from' => '', 'to' => 'No matching items found to remove'];
            }
        }

        $count = count(array_filter($changes, function ($c) { return $c['type'] === 'delete'; }));
        return ['title' => 'Remove ' . $count . ' item(s) from ' . $menu_name, 'changes' => $changes];
    }

    // ─── Execute ───

    public function execute(array $params): array
    {
        $action = $params['action'];

        if ($action === 'list_menus') {
            $menus = wp_get_nav_menus();
            if (empty($menus)) {
                return ['success' => true, 'message' => 'No navigation menus found.'];
            }
            $locations = get_nav_menu_locations();
            $location_map = [];
            foreach ($locations as $loc => $mid) {
                $location_map[$mid][] = $loc;
            }
            $lines = [];
            foreach ($menus as $menu) {
                $items = wp_get_nav_menu_items($menu->term_id, array('post_status' => 'any'));
                $count = $items ? count($items) : 0;
                $locs = isset($location_map[$menu->term_id]) ? ' [' . implode(', ', $location_map[$menu->term_id]) . ']' : '';
                $lines[] = '- ' . $menu->name . ' (ID: ' . $menu->term_id . ', ' . $count . ' items)' . $locs;
            }
            return ['success' => true, 'message' => count($menus) . " menu(s):\n" . implode("\n", $lines)];
        }

        if ($action === 'list_items') {
            $menu = $this->resolveMenu($params);
            if (!$menu) {
                return ['success' => false, 'message' => 'Menu ' . $this->describeMenuIdentifier($params) . ' not found.'];
            }
            $items = wp_get_nav_menu_items($menu->term_id, array('post_status' => 'any'));
            if (!$items) {
                return ['success' => true, 'message' => 'Menu "' . $menu->name . '" has no items.'];
            }
            $lines = $this->formatMenuItems($items);
            return ['success' => true, 'message' => 'Menu "' . $menu->name . '" (' . count($items) . " items):\n" . implode("\n", $lines)];
        }

        if ($action === 'create') {
            return $this->executeCreate($params);
        }

        // All other actions need an existing menu
        $menu = $this->resolveMenu($params);
        if (!$menu) {
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
        $item_ids = $this->resolveRemovalIds($menu, $params);
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
