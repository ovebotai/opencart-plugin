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
        </div>
        <span class="ovebotai-connection-badge">
          <span class="ovebotai-status-dot<?php echo $is_connected ? ' is-connected' : ' is-disconnected'; ?>"></span>
          <?php echo $text_connected; ?><?php echo $workspace ? ' &middot; ' . htmlspecialchars($workspace, ENT_QUOTES, 'UTF-8') : ''; ?>
          <?php if ($is_connected) { ?>
          &middot; <a href="<?php echo $disconnect_url; ?>" data-ovebotai-confirm="<?php echo htmlspecialchars($text_confirm_disconnect, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $button_disconnect; ?></a>
          <?php } ?>
        </span>
      </div>

      <div class="ovebotai-dashboard-cards">
        <a href="<?php echo $chat_url; ?>" target="_blank" rel="noopener noreferrer" class="ovebotai-dash-card">
          <span class="ovebotai-dash-card-icon"><i class="fa fa-comments"></i></span>
          <span class="ovebotai-dash-card-title"><?php echo $button_chat; ?></span>
        </a>
        <a href="<?php echo $settings_url; ?>" class="ovebotai-dash-card">
          <span class="ovebotai-dash-card-icon"><i class="fa fa-cog"></i></span>
          <span class="ovebotai-dash-card-title"><?php echo $button_settings; ?></span>
        </a>
        <?php if ($account_url) { ?>
        <a href="<?php echo $account_url; ?>" target="_blank" rel="noopener noreferrer" class="ovebotai-dash-card">
          <span class="ovebotai-dash-card-icon"><i class="fa fa-external-link"></i></span>
          <span class="ovebotai-dash-card-title"><?php echo $button_account; ?></span>
        </a>
        <?php } ?>
      </div>

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
<?php echo $footer; ?>
