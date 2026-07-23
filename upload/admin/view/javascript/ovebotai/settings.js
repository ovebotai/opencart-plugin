/* global jQuery, ovebotaiSettings */
(function ($) {
	'use strict';

	var cfg = ovebotaiSettings;

	$(function () {

		// ── Unsaved changes guard ────────────────────────────────────────────
		// No change-tracking state to keep in sync — just diff the form's
		// current serialization against its initial one at the moment the
		// browser actually asks, so an edit that's later undone (typed then
		// untyped) doesn't leave a stale "dirty" flag behind.

		var $oveForm    = $('#oveSettingsForm');
		var $oveSaveBtn = $('#oveSaveBtn');
		var initialSerialized = $oveForm.serialize();

		$(window).on('beforeunload', function (e) {
			if ($oveForm.serialize() !== initialSerialized) {
				e.preventDefault();
				e.returnValue = '';
				return '';
			}
		});

		// Pulse the Save button while there are unsaved changes, so it's
		// obvious there's something to do before leaving the page.
		function updateSaveAttention() {
			$oveSaveBtn.toggleClass('ovebotai-attn', $oveForm.serialize() !== initialSerialized);
		}

		$oveForm.on('input change', 'input, select', updateSaveAttention);

		// ── Save form ────────────────────────────────────────────────────────

		var oveSaveBtnText = $oveSaveBtn.text();

		$oveForm.on('submit', function (e) {
			e.preventDefault();
			var $btn = $oveSaveBtn.prop('disabled', true).removeClass('ovebotai-attn').text(cfg.i18n.saving);

			$.ajax({
				url: cfg.saveUrl,
				type: 'POST',
				dataType: 'json',
				data: $oveForm.serialize()
			})
				.done(function (resp) {
					var msg = resp && resp.message ? resp.message : (resp && resp.success ? cfg.i18n.saved : cfg.i18n.error);
					var warnings = resp && resp.warnings;
					// needs_reconnect has no warnings list of its own — it's a single
					// caveat on the save itself, so it still takes over the main notice.
					var needsReconnect = resp && resp.success && resp.needs_reconnect && !(warnings && warnings.length);
					showNotice(!needsReconnect && resp && resp.success, msg, needsReconnect ? 'warning' : null);
					showWarnings(resp && resp.success ? warnings : null);

					if (resp && resp.success) {
						// Saved state is now the new baseline — further edits are
						// judged against it, not the page-load snapshot.
						initialSerialized = $oveForm.serialize();
					}

					if (resp && resp.success && cfg.dashboardUrl) {
						setTimeout(function () { window.location.href = cfg.dashboardUrl; }, 1200);
					} else {
						updateSaveAttention();
						$btn.prop('disabled', false).text(oveSaveBtnText);
					}
				})
				.fail(function () {
					updateSaveAttention();
					showNotice(false, cfg.i18n.error);
					showWarnings(null);
					$btn.prop('disabled', false).text(oveSaveBtnText);
				});
		});

		// ── Toggle chat status label ─────────────────────────────────────────

		$('#oveChatStatus').on('change', function () {
			$('#oveChatStatusLbl').text($(this).is(':checked') ? cfg.i18n.enabled : cfg.i18n.disabled);
		});

		// ── Appearance panel toggle ──────────────────────────────────────────

		$('#oveAppearanceToggle').on('click', function () {
			var $p = $('#oveAppearancePanel');
			var open = $p.is(':visible');
			$p.slideToggle(180);
			$('#oveAppearanceToggleIcon').toggleClass('fa-chevron-down', open).toggleClass('fa-chevron-up', !open);
		});

		// Sync color picker ↔ text input.
		$('#ove_color_picker').on('input', function () {
			$('[name="widget_accent_color"]').val($(this).val());
			updateSaveAttention();
		});
		$('[name="widget_accent_color"]').on('input', function () {
			var v = $(this).val();
			if (/^#[0-9a-f]{6}$/i.test(v)) {
				$('#ove_color_picker').val(v);
			}
		});

		// ── Copy buttons ─────────────────────────────────────────────────────

		$(document).on('click', '.ovebotai-copy-btn', function () {
			var targetId = $(this).data('target');
			var val      = $('#' + targetId).val();
			navigator.clipboard.writeText(val).then(function () {
				// brief label swap
			}).catch(function () {
				var el = document.getElementById(targetId);
				el.select();
				document.execCommand('copy');
			});
			var $btn  = $(this);
			var orig  = $btn.text();
			$btn.text(cfg.i18n.copied);
			setTimeout(function () { $btn.text(orig); }, 1800);
		});

		// ── Regenerate feed hash ─────────────────────────────────────────────

		$('.ovebotai-regen-hash-btn').on('click', function () {
			if (!confirm(cfg.i18n.confirmRegenHash)) return;
			var $btn = $(this).prop('disabled', true);
			$.ajax({ url: cfg.regenHashUrl, type: 'POST', dataType: 'json' })
				.done(function (resp) {
					if (resp && resp.success) {
						$('#oveFeedUrl').val(resp.url);
						showNotice(true, resp.message || cfg.i18n.saved);
					} else {
						showNotice(false, (resp && resp.message) || cfg.i18n.error);
					}
				})
				.fail(function () { showNotice(false, cfg.i18n.error); })
				.always(function () { $btn.prop('disabled', false); });
		});

		// ── Regenerate API credentials ───────────────────────────────────────

		$('.ovebotai-regen-creds-btn').on('click', function () {
			if (!confirm(cfg.i18n.confirmRegenCreds)) return;
			var $btn = $(this).prop('disabled', true);
			$.ajax({ url: cfg.regenCredsUrl, type: 'POST', dataType: 'json' })
				.done(function (resp) {
					if (resp && resp.success) {
						$('#oveApiUser').val(resp.user);
						$('#oveApiPass').val(resp.pass);
						showNotice(true, resp.message || cfg.i18n.saved);
					} else {
						showNotice(false, (resp && resp.message) || cfg.i18n.error);
					}
				})
				.fail(function () { showNotice(false, cfg.i18n.error); })
				.always(function () { $btn.prop('disabled', false); });
		});

		// ── Clear feed cache ─────────────────────────────────────────────────

		$('.ovebotai-clear-cache-btn').on('click', function () {
			if (!confirm(cfg.i18n.confirmClearCache)) return;
			var $btn = $(this).prop('disabled', true);
			$.ajax({ url: cfg.clearCacheUrl, type: 'POST', dataType: 'json' })
				.done(function (resp) {
					showNotice(resp && resp.success, (resp && resp.message) || cfg.i18n.error);
				})
				.fail(function () { showNotice(false, cfg.i18n.error); })
				.always(function () { $btn.prop('disabled', false); });
		});

		// ── Notice helper ────────────────────────────────────────────────────

		function showNotice(ok, msg, type) {
			var cls = type === 'warning' ? 'ovebotai-notice-warning' : (ok ? 'ovebotai-notice-success' : 'ovebotai-notice-error');
			var $n  = $('#oveSettingsNotice');
			$n.removeClass('ovebotai-notice-success ovebotai-notice-error ovebotai-notice-warning')
				.addClass(cls)
				.html('<p>' + msg + '</p>')
				.slideDown(180);
			$('html, body').animate({ scrollTop: Math.max(0, $n.offset().top - 40) }, 300);
			clearTimeout($n.data('timer'));
			var delay = type === 'warning' ? 7000 : 4000;
			$n.data('timer', setTimeout(function () { $n.slideUp(180); }, delay));
		}

		function showWarnings(warnings) {
			var $w = $('#oveSettingsWarnings');
			clearTimeout($w.data('timer'));
			if (warnings && warnings.length) {
				$w.html('<p>' + warnings.join('<br>') + '</p>').slideDown(180);
				$w.data('timer', setTimeout(function () { $w.slideUp(180); }, 9000));
			} else {
				$w.slideUp(180);
			}
		}

		// ── Defensive: not connected — send back to the dashboard's reconnect
		// panel instead of showing a half-usable form. Structurally unreachable
		// today (index() only reaches this view when isSetupComplete() already
		// implies a live connection), kept as a safety net mirroring the
		// WordPress plugin's own guard.
		if (!cfg.isConnected && cfg.dashboardUrl) {
			window.location.replace(cfg.dashboardUrl);
		}
	});

}(jQuery));
