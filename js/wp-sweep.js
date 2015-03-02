(function ($) {
    'use strict';

    var body = $('body'),
        btn = $('[data-sweeping]');

    $(function () {

        /*
        Prevent multiple sweeps at once
         */
        btn.on('click', function () {
            $(this).addClass('disabled').text($(this).attr('data-sweeping'));
            setTimeout(function () {
                body.addClass('sweep-active');
            }, 50);
        });

        $('.btn-sweep-details').click(function(evt) {
            var $node = $(this);
            evt.preventDefault();
            $.get( wp_sweep.ajax_url, { action: $(this).data('action'), sweep_details: $(this).data('sweep_details'), '_wpnonce': $(this).data('nonce') }, function( data ) {
                if( data.data.success ) {
                    if( data.data.data.length > 0 ) {
                        var html = '';
                        $.each( data.data.data, function( i, n ) {
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
            if (body.hasClass('sweep-active')) {
                (e || window.event).returnValue = wp_sweep.close_warning; // Gecko and Trident
                return wp_sweep.close_warning; // Gecko and WebKit
            }
        });
    });

})(jQuery);