/* Ovebot.ai - "Add to cart" from chat + cart sync.
 *
 * Loaded by the storefront footer snippet (catalog/controller/extension/module/ovebotai.php
 * index()) only when the module's "Add to cart button" setting is on. The chat options
 * pushed right before this script point the widget at window.ovebotaiAddToCart and carry
 * the cart as it was when the page rendered (cart_count / cart_items).
 */
(function (window) {
	'use strict';

	var ADD_URL  = 'index.php?route=checkout/cart/add';
	var CART_URL = 'index.php?route=extension/module/ovebotai/cart';

	// Checkout pages already show the cart, so after a successful add from chat
	// the page is reloaded. A checkout page is one the module flagged by route
	// (data-checkout on this script tag) or whose URL contains "checkout".
	var ownScript = document.currentScript;

	function onCheckoutPage() {
		if (ownScript && ownScript.getAttribute('data-checkout') === '1') {
			return true;
		}

		return /checkout/i.test(window.location.href);
	}

	function reloadIfCheckout(result) {
		if (result === true && onCheckoutPage()) {
			window.location.reload();
		}
	}

	function queue() {
		window.ovebot_ai = window.ovebot_ai || [];
		return window.ovebot_ai;
	}

	function parseJson(text) {
		try {
			return JSON.parse(text) || {};
		} catch (e) {
			return {};
		}
	}

	// Same-origin form POST without depending on the theme's jQuery. done(null) on HTTP errors.
	function post(url, body, done) {
		var xhr = new XMLHttpRequest();

		xhr.open('POST', url, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhr.onreadystatechange = function () {
			if (xhr.readyState === 4) {
				done(xhr.status >= 200 && xhr.status < 300 ? parseJson(xhr.responseText) : null);
			}
		};
		xhr.send(body || '');
	}

	// ── Cart sync ────────────────────────────────────────────────────────────

	// The cart the widget already knows about: the one rendered with the page
	// (the 'chat' options pushed just before this script), then every one we send.
	var state = (function () {
		var q = queue();

		for (var i = (q.length || 0) - 1; i >= 0; i--) {
			if (q[i] && q[i][0] === 'chat' && q[i][1]) {
				return JSON.stringify({ count: q[i][1].cart_count || 0, items: q[i][1].cart_items || [] });
			}
		}

		return '';
	})();

	var syncTimer = null;

	function syncCart() {
		post(CART_URL, '', function (json) {
			if (!json || typeof json.count === 'undefined') {
				return;
			}

			var next = JSON.stringify(json);

			if (next === state) {
				return;
			}

			state = next;
			queue().push(['cart', json]);
		});
	}

	// Several cart requests usually fire together (add + mini-cart refresh).
	function scheduleSync() {
		clearTimeout(syncTimer);
		syncTimer = setTimeout(syncCart, 300);
	}

	// Any jQuery AJAX call that touches the cart (add / edit / remove, mini-cart
	// reloads, quick-checkout cart updates) - except our own endpoint.
	if (window.jQuery) {
		window.jQuery(document).ajaxComplete(function (event, xhr, settings) {
			if (!settings || !settings.url || !/cart/i.test(settings.url) || settings.url.indexOf('extension/module/ovebotai/cart') !== -1) {
				return;
			}

			scheduleSync();
		});
	}

	// ── Add to cart ──────────────────────────────────────────────────────────

	// What the widget expects back: true, false, or { redirect: "..." } (e.g. a
	// product with required options, which OpenCart sends to the product page).
	function outcome(json) {
		if (json && json.success) {
			return true;
		}

		if (json && json.redirect) {
			return { redirect: json.redirect };
		}

		return false;
	}

	// Is this completed jQuery request the theme's cart.add() call for `ref`?
	function isAddFor(settings, ref) {
		if (!settings || !settings.url || !/route=checkout\/cart\/add(&|$)/.test(settings.url)) {
			return false;
		}

		if (typeof settings.data !== 'string') {
			return true;
		}

		var match = settings.data.match(/(?:^|&)product_id=([^&]*)/);

		return !match || decodeURIComponent(match[1]) === ref;
	}

	// product = { ref, sku, quantity, name, url, price, currency }
	// ref is the OpenCart product id - the same 'ref' the product feed publishes.
	window.ovebotaiAddToCart = function (product) {
		return new Promise(function (resolve) {
			product = product || {};

			var ref      = String(product.ref);
			var quantity = parseInt(product.quantity, 10) || 1;
			var $        = window.jQuery;

			// Preferred: the theme's own cart.add(), so its notifications, mini-cart
			// refresh and tracking events run exactly as for a normal click. The
			// result is read off the request it fires.
			if ($ && window.cart && typeof window.cart.add === 'function') {
				var settled = false;
				var timer   = null;

				var finish = function (value) {
					if (settled) {
						return;
					}

					settled = true;
					$(document).off('ajaxComplete', onComplete);
					clearTimeout(timer);
					resolve(value);
					reloadIfCheckout(value);
				};

				var onComplete = function (event, xhr, settings) {
					if (isAddFor(settings, ref)) {
						finish(outcome(xhr.responseJSON || parseJson(xhr.responseText)));
					}
				};

				$(document).on('ajaxComplete', onComplete);
				timer = setTimeout(function () { finish(false); }, 20000);

				try {
					window.cart.add(ref, quantity);
				} catch (e) {
					finish(false);
				}

				return;
			}

			// Fallback for themes without a global cart.add(): post to OpenCart directly.
			post(ADD_URL, 'product_id=' + encodeURIComponent(ref) + '&quantity=' + quantity, function (json) {
				var value = outcome(json);

				scheduleSync();
				resolve(value);
				reloadIfCheckout(value);
			});
		});
	};

}(window));
