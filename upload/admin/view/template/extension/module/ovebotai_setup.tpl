<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>

  <div class="container-fluid">
    <div class="ovebotai-wrap">
      <div class="ovebotai-setup-card">

        <!-- Header -->
        <div class="ovebotai-setup-header">
          <div class="ovebotai-logo">
            <a href="https://ovebot.ai" target="_blank" rel="noopener noreferrer">
              <img src="view/image/ovebotai/logo.png" alt="Ovebot.ai" height="36" onerror="this.style.display='none'">
            </a>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="ovebotai-progress-track">
          <div class="ovebotai-progress-bar" id="oveProgressBar"></div>
        </div>

        <!-- Step indicators -->
        <div class="ovebotai-steps-nav">
          <div class="ovebotai-steps-nav-inner">
            <?php
            $ovebotai_step_labels = array(
              1 => $text_step_connect,
              2 => $text_step_pages,
              3 => $text_step_products,
              4 => $text_step_golive,
            );
            $ovebotai_current_pos = array_search($initial_step, $steps_seq, true);
            foreach ($steps_seq as $ovebotai_pos => $ovebotai_num) {
              $ovebotai_classes = 'ovebotai-step-dot';
              if ($ovebotai_num === $initial_step) { $ovebotai_classes .= ' is-active'; }
              if ($ovebotai_pos < $ovebotai_current_pos) { $ovebotai_classes .= ' is-done'; }
            ?>
            <div class="<?php echo $ovebotai_classes; ?>" data-step="<?php echo $ovebotai_num; ?>">
              <div class="ovebotai-dot-circle">
                <span class="dot-num"><?php echo $ovebotai_pos + 1; ?></span>
                <span class="dot-check"><i class="fa fa-check"></i></span>
              </div>
              <span class="ovebotai-dot-label"><?php echo $ovebotai_step_labels[$ovebotai_num]; ?></span>
            </div>
            <?php } ?>
          </div>
        </div>

        <!-- Step panels -->
        <div class="ovebotai-panels">

          <!-- Step 1: Connect -->
          <div class="ovebotai-panel" data-panel="1"<?php echo 1 !== $initial_step ? ' style="display:none"' : ''; ?>>
            <h2><?php echo $text_connect_heading; ?></h2>
            <p class="ovebotai-lead"><?php echo $text_connect_lead; ?></p>

            <?php if ($oauth_error) { ?>
            <div class="ovebotai-notice ovebotai-notice-error"><p><?php echo htmlspecialchars($oauth_error, ENT_QUOTES, 'UTF-8'); ?></p></div>
            <?php } ?>

            <div class="ovebotai-connect-box">
              <div class="ovebotai-connect-actions">
                <a href="<?php echo $connect_url; ?>" class="button ovebotai-btn-connect"><?php echo $button_connect_existing; ?></a>
                <a href="<?php echo $register_url; ?>" target="_blank" rel="noopener noreferrer" class="button ovebotai-btn-trial"><?php echo $button_try_free; ?></a>
              </div>
            </div>
          </div>

          <!-- Step 2: Website pages -->
          <div class="ovebotai-panel" data-panel="2"<?php echo 2 !== $initial_step ? ' style="display:none"' : ''; ?>>
            <h2><?php echo $text_pages_heading; ?></h2>
            <p class="ovebotai-lead"><?php echo $text_pages_lead; ?></p>

            <?php if (empty($pages)) { ?>
            <p class="ovebotai-muted"><?php echo $text_no_pages; ?></p>
            <?php } else { ?>
            <div class="ovebotai-pages-list">
              <?php foreach ($pages as $page) { ?>
              <label class="ovebotai-page-item">
                <input type="checkbox" name="kb_pages[]" value="<?php echo $page['information_id']; ?>"<?php echo $page['checked'] ? ' checked="checked"' : ''; ?>>
                <span class="ovebotai-checkbox-mark" aria-hidden="true"></span>
                <div class="ovebotai-page-info">
                  <span class="ovebotai-page-title"><?php echo htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
              </label>
              <?php } ?>
            </div>
            <?php } ?>
          </div>

          <!-- Step 3: Products -->
          <div class="ovebotai-panel" data-panel="3"<?php echo 3 !== $initial_step ? ' style="display:none"' : ''; ?>>
            <h2><?php echo $text_products_heading; ?></h2>
            <p class="ovebotai-lead" id="oveProductMsg"><?php echo $text_products_checking; ?></p>
          </div>

          <!-- Step 4: Go live -->
          <div class="ovebotai-panel" data-panel="4"<?php echo 4 !== $initial_step ? ' style="display:none"' : ''; ?>>
            <div class="ovebotai-sync-idle" id="oveSyncIdle">
              <h2><?php echo $text_golive_heading; ?></h2>
              <p class="ovebotai-lead"><?php echo $text_golive_lead; ?></p>
            </div>
            <div class="ovebotai-sync-loading" id="oveSyncLoading" style="display:none">
              <div class="ovebotai-spinner-wrap">
                <span class="ovebotai-spinner"></span>
                <p id="oveSyncStatus"><?php echo $text_syncing; ?></p>
              </div>
            </div>
            <div class="ovebotai-sync-done" id="oveSyncDone" style="display:none">
              <div class="ovebotai-done-icon"><i class="fa fa-check"></i></div>
              <h2><?php echo $text_done_heading; ?></h2>
              <p class="ovebotai-lead"><?php echo $text_done_lead; ?></p>
              <div class="ovebotai-notice ovebotai-notice-warning" id="oveSyncWarnings" style="display:none"></div>
              <div class="ovebotai-done-actions">
                <a href="<?php echo $settings_url; ?>" class="button ovebotai-btn-muted"><?php echo $button_settings; ?></a>
                <a href="<?php echo $chat_url; ?>" class="button button-primary" target="_blank" rel="noopener noreferrer"><?php echo $button_chat; ?></a>
              </div>
            </div>
            <div class="ovebotai-sync-error" id="oveSyncError" style="display:none">
              <div class="ovebotai-notice ovebotai-notice-error" id="oveSyncErrorMsg"></div>
            </div>
          </div>

        </div><!-- /.ovebotai-panels -->

        <!-- Navigation -->
        <div class="ovebotai-setup-nav" id="oveSetupNav">
          <button type="button" class="button" id="ovePrevBtn" style="display:none"><?php echo $button_prev; ?></button>
          <?php $ovebotai_next_hidden = (1 === $initial_step && !$is_connected); ?>
          <button type="button" class="button button-primary" id="oveNextBtn"<?php echo $ovebotai_next_hidden ? ' style="display:none"' : ''; ?>><?php echo $button_next; ?></button>
        </div>

      </div><!-- /.ovebotai-setup-card -->
    </div><!-- /.ovebotai-wrap -->
  </div>
</div>

<script type="text/javascript">
var ovebotaiSetup = {
  syncUrl: '<?php echo html_entity_decode($sync_url, ENT_QUOTES, 'UTF-8'); ?>',
  initialStep: <?php echo (int)$initial_step; ?>,
  stepsSequence: <?php echo json_encode($steps_seq); ?>,
  isConnected: <?php echo (int)$is_connected; ?>,
  productCounts: <?php echo json_encode($product_counts); ?>,
  oauthError: <?php echo json_encode($oauth_error); ?>,
  i18n: {
    next: <?php echo json_encode($button_next); ?>,
    finish: <?php echo json_encode($button_finish); ?>,
    retry: <?php echo json_encode($button_retry); ?>,
    error: <?php echo json_encode($text_error); ?>,
    noProducts: <?php echo json_encode($text_no_products); ?>,
    productsWillBeIndexed: <?php echo json_encode($text_products_indexed); ?>
  }
};
</script>

<script src="view/javascript/ovebotai/ovebotai.js"></script>
<script src="view/javascript/ovebotai/setup.js"></script>

<?php echo $footer; ?>
