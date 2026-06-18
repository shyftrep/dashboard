(function () {
	'use strict';

	function initOfferForm(root) {
		if (!root) {
			return;
		}

		var typeField = root.querySelector('[data-offer-type]');
		var timedFields = root.querySelector('[data-offer-timed-fields]');
		var standardFields = root.querySelector('[data-offer-standard-fields]');
		var featuresWrap = root.querySelector('[data-offer-features]');
		var addFeatureButton = root.querySelector('[data-offer-add-feature]');
		var imageInput = root.querySelector('[data-offer-image-id]');
		var imagePreview = root.querySelector('[data-offer-image-preview]');
		var pickImageButton = root.querySelector('[data-offer-pick-image]');
		var removeImageButton = root.querySelector('[data-offer-remove-image]');

		function toggleTypeFields() {
			var isTimed = typeField && typeField.value === 'timed';

			if (timedFields) {
				timedFields.hidden = !isTimed;
			}

			if (standardFields) {
				standardFields.hidden = isTimed;
			}
		}

		function createFeatureRow(label) {
			var row = document.createElement('div');
			row.className = 'shyft-offer-form__feature-row';
			row.innerHTML =
				'<span class="shyft-offer-form__feature-check" aria-hidden="true"></span>' +
				'<input type="text" name="offer_feature_labels[]" class="shyft-form__input" placeholder="Vorteil / Aufzählungspunkt" value="' +
				(label || '') +
				'">' +
				'<button type="button" class="shyft-offer-form__icon-remove" data-offer-remove-feature aria-label="Entfernen">&times;</button>';
			return row;
		}

		if (typeField) {
			typeField.addEventListener('change', toggleTypeFields);
			toggleTypeFields();
		}

		if (addFeatureButton && featuresWrap) {
			addFeatureButton.addEventListener('click', function () {
				featuresWrap.appendChild(createFeatureRow(''));
			});

			featuresWrap.addEventListener('click', function (event) {
				var target = event.target;
				if (!(target instanceof HTMLElement)) {
					return;
				}

				if (target.hasAttribute('data-offer-remove-feature')) {
					var row = target.closest('.shyft-offer-form__feature-row');
					if (row) {
						row.remove();
					}
				}
			});
		}

		if (pickImageButton && imageInput) {
			pickImageButton.addEventListener('click', function (event) {
				event.preventDefault();

				if (!window.wp || !window.wp.media) {
					return;
				}

				var frame = window.wp.media({
					title: 'Angebotsbild wählen',
					button: { text: 'Übernehmen' },
					multiple: false,
					library: { type: 'image' },
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
				imageInput.value = '0';
				if (imagePreview) {
					imagePreview.innerHTML = '';
				}
			});
		}
	}

	document.querySelectorAll('[data-shyft-offer-form]').forEach(initOfferForm);
})();
