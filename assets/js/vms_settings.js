(function($) {
    'use strict';

    $(function() {
        $('.vms-v2-independent-toggle').on('change', function() {
            var $el = $(this);
            var data = {
                action: $el.data('action'),
                security: vmsV2Settings.nonce
            };
            data[$el.data('param')] = $el.is(':checked') ? 1 : 0;
            $.post(vmsV2Settings.ajaxUrl, data);
        });

        $('#vms-v2-test-api').on('click', function() {
            var $button = $(this);
            var $status = $('#vms-v2-api-status');

            $button.prop('disabled', true).text(vmsV2Settings.strings.testing);
            $status.text('Testing...');

            $.get(vmsV2Settings.restTestUrl)
                .done(function() {
                    $status.html('<span style="color: green;">REST API v2 OK</span>');
                })
                .fail(function(xhr) {
                    $status.html('<span style="color: red;">REST API error: ' + xhr.status + '</span>');
                })
                .always(function() {
                    $button.prop('disabled', false).text(vmsV2Settings.strings.testRestApi);
                });
        });

        $('#vms-v2-flush-permalinks').on('click', function() {
            var $button = $(this);
            var $status = $('#vms-v2-api-status');

            $button.prop('disabled', true).text(vmsV2Settings.strings.resetting);
            $.post(vmsV2Settings.ajaxUrl, {
                action: 'vms_v2_flush_permalinks',
                nonce: vmsV2Settings.flushNonce
            })
                .done(function(response) {
                    if (response.success) {
                        $status.html('<span style="color: green;">Permalinks reset</span>');
                        setTimeout(function() {
                            $('#vms-v2-test-api').trigger('click');
                        }, 500);
                    } else {
                        $status.html('<span style="color: red;">Error</span>');
                    }
                })
                .always(function() {
                    $button.prop('disabled', false).text(vmsV2Settings.strings.resetPermalinks);
                });
        });
    });
})(jQuery);
