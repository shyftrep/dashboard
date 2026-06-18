(function () {
	'use strict';

	function getVisibleColumns(root) {
		var styles = window.getComputedStyle(root);
		var columns = parseInt(styles.getPropertyValue('--shyft-reviews-columns'), 10);
		return Number.isFinite(columns) && columns > 0 ? columns : 1;
	}

	function initWidget(root) {
		if (!root || root.getAttribute('data-shyft-reviews-slider') !== '1') {
			return;
		}

		var track = root.querySelector('[data-shyft-reviews-track]');
		var slides = track ? track.querySelectorAll('[data-shyft-reviews-slide]') : [];
		var prev = root.querySelector('[data-shyft-reviews-prev]');
		var next = root.querySelector('[data-shyft-reviews-next]');
		var dotsWrap = root.querySelector('[data-shyft-reviews-dots]');
		var index = 0;

		if (!track || slides.length < 2) {
			return;
		}

		function maxIndex() {
			return Math.max(0, slides.length - getVisibleColumns(root));
		}

		function goTo(i) {
			var limit = maxIndex();
			index = Math.max(0, Math.min(i, limit));
			var slide = slides[index];

			if (slide) {
				track.scrollLeft = slide.offsetLeft - track.offsetLeft;
			}

			if (dotsWrap) {
				var dots = dotsWrap.querySelectorAll('.shyft-reviews__dot');
				dots.forEach(function (dot, dotIndex) {
					dot.classList.toggle('is-active', dotIndex === index);
				});
			}

			if (prev) {
				prev.disabled = index <= 0;
			}

			if (next) {
				next.disabled = index >= limit;
			}
		}

		if (dotsWrap) {
			var pageCount = maxIndex() + 1;

			for (var slideIndex = 0; slideIndex < pageCount; slideIndex += 1) {
				var dot = document.createElement('button');
				dot.type = 'button';
				dot.className = 'shyft-reviews__dot' + (slideIndex === 0 ? ' is-active' : '');
				dot.addEventListener('click', function (targetIndex) {
					return function () {
						goTo(targetIndex);
					};
				}(slideIndex));
				dotsWrap.appendChild(dot);
			}
		}

		if (prev) {
			prev.addEventListener('click', function () {
				goTo(index - 1);
			});
		}

		if (next) {
			next.addEventListener('click', function () {
				goTo(index + 1);
			});
		}

		window.addEventListener('resize', function () {
			goTo(index);
		});

		goTo(0);

		var autoplay = window.setInterval(function () {
			goTo(index >= maxIndex() ? 0 : index + 1);
		}, 7000);

		root.addEventListener('mouseenter', function () {
			window.clearInterval(autoplay);
		});

		root.addEventListener('mouseleave', function () {
			autoplay = window.setInterval(function () {
				goTo(index >= maxIndex() ? 0 : index + 1);
			}, 7000);
		});
	}

	document.querySelectorAll('.shyft-reviews[data-shyft-reviews-slider="1"]').forEach(initWidget);
})();
