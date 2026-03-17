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
        return 'Create, update, or trash a WordPress post or page. Supports setting title, content, status, and post type.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['create', 'update', 'trash'],
                    'description' => 'The action to perform on the post.',
                ],
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'The post ID (required for update and trash).',
                ],
                'title' => [
                    'type'        => 'string',
                    'description' => 'The post title.',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'The post content (HTML).',
                ],
                'status' => [
                    'type'        => 'string',
                    'description' => 'Post status (default: publish).',
                ],
                'post_type' => [
                    'type'        => 'string',
                    'description' => 'Post type (default: post).',
                ],
            ],
            'required' => ['action'],
        ];
    }

    /**
     * Get the singular label for a post type (e.g. "Page", "Post").
     */
    private function getPostTypeLabel(string $post_type): string
    {
        $obj = get_post_type_object($post_type);
        return $obj ? $obj->labels->singular_name : ucfirst($post_type);
    }

    public function preview(array $params): array
    {
        $action = $params['action'];

        if ($action === 'create') {
            $title = isset($params['title']) ? $params['title'] : 'Untitled';
            $type_slug = isset($params['post_type']) ? $params['post_type'] : 'post';
            $label = $this->getPostTypeLabel($type_slug);

            return [
                'title'   => 'Create ' . strtolower($label) . ': ' . $title,
                'changes' => [['type' => 'create', 'label' => $label, 'from' => '', 'to' => $title]],
            ];
        }

        if ($action === 'update') {
            $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;
            $post    = get_post($post_id);

            if (!$post) {
                return [
                    'title'   => 'Update post #' . $post_id,
                    'changes' => [['type' => 'error', 'label' => 'Post not found', 'from' => '', 'to' => '']],
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
        $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;
        $post    = get_post($post_id);
        $title   = $post ? $post->post_title : '#' . $post_id;
        $label   = $post ? $this->getPostTypeLabel($post->post_type) : 'Post';

        return [
            'title'   => 'Trash ' . strtolower($label) . ': ' . $title,
            'changes' => [['type' => 'delete', 'label' => $label, 'from' => $title, 'to' => 'trash']],
        ];
    }

    public function execute(array $params): array
    {
        $action = $params['action'];

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

        if ($action === 'update') {
            $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;

            if (!$post_id || !get_post($post_id)) {
                return ['success' => false, 'message' => 'Post #' . $post_id . ' not found.'];
            }

            $post_data = ['ID' => $post_id];
            if (isset($params['title'])) $post_data['post_title'] = $params['title'];
            if (isset($params['content'])) $post_data['post_content'] = $params['content'];
            if (isset($params['status'])) $post_data['post_status'] = $params['status'];

            $result = wp_update_post($post_data, true);

            if (is_wp_error($result)) {
                return ['success' => false, 'message' => 'Failed to update post: ' . $result->get_error_message()];
            }

            return [
                'success' => true,
                'message' => 'Post #' . $post_id . ' updated successfully.',
                'link'    => ['url' => admin_url('post.php?post=' . $post_id . '&action=edit'), 'label' => 'Edit post'],
            ];
        }

        // trash
        $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;

        if (!$post_id) {
            return ['success' => false, 'message' => 'post_id is required for trash action.'];
        }

        $result = wp_trash_post($post_id);

        if (!$result) {
            return ['success' => false, 'message' => 'Failed to trash post #' . $post_id . '.'];
        }

        return ['success' => true, 'message' => 'Post #' . $post_id . ' moved to trash.'];
    }
}
