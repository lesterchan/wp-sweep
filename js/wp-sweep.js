(function ($) {
    'use strict';

    $(function () {

        var $body = $('body');

        $('.btn-sweep').click(function(evt) {
            evt.preventDefault();
            // Add Active
            $body.addClass('sweep-active');
            // Add Disabled
            $(this).prop('disabled', true).text(wp_sweep.text_sweeping);

            var $node = $(this), $row = $node.parents('tr');

            $.get(wp_sweep.ajax_url, { action: $node.data('action'), sweep_name: $node.data('sweep_name'), sweep_type: $node.data('sweep_type'), '_wpnonce': $node.data('nonce') }, function(data) {
                if(data.success) {
                    var count = parseInt(data.data.count, 10);
                    // Count Col
                    $('.sweep-count', $row).text(count.toLocaleString());
                    // % Of Col
                    $('.sweep-percentage', $row).text(data.data.percentage);
                    // Action Col
                    if(count === 0) {
                       $node.parent('td').html(wp_sweep.text_na);
                    }
                    // Stats
                    $.each(data.data.stats, function(key, value) {
                        $('.sweep-count-type-' + key).text(parseInt(value, 10).toLocaleString());
                    });
                    // Message
                    $row.parents('.table-sweep').prev('.sweep-message').html('<div class="updated"><p>' + data.data.sweep + '</p></div>');
                    // Remove Active
                    $body.removeClass('sweep-active');
                    // Remove Disabled
                    $node.prop('disabled', false).text(wp_sweep.text_sweep);
                }
            });
        });

        $('.btn-sweep-details').click(function(evt) {
            evt.preventDefault();
            var $node = $(this);

            $.get(wp_sweep.ajax_url, { action: $node.data('action'), sweep_name: $node.data('sweep_name'), sweep_type: $node.data('sweep_type'), '_wpnonce': $node.data('nonce') }, function(data) {
                if(data.success) {
                    if(data.data.length > 0) {
                        var html = '';
                        $.each(data.data, function(i, n) {
                            html += '<li>' + n + '</li>';
                        });
                        $('.sweep-details', $node.parents('tr')).html('<ol>' + html + '</ol>').show();
                    }
                }
            });
        });

        /*
        Page closing confirmation
        https://developer.mozilla.org/en-US/docs/DOM/Mozilla_event_reference/beforeunload
         */
        $(window).on('beforeunload', function (e) {
            if ($body.hasClass('sweep-active')) {
                (e || window.event).returnValue = wp_sweep.text_close_warning; // Gecko and Trident
                return wp_sweep.text_close_warning; // Gecko and WebKit
            }
        });
    });

})(jQuery);