/* global ovebotaiSetup, jQuery */
(function ($, ovebotaiSetup) {
	'use strict';

	var cfg        = ovebotaiSetup;
	// [1,2,3,4] — Connect, Website pages, Products, Go live.
	var stepsSeq   = cfg.stepsSequence || [1, 2, 3, 4];
	var current    = parseInt(cfg.initialStep, 10) || stepsSeq[0];
	var navigating = false;

	// The step whose "Next" triggers sync + advances to Finish — second-to-last
	// in the sequence (last is always the Finish panel, step 4).
	var finishStep = stepsSeq[stepsSeq.length - 2];

	function posOf(step) {
		var i = stepsSeq.indexOf(step);
		return i === -1 ? 0 : i;
	}

	$(function () {
		renderStep(current);

		$('#oveNextBtn').on('click', handleNext);
		$('#ovePrevBtn').on('click', handlePrev);

		if (cfg.oauthError) {
			showOauthError(cfg.oauthError);
		}
	});

	// ── Step navigation ──────────────────────────────────────────────────────

	function renderStep(step) {
		current = step;
		var pos = posOf(step);

		$('.ovebotai-panel').hide();
		$('.ovebotai-panel[data-panel="' + step + '"]').show();

		$('.ovebotai-step-dot').each(function () {
			var n = parseInt($(this).data('step'), 10);
			$(this).toggleClass('is-active', n === step);
			$(this).toggleClass('is-done', posOf(n) < pos);
		});

		var pct = stepsSeq.length > 1 ? (pos / (stepsSeq.length - 1)) * 100 : 100;
		$('#oveProgressBar').css('width', pct + '%');

		var isLast = pos === stepsSeq.length - 1;

		$('#oveSetupNav').toggle(!isLast);
		$('#ovePrevBtn').toggle(pos > 0);
		$('#oveNextBtn').toggle(true);

		// Step 1: hide Next until connected.
		if (step === 1) {
			$('#oveNextBtn').toggle(parseInt(cfg.isConnected, 10) === 1);
		}

		// Last content step before Finish: relabel Next to "Finish setup".
		if (step === finishStep) {
			$('#oveNextBtn').text(cfg.i18n.finish + ' →');
			if (step === 3) { updateProductMessage(); }
		} else {
			$('#oveNextBtn').text(cfg.i18n.next + ' →');
		}
	}

	function handleNext() {
		if (navigating) { return; }

		if (current === 2) {
			syncPages();
			return;
		}

		navigating = true;
		setTimeout(function () { navigating = false; }, 500);

		if (current === 4) {
			// Next visible on step 4 only after a failed sync — retry.
			doSync();
		} else if (current === finishStep) {
			renderStep(4);
			doSync();
		} else {
			renderStep(stepsSeq[posOf(current) + 1]);
		}
	}

	function handlePrev() {
		if (navigating) { return; }
		navigating = true;
		setTimeout(function () { navigating = false; }, 500);
		renderStep(stepsSeq[posOf(current) - 1]);
	}

	// ── Product count message ─────────────────────────────────────────────────

	function updateProductMessage() {
		var counts = cfg.productCounts;
		if (!counts) { return; }

		if (parseInt(counts.total, 10) === 0) {
			$('#oveProductMsg').html(cfg.i18n.noProducts);
			return;
		}

		$('#oveProductMsg').html('<span class="ovebotai-count-badge">' + counts.feed_count + '</span> ' + cfg.i18n.productsWillBeIndexed);
	}

	// ── Step 2: page sync ────────────────────────────────────────────────────

	// Fires on every "Next" click from step 2. Whatever the server reports as
	// unsent (real error, or blocked by the kb_limit quota) gets unchecked and
	// flagged inline; the button stays put on step 2 in that case, so the same
	// click that "failed" is really just a cleanup pass — the very next click
	// (now with those boxes unchecked) goes through and advances normally.
	function syncPages() {
		navigating = true;

		$('.ovebotai-page-error').hide().text('').removeClass('is-success');
		$('#oveKbLimitNotice').hide().text('');

		var pageIds = [];
		$('input[name="kb_pages[]"]:checked').each(function () {
			pageIds.push($(this).val());
		});

		$('#oveNextBtn').prop('disabled', true).text(cfg.i18n.syncingPages);
		$('#ovePrevBtn').prop('disabled', true);

		$.ajax({
			url: cfg.syncPagesUrl,
			type: 'POST',
			dataType: 'json',
			data: { page_ids: pageIds }
		})
			.done(function (resp) {
				navigating = false;
				$('#oveNextBtn').prop('disabled', false);
				$('#ovePrevBtn').prop('disabled', false);

				if (!resp || !resp.success) {
					$('#oveKbLimitNotice').text(cfg.i18n.error).show();
					renderStep(2);
					return;
				}

				applyPageFailures(resp.failed || {});
				applyPageFailures(indexKbLimitIds(resp.kb_limit_ids || []));

				if (resp.kb_limit) {
					$('#oveKbLimitNotice').text(resp.kb_limit).show();
				}

				if (resp.clean) {
					renderStep(3);
				} else {
					// Stay on step 2 — checkboxes are already cleaned up above.
					// Whatever's still checked out of this attempt actually went
					// through fine — flag it green so it reads as "this one's
					// done", not lumped in with the failures above.
					var failedIds = Object.keys(resp.failed || {}).concat((resp.kb_limit_ids || []).map(String));
					var succeededIds = pageIds.filter(function (id) { return failedIds.indexOf(id) === -1; });
					applyPageSuccess(succeededIds);
					renderStep(2);
				}
			})
			.fail(function () {
				navigating = false;
				$('#oveNextBtn').prop('disabled', false);
				$('#ovePrevBtn').prop('disabled', false);
				$('#oveKbLimitNotice').text(cfg.i18n.error).show();
			});
	}

	// kb_limit_ids has no per-page message of its own (it's the same quota
	// error for all of them). The banner shows the API's message exactly as
	// received (see the caller) — under each affected checkbox this uses its
	// own separate, static string instead of altering/reusing that API text.
	function indexKbLimitIds(ids) {
		var map = {};
		ids.forEach(function (id) {
			map[id] = cfg.i18n.kbLimitPageSkipped;
		});
		return map;
	}

	function applyPageFailures(failedMap) {
		Object.keys(failedMap).forEach(function (informationId) {
			var $row = $('.ovebotai-page-row[data-information-id="' + informationId + '"]');
			$row.find('input[name="kb_pages[]"]').prop('checked', false);
			$row.find('.ovebotai-page-error').removeClass('is-success').text(failedMap[informationId]).show();
		});
	}

	// Only called when the attempt as a whole wasn't clean (some pages
	// failed) — flags the pages that stayed checked as actually synced this
	// round, same top-right spot as the error text but green, so it's clear
	// they don't need re-sending on the next "Next" click.
	function applyPageSuccess(informationIds) {
		informationIds.forEach(function (informationId) {
			var $row = $('.ovebotai-page-row[data-information-id="' + informationId + '"]');
			$row.find('.ovebotai-page-error').addClass('is-success').text(cfg.i18n.pageUpdated).show();
		});
	}

	// ── Sync ─────────────────────────────────────────────────────────────────

	function doSync() {
		$('#oveSetupNav').hide();
		$('#oveSyncIdle').hide();
		$('#oveSyncLoading').show();
		$('#oveSyncError').hide();

		var productsEnabled = $('#oveProductsIntegrated').is(':checked') ? 1 : 0;

		$.ajax({
			url: cfg.syncUrl,
			type: 'POST',
			dataType: 'json',
			data: { products_enabled: productsEnabled }
		})
			.done(function (resp) {
				$('#oveSyncLoading').hide();
				if (resp && resp.success) {
					$('#oveSyncDone').show();
					// Every dot (including the last) turns green.
					$('.ovebotai-step-dot').removeClass('is-active').addClass('is-done');
				} else {
					showSyncError(resp && resp.message ? resp.message : cfg.i18n.error);
				}
			})
			.fail(function () {
				$('#oveSyncLoading').hide();
				showSyncError(cfg.i18n.error);
			});
	}

	function showSyncError(msg) {
		$('#oveSyncErrorMsg').html('<p>' + escapeHtml(msg).replace(/\n/g, '<br>') + '</p>');
		$('#oveSyncError').show();
		$('#oveNextBtn').text(cfg.i18n.retry).show();
		$('#ovePrevBtn').show();
		$('#oveSetupNav').show();
	}

	function showOauthError(msg) {
		var $notice = $('.ovebotai-notice-error').first();
		if (!$notice.length) {
			$notice = $('<div class="ovebotai-notice ovebotai-notice-error"><p></p></div>');
			$('.ovebotai-connect-box').before($notice);
		}
		$notice.find('p').text(msg).end().show();
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

}(jQuery, ovebotaiSetup));
