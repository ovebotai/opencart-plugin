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

      <a href="<?php echo $dashboard_url; ?>" class="ovebotai-back-link"><i class="fa fa-angle-left"></i> <?php echo $text_back; ?></a>

      <?php if (!empty($success)) { ?>
      <div class="ovebotai-notice ovebotai-notice-success"><p><?php echo $success; ?></p></div>
      <?php } ?>

      <div class="ovebotai-settings-header">
        <h1><?php echo $text_settings_heading; ?></h1>
        <span class="ovebotai-connection-badge">
          <span class="ovebotai-status-dot<?php echo $is_connected ? ' is-connected' : ' is-disconnected'; ?>"></span>
          <?php echo $text_connected; ?><?php echo $workspace ? ' &middot; ' . htmlspecialchars($workspace, ENT_QUOTES, 'UTF-8') : ''; ?>
          <?php if ($is_connected) { ?>
          &middot; <a href="<?php echo $disconnect_url; ?>" data-ovebotai-confirm="<?php echo htmlspecialchars($text_confirm_disconnect, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $button_disconnect; ?></a>
          <?php } ?>
        </span>
      </div>

      <p class="ovebotai-lead"><?php echo $text_settings_lead; ?></p>

    </div>
  </div>
</div>
<?php echo $footer; ?>
