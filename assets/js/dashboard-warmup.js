(function () {
	'use strict';

	var config = typeof shyftDashboardWarmup !== 'undefined' ? shyftDashboardWarmup : null;

	if (!config || !Array.isArray(config.urls) || config.urls.length === 0) {
		return;
	}

	function postComplete() {
		if (!config.ajaxUrl || !config.action || !config.nonce) {
			return Promise.resolve();
		}

		var body = new URLSearchParams();
		body.set('action', config.action);
		body.set('nonce', config.nonce);

		return fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).catch(function () {
			// Still redirect / continue if marking failed.
		});
	}

	function preloadUrl(url) {
		return fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
			redirect: 'follow'
		}).catch(function () {
			return null;
		});
	}

	function runWarmup() {
		var chain = Promise.resolve();

		config.urls.forEach(function (url) {
			chain = chain.then(function () {
				return preloadUrl(url);
			});
		});

		return chain.then(postComplete);
	}

	function finish() {
		if (config.mode === 'redirect' && config.redirectUrl) {
			window.location.replace(config.redirectUrl);
			return;
		}
	}

	var start = window.requestIdleCallback || function (cb) {
		window.setTimeout(cb, 1500);
	};

	start(function () {
		runWarmup().then(finish);
	});
})();
