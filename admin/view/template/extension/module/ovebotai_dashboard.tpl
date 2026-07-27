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

      <?php if (!empty($success)) { ?>
      <div class="ovebotai-notice ovebotai-notice-success"><p><?php echo $success; ?></p></div>
      <?php } ?>

      <div class="ovebotai-settings-header">
        <div class="ovebotai-logo">
          <a href="https://ovebot.ai" target="_blank" rel="noopener noreferrer">
            <img src="view/image/ovebotai/logo.png" alt="Ovebot.ai" height="32" onerror="this.style.display='none'">
          </a>
          <h1><?php echo $text_dashboard_heading; ?></h1>
          <span class="ovebotai-version">v<?php echo $module_version; ?></span>
        </div>
        <span class="ovebotai-connection-badge">
          <span class="ovebotai-status-dot<?php echo $is_connected ? ' is-connected' : ' is-disconnected'; ?>"></span>
          <?php $ovebotai_badge = $workspace ? $workspace . ':' . ($agent !== '' ? $agent : 'default') : ''; ?>
          <?php echo $text_connected; ?><?php echo $ovebotai_badge ? ' &middot; ' . htmlspecialchars($ovebotai_badge, ENT_QUOTES, 'UTF-8') : ''; ?>
          <?php if ($is_connected) { ?>
          &middot; <a href="<?php echo $disconnect_url; ?>" class="ovebotai-disconnect-link" data-ovebotai-confirm="<?php echo htmlspecialchars($text_confirm_disconnect, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $button_disconnect; ?></a>
          <?php } ?>
        </span>
      </div>

      <div class="ovebotai-dashboard-cards">
        <?php if ($account_url) { ?>
        <a href="<?php echo $account_url; ?>" target="_blank" rel="noopener noreferrer" class="ovebotai-dash-card">
          <span class="ovebotai-dash-card-icon"><i class="fa fa-external-link"></i></span>
          <span class="ovebotai-dash-card-title"><?php echo $button_account; ?></span>
        </a>
        <?php } ?>
        <a href="<?php echo $settings_url; ?>" class="ovebotai-dash-card">
          <span class="ovebotai-dash-card-icon"><i class="fa fa-cog"></i></span>
          <span class="ovebotai-dash-card-title"><?php echo $button_settings; ?></span>
        </a>
        <?php if ($chat_status) { ?>
        <a href="<?php echo $chat_url; ?>" target="_blank" rel="noopener noreferrer" class="ovebotai-dash-card">
          <span class="ovebotai-dash-card-icon"><i class="fa fa-comments"></i></span>
          <span class="ovebotai-dash-card-title"><?php echo $button_chat; ?> &rarr;</span>
        </a>
        <?php } else { ?>
        <a href="<?php echo $settings_chat_highlight_url; ?>" class="ovebotai-dash-card ovebotai-dash-card-muted">
          <span class="ovebotai-dash-card-icon"><i class="fa fa-comments"></i></span>
          <span class="ovebotai-dash-card-title"><?php echo $text_chat_disabled; ?> &rarr;</span>
        </a>
        <?php } ?>
      </div>

      <?php if ($setup_url) { ?>
      <a href="<?php echo htmlspecialchars($setup_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="ovebotai-advanced-panel">
        <span class="ovebotai-advanced-icon"><i class="fa fa-sliders"></i></span>
        <span class="ovebotai-advanced-body">
          <span class="ovebotai-advanced-title"><?php echo $text_advanced_title; ?></span>
          <span class="ovebotai-advanced-desc"><?php echo $text_advanced_desc; ?></span>
        </span>
        <span class="ovebotai-advanced-cta"><?php echo $button_open_account_settings; ?> &rarr;</span>
      </a>
      <?php } ?>

      <?php
      // "here" links pointing at the local settings page, deep-linked to the
      // specific toggle (which is synced to the account) - safer than sending
      // the merchant off to the Ovebot.ai account to change it.
      $ovebotai_products_link = '<a href="' . $settings_products_highlight_url . '">' . $text_link_here . '</a>';
      $ovebotai_order_link    = '<a href="' . $settings_order_highlight_url . '">' . $text_link_here . '</a>';
      ?>

      <?php if ($products_recommend_enabled) { ?>
      <div class="ovebotai-dash-card-wide">
        <span class="ovebotai-dash-card-wide-label">
          <i class="fa fa-shopping-cart"></i>
          <?php echo $text_products_indexed_label; ?>
        </span>
        <?php $ovebotai_count_classes = 'ovebotai-dash-card-wide-count ' . ($products_count > 0 ? 'is-positive' : 'is-zero'); ?>
        <?php if ($products_url) { ?>
        <a class="<?php echo $ovebotai_count_classes; ?>" href="<?php echo $products_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo number_format($products_count); ?></a>
        <?php } else { ?>
        <span class="<?php echo $ovebotai_count_classes; ?>"><?php echo number_format($products_count); ?></span>
        <?php } ?>
      </div>
      <?php } else { ?>
      <div class="ovebotai-notice ovebotai-notice-warning ovebotai-notice-titled">
        <div class="ovebotai-notice-content">
          <span class="ovebotai-notice-title"><?php echo $text_products_recommend_off_title; ?></span>
          <p><?php echo sprintf($text_products_recommend_off, $ovebotai_products_link); ?></p>
        </div>
      </div>
      <?php } ?>

      <?php if (!$order_api_enabled) { ?>
      <div class="ovebotai-notice ovebotai-notice-warning ovebotai-notice-titled">
        <div class="ovebotai-notice-content">
          <span class="ovebotai-notice-title"><?php echo $text_order_api_off_title; ?></span>
          <p><?php echo sprintf($text_order_api_off, $ovebotai_order_link); ?></p>
        </div>
      </div>
      <?php } ?>

      <div class="ovebotai-fieldset">
        <div class="ovebotai-fieldset-legend">
          <i class="fa fa-book"></i>
          <?php echo $text_kb_heading; ?>
        </div>
        <div class="ovebotai-fieldset-body">

          <p class="ovebotai-muted ovebotai-fieldset-intro"><?php echo $text_kb_intro; ?></p>

          <?php if ($kb_error) { ?>
          <div class="ovebotai-notice ovebotai-notice-warning"><p><?php echo $text_kb_error; ?></p></div>
          <?php } elseif (empty($kb_entries)) { ?>
          <p class="ovebotai-muted"><?php echo sprintf($text_kb_none, '<a href="' . htmlspecialchars($kb_create_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $text_kb_add_one . '</a>'); ?></p>
          <?php } else { ?>
          <p class="ovebotai-muted ovebotai-kb-summary"><?php echo sprintf($text_kb_active_count, (int)$kb_active_count, count($kb_entries)); ?></p>
          <div class="ovebotai-pages-list ovebotai-kb-list">
            <?php foreach ($kb_entries as $ovebotai_entry) { ?>
            <div class="ovebotai-page-item">
              <div class="ovebotai-page-info">
                <span class="ovebotai-page-title"><?php echo htmlspecialchars($ovebotai_entry['title'], ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="ovebotai-kb-item-actions">
                <?php if ($ovebotai_entry['is_active']) { ?>
                <span class="ovebotai-status-badge is-active"><?php echo $text_kb_active; ?></span>
                <?php } else { ?>
                <span class="ovebotai-status-badge is-inactive"><?php echo $text_kb_inactive; ?></span>
                <?php } ?>
                <a href="<?php echo htmlspecialchars($ovebotai_entry['edit_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="ovebotai-page-url">
                  <?php echo $button_edit_on_ovebotai; ?>
                  <i class="fa fa-external-link ovebotai-ext-icon"></i>
                </a>
              </div>
            </div>
            <?php } ?>
          </div>
          <?php } ?>

        </div>
      </div>

    </div>
  </div>
</div>

<script src="view/javascript/ovebotai/ovebotai.js"></script>

<?php echo $footer; ?>
