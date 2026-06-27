(function($) {
    'use strict';

    $(document).on('click', '.vms-v2-toggle', function(event) {
        event.preventDefault();

        var $link = $(this);
        var postId = $link.data('post-id');
        var nonce = $link.data('nonce');

        $.post(vmsV2Toggle.ajaxUrl, {
            action: 'vms_v2_toggle_vms',
            post_id: postId,
            nonce: nonce
        }).done(function(response) {
            if (response.success) {
                $link.html(response.data.post_isvms ? '&#9733;' : '&#9734;');
            }
        });
    });
})(jQuery);
