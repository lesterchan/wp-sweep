jQuery(document).ready(function ($) {

	var body = $('body'),
		btn = $('[data-sweeping]');

	/*
	Prevent multiple sweeps at once
	 */
	btn.on('click', function (event) {
		event.preventDefault();
		$(this).addClass('disabled').text($(this).attr('data-sweeping'));
		body.addClass('sweep-active');
	});

	/*
	Page closing confirmation
	https://developer.mozilla.org/en-US/docs/DOM/Mozilla_event_reference/beforeunload
	 */
	$(window).on('beforeunload', function (e) {
		if (body.hasClass('sweep-active')) {
			return wpSweep.closeWarning;
		}
	});

});