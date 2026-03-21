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
        return 'Manage WordPress navigation menus. Actions: "list_menus" to see all menus, "list_items" to see items (with hierarchy), "create", "update" (rename + assign location), "delete", "add_items" (with nested children support), "remove_items", "replace_items" (remove + add in one step), "reorder_items". Use "replace_items" when the user wants to remove items and add new ones — it combines removal (remove_all, remove_children_of, remove_after, item_ids, item_names) with adding (items array with nested children). This avoids multiple tool calls. For existing menus, provide menu_id OR menu_name. Menu items can be custom links (use url "#" for label-only groupings), pages, posts, products, or any taxonomy term — provide object_id OR object_name.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['list_menus', 'list_items', 'create', 'update', 'delete', 'add_items', 'remove_items', 'replace_items', 'reorder_items'],
                    'description' => 'Actions: "list_menus", "list_items" (read), "create", "update", "delete", "add_items", "remove_items", "replace_items" (remove then add in one step), "reorder_items". Use "replace_items" when the user wants to remove some items and add new ones in a single operation — combine any remove parameter (remove_all, remove_children_of, remove_after, item_ids, item_names) with "items" array.',
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
                    'description' => 'Menu items to add (for "add_items" or "create"). Each item can have a "children" array for nesting — children are created recursively under their parent. This allows building a full multi-level hierarchy in a single call without knowing item IDs in advance.',
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
                                'description' => 'Type of menu item. Use "custom" for custom links (or label-only groupings with url "#"), or any registered post type slug (page, post, product, etc.) or taxonomy slug (category, post_tag, product_cat, etc.). Defaults to "custom".',
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
                                'description' => 'Menu item ID of an existing parent. Not needed when using "children" array.',
                            ],
                            'children' => [
                                'type'        => 'array',
                                'description' => 'Nested child items. Same structure as items — can be nested multiple levels deep. The parent ID is set automatically.',
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
                'reorder' => [
                    'type'        => 'array',
                    'description' => 'For "reorder_items": array of reorder instructions. Each entry moves one item to a new position and/or parent. Items not mentioned keep their current position. Provide item_id or item_name, a new position (1-based order), and optionally a new parent_id (0 for top-level, or another item ID to nest under).',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'item_id' => [
                                'type'        => 'integer',
                                'description' => 'The menu item ID to move.',
                            ],
                            'item_name' => [
                                'type'        => 'string',
                                'description' => 'The menu item title to move (used if item_id is not provided).',
                            ],
                            'position' => [
                                'type'        => 'integer',
                                'description' => 'New 1-based position in the menu order.',
                            ],
                            'parent_id' => [
                                'type'        => 'integer',
                                'description' => 'New parent menu item ID. Use 0 to make it top-level, or another item ID to nest it as a child.',
                            ],
                        ],
                    ],
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

            $this->collectPreviewChanges(isset($params['items']) ? $params['items'] : [], $changes, 1);

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
            $this->collectPreviewChanges(isset($params['items']) ? $params['items'] : [], $changes, 0);

            return ['title' => 'Add ' . count($changes) . ' item(s) to ' . $menu_name, 'changes' => $changes];
        }

        if ($action === 'replace_items') {
            $menu_name = $menu ? $menu->name : $this->describeMenuIdentifier($params);
            $changes = [];

            // Show removals
            if ($menu) {
                $ids_to_remove = $this->resolveRemovalIds($menu, $params);
                $remove_count = count($ids_to_remove);
                if ($remove_count > 10) {
                    // Summarize large removals
                    $changes[] = ['type' => 'delete', 'label' => $remove_count . ' items', 'from' => 'current items', 'to' => 'removed'];
                } else {
                    foreach ($ids_to_remove as $item_id) {
                        $item_title = $this->getMenuItemTitle($item_id);
                        $changes[] = ['type' => 'delete', 'label' => $item_title, 'from' => $item_title, 'to' => 'removed'];
                    }
                }
            }

            // Show additions
            $add_changes = [];
            $this->collectPreviewChanges(isset($params['items']) ? $params['items'] : [], $add_changes, 0);
            $changes = array_merge($changes, $add_changes);

            $del_count = count(array_filter($changes, function ($c) { return $c['type'] === 'delete'; }));
            $add_count = count($add_changes);
            return ['title' => 'Replace items in ' . $menu_name . ' (-' . $del_count . ' +' . $add_count . ')', 'changes' => $changes];
        }

        if ($action === 'reorder_items') {
            return $this->previewReorderItems($menu, $params);
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

    /**
     * Preview for reorder_items.
     */
    private function previewReorderItems(?\WP_Term $menu, array $params): array
    {
        $menu_name = $menu ? $menu->name : $this->describeMenuIdentifier($params);
        $reorder = isset($params['reorder']) ? $params['reorder'] : [];
        $changes = [];

        foreach ($reorder as $instruction) {
            $item_id = isset($instruction['item_id']) ? (int) $instruction['item_id'] : 0;
            $item_name_param = isset($instruction['item_name']) ? $instruction['item_name'] : '';

            // Resolve item
            if (!$item_id && $item_name_param && $menu) {
                $resolved = $this->resolveItemIdsByName($menu->term_id, [$item_name_param]);
                $item_id = !empty($resolved) ? $resolved[0] : 0;
            }

            $item_title = $item_id ? $this->getMenuItemTitle($item_id) : ($item_name_param ?: '(unknown)');

            $details = [];
            if (isset($instruction['position'])) {
                $details[] = 'position ' . $instruction['position'];
            }
            if (isset($instruction['parent_id'])) {
                $parent_id = (int) $instruction['parent_id'];
                if ($parent_id === 0) {
                    $details[] = 'top-level';
                } else {
                    $parent_title = $this->getMenuItemTitle($parent_id);
                    $details[] = 'nested under "' . $parent_title . '"';
                }
            }

            $changes[] = [
                'type'  => 'update',
                'label' => $item_title,
                'from'  => 'current position',
                'to'    => implode(', ', $details) ?: 'no change',
            ];
        }

        if (empty($changes)) {
            $changes[] = ['type' => 'error', 'label' => 'No instructions', 'from' => '', 'to' => 'No reorder instructions provided'];
        }

        return ['title' => 'Reorder items in ' . $menu_name, 'changes' => $changes];
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
        if ($action === 'replace_items') return $this->executeReplaceItems($menu, $params);
        if ($action === 'reorder_items') return $this->executeReorderItems($menu, $params);

        return ['success' => false, 'message' => 'Unknown action: ' . $action];
    }

    private function executeCreate(array $params): array
    {
        $menu_name = isset($params['menu_name']) ? $params['menu_name'] : 'Untitled Menu';
        $menu_id   = wp_create_nav_menu($menu_name);

        if (is_wp_error($menu_id)) {
            return ['success' => false, 'message' => 'Failed to create menu: ' . $menu_id->get_error_message()];
        }

        $created = [];
        $added = $this->addItemsToMenu($menu_id, isset($params['items']) ? $params['items'] : [], 0, $created);

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

        $created = [];
        $added = $this->addItemsToMenu($menu->term_id, $items, 0, $created);

        $lines = [];
        foreach ($created as $c) {
            $lines[] = '- ' . $c['title'] . ' (ID: ' . $c['item_id'] . ')';
        }

        return [
            'success' => true,
            'message' => $added . ' item(s) added to menu "' . $menu->name . '".' . (!empty($lines) ? "\n" . implode("\n", $lines) : ''),
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

    private function executeReplaceItems(\WP_Term $menu, array $params): array
    {
        // Step 1: Remove
        $ids_to_remove = $this->resolveRemovalIds($menu, $params);
        $removed = 0;
        foreach (array_unique(array_map('intval', $ids_to_remove)) as $item_id) {
            if (wp_delete_post($item_id, true)) {
                $removed++;
            }
        }

        // Step 2: Add (with parent_item_id support for nesting under existing items)
        $parent_id = 0;
        // If removing children of an item, add new items under that same parent
        if (!empty($params['remove_children_of'])) {
            $parent_id = (int) $params['remove_children_of'];
        }

        $created = [];
        $items = isset($params['items']) ? $params['items'] : [];
        $added = $this->addItemsToMenu($menu->term_id, $items, $parent_id, $created);

        $lines = [];
        foreach ($created as $c) {
            $lines[] = '- ' . $c['title'] . ' (ID: ' . $c['item_id'] . ')';
        }

        return [
            'success' => true,
            'message' => 'Replaced items in menu "' . $menu->name . '": removed ' . $removed . ', added ' . $added . '.' . (!empty($lines) ? "\n" . implode("\n", $lines) : ''),
            'link'    => ['url' => admin_url('nav-menus.php?action=edit&menu=' . $menu->term_id), 'label' => 'Edit menu'],
        ];
    }

    private function executeReorderItems(\WP_Term $menu, array $params): array
    {
        $reorder = isset($params['reorder']) ? $params['reorder'] : [];
        if (empty($reorder)) {
            return ['success' => false, 'message' => 'No reorder instructions provided.'];
        }

        $all_items = wp_get_nav_menu_items($menu->term_id, array('post_status' => 'any'));
        if (!$all_items) {
            return ['success' => false, 'message' => 'Menu has no items to reorder.'];
        }

        // Build current order: array of item IDs in menu_order
        $ordered_ids = array_map(function ($item) { return $item->ID; }, $all_items);

        // Process instructions
        $moved = 0;
        foreach ($reorder as $instruction) {
            $item_id = isset($instruction['item_id']) ? (int) $instruction['item_id'] : 0;

            // Resolve by name
            if (!$item_id && !empty($instruction['item_name'])) {
                $resolved = $this->resolveItemIdsByName($menu->term_id, [$instruction['item_name']]);
                $item_id = !empty($resolved) ? $resolved[0] : 0;
            }

            if (!$item_id) {
                continue;
            }

            // Update parent if specified
            if (isset($instruction['parent_id'])) {
                $new_parent = (int) $instruction['parent_id'];
                update_post_meta($item_id, '_menu_item_menu_item_parent', $new_parent);

                // Also update the post field WordPress uses
                wp_update_post([
                    'ID' => $item_id,
                    'post_parent' => 0, // nav_menu_items don't use post_parent
                ]);

                // Set the menu_item_parent via the standard method
                $menu_item_data = [
                    'menu-item-parent-id' => $new_parent,
                ];
                wp_update_nav_menu_item($menu->term_id, $item_id, $menu_item_data);
            }

            // Update position if specified
            if (isset($instruction['position'])) {
                $new_position = (int) $instruction['position'];
                wp_update_post([
                    'ID'         => $item_id,
                    'menu_order' => $new_position,
                ]);
            }

            $moved++;
        }

        // Re-normalize menu_order to prevent gaps (1, 2, 3, ...)
        $refreshed_items = wp_get_nav_menu_items($menu->term_id, array('post_status' => 'any'));
        if ($refreshed_items) {
            foreach ($refreshed_items as $index => $item) {
                $expected_order = $index + 1;
                if ((int) $item->menu_order !== $expected_order) {
                    wp_update_post([
                        'ID'         => $item->ID,
                        'menu_order' => $expected_order,
                    ]);
                }
            }
        }

        return [
            'success' => true,
            'message' => $moved . ' item(s) repositioned in menu "' . $menu->name . '".',
            'link'    => ['url' => admin_url('nav-menus.php?action=edit&menu=' . $menu->term_id), 'label' => 'Edit menu'],
        ];
    }

    // ─── Helpers ───

    /**
     * Add items to a menu, supporting recursive children for multi-level hierarchy.
     *
     * @param int   $menu_id   The nav menu term ID.
     * @param array $items     Items to add (each may have a 'children' array).
     * @param int   $parent_id Parent menu item ID (0 for top-level).
     * @param array &$created  Collects created item info for the response.
     * @return int Number of items added.
     */
    private function addItemsToMenu(int $menu_id, array $items, int $parent_id = 0, array &$created = []): int
    {
        $added = 0;

        foreach ($items as $item) {
            $object_type = isset($item['object_type']) ? $item['object_type'] : 'custom';
            $object_id   = isset($item['object_id']) ? (int) $item['object_id'] : 0;
            $object_name = isset($item['object_name']) ? $item['object_name'] : '';

            // parent_item_id from the item overrides the recursive parent
            $effective_parent = isset($item['parent_item_id']) ? (int) $item['parent_item_id'] : $parent_id;

            $menu_item_data = [
                'menu-item-status'    => 'publish',
                'menu-item-parent-id' => $effective_parent,
            ];

            $resolved_title = isset($item['title']) ? $item['title'] : '';

            if ($object_type === 'custom') {
                $menu_item_data['menu-item-type']  = 'custom';
                $menu_item_data['menu-item-title'] = $resolved_title ?: 'Link';
                $menu_item_data['menu-item-url']   = isset($item['url']) ? $item['url'] : '#';
            } elseif (post_type_exists($object_type)) {
                if (!$object_id && $object_name) {
                    $post = $this->resolvePostByName($object_name, $object_type);
                    if ($post) $object_id = $post->ID;
                }
                $post = $object_id ? get_post($object_id) : null;
                if (!$post) continue;

                $menu_item_data['menu-item-type']      = 'post_type';
                $menu_item_data['menu-item-object']     = $post->post_type;
                $menu_item_data['menu-item-object-id']  = $object_id;
                $menu_item_data['menu-item-title']      = $resolved_title ?: $post->post_title;
                $menu_item_data['menu-item-url']        = get_permalink($object_id);
            } elseif (taxonomy_exists($object_type)) {
                if (!$object_id && $object_name) {
                    $term = $this->resolveTermByName($object_name, $object_type);
                    if ($term) $object_id = $term->term_id;
                }
                $term = $object_id ? get_term($object_id, $object_type) : null;
                if (!$term || is_wp_error($term)) continue;

                $menu_item_data['menu-item-type']      = 'taxonomy';
                $menu_item_data['menu-item-object']     = $object_type;
                $menu_item_data['menu-item-object-id']  = $object_id;
                $menu_item_data['menu-item-title']      = $resolved_title ?: $term->name;
                $menu_item_data['menu-item-url']        = get_term_link($term);
            } else {
                continue;
            }

            $new_item_id = wp_update_nav_menu_item($menu_id, 0, $menu_item_data);
            if (is_wp_error($new_item_id)) {
                continue;
            }

            $added++;
            $created[] = [
                'item_id' => $new_item_id,
                'title'   => $menu_item_data['menu-item-title'],
                'type'    => $object_type,
            ];

            // Recursively add children under this new item
            if (!empty($item['children']) && is_array($item['children'])) {
                $added += $this->addItemsToMenu($menu_id, $item['children'], $new_item_id, $created);
            }
        }

        return $added;
    }

    /**
     * Recursively collect preview changes from nested items.
     */
    private function collectPreviewChanges(array $items, array &$changes, int $depth): void
    {
        foreach ($items as $item) {
            $desc = $this->describeItem($item);
            $item_title = isset($item['title']) ? $item['title'] : (isset($item['object_name']) ? $item['object_name'] : $desc);
            $indent = $depth > 0 ? str_repeat('  ', $depth) : '';
            $changes[] = ['type' => 'create', 'label' => $indent . $item_title, 'from' => '', 'to' => $desc];

            if (!empty($item['children']) && is_array($item['children'])) {
                $this->collectPreviewChanges($item['children'], $changes, $depth + 1);
            }
        }
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

        if (post_type_exists($object_type)) {
            if (!$object_id && $object_name) {
                $post = $this->resolvePostByName($object_name, $object_type);
                if ($post) $object_id = $post->ID;
            }
            $post = $object_id ? get_post($object_id) : null;
            $resolved_title = $title ?: $object_name ?: ($post ? $post->post_title : '#' . $object_id);
            $type_obj = get_post_type_object($object_type);
            $type_label = $type_obj ? $type_obj->labels->singular_name : ucfirst($object_type);
            return $resolved_title . ' [' . $type_label . ']';
        }

        if (taxonomy_exists($object_type)) {
            if (!$object_id && $object_name) {
                $term = $this->resolveTermByName($object_name, $object_type);
                if ($term) $object_id = $term->term_id;
            }
            $term = $object_id ? get_term($object_id, $object_type) : null;
            $resolved_title = $title ?: $object_name ?: ($term && !is_wp_error($term) ? $term->name : '#' . $object_id);
            $tax_obj = get_taxonomy($object_type);
            $tax_label = $tax_obj ? $tax_obj->labels->singular_name : ucfirst($object_type);
            return $resolved_title . ' [' . $tax_label . ']';
        }

        return ($title ?: $object_name ?: '#' . $object_id) . ' [' . $object_type . ']';
    }
}
