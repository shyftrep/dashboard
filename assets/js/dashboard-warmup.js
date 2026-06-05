(function () {
	'use strict';

	var config = typeof shyftDashboardWarmup !== 'undefined' ? shyftDashboardWarmup : null;

	if (!config || !Array.isArray(config.urls) || config.urls.length === 0) {
		return;
	}

	function postComplete() {
		if (!config.ajaxUrl || !config.action || !config.nonce) {
			return Promise.resolve(true);
		}

		var body = new URLSearchParams();
		body.set('action', config.action);
		body.set('nonce', config.nonce);

		return fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (response) {
				if (!response.ok) {
					return false;
				}

				return response.json().then(function (payload) {
					return !!(payload && payload.success);
				});
			})
			.catch(function () {
				return false;
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

		return chain;
	}

	function finish(markedComplete) {
		if (config.mode !== 'redirect' || !config.redirectUrl || !markedComplete) {
			return;
		}

		window.location.replace(config.redirectUrl);
	}

	var start = window.requestIdleCallback || function (cb) {
		window.setTimeout(cb, 1500);
	};

	start(function () {
		runWarmup()
			.then(postComplete)
			.then(finish);
	});
})();
