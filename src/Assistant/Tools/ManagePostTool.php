<?php
namespace BrixlabAssistant\Assistant\Tools;

defined('ABSPATH') || exit;

use BrixlabAssistant\Assistant\AbstractAssistantTool;

class ManagePostTool extends AbstractAssistantTool
{
    public function getName(): string
    {
        return 'manage_post';
    }

    public function getDescription(): string
    {
        return 'List, create, update, or trash WordPress posts and pages. Use "list" to search and discover existing content before modifying it. For update/trash, provide either post_id OR post_name (title) — an ID is not required.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['list', 'create', 'update', 'trash'],
                    'description' => 'The action: "list" searches posts/pages, "create" makes a new one, "update" modifies existing, "trash" moves to trash.',
                ],
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'The post ID. Optional — if not provided, the post will be looked up by post_name.',
                ],
                'post_name' => [
                    'type'        => 'string',
                    'description' => 'The title of the post/page to find. Used when post_id is not provided.',
                ],
                'title' => [
                    'type'        => 'string',
                    'description' => 'The post title (for create, or the new title for update).',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'The post content (HTML).',
                ],
                'status' => [
                    'type'        => 'string',
                    'description' => 'Post status. For "list": filter by status (default: any). For "create": initial status (default: publish).',
                ],
                'post_type' => [
                    'type'        => 'string',
                    'description' => 'Post type. For "list": filter by type (default: any). For "create": the type to create (default: post). Use "page" for pages.',
                ],
                'search' => [
                    'type'        => 'string',
                    'description' => 'Search keyword for "list" action. Searches in post titles and content.',
                ],
                'per_page' => [
                    'type'        => 'integer',
                    'description' => 'Number of results to return for "list" action (default: 20, max: 50).',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function isReadOnly(array $params): bool
    {
        return isset($params['action']) && $params['action'] === 'list';
    }

    private function getPostTypeLabel(string $post_type): string
    {
        $obj = get_post_type_object($post_type);
        return $obj ? $obj->labels->singular_name : ucfirst($post_type);
    }

    /**
     * Resolve a post by ID or name. Returns the post or null.
     */
    private function resolvePost(array $params): ?\WP_Post
    {
        if (!empty($params['post_id'])) {
            return get_post((int) $params['post_id']);
        }

        if (!empty($params['post_name'])) {
            $post_type = isset($params['post_type']) ? $params['post_type'] : 'any';
            $found = get_posts([
                'title'          => $params['post_name'],
                'post_type'      => $post_type,
                'post_status'    => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => 1,
                'no_found_rows'  => true,
                'orderby'        => 'post_status',
                'order'          => 'ASC',
            ]);
            return !empty($found) ? $found[0] : null;
        }

        return null;
    }

    public function preview(array $params): array
    {
        $action = $params['action'];

        if ($action === 'list') {
            $results = $this->queryPosts($params);
            $changes = [];
            foreach ($results as $post) {
                $label = $this->getPostTypeLabel($post->post_type);
                $changes[] = ['type' => 'update', 'label' => $label . ' (ID: ' . $post->ID . ')', 'from' => $post->post_status, 'to' => $post->post_title];
            }
            if (empty($changes)) {
                $changes[] = ['type' => 'update', 'label' => 'No results', 'from' => '', 'to' => 'No matching posts found'];
            }
            return ['title' => 'Found ' . count($results) . ' result(s)', 'changes' => $changes];
        }

        if ($action === 'create') {
            $title = isset($params['title']) ? $params['title'] : 'Untitled';
            $type_slug = isset($params['post_type']) ? $params['post_type'] : 'post';
            $label = $this->getPostTypeLabel($type_slug);

            return [
                'title'   => 'Create ' . strtolower($label) . ': ' . $title,
                'changes' => [['type' => 'create', 'label' => $title, 'from' => '', 'to' => $label . ' (publish)']],
            ];
        }

        $post = $this->resolvePost($params);

        if ($action === 'update') {
            if (!$post) {
                $identifier = !empty($params['post_name']) ? '"' . $params['post_name'] . '"' : '#' . ($params['post_id'] ?? 0);
                return [
                    'title'   => 'Update post ' . $identifier,
                    'changes' => [['type' => 'error', 'label' => 'Not found', 'from' => $identifier, 'to' => 'Could not find this post']],
                ];
            }

            $label = $this->getPostTypeLabel($post->post_type);
            $changes = [];

            if (isset($params['title'])) {
                $changes[] = ['type' => 'update', 'label' => 'Title', 'from' => $post->post_title, 'to' => $params['title']];
            }
            if (isset($params['content'])) {
                $changes[] = ['type' => 'update', 'label' => 'Content', 'from' => wp_trim_words($post->post_content, 20, '...'), 'to' => wp_trim_words($params['content'], 20, '...')];
            }
            if (isset($params['status'])) {
                $changes[] = ['type' => 'update', 'label' => 'Status', 'from' => $post->post_status, 'to' => $params['status']];
            }

            return ['title' => 'Update ' . strtolower($label) . ': ' . $post->post_title, 'changes' => $changes];
        }

        // trash
        $title = $post ? $post->post_title : (!empty($params['post_name']) ? $params['post_name'] : '#' . ($params['post_id'] ?? 0));
        $label = $post ? $this->getPostTypeLabel($post->post_type) : 'Post';

        return [
            'title'   => 'Trash ' . strtolower($label) . ': ' . $title,
            'changes' => [['type' => 'delete', 'label' => $title, 'from' => $label . ', ' . ($post ? $post->post_status : ''), 'to' => 'trash']],
        ];
    }

    /**
     * Query posts for the list action.
     */
    private function queryPosts(array $params): array
    {
        $args = [
            'posts_per_page' => min(isset($params['per_page']) ? (int) $params['per_page'] : 20, 50),
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $args['post_type'] = !empty($params['post_type']) ? $params['post_type'] : 'any';
        $args['post_status'] = !empty($params['status']) ? $params['status'] : ['publish', 'draft', 'pending', 'private'];

        if (!empty($params['search'])) {
            $args['s'] = $params['search'];
        }

        return get_posts($args);
    }

    public function execute(array $params): array
    {
        $action = $params['action'];

        if ($action === 'list') {
            $results = $this->queryPosts($params);
            if (empty($results)) {
                return ['success' => true, 'message' => 'No matching posts found.'];
            }
            $lines = [];
            foreach ($results as $post) {
                $label = $this->getPostTypeLabel($post->post_type);
                $lines[] = '- ' . $post->post_title . ' (ID: ' . $post->ID . ', ' . $label . ', ' . $post->post_status . ')';
            }
            return ['success' => true, 'message' => 'Found ' . count($results) . " result(s):\n" . implode("\n", $lines)];
        }

        if ($action === 'create') {
            $post_data = [
                'post_title'   => isset($params['title']) ? $params['title'] : 'Untitled',
                'post_content' => isset($params['content']) ? $params['content'] : '',
                'post_status'  => isset($params['status']) ? $params['status'] : 'publish',
                'post_type'    => isset($params['post_type']) ? $params['post_type'] : 'post',
            ];

            $label = $this->getPostTypeLabel($post_data['post_type']);
            $post_id = wp_insert_post($post_data, true);

            if (is_wp_error($post_id)) {
                return ['success' => false, 'message' => 'Failed to create ' . strtolower($label) . ': ' . $post_id->get_error_message()];
            }

            return [
                'success' => true,
                'message' => $label . ' "' . $post_data['post_title'] . '" created successfully (ID: ' . $post_id . ').',
                'link'    => ['url' => admin_url('post.php?post=' . $post_id . '&action=edit'), 'label' => 'Edit ' . strtolower($label)],
            ];
        }

        $post = $this->resolvePost($params);

        if ($action === 'update') {
            if (!$post) {
                $identifier = !empty($params['post_name']) ? '"' . $params['post_name'] . '"' : '#' . ($params['post_id'] ?? 0);
                return ['success' => false, 'message' => 'Post ' . $identifier . ' not found.'];
            }

            $label = $this->getPostTypeLabel($post->post_type);
            $post_data = ['ID' => $post->ID];
            if (isset($params['title'])) $post_data['post_title'] = $params['title'];
            if (isset($params['content'])) $post_data['post_content'] = $params['content'];
            if (isset($params['status'])) $post_data['post_status'] = $params['status'];

            $result = wp_update_post($post_data, true);

            if (is_wp_error($result)) {
                return ['success' => false, 'message' => 'Failed to update ' . strtolower($label) . ': ' . $result->get_error_message()];
            }

            return [
                'success' => true,
                'message' => $label . ' "' . $post->post_title . '" updated successfully.',
                'link'    => ['url' => admin_url('post.php?post=' . $post->ID . '&action=edit'), 'label' => 'Edit ' . strtolower($label)],
            ];
        }

        // trash
        if (!$post) {
            $identifier = !empty($params['post_name']) ? '"' . $params['post_name'] . '"' : '#' . ($params['post_id'] ?? 0);
            return ['success' => false, 'message' => 'Post ' . $identifier . ' not found.'];
        }

        $label = $this->getPostTypeLabel($post->post_type);
        $result = wp_trash_post($post->ID);

        if (!$result) {
            return ['success' => false, 'message' => 'Failed to trash ' . strtolower($label) . ' "' . $post->post_title . '".'];
        }

        return ['success' => true, 'message' => $label . ' "' . $post->post_title . '" moved to trash.'];
    }
}
