<?php
/**
 * Settings Page Template
 */
defined('ABSPATH') || exit;

$allowedRoles = \BrixlabAssistant\Services\Settings::getAllowedRoles();
$showBubble = \BrixlabAssistant\Services\Settings::getShowBubble();
$nonce = wp_create_nonce('brixlab_assistant_settings_nonce');

// Get all available roles
$wp_roles = wp_roles();
$all_roles = $wp_roles->get_names();
?>

<div class="wrap">
    <h1><?php esc_html_e('BrixLab Assistant Settings', 'brixlab-assistant'); ?></h1>

    <form id="brixlab-assistant-settings-form" method="post">
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Allowed Roles', 'brixlab-assistant'); ?></th>
                <td>
                    <fieldset>
                        <?php foreach ($all_roles as $role_slug => $role_name) : ?>
                            <label>
                                <input
                                    type="checkbox"
                                    name="allowed_roles[]"
                                    value="<?php echo esc_attr($role_slug); ?>"
                                    <?php checked(in_array($role_slug, $allowedRoles, true)); ?>
                                    <?php disabled($role_slug === 'administrator'); ?>
                                >
                                <?php echo esc_html(translate_user_role($role_name)); ?>
                            </label><br>
                        <?php endforeach; ?>
                        <!-- Always include administrator even if checkbox is disabled -->
                        <input type="hidden" name="allowed_roles[]" value="administrator">
                    </fieldset>
                    <p class="description"><?php esc_html_e('Select which user roles can access the AI Assistant. Administrators always have access.', 'brixlab-assistant'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Show Assistant Bubble', 'brixlab-assistant'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="show_bubble" value="everywhere" <?php checked($showBubble, 'everywhere'); ?>>
                            <?php esc_html_e('On all admin pages', 'brixlab-assistant'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="show_bubble" value="specific_pages" <?php checked($showBubble, 'specific_pages'); ?>>
                            <?php esc_html_e('Only on specific pages (dashboard)', 'brixlab-assistant'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'brixlab-assistant'); ?></button>
        </p>
    </form>

    <div id="brixlab-assistant-settings-message" style="display:none;"></div>
</div>

<script>
jQuery(function($) {
    $('#brixlab-assistant-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        var $msg = $('#brixlab-assistant-settings-message');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text('Saving...');
        $msg.hide().removeClass('notice-success notice-error');

        $.post(ajaxurl, $(this).serialize() + '&action=brixlab_assistant_save_settings')
        .done(function(resp) {
            if (resp.success) {
                $msg.addClass('notice notice-success').html('<p>' + resp.data.message + '</p>').show();
            } else {
                $msg.addClass('notice notice-error').html('<p>' + (resp.data || 'Save failed.') + '</p>').show();
            }
            $btn.prop('disabled', false).text(originalText);
        })
        .fail(function() {
            $msg.addClass('notice notice-error').html('<p>Connection error.</p>').show();
            $btn.prop('disabled', false).text(originalText);
        });
    });
});
</script>
