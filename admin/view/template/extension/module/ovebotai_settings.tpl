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

            <div class="ovebotai-field ovebotai-field-switch">
              <label><?php echo $entry_products_recommend; ?></label>
              <div class="ovebotai-switch-wrap">
                <label class="ovebotai-switch">
                  <input type="checkbox" name="products_recommend" id="oveProductsRecommend" value="1"<?php echo $products_recommend ? ' checked' : ''; ?>>
                  <span class="ovebotai-switch-slider"></span>
                </label>
                <span class="ovebotai-switch-lbl" id="oveProductsRecommendLbl"><?php echo $products_recommend ? $text_enabled : $text_disabled; ?></span>
              </div>
              <p class="description"><?php echo $help_products_recommend; ?></p>
            </div>

            <div class="ovebotai-field ovebotai-field-switch">
              <label><?php echo $entry_add_to_cart; ?></label>
              <div class="ovebotai-switch-wrap">
                <label class="ovebotai-switch">
                  <input type="checkbox" name="add_to_cart" id="oveAddToCart" value="1"<?php echo $add_to_cart ? ' checked' : ''; ?>>
                  <span class="ovebotai-switch-slider"></span>
                </label>
                <span class="ovebotai-switch-lbl" id="oveAddToCartLbl"><?php echo $add_to_cart ? $text_enabled : $text_disabled; ?></span>
              </div>
              <p class="description"><?php echo $help_add_to_cart; ?></p>
            </div>

            <div class="ovebotai-field ovebotai-field-switch">
              <label><?php echo $entry_products_enabled; ?></label>
              <div class="ovebotai-switch-wrap">
                <label class="ovebotai-switch">
                  <input type="checkbox" name="products_enabled" id="oveProductsEnabled" value="1"<?php echo $products_enabled ? ' checked' : ''; ?>>
                  <span class="ovebotai-switch-slider"></span>
                </label>
                <span class="ovebotai-switch-lbl" id="oveProductsEnabledLbl"><?php echo $products_enabled ? $text_enabled : $text_disabled; ?></span>
              </div>
              <p class="description"><?php echo $help_products_enabled; ?><?php if ($setup_url) { ?> <a href="<?php echo $setup_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo $setup_url; ?></a><?php } ?></p>
            </div>

            <div class="ovebotai-field" id="oveFeedUrlField"<?php echo $products_enabled ? '' : ' style="display:none"'; ?>>
              <label><?php echo $entry_feed_url; ?></label>
              <div class="ovebotai-url-row">
                <input type="text" class="regular-text ovebotai-readonly-url" id="oveFeedUrl" value="<?php echo htmlspecialchars($feed_url, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                <button type="button" class="button ovebotai-copy-btn" data-target="oveFeedUrl"><?php echo $button_copy; ?></button>
                <button type="button" class="button ovebotai-regen-hash-btn" aria-label="<?php echo htmlspecialchars($button_regen_hash, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($button_regen_hash, ENT_QUOTES, 'UTF-8'); ?>">
                  <i class="fa fa-refresh"></i>
                </button>
              </div>
              <p class="description ovebotai-fieldset-intro"><?php echo $help_feed_intro; ?></p>
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

            <div class="ovebotai-field ovebotai-field-switch">
              <label><?php echo $entry_order_enabled; ?></label>
              <div class="ovebotai-switch-wrap">
                <label class="ovebotai-switch">
                  <input type="checkbox" name="order_enabled" id="oveOrderEnabled" value="1"<?php echo $order_enabled ? ' checked' : ''; ?>>
                  <span class="ovebotai-switch-slider"></span>
                </label>
                <span class="ovebotai-switch-lbl" id="oveOrderEnabledLbl"><?php echo $order_enabled ? $text_enabled : $text_disabled; ?></span>
              </div>
              <p class="description"><?php echo $help_order_enabled; ?></p>
            </div>

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

        <!-- ── Appearance ─────────────────────────────────────────────────── -->
        <?php
        $ovebotai_w = array_merge(array(
            'accent_color' => '', 'theme' => '', 'language' => '', 'audio_beep' => '',
            'side' => '', 'offset_y' => '', 'z_index' => '', 'subtitle' => '', 'proactive_message' => '', 'proactive_delay' => '',
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
                    <input type="color" id="ove_color_picker" value="<?php echo htmlspecialchars($ovebotai_w['accent_color'] !== '' ? $ovebotai_w['accent_color'] : '#615ED6', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="text" name="widget_accent_color" id="ove_accent_color" class="regular-text" value="<?php echo htmlspecialchars($ovebotai_w['accent_color'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="#615ED6" maxlength="7">
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

              <div class="ovebotai-field ovebotai-field-full">
                <label for="ove_z_index"><?php echo $entry_z_index; ?></label>
                <input type="number" name="widget_z_index" id="ove_z_index" class="regular-text" value="<?php echo htmlspecialchars($ovebotai_w['z_index'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="2147483644">
                <p class="description"><?php echo $help_z_index; ?></p>
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
  i18n: {
    saved: <?php echo json_encode($text_settings_saved); ?>,
    saving: <?php echo json_encode($text_saving); ?>,
    error: <?php echo json_encode($text_error); ?>,
    enabled: <?php echo json_encode($text_enabled); ?>,
    disabled: <?php echo json_encode($text_disabled); ?>,
    confirmRegenHash: <?php echo json_encode($text_confirm_regen_hash); ?>,
    confirmRegenCreds: <?php echo json_encode($text_confirm_regen_creds); ?>,
    copied: <?php echo json_encode($text_copied); ?>
  }
};
</script>

<script src="view/javascript/ovebotai/ovebotai.js"></script>
<script src="view/javascript/ovebotai/settings.js"></script>

<?php echo $footer; ?>
