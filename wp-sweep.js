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

		/*
		Page closing confirmation
		https://developer.mozilla.org/en-US/docs/DOM/Mozilla_event_reference/beforeunload
		 */
		$(window).on('beforeunload', function (e) {
			if (body.hasClass('sweep-active')) {
				(e || window.event).returnValue = wpSweep.closeWarning; // Gecko and Trident
				return wpSweep.closeWarning; // Gecko and WebKit
			}
		});

	});

})(jQuery);