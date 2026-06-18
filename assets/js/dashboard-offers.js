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
		var imageFileInput = root.querySelector('[data-offer-image-file]');
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

		function clearImageSelection() {
			if (imageInput) {
				imageInput.value = '0';
			}
			if (imageFileInput) {
				imageFileInput.value = '';
			}
			if (imagePreview) {
				imagePreview.innerHTML = '';
			}
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

		if (imageFileInput && imagePreview) {
			imageFileInput.addEventListener('change', function () {
				var file = imageFileInput.files && imageFileInput.files[0];

				if (!file) {
					return;
				}

				if (imageInput) {
					imageInput.value = '0';
				}

				var reader = new FileReader();
				reader.onload = function () {
					imagePreview.innerHTML = '<img src="' + reader.result + '" alt="">';
				};
				reader.readAsDataURL(file);
			});
		}

		if (removeImageButton) {
			removeImageButton.addEventListener('click', function (event) {
				event.preventDefault();
				clearImageSelection();
			});
		}
	}

	document.querySelectorAll('[data-shyft-offer-form]').forEach(initOfferForm);
})();
