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
        return 'Create, update, or delete WordPress users. Can set username, email, display name, role, and password. For update/delete, you can provide user_id OR username OR email to identify the user — an ID is not required.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['create', 'update', 'delete'], 'description' => 'The action to perform on the user.'],
                'user_id' => ['type' => 'integer', 'description' => 'The user ID. Optional — if not provided, the user will be looked up by username or email.'],
                'username' => ['type' => 'string', 'description' => 'The login username. Used for create, or to find an existing user when user_id is not provided.'],
                'email' => ['type' => 'string', 'description' => 'The user email address. Also used to find an existing user when user_id is not provided.'],
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

    /**
     * Resolve a user by ID, username, or email. Returns WP_User or false.
     */
    private function resolveUser(array $params)
    {
        if (!empty($params['user_id'])) {
            return get_userdata((int) $params['user_id']);
        }

        if (!empty($params['username'])) {
            $user = get_user_by('login', $params['username']);
            if ($user) return $user;
        }

        if (!empty($params['email'])) {
            $user = get_user_by('email', $params['email']);
            if ($user) return $user;
        }

        // Try display name as last resort
        if (!empty($params['display_name'])) {
            $users = get_users([
                'search'         => $params['display_name'],
                'search_columns' => ['display_name'],
                'number'         => 1,
            ]);
            if (!empty($users)) return $users[0];
        }

        return false;
    }

    /**
     * Describe which identifier was used (for error messages).
     */
    private function describeIdentifier(array $params): string
    {
        if (!empty($params['user_id'])) return '#' . $params['user_id'];
        if (!empty($params['username'])) return '"' . $params['username'] . '"';
        if (!empty($params['email'])) return '"' . $params['email'] . '"';
        if (!empty($params['display_name'])) return '"' . $params['display_name'] . '"';
        return '(unknown)';
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

        $user = $this->resolveUser($params);

        if ($action === 'update') {
            if (!$user) {
                return ['title' => 'Update user ' . $this->describeIdentifier($params), 'changes' => [['type' => 'error', 'label' => 'Not found', 'from' => $this->describeIdentifier($params), 'to' => 'Could not find this user']]];
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
        $name = $user ? $user->display_name : $this->describeIdentifier($params);

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

        $user = $this->resolveUser($params);

        if ($action === 'update') {
            if (!$user) return ['success' => false, 'message' => 'User ' . $this->describeIdentifier($params) . ' not found.'];

            $user_data = ['ID' => $user->ID];

            if (isset($params['email'])) {
                $email = sanitize_email($params['email']);
                if (!is_email($email)) return ['success' => false, 'message' => 'Invalid email address.'];
                $existing = email_exists($email);
                if ($existing && $existing !== $user->ID) return ['success' => false, 'message' => 'Email "' . $email . '" is already registered to another user.'];
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
                'message' => 'User "' . $user->display_name . '" updated successfully.',
                'link'    => ['url' => admin_url('user-edit.php?user_id=' . $user->ID), 'label' => 'Edit user'],
            ];
        }

        // delete
        if (!$user) return ['success' => false, 'message' => 'User ' . $this->describeIdentifier($params) . ' not found.'];
        if ($user->ID === get_current_user_id()) return ['success' => false, 'message' => 'Cannot delete the currently logged-in user.'];

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $reassign = isset($params['reassign']) ? (int) $params['reassign'] : get_current_user_id();
        $result = wp_delete_user($user->ID, $reassign);

        if (!$result) return ['success' => false, 'message' => 'Failed to delete user "' . $user->display_name . '".'];

        return ['success' => true, 'message' => 'User "' . $user->display_name . '" deleted. Posts reassigned to user #' . $reassign . '.'];
    }
}
