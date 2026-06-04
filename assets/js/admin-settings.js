(function ($) {
	'use strict';

	$(function () {
		var frame;

		$('.shyft-upload-logo').on('click', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: 'Dashboard-Logo auswählen',
				button: {
					text: 'Logo verwenden'
				},
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var $field = $('.shyft-logo-url');
				var $preview = $('.shyft-logo-preview');

				$field.val(attachment.url);
				$preview.html('<img src="' + attachment.url + '" alt="" style="max-height:60px;width:auto;" />');
			});

			frame.open();
		});
	});
})(jQuery);
