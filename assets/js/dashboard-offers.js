(function () {
	'use strict';

	function initOfferForm(root) {
		if (!root) {
			return;
		}

		var typeField = root.querySelector('[data-offer-type]');
		var timedFields = root.querySelector('[data-offer-timed-fields]');
		var iconsWrap = root.querySelector('[data-offer-icons]');
		var addIconButton = root.querySelector('[data-offer-add-icon]');
		var imageInput = root.querySelector('[data-offer-image-id]');
		var imagePreview = root.querySelector('[data-offer-image-preview]');
		var pickImageButton = root.querySelector('[data-offer-pick-image]');
		var removeImageButton = root.querySelector('[data-offer-remove-image]');

		function toggleTimedFields() {
			if (!typeField || !timedFields) {
				return;
			}

			timedFields.hidden = typeField.value !== 'timed';
		}

		function createIconRow(icon, label) {
			var row = document.createElement('div');
			row.className = 'shyft-offer-form__icon-row';
			row.innerHTML =
				'<input type="text" name="offer_icons[]" class="shyft-form__input" placeholder="Icon (Emoji, dashicons-yes-alt oder URL)" value="' +
				(icon || '') +
				'">' +
				'<input type="text" name="offer_icon_labels[]" class="shyft-form__input" placeholder="Text" value="' +
				(label || '') +
				'">' +
				'<button type="button" class="shyft-offer-form__icon-remove" data-offer-remove-icon aria-label="Entfernen">&times;</button>';
			return row;
		}

		if (typeField) {
			typeField.addEventListener('change', toggleTimedFields);
			toggleTimedFields();
		}

		if (addIconButton && iconsWrap) {
			addIconButton.addEventListener('click', function () {
				iconsWrap.appendChild(createIconRow('', ''));
			});

			iconsWrap.addEventListener('click', function (event) {
				var target = event.target;
				if (!(target instanceof HTMLElement)) {
					return;
				}

				if (target.hasAttribute('data-offer-remove-icon')) {
					var row = target.closest('.shyft-offer-form__icon-row');
					if (row) {
						row.remove();
					}
				}
			});
		}

		if (pickImageButton && imageInput && window.wp && window.wp.media) {
			var frame;

			pickImageButton.addEventListener('click', function (event) {
				event.preventDefault();

				if (frame) {
					frame.open();
					return;
				}

				frame = window.wp.media({
					title: 'Angebotsbild wählen',
					button: { text: 'Übernehmen' },
					multiple: false,
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					imageInput.value = String(attachment.id || '');
					if (imagePreview) {
						imagePreview.innerHTML = attachment.url
							? '<img src="' + attachment.url + '" alt="">'
							: '';
					}
				});

				frame.open();
			});
		}

		if (removeImageButton && imageInput) {
			removeImageButton.addEventListener('click', function (event) {
				event.preventDefault();
				imageInput.value = '';
				if (imagePreview) {
					imagePreview.innerHTML = '';
				}
			});
		}
	}

	document.querySelectorAll('[data-shyft-offer-form]').forEach(initOfferForm);
})();
