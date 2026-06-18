(function () {
	'use strict';

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

		function goTo(i) {
			index = (i + slides.length) % slides.length;
			var slide = slides[index];
			track.scrollLeft = slide.offsetLeft - track.offsetLeft;

			if (dotsWrap) {
				var dots = dotsWrap.querySelectorAll('.shyft-reviews__dot');
				dots.forEach(function (dot, dotIndex) {
					dot.classList.toggle('is-active', dotIndex === index);
				});
			}
		}

		if (dotsWrap) {
			slides.forEach(function (_, slideIndex) {
				var dot = document.createElement('button');
				dot.type = 'button';
				dot.className = 'shyft-reviews__dot' + (slideIndex === 0 ? ' is-active' : '');
				dot.addEventListener('click', function () {
					goTo(slideIndex);
				});
				dotsWrap.appendChild(dot);
			});
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

		var autoplay = window.setInterval(function () {
			goTo(index + 1);
		}, 7000);

		root.addEventListener('mouseenter', function () {
			window.clearInterval(autoplay);
		});

		root.addEventListener('mouseleave', function () {
			autoplay = window.setInterval(function () {
				goTo(index + 1);
			}, 7000);
		});
	}

	document.querySelectorAll('.shyft-reviews[data-shyft-reviews-slider="1"]').forEach(initWidget);
})();
