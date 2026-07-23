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

      <div class="ovebotai-settings-header" style="margin-bottom: 10px;">
        <div class="ovebotai-logo">
          <a href="https://ovebot.ai" target="_blank" rel="noopener noreferrer">
            <img src="view/image/ovebotai/logo.png" alt="Ovebot.ai" height="32" onerror="this.style.display='none'">
          </a>
          <h1><?php echo $text_settings_heading; ?></h1>
        </div>
        <span class="ovebotai-connection-badge">
          <span class="ovebotai-status-dot<?php echo $is_connected ? ' is-connected' : ' is-disconnected'; ?>"></span>
          <?php echo $text_connected; ?><?php echo $workspace ? ' &middot; ' . htmlspecialchars($workspace, ENT_QUOTES, 'UTF-8') : ''; ?>
          <?php if ($is_connected) { ?>
          &middot; <a href="<?php echo $disconnect_url; ?>" class="ovebotai-disconnect-link" data-ovebotai-confirm="<?php echo htmlspecialchars($text_confirm_disconnect, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $button_disconnect; ?></a>
          <?php } ?>
        </span>
      </div>

      <a href="<?php echo $dashboard_url; ?>" class="ovebotai-back-link"><i class="fa fa-angle-left"></i> <?php echo $text_back; ?></a>

      <div id="oveSettingsNotice" class="ovebotai-save-notice" style="display:none"></div>
      <div id="oveSettingsWarnings" class="ovebotai-save-notice ovebotai-notice-warning" style="display:none"></div>

      <!-- onsubmit="return false" is a safety net against a native submit firing
           before settings.js attaches its own submit handler. It never blocks
           the real AJAX handler, which listens for the same submit event
           independently. -->
      <form id="oveSettingsForm" onsubmit="return false;">

        <!-- ── Chat Widget ────────────────────────────────────────────────── -->
        <div class="ovebotai-fieldset">
          <div class="ovebotai-fieldset-legend">
            <i class="fa fa-comments"></i>
            <?php echo $text_widget_heading; ?>
          </div>
          <div class="ovebotai-fieldset-body">

            <div class="ovebotai-field ovebotai-field-switch">
              <label><?php echo $entry_chat_status; ?></label>
              <div class="ovebotai-switch-wrap">
                <label class="ovebotai-switch">
                  <input type="checkbox" name="chat_status" id="oveChatStatus" value="1"<?php echo $chat_status ? ' checked' : ''; ?>>
                  <span class="ovebotai-switch-slider"></span>
                </label>
                <span class="ovebotai-switch-lbl" id="oveChatStatusLbl"><?php echo $chat_status ? $text_enabled : $text_disabled; ?></span>
              </div>
              <p class="description"><?php echo $help_chat_status; ?></p>
            </div>

          </div>
        </div>

        <!-- ── Product Feed ───────────────────────────────────────────────── -->
        <div class="ovebotai-fieldset">
          <div class="ovebotai-fieldset-legend">
            <i class="fa fa-rss"></i>
            <?php echo $text_feed_heading; ?>
          </div>
          <div class="ovebotai-fieldset-body">

            <p class="description ovebotai-fieldset-intro"><?php echo $help_feed_intro; ?></p>

            <div class="ovebotai-field">
              <label><?php echo $entry_feed_url; ?></label>
              <div class="ovebotai-url-row">
                <input type="text" class="regular-text ovebotai-readonly-url" id="oveFeedUrl" value="<?php echo htmlspecialchars($feed_url, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                <button type="button" class="button ovebotai-copy-btn" data-target="oveFeedUrl"><?php echo $button_copy; ?></button>
                <button type="button" class="button ovebotai-regen-hash-btn" aria-label="<?php echo htmlspecialchars($button_regen_hash, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($button_regen_hash, ENT_QUOTES, 'UTF-8'); ?>">
                  <i class="fa fa-refresh"></i>
                </button>
              </div>
            </div>

            <div class="ovebotai-field">
              <button type="button" class="button ovebotai-clear-cache-btn"><?php echo $button_clear_cache; ?></button>
              <p class="description"><?php echo $help_clear_cache; ?></p>
            </div>

          </div>
        </div>

        <!-- ── Order Tracking ─────────────────────────────────────────────── -->
        <div class="ovebotai-fieldset">
          <div class="ovebotai-fieldset-legend">
            <i class="fa fa-map-marker"></i>
            <?php echo $text_orders_heading; ?>
          </div>
          <div class="ovebotai-fieldset-body">

            <p class="description ovebotai-fieldset-intro"><?php echo $help_orders_intro; ?></p>

            <div class="ovebotai-field">
              <label><?php echo $entry_order_url; ?></label>
              <div class="ovebotai-url-row">
                <input type="text" class="regular-text ovebotai-readonly-url" id="oveOrderUrl" value="<?php echo htmlspecialchars($order_url, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                <button type="button" class="button ovebotai-copy-btn" data-target="oveOrderUrl"><?php echo $button_copy; ?></button>
              </div>
            </div>

            <div class="ovebotai-field">
              <label><?php echo $entry_api_user; ?></label>
              <div class="ovebotai-url-row">
                <input type="text" class="regular-text ovebotai-readonly-url" id="oveApiUser" value="<?php echo htmlspecialchars($order_user, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                <button type="button" class="button ovebotai-copy-btn" data-target="oveApiUser"><?php echo $button_copy; ?></button>
              </div>
            </div>

            <div class="ovebotai-field">
              <label><?php echo $entry_api_pass; ?></label>
              <div class="ovebotai-url-row">
                <input type="text" class="regular-text ovebotai-readonly-url" id="oveApiPass" value="<?php echo htmlspecialchars($order_pass, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                <button type="button" class="button ovebotai-copy-btn" data-target="oveApiPass"><?php echo $button_copy; ?></button>
              </div>
            </div>

            <div class="ovebotai-field">
              <button type="button" class="button ovebotai-regen-creds-btn"><?php echo $button_regen_creds; ?></button>
              <p class="description"><?php echo $help_regen_creds; ?></p>
            </div>

          </div>
        </div>

        <!-- ── Delivery Estimate ──────────────────────────────────────────── -->
        <div class="ovebotai-fieldset">
          <div class="ovebotai-fieldset-legend">
            <i class="fa fa-clock-o"></i>
            <?php echo $text_delivery_heading; ?>
          </div>
          <div class="ovebotai-fieldset-body">

            <p class="description ovebotai-fieldset-intro"><?php echo $help_delivery_intro; ?></p>

            <div class="ovebotai-field ovebotai-field-delivery">
              <label><?php echo $entry_delivery_shipped; ?></label>
              <div class="ovebotai-delivery-inputs">
                <input type="number" name="days_shipped_min" class="small-text" value="<?php echo (int)$delivery['days_shipped_min']; ?>" min="0" max="60">
                <span class="ovebotai-dash">&ndash;</span>
                <input type="number" name="days_shipped_max" class="small-text" value="<?php echo (int)$delivery['days_shipped_max']; ?>" min="0" max="60">
                <span class="ovebotai-unit"><?php echo $text_business_days; ?></span>
              </div>
              <p class="description"><?php echo $help_delivery_shipped; ?></p>
            </div>

            <div class="ovebotai-field ovebotai-field-delivery">
              <label><?php echo $entry_delivery_instock; ?></label>
              <div class="ovebotai-delivery-inputs">
                <input type="number" name="days_instock_min" class="small-text" value="<?php echo (int)$delivery['days_instock_min']; ?>" min="0" max="60">
                <span class="ovebotai-dash">&ndash;</span>
                <input type="number" name="days_instock_max" class="small-text" value="<?php echo (int)$delivery['days_instock_max']; ?>" min="0" max="60">
                <span class="ovebotai-unit"><?php echo $text_business_days; ?></span>
              </div>
              <p class="description"><?php echo $help_delivery_instock; ?></p>
            </div>

            <div class="ovebotai-field ovebotai-field-delivery">
              <label><?php echo $entry_delivery_oos; ?></label>
              <div class="ovebotai-delivery-inputs">
                <input type="number" name="days_oos_min" class="small-text" value="<?php echo (int)$delivery['days_oos_min']; ?>" min="0" max="60">
                <span class="ovebotai-dash">&ndash;</span>
                <input type="number" name="days_oos_max" class="small-text" value="<?php echo (int)$delivery['days_oos_max']; ?>" min="0" max="60">
                <span class="ovebotai-unit"><?php echo $text_business_days; ?></span>
              </div>
              <p class="description"><?php echo $help_delivery_oos; ?></p>
            </div>

          </div>
        </div>

        <!-- ── Appearance ─────────────────────────────────────────────────── -->
        <?php
        $ovebotai_w = array_merge(array(
            'accent_color' => '', 'theme' => '', 'language' => '', 'audio_beep' => '',
            'side' => '', 'offset_y' => '', 'subtitle' => '', 'proactive_message' => '', 'proactive_delay' => '',
        ), $widget);
        ?>
        <div class="ovebotai-fieldset">
          <div class="ovebotai-fieldset-legend">
            <i class="fa fa-paint-brush"></i>
            <?php echo $text_appearance_heading; ?>
          </div>
          <div class="ovebotai-fieldset-body">

            <div class="ovebotai-field">
              <button type="button" class="button" id="oveAppearanceToggle">
                <?php echo $button_configure_appearance; ?>
                <i class="fa fa-chevron-down" id="oveAppearanceToggleIcon"></i>
              </button>
            </div>

            <div class="ovebotai-appearance-panel" id="oveAppearancePanel" style="display:none">

              <div class="ovebotai-appearance-subhead"><?php echo $text_look_feel; ?></div>
              <div class="ovebotai-appearance-grid">

                <div class="ovebotai-field">
                  <label for="ove_accent_color"><?php echo $entry_accent_color; ?></label>
                  <div class="ovebotai-color-wrap">
                    <input type="color" id="ove_color_picker" value="<?php echo htmlspecialchars($ovebotai_w['accent_color'] !== '' ? $ovebotai_w['accent_color'] : '#2271B1', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="text" name="widget_accent_color" id="ove_accent_color" class="regular-text" value="<?php echo htmlspecialchars($ovebotai_w['accent_color'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="#2271B1" maxlength="7">
                  </div>
                </div>

                <div class="ovebotai-field">
                  <label for="ove_theme"><?php echo $entry_theme; ?></label>
                  <select name="widget_theme" id="ove_theme">
                    <option value=""<?php echo $ovebotai_w['theme'] === '' ? ' selected' : ''; ?>><?php echo $text_theme_default; ?></option>
                    <option value="light"<?php echo $ovebotai_w['theme'] === 'light' ? ' selected' : ''; ?>><?php echo $text_theme_light; ?></option>
                    <option value="dark"<?php echo $ovebotai_w['theme'] === 'dark' ? ' selected' : ''; ?>><?php echo $text_theme_dark; ?></option>
                  </select>
                </div>

                <div class="ovebotai-field">
                  <label for="ove_language"><?php echo $entry_widget_language; ?></label>
                  <select name="widget_language" id="ove_language">
                    <option value=""<?php echo $ovebotai_w['language'] === '' ? ' selected' : ''; ?>><?php echo $text_lang_default; ?></option>
                    <option value="auto"<?php echo $ovebotai_w['language'] === 'auto' ? ' selected' : ''; ?>><?php echo $text_lang_auto; ?></option>
                    <option value="en"<?php echo $ovebotai_w['language'] === 'en' ? ' selected' : ''; ?>>English</option>
                    <option value="ro"<?php echo $ovebotai_w['language'] === 'ro' ? ' selected' : ''; ?>>Română</option>
                    <option value="de"<?php echo $ovebotai_w['language'] === 'de' ? ' selected' : ''; ?>>Deutsch</option>
                    <option value="fr"<?php echo $ovebotai_w['language'] === 'fr' ? ' selected' : ''; ?>>Français</option>
                  </select>
                </div>

                <div class="ovebotai-field">
                  <label for="ove_audio_beep"><?php echo $entry_audio_beep; ?></label>
                  <select name="widget_audio_beep" id="ove_audio_beep">
                    <option value=""<?php echo $ovebotai_w['audio_beep'] === '' ? ' selected' : ''; ?>><?php echo $text_audio_default; ?></option>
                    <option value="play"<?php echo $ovebotai_w['audio_beep'] === 'play' ? ' selected' : ''; ?>><?php echo $text_audio_play; ?></option>
                    <option value="none"<?php echo $ovebotai_w['audio_beep'] === 'none' ? ' selected' : ''; ?>><?php echo $text_audio_none; ?></option>
                  </select>
                </div>

              </div><!-- /.ovebotai-appearance-grid -->

              <div class="ovebotai-appearance-subhead"><?php echo $text_position_heading; ?></div>
              <div class="ovebotai-field ovebotai-field-full">
                <label for="ove_side"><?php echo $entry_widget_position; ?></label>
                <div class="ovebotai-combo-row">
                  <select name="widget_side" id="ove_side">
                    <option value=""<?php echo $ovebotai_w['side'] === '' ? ' selected' : ''; ?>><?php echo $text_position_default; ?></option>
                    <option value="right"<?php echo $ovebotai_w['side'] === 'right' ? ' selected' : ''; ?>><?php echo $text_position_right; ?></option>
                    <option value="left"<?php echo $ovebotai_w['side'] === 'left' ? ' selected' : ''; ?>><?php echo $text_position_left; ?></option>
                  </select>
                  <div class="ovebotai-combo-sub">
                    <input type="number" name="widget_offset_y" id="ove_offset_y" class="small-text" value="<?php echo htmlspecialchars($ovebotai_w['offset_y'], ENT_QUOTES, 'UTF-8'); ?>" min="0" placeholder="20">
                    <span class="ovebotai-combo-suffix"><?php echo $text_px_offset; ?></span>
                  </div>
                </div>
                <p class="description"><?php echo $help_widget_position; ?></p>
              </div>

              <div class="ovebotai-appearance-subhead"><?php echo $text_messages_heading; ?></div>
              <div class="ovebotai-field ovebotai-field-full">
                <label for="ove_subtitle"><?php echo $entry_subtitle; ?></label>
                <input type="text" name="widget_subtitle" id="ove_subtitle" class="regular-text" value="<?php echo htmlspecialchars($ovebotai_w['subtitle'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($text_subtitle_placeholder, ENT_QUOTES, 'UTF-8'); ?>">
                <p class="description"><?php echo $help_subtitle; ?></p>
              </div>

              <div class="ovebotai-field ovebotai-field-full">
                <label for="ove_proactive_message"><?php echo $entry_proactive_message; ?></label>
                <div class="ovebotai-combo-row">
                  <input type="text" name="widget_proactive_message" id="ove_proactive_message" class="regular-text" value="<?php echo htmlspecialchars($ovebotai_w['proactive_message'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($text_proactive_placeholder, ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="ovebotai-combo-sub">
                    <input type="number" name="widget_proactive_delay" id="ove_proactive_delay" class="small-text" value="<?php echo htmlspecialchars($ovebotai_w['proactive_delay'], ENT_QUOTES, 'UTF-8'); ?>" min="0" max="300" placeholder="4">
                    <span class="ovebotai-combo-suffix"><?php echo $text_seconds_after_load; ?></span>
                  </div>
                </div>
                <p class="description"><?php echo $help_proactive_message; ?></p>
              </div>

            </div><!-- /.ovebotai-appearance-panel -->

          </div>
        </div>

        <!-- Save -->
        <div class="ovebotai-save-row">
          <button type="submit" class="button button-primary button-large" id="oveSaveBtn"><?php echo $button_save_settings; ?></button>
        </div>

      </form>

    </div>
  </div>
</div>

<script type="text/javascript">
var ovebotaiSettings = {
  isConnected: <?php echo (int)$is_connected; ?>,
  dashboardUrl: <?php echo json_encode(html_entity_decode($dashboard_url, ENT_QUOTES, 'UTF-8')); ?>,
  saveUrl: <?php echo json_encode(html_entity_decode($save_url, ENT_QUOTES, 'UTF-8')); ?>,
  regenHashUrl: <?php echo json_encode(html_entity_decode($regen_hash_url, ENT_QUOTES, 'UTF-8')); ?>,
  regenCredsUrl: <?php echo json_encode(html_entity_decode($regen_creds_url, ENT_QUOTES, 'UTF-8')); ?>,
  clearCacheUrl: <?php echo json_encode(html_entity_decode($clear_cache_url, ENT_QUOTES, 'UTF-8')); ?>,
  i18n: {
    saved: <?php echo json_encode($text_settings_saved); ?>,
    saving: <?php echo json_encode($text_saving); ?>,
    error: <?php echo json_encode($text_error); ?>,
    enabled: <?php echo json_encode($text_enabled); ?>,
    disabled: <?php echo json_encode($text_disabled); ?>,
    confirmRegenHash: <?php echo json_encode($text_confirm_regen_hash); ?>,
    confirmRegenCreds: <?php echo json_encode($text_confirm_regen_creds); ?>,
    confirmClearCache: <?php echo json_encode($text_confirm_clear_cache); ?>,
    copied: <?php echo json_encode($text_copied); ?>
  }
};
</script>

<script src="view/javascript/ovebotai/ovebotai.js"></script>
<script src="view/javascript/ovebotai/settings.js"></script>

<?php echo $footer; ?>
