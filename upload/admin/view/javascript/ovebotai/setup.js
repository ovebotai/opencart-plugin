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
			$('#oveNextBtn').text(cfg.i18n.finish);
			if (step === 3) { updateProductMessage(); }
		} else {
			$('#oveNextBtn').text(cfg.i18n.next);
		}
	}

	function handleNext() {
		if (navigating) { return; }
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

	// ── Sync ─────────────────────────────────────────────────────────────────

	function doSync() {
		$('#oveSetupNav').hide();
		$('#oveSyncIdle').hide();
		$('#oveSyncLoading').show();
		$('#oveSyncError').hide();

		var pageIds = [];
		$('input[name="kb_pages[]"]:checked').each(function () {
			pageIds.push($(this).val());
		});

		$.ajax({
			url: cfg.syncUrl,
			type: 'POST',
			dataType: 'json',
			data: { page_ids: pageIds }
		})
			.done(function (resp) {
				$('#oveSyncLoading').hide();
				if (resp && resp.success) {
					$('#oveSyncDone').show();
					var warnings = resp.warnings;
					if (warnings && warnings.length) {
						$('#oveSyncWarnings').html('<p>' + warnings.join('<br>') + '</p>').show();
					}
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
