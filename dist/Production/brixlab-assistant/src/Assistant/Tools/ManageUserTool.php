<?php
namespace BrixlabAssistant\Assistant\Tools;

defined('ABSPATH') || exit;

use BrixlabAssistant\Assistant\AbstractAssistantTool;

class ManageUserTool extends AbstractAssistantTool
{
    private static $allowed_roles = [
        'administrator', 'editor', 'author', 'contributor', 'subscriber',
    ];

    public function getName(): string
    {
        return 'manage_user';
    }

    public function getDescription(): string
    {
        return 'Create, update, or delete WordPress users. Can set username, email, display name, role, and password. Use this when the user asks to add team members, change roles, update user profiles, or remove users.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['create', 'update', 'delete'], 'description' => 'The action to perform on the user.'],
                'user_id' => ['type' => 'integer', 'description' => 'The user ID (required for update and delete).'],
                'username' => ['type' => 'string', 'description' => 'The login username (required for create).'],
                'email' => ['type' => 'string', 'description' => 'The user email address.'],
                'display_name' => ['type' => 'string', 'description' => 'The display name shown publicly.'],
                'first_name' => ['type' => 'string', 'description' => 'The user first name.'],
                'last_name' => ['type' => 'string', 'description' => 'The user last name.'],
                'role' => ['type' => 'string', 'enum' => self::$allowed_roles, 'description' => 'The user role.'],
                'password' => ['type' => 'string', 'description' => 'The user password. If omitted on create, a random password will be generated.'],
                'reassign' => ['type' => 'integer', 'description' => 'When deleting, reassign posts to this user ID.'],
            ],
            'required' => ['action'],
        ];
    }

    public function preview(array $params): array
    {
        $action = $params['action'];

        if ($action === 'create') {
            $username = isset($params['username']) ? $params['username'] : '(no username)';
            $email    = isset($params['email']) ? $params['email'] : '(no email)';
            $role     = isset($params['role']) ? $params['role'] : 'subscriber';
            $display  = isset($params['display_name']) ? $params['display_name'] : $username;

            return [
                'title'   => 'Create user: ' . $display,
                'changes' => [
                    ['type' => 'create', 'label' => 'Username', 'to' => $username],
                    ['type' => 'create', 'label' => 'Email', 'to' => $email],
                    ['type' => 'create', 'label' => 'Role', 'to' => $role],
                ],
            ];
        }

        if ($action === 'update') {
            $user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
            $user    = get_userdata($user_id);

            if (!$user) {
                return ['title' => 'Update user #' . $user_id, 'changes' => [['type' => 'update', 'label' => 'Error', 'from' => '', 'to' => 'User not found']]];
            }

            $changes = [];
            if (isset($params['email'])) $changes[] = ['type' => 'update', 'label' => 'Email', 'from' => $user->user_email, 'to' => $params['email']];
            if (isset($params['display_name'])) $changes[] = ['type' => 'update', 'label' => 'Display Name', 'from' => $user->display_name, 'to' => $params['display_name']];
            if (isset($params['first_name'])) $changes[] = ['type' => 'update', 'label' => 'First Name', 'from' => $user->first_name, 'to' => $params['first_name']];
            if (isset($params['last_name'])) $changes[] = ['type' => 'update', 'label' => 'Last Name', 'from' => $user->last_name, 'to' => $params['last_name']];
            if (isset($params['role'])) $changes[] = ['type' => 'update', 'label' => 'Role', 'from' => implode(', ', $user->roles), 'to' => $params['role']];
            if (isset($params['password'])) $changes[] = ['type' => 'update', 'label' => 'Password', 'from' => '********', 'to' => '(new password)'];

            return ['title' => 'Update user: ' . $user->display_name, 'changes' => $changes];
        }

        // delete
        $user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
        $user    = get_userdata($user_id);
        $name    = $user ? $user->display_name : '#' . $user_id;

        return [
            'title'   => 'Delete user: ' . $name,
            'changes' => [['type' => 'delete', 'label' => 'User', 'from' => $name . ($user ? ' (' . $user->user_email . ')' : ''), 'to' => 'permanently deleted']],
        ];
    }

    public function execute(array $params): array
    {
        $action = $params['action'];

        if ($action === 'create') {
            $username = isset($params['username']) ? sanitize_user($params['username']) : '';
            $email    = isset($params['email']) ? sanitize_email($params['email']) : '';

            if (empty($username)) return ['success' => false, 'message' => 'Username is required to create a user.'];
            if (empty($email) || !is_email($email)) return ['success' => false, 'message' => 'A valid email address is required to create a user.'];
            if (username_exists($username)) return ['success' => false, 'message' => 'Username "' . $username . '" already exists.'];
            if (email_exists($email)) return ['success' => false, 'message' => 'Email "' . $email . '" is already registered.'];

            $role = isset($params['role']) ? $params['role'] : 'subscriber';
            if (!in_array($role, self::$allowed_roles, true)) return ['success' => false, 'message' => 'Invalid role: ' . $role];

            $password = isset($params['password']) ? $params['password'] : wp_generate_password(16, true, true);

            $user_data = ['user_login' => $username, 'user_email' => $email, 'user_pass' => $password, 'role' => $role];
            if (isset($params['display_name'])) $user_data['display_name'] = sanitize_text_field($params['display_name']);
            if (isset($params['first_name'])) $user_data['first_name'] = sanitize_text_field($params['first_name']);
            if (isset($params['last_name'])) $user_data['last_name'] = sanitize_text_field($params['last_name']);

            $user_id = wp_insert_user($user_data);

            if (is_wp_error($user_id)) {
                return ['success' => false, 'message' => 'Failed to create user: ' . $user_id->get_error_message()];
            }

            return [
                'success' => true,
                'message' => 'User "' . $username . '" created successfully with role "' . $role . '" (ID: ' . $user_id . ').',
                'link'    => ['url' => admin_url('user-edit.php?user_id=' . $user_id), 'label' => 'Edit user'],
            ];
        }

        if ($action === 'update') {
            $user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
            if (!$user_id || !get_userdata($user_id)) return ['success' => false, 'message' => 'User #' . $user_id . ' not found.'];

            $user_data = ['ID' => $user_id];

            if (isset($params['email'])) {
                $email = sanitize_email($params['email']);
                if (!is_email($email)) return ['success' => false, 'message' => 'Invalid email address.'];
                $existing = email_exists($email);
                if ($existing && $existing !== $user_id) return ['success' => false, 'message' => 'Email "' . $email . '" is already registered to another user.'];
                $user_data['user_email'] = $email;
            }

            if (isset($params['display_name'])) $user_data['display_name'] = sanitize_text_field($params['display_name']);
            if (isset($params['first_name'])) $user_data['first_name'] = sanitize_text_field($params['first_name']);
            if (isset($params['last_name'])) $user_data['last_name'] = sanitize_text_field($params['last_name']);
            if (isset($params['password'])) $user_data['user_pass'] = $params['password'];

            if (isset($params['role'])) {
                if (!in_array($params['role'], self::$allowed_roles, true)) return ['success' => false, 'message' => 'Invalid role: ' . $params['role']];
                $user_data['role'] = $params['role'];
            }

            $result = wp_update_user($user_data);
            if (is_wp_error($result)) return ['success' => false, 'message' => 'Failed to update user: ' . $result->get_error_message()];

            return [
                'success' => true,
                'message' => 'User #' . $user_id . ' updated successfully.',
                'link'    => ['url' => admin_url('user-edit.php?user_id=' . $user_id), 'label' => 'Edit user'],
            ];
        }

        // delete
        $user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
        if (!$user_id) return ['success' => false, 'message' => 'user_id is required for delete action.'];
        if (!get_userdata($user_id)) return ['success' => false, 'message' => 'User #' . $user_id . ' not found.'];
        if ($user_id === get_current_user_id()) return ['success' => false, 'message' => 'Cannot delete the currently logged-in user.'];

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $reassign = isset($params['reassign']) ? (int) $params['reassign'] : get_current_user_id();
        $result = wp_delete_user($user_id, $reassign);

        if (!$result) return ['success' => false, 'message' => 'Failed to delete user #' . $user_id . '.'];

        return ['success' => true, 'message' => 'User #' . $user_id . ' deleted. Posts reassigned to user #' . $reassign . '.'];
    }
}
