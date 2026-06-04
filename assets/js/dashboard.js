(function () {
	'use strict';

	function getTheme() {
		return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
	}

	function getStorageKey() {
		if (typeof shyftDashboard !== 'undefined' && shyftDashboard.theme && shyftDashboard.theme.storageKey) {
			return shyftDashboard.theme.storageKey;
		}
		return 'shyft_dashboard_theme';
	}

	function setTheme(theme) {
		var nextTheme = theme === 'dark' ? 'dark' : 'light';
		document.documentElement.setAttribute('data-theme', nextTheme);

		try {
			localStorage.setItem(getStorageKey(), nextTheme);
		} catch (e) {
			// Ignore storage errors (private mode, etc.).
		}

		updateThemeSwitch(nextTheme);
	}

	function updateThemeSwitch(theme) {
		var buttons = document.querySelectorAll('.shyft-theme-switch__btn');

		buttons.forEach(function (button) {
			var isActive = button.getAttribute('data-theme') === theme;
			button.classList.toggle('is-active', isActive);
			button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
		});
	}

	function initTheme() {
		var theme = getTheme();
		updateThemeSwitch(theme);

		document.querySelectorAll('.shyft-theme-switch').forEach(function (switchGroup) {
			switchGroup.addEventListener('click', function (event) {
				var button = event.target.closest('.shyft-theme-switch__btn');
				if (!button) {
					return;
				}

				var nextTheme = button.getAttribute('data-theme');
				if (!nextTheme || nextTheme === getTheme()) {
					return;
				}

				setTheme(nextTheme);
			});
		});
	}

	function initPeriodNavigation() {
		document.querySelectorAll('.shyft-period-switch__btn').forEach(function (link) {
			link.addEventListener(
				'click',
				function (event) {
					if (link.classList.contains('is-active')) {
						return;
					}

					var href = link.getAttribute('href');
					if (!href) {
						return;
					}

					event.preventDefault();
					event.stopImmediatePropagation();
					window.location.assign(href);
				},
				true
			);
		});
	}

	function initForm() {
		var form = document.querySelector('.shyft-form');
		if (!form) {
			return;
		}

		var fileInput = form.querySelector('#shyft_attachment');
		var fileHint = document.getElementById('shyft_attachment_name');

		if (fileInput && fileHint) {
			fileInput.addEventListener('change', function () {
				if (fileInput.files && fileInput.files.length > 0) {
					fileHint.textContent = fileInput.files[0].name;
				}
			});
		}

		form.addEventListener('submit', function () {
			var button = form.querySelector('.shyft-button');
			if (button) {
				button.disabled = true;
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initTheme();
		initPeriodNavigation();
		initForm();
	});
})();
