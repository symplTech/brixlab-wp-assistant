jQuery(function($) {
    var L = window.brixlabAssistantLicenseL10n || {};

    $('#wpb-activate-form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        var $msg = $('#wpb-license-message');
        var originalText = $btn.text();

        $btn.prop('disabled', true).text(L.activating);
        $msg.hide().removeClass('success error');

        $.post(L.ajaxUrl, {
            action: 'brixlab_assistant_activate_license',
            nonce: $(this).find('[name="nonce"]').val(),
            license_key: $('#wpb-license-key').val()
        })
        .done(function(resp) {
            if (resp.success) {
                $msg.removeClass('error').addClass('success').text(resp.data.message).show();
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                var errorMsg = resp.data || L.activationFailed;
                $msg.removeClass('success').addClass('error').text(errorMsg).show();
                $btn.prop('disabled', false).text(originalText);
            }
        })
        .fail(function() {
            $msg.removeClass('success').addClass('error').text(L.connectionError).show();
            $btn.prop('disabled', false).text(originalText);
        });
    });

    $('#wpb-deactivate-form').on('submit', function(e) {
        e.preventDefault();
        if (!confirm(L.confirmDeactivate)) {
            return;
        }

        var $btn = $(this).find('button[type="submit"]');
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(L.deactivating);

        $.post(L.ajaxUrl, {
            action: 'brixlab_assistant_deactivate_license',
            nonce: $(this).find('[name="nonce"]').val()
        })
        .done(function() {
            location.reload();
        })
        .fail(function() {
            alert(L.failedToDeactivate);
            $btn.prop('disabled', false).text(originalText);
        });
    });
});
