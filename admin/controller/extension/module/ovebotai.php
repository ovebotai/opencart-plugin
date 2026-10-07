<?php

require_once DIR_SYSTEM . 'library/ovebotai.php';

// Admin entry point for the Ovebot.ai module. A single page whose content
// depends on state, mirroring the WordPress plugin's render_page():
//   - not connected / not finished  -> the setup wizard
//   - finished, ?view=settings      -> the settings screen (phase 2 stub)
//   - finished                      -> the dashboard (phase 2 stub)
// Compatible with OpenCart 2.3 (token=, extension/extension) and 3.x
// (user_token=, marketplace/extension) via the small compat helpers at the
// bottom, and renders .tpl templates on both.
class ControllerExtensionModuleOvebotai extends Controller {
    private $error = array();
    private $ovebotaiLib = null;

    // Single Ovebotai instance shared across the request. The live-connection
    // probe in index() and the product-count read during renderDashboard() must
    // be the same object for the status call to be memoized to one request.
    private function ovebotai(): \Ovebotai\Ovebotai {
        if ($this->ovebotaiLib === null) {
            $this->ovebotaiLib = new \Ovebotai\Ovebotai($this->registry);
        }
        return $this->ovebotaiLib;
    }

    public function index() {
        $this->load->language('extension/module/ovebotai');
        $this->load->model('setting/setting');
        $this->load->model('extension/module/ovebotai');

        $this->document->setTitle($this->language->get('heading_title'));

        // OAuth return from account.ovebot.ai lands here (callback_url is this
        // page, with the session token already in it) carrying ?code&state.
        if (isset($this->request->get['code'])) {
            return $this->handleOauthReturn();
        }

        // Settings-form save (the module status form, and later the full
        // settings screen). Re-inject preserved system keys so editSetting()
        // can't wipe tokens / setup state.
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_ovebotai', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->link('extension/module/ovebotai'));
            return;
        }

        // Common module admin assets, loaded on every view (setup / dashboard /
        // settings). View-specific scripts (e.g. setup.js) are added on top.
        $this->document->addStyle('view/stylesheet/ovebotai/ovebotai.css');

        // A module update that added a hook must not wait for a reinstall.
        $this->model_extension_module_ovebotai->ensureEvents();

        // Before showing an authenticated view, confirm the connection is still
        // live. The probe runs apiRequest(), which clears a revoked/expired
        // token, so isSetupComplete() below then flips to false and drops
        // through to the reconnect wizard on THIS load - instead of showing a
        // stale dashboard until the next refresh. Memoized, so the product
        // count during render reuses this same call.
        if ($this->isSetupComplete()) {
            if (!$this->ovebotai()->isConnectedLive()) {
                $this->disconnect();
            }
        }

        // Pull the account's live integration status once (memoized for the
        // rest of the request) and reconcile local flags with it - e.g. order
        // tracking toggled off directly in the Ovebot.ai account is mirrored
        // back into module_ovebotai_order_enabled here.
        $this->ovebotai()->syncSettings();

        if (!$this->isSetupComplete()) {
            $this->renderSetup();
            return;
        }

        if (isset($this->request->get['view']) && $this->request->get['view'] == 'settings') {
            $this->renderSettings();
            return;
        }

        $this->renderDashboard();
    }

    // ── Setup wizard ─────────────────────────────────────────────────────────

    private function renderSetup() {

        $ovebotai = $this->ovebotai();

        $connected = $ovebotai->isConnected();

        // Steps: 1 Connect, 2 Website pages, 3 Products, 4 Go live.
        $steps_seq = array(1, 2, 3, 4);

        // The step we open on is dictated by state alone - never by the query
        // string. A stale ?step=2 left in the URL from an earlier OAuth return
        // used to strand a freshly-disconnected store on step 2 instead of the
        // reconnect step. Derivation:
        //   - not connected             -> step 1 (connect / reconnect)
        //   - connected, setup not done -> step 2 (continue with pages)
        // (When connected AND setup is complete, index() never reaches here -
        // isSetupComplete() routes to the dashboard.)
        $initial_step = $connected ? 2 : 1;

        $language_id = (int)$this->config->get('config_language_id');

        $data = $this->commonData();

        // Titles come out of the DB HTML-entity-encoded (OpenCart's admin
        // saves them that way, e.g. "About &amp; FAQ") - decode here so the
        // .tpl's htmlspecialchars() doesn't double-encode them into
        // "About &amp;amp; FAQ" on screen. view_url points at the page's live
        // storefront URL, so a page that fails to sync can be checked (e.g.
        // "not enough text content") without leaving the wizard.
        $data['pages'] = array_map(function ($page) use ($ovebotai) {
            $page['title']    = html_entity_decode($page['title'], ENT_QUOTES, 'UTF-8');
            $page['view_url'] = $ovebotai->getInformationPageUrl($page['information_id']);
            return $page;
        }, $this->model_extension_module_ovebotai->getInformationPages($language_id));

        $data['product_counts']   = $this->model_extension_module_ovebotai->getProductCounts();
        $data['products_enabled'] = $ovebotai->getProductsEnabled() ? 1 : 0;
        $data['setup_url']        = $ovebotai->getSetupUrl();
        $data['is_connected']     = $connected ? 1 : 0;
        $data['initial_step']     = $initial_step;
        $data['steps_seq']        = $steps_seq;

        $data['connect_url']     = $this->link('extension/module/ovebotai/connect');
        $data['register_url']    = $ovebotai->getRegisterUrl();
        $data['sync_pages_url']  = $this->link('extension/module/ovebotai/syncPages');
        $data['sync_url']        = $this->link('extension/module/ovebotai/sync');
        $data['settings_url']    = $this->link('extension/module/ovebotai', '&view=settings');
        $data['chat_url']        = $this->chatUrl($ovebotai);

        $data['oauth_error']    = isset($this->request->get['oauth_error'])
            ? html_entity_decode($this->request->get['oauth_error'], ENT_QUOTES, 'UTF-8')
            : '';

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->withTemplateSupport(function () use ($data) {
            $this->response->setOutput($this->load->view('extension/module/ovebotai_setup', $data));
        });
    }

    // ── Dashboard (phase 2 - minimal) ────────────────────────────────────────

    private function renderDashboard() {
        $ovebotai = $this->ovebotai();

        $data = $this->commonData();

        // Account-side on/off status for the two features whose card / notice
        // the dashboard swaps to a warning when they're disabled.
        $data['products_recommend_enabled'] = $ovebotai->isProductRecommendationEnabled() ? 1 : 0;
        $data['order_api_enabled']          = $ovebotai->isOrderApiEnabled() ? 1 : 0;
        // Where the "change this in your account settings" links point.
        $data['setup_url']                  = $ovebotai->getSetupUrl();

        $data['is_connected']  = $ovebotai->isConnected() ? 1 : 0;
        $data['workspace']     = $ovebotai->getWorkspace();
        $data['agent']         = $ovebotai->getAgent();
        $data['chat_status']   = $ovebotai->getChatStatus() ? 1 : 0;
        $data['settings_url']  = $this->link('extension/module/ovebotai', '&view=settings');
        // Sends the settings screen straight to (and pulses) the relevant
        // toggle, so a "turned off" notice doesn't leave the merchant hunting
        // for it. These now point at the local settings page (the toggles are
        // synced to the account) rather than the Ovebot.ai account.
        $data['settings_chat_highlight_url']     = $this->link('extension/module/ovebotai', '&view=settings&highlight=oveChatStatus');
        $data['settings_products_highlight_url'] = $this->link('extension/module/ovebotai', '&view=settings&highlight=oveProductsRecommend');
        $data['settings_order_highlight_url']    = $this->link('extension/module/ovebotai', '&view=settings&highlight=oveOrderEnabled');
        $data['chat_url']      = $this->chatUrl($ovebotai);
        $data['disconnect_url'] = $this->link('extension/module/ovebotai/disconnect');

        // Live figures from Ovebot.ai - the products it has indexed and the
        // knowledge base entries feeding the agent.
        $data['account_url']    = $ovebotai->getAccountUrl();
        $data['products_url']   = $ovebotai->getProductsUrl();
        $data['products_count'] = $ovebotai->getIndexedProductCount();

        $kb = $ovebotai->getKbEntries();
        $data['kb_entries']      = $kb['entries'];
        $data['kb_error']        = $kb['error'];
        $data['kb_create_url']   = $ovebotai->getKbCreateUrl();
        $data['kb_active_count'] = count(array_filter($kb['entries'], function ($e) {
            return $e['is_active'];
        }));

        $data['success'] = $this->pullSession('success');

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->withTemplateSupport(function () use ($data) {
            $this->response->setOutput($this->load->view('extension/module/ovebotai_dashboard', $data));
        });
    }

    // ── Settings ─────────────────────────────────────────────────────────────

    private function renderSettings() {
        $ovebotai = $this->ovebotai();

        $data = $this->commonData();

        $data['is_connected']   = $ovebotai->isConnected() ? 1 : 0;
        $data['workspace']      = $ovebotai->getWorkspace();
        $data['agent']          = $ovebotai->getAgent();
        $data['dashboard_url']  = $this->link('extension/module/ovebotai');
        $data['disconnect_url'] = $this->link('extension/module/ovebotai/disconnect');
        $data['chat_url']       = $this->chatUrl($ovebotai);

        $data['chat_status']      = $ovebotai->getChatStatus() ? 1 : 0;
        $data['widget']           = $ovebotai->getWidget();
        $data['products_enabled']   = $ovebotai->getProductsEnabled() ? 1 : 0;
        $data['products_recommend'] = $ovebotai->getProductsRecommend() ? 1 : 0;
        $data['add_to_cart']        = $ovebotai->getAddToCart() ? 1 : 0;
        $data['order_enabled']      = $ovebotai->getOrderEnabled() ? 1 : 0;
        $data['setup_url']          = $ovebotai->getSetupUrl();

        $data['feed_url']   = $ovebotai->getFeedUrl();
        $data['order_url']  = $ovebotai->getOrderUrl();
        $data['order_user'] = $ovebotai->getOrderUser();
        $data['order_pass'] = $ovebotai->getOrderPass();

        $data['save_url']        = $this->link('extension/module/ovebotai/saveSettings');
        $data['regen_hash_url']  = $this->link('extension/module/ovebotai/regenFeedHash');
        $data['regen_creds_url'] = $this->link('extension/module/ovebotai/regenOrderCreds');

        $data['success'] = $this->pullSession('success');

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->withTemplateSupport(function () use ($data) {
            $this->response->setOutput($this->load->view('extension/module/ovebotai_settings', $data));
        });
    }

    // ── OAuth: start ─────────────────────────────────────────────────────────

    public function connect() {
        $this->load->language('extension/module/ovebotai');

        if (!$this->user->hasPermission('modify', 'extension/module/ovebotai')) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->link('extension/module/ovebotai'));
            return;
        }

        $ovebotai = $this->ovebotai();

        // callback_url must be a clean URL for the external redirect back -
        // url->link() HTML-encodes ampersands (&amp;), so decode them.
        $callback = html_entity_decode($this->link('extension/module/ovebotai'), ENT_QUOTES, 'UTF-8');

        $this->response->redirect($ovebotai->getAuthUrl($callback));
    }

    // ── OAuth: return ────────────────────────────────────────────────────────

    private function handleOauthReturn() {
        if (!$this->user->hasPermission('modify', 'extension/module/ovebotai')) {
            $this->response->redirect($this->link('extension/module/ovebotai'));
            return;
        }

        $ovebotai = $this->ovebotai();

        $code  = $this->request->get['code'];
        $state = isset($this->request->get['state']) ? $this->request->get['state'] : '';

        $result = $ovebotai->handleCallback($code, $state);

        // Redirect to a clean URL either way so a browser refresh can't replay
        // the (now-consumed) authorization code. No &step= is appended - the
        // rendered step is derived from state (see renderSetup): back from a
        // successful OAuth the store is connected, so the wizard opens on step
        // 2 on its own (or the dashboard, if setup was already complete).
        if (!empty($result['error'])) {
            $this->response->redirect($this->link('extension/module/ovebotai', '&oauth_error=' . rawurlencode($result['error'])));
        } else {
            $this->response->redirect($this->link('extension/module/ovebotai'));
        }
    }

    // ── Disconnect ───────────────────────────────────────────────────────────

    public function disconnect() {
        $this->load->language('extension/module/ovebotai');

        if (!$this->user->hasPermission('modify', 'extension/module/ovebotai')) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->link('extension/module/ovebotai'));
            return;
        }

        $ovebotai = $this->ovebotai();
        $ovebotai->disconnect();

        $this->response->redirect($this->link('extension/module/ovebotai'));
    }

    // ── Step 2: page sync (AJAX) ─────────────────────────────────────────────

    // Fired on every "Next" click from step 2 - sync-per-step instead of one
    // giant sync at the end. Whatever couldn't be sent (real API error, or an
    // intentional skip like "not enough text") comes back keyed by
    // information_id in 'failed'; the frontend unchecks those boxes, shows the
    // message under each, and keeps the user on step 2 for another attempt.
    // Re-sending everything checked on every attempt is intentional - no
    // "only send what changed" bookkeeping, so a page fixed elsewhere and
    // re-checked just goes through normally next time.
    public function syncPages() {
        $this->load->language('extension/module/ovebotai');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/ovebotai')) {
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $page_ids = array();
        if (isset($this->request->post['page_ids'])) {
            $page_ids = array_map('intval', (array)$this->request->post['page_ids']);
        }

        $ovebotai = $this->ovebotai();
        $ovebotai->saveKbPageIds($page_ids);

        $result = array('failed' => array(), 'kb_limit' => '', 'kb_limit_ids' => array());

        try {
            $result = $ovebotai->syncKbPages($page_ids, true);
        } catch (\Ovebotai\Exceptions\OvebotaiException $e) {
            // Couldn't even reach the API (connection/auth) - every requested
            // page is equally "failed", same message on each.
            foreach ($page_ids as $information_id) {
                $result['failed'][$information_id] = $e->getMessage();
            }
        }

        $json['success']      = true;
        $json['failed']       = $result['failed'];
        $json['kb_limit']     = $result['kb_limit'];
        $json['kb_limit_ids'] = $result['kb_limit_ids'];
        $json['clean']        = !$result['failed'] && !$result['kb_limit_ids'];

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // ── Finish step (AJAX) ───────────────────────────────────────────────────

    // Page sync already happened per-step (see syncPages() above), so all
    // that's left here is the products-feed choice from step 3 (only
    // persisted now, at Finish - see resyncSetup()) and pushing the whole
    // /setup payload (widget language, products, order lookup credentials).
    public function sync() {
        $this->load->language('extension/module/ovebotai');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/ovebotai')) {
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $ovebotai = $this->ovebotai();
        $ovebotai->setProductsEnabled(!empty($this->request->post['products_enabled']));

        $errors = array();

        try {
            $resync = $ovebotai->resyncSetup();
            if (!$resync['success']) {
                $errors[] = $this->language->get('error_sync_setup') . ($resync['error'] !== '' ? ' ' . $resync['error'] : '');
            }
        } catch (\Ovebotai\Exceptions\OvebotaiException $e) {
            $errors[] = $e->getMessage();
        }

        if (!$errors) {
            $ovebotai->markComplete();

            $json['success'] = true;
            $json['message'] = $this->language->get('text_setup_complete');
        } else {
            $json['success'] = false;
            $json['message'] = implode("\n", $errors);
            $json['errors']  = $errors;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // ── Settings: save (AJAX) ────────────────────────────────────────────────

    public function saveSettings() {
        $this->load->language('extension/module/ovebotai');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/ovebotai')) {
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $chatStatus = !empty($this->request->post['chat_status']);

        // Widget appearance fields the settings form collects. Anything not in
        // this list (width/height/offset_x) has no field in the form, so
        // saving always drops them from module_ovebotai_widget.
        $widget = array();
        foreach (array('accent_color', 'theme', 'language', 'audio_beep', 'side', 'offset_y', 'z_index', 'subtitle', 'proactive_message', 'proactive_delay') as $key) {
            if (isset($this->request->post['widget_' . $key])) {
                $widget[$key] = (string)$this->request->post['widget_' . $key];
            }
        }

        $productsEnabled   = !empty($this->request->post['products_enabled']);
        $orderEnabled      = !empty($this->request->post['order_enabled']);
        $productsRecommend = !empty($this->request->post['products_recommend']);
        $addToCart         = !empty($this->request->post['add_to_cart']);

        $result = $this->ovebotai()->saveSettings($chatStatus, $widget, $productsEnabled, $orderEnabled, $productsRecommend, $addToCart);

        $json['success'] = true;

        if ($result['needs_reconnect']) {
            $json['message']         = $this->language->get('text_settings_saved_reconnect');
            $json['needs_reconnect'] = true;
        } else {
            $json['message']  = $this->language->get('text_settings_saved');
            $json['warnings'] = $result['sync_error']
                ? array($this->language->get('text_settings_sync_failed') . ($result['sync_error_message'] !== '' ? ' ' . $result['sync_error_message'] : ''))
                : array();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // ── Settings: regenerate feed hash (AJAX) ────────────────────────────────

    public function regenFeedHash() {
        $this->load->language('extension/module/ovebotai');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/ovebotai')) {
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $result = $this->ovebotai()->regenerateFeedHash();

        if (empty($result['success'])) {
            $json['success'] = false;
            $json['message'] = $this->language->get('text_feed_regen_failed') . (!empty($result['error']) ? ' ' . $result['error'] : '');
        } else {
            $json['success'] = true;
            $json['hash']    = $result['hash'];
            $json['url']     = $result['url'];
            $json['message'] = $this->language->get('text_feed_regenerated');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // ── Settings: regenerate order-lookup credentials (AJAX) ─────────────────

    public function regenOrderCreds() {
        $this->load->language('extension/module/ovebotai');

        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/ovebotai')) {
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $result = $this->ovebotai()->regenerateOrderCreds();

        if (empty($result['success'])) {
            $json['success'] = false;
            $json['message'] = $this->language->get('text_creds_regen_failed') . (!empty($result['error']) ? ' ' . $result['error'] : '');
        } else {
            $json['success'] = true;
            $json['user']    = $result['user'];
            $json['pass']    = $result['pass'];
            $json['message'] = $this->language->get('text_creds_regenerated');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    // ── Information page hooks (knowledge base resync) ───────────────────────

    // Fired by 'admin/model/catalog/information/editInformation/after'
    // ($args = [information_id, data]). When the page is one the wizard
    // synced to the agent's knowledge base, push it again (title/body as just
    // saved) - or deactivate the entry when the page was disabled. Best-effort:
    // errors are logged, a page save never fails because Ovebot.ai is unreachable.
    public function informationSaved(&$route, &$args, &$output) {
        $information_id = isset($args[0]) ? (int)$args[0] : 0;
        $data           = isset($args[1]) && is_array($args[1]) ? $args[1] : array();

        if (!$this->holdsPage($information_id)) {
            return;
        }

        try {
            $result = $this->ovebotai()->syncKbPages(array($information_id), !empty($data['status']));

            foreach ($result['failed'] as $message) {
                $this->logKb($information_id, 'resync skipped: ' . $message);
            }
            if ($result['kb_limit'] !== '') {
                $this->logKb($information_id, 'resync blocked by the knowledge base quota: ' . $result['kb_limit']);
            }
        } catch (\Exception $e) {
            $this->logKb($information_id, 'resync failed: ' . $e->getMessage());
        }
    }

    // Fired by 'admin/model/catalog/information/deleteInformation/before'
    // ($args = [information_id]) - BEFORE, because the entry is deactivated
    // with the page's current title/body (the API's update needs them) and
    // those rows are gone once the delete has run. The page is also dropped
    // from the saved selection so a wizard re-run doesn't tick it.
    public function informationDeleting(&$route, &$args) {
        $information_id = isset($args[0]) ? (int)$args[0] : 0;

        if (!$this->holdsPage($information_id)) {
            return;
        }

        try {
            $ovebotai = $this->ovebotai();

            $ovebotai->saveKbPageIds(array_diff($ovebotai->getKbPageIds(), array($information_id)));

            $ovebotai->syncKbPages(array($information_id), false);
        } catch (\Exception $e) {
            $this->logKb($information_id, 'deactivation after delete failed: ' . $e->getMessage());
        }
    }

    // Only pages the finished wizard selected are mirrored; anything else (or a
    // store that is disconnected / mid-setup) is left alone.
    private function holdsPage($information_id) {
        if (!$information_id) {
            return false;
        }

        $ovebotai = $this->ovebotai();

        return $ovebotai->isSetupComplete() && in_array((int)$information_id, $ovebotai->getKbPageIds(), true);
    }

    private function logKb($information_id, $message) {
        $this->log->write('Ovebot.ai information #' . (int)$information_id . ' ' . $message);
    }

    // ── TEMP: API debug (remove before release) ──────────────────────────────

    // index.php?route=extension/module/ovebotai/debugIntegration&token=... (user_token= on 3.x)
    // Read-only: prints what the Ovebot.ai API returns for this connection.
    public function debugIntegration() {
        exit();
        $this->load->language('extension/module/ovebotai');

        if (!$this->user->hasPermission('modify', 'extension/module/ovebotai')) {
            $out = array('error' => $this->language->get('error_permission'));
        } else {
            $out = $this->ovebotai()->debugApi();
            $out['local'] = array(
                'add_to_cart'        => $this->config->get('module_ovebotai_add_to_cart'),
                'products_recommend' => $this->config->get('module_ovebotai_products_recommend'),
                'order_enabled'      => $this->config->get('module_ovebotai_order_enabled'),
            );
        }

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    // ── Install / uninstall ──────────────────────────────────────────────────

    public function install() {
        $this->load->model('extension/module/ovebotai');
        $this->model_extension_module_ovebotai->install();
    }

    public function uninstall() {
        $this->load->model('extension/module/ovebotai');
        $this->model_extension_module_ovebotai->uninstall();
    }

    // ── Shared view data ─────────────────────────────────────────────────────

    private function commonData() {
        $data = array();

        // Every string the templates need, resolved once. Keeping this in the
        // controller (not the template) means the templates stay logic-free.
        foreach ($this->languageKeys() as $key) {
            $data[$key] = $this->language->get($key);
        }

        // Shown across every view (setup / dashboard / settings) at the top.
        $data['module_version'] = $this->ovebotai()->getModuleVersion();

        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->link('common/dashboard'),
            ),
            array(
                'text' => $this->language->get('text_extension'),
                'href' => $this->link($this->extensionListRoute(), '&type=module'),
            ),
            array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->link('extension/module/ovebotai'),
            ),
        );

        return $data;
    }

    // Every non-error key the language file defines - templates get the whole
    // set via commonData() without this list needing to be kept in sync by
    // hand every time a key is added. error_* is excluded: those are only
    // ever used directly via $this->language->get('error_...') in flash
    // messages / JSON responses, never echoed by a template.
    private function languageKeys() {
        include DIR_LANGUAGE . $this->config->get('config_language') . '/extension/module/ovebotai.php';

        $keys = array();
        foreach ($_ as $key => $value) {
            if (strpos($key, 'error_') !== 0) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    // ── Validation ───────────────────────────────────────────────────────────

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/ovebotai')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        // These keys have no field in the form. editSetting() replaces the
        // whole `module_ovebotai` group, so re-populate them from the stored
        // config here - otherwise a settings save wipes the tokens / setup
        // state / storefront credentials.
        $this->request->post['module_ovebotai_access_token']   = $this->config->get('module_ovebotai_access_token');
        $this->request->post['module_ovebotai_refresh_token']  = $this->config->get('module_ovebotai_refresh_token');
        $this->request->post['module_ovebotai_token_expires']  = $this->config->get('module_ovebotai_token_expires');
        $this->request->post['module_ovebotai_workspace']      = $this->config->get('module_ovebotai_workspace');
        $this->request->post['module_ovebotai_agent']          = $this->config->get('module_ovebotai_agent');
        $this->request->post['module_ovebotai_setup_complete'] = $this->config->get('module_ovebotai_setup_complete');
        $this->request->post['module_ovebotai_kb_page_ids']    = $this->config->get('module_ovebotai_kb_page_ids');
        $this->request->post['module_ovebotai_chat_status']    = $this->config->get('module_ovebotai_chat_status');
        $this->request->post['module_ovebotai_feed_hash']      = $this->config->get('module_ovebotai_feed_hash');
        $this->request->post['module_ovebotai_order_user']     = $this->config->get('module_ovebotai_order_user');
        $this->request->post['module_ovebotai_order_pass']     = $this->config->get('module_ovebotai_order_pass');
        $this->request->post['module_ovebotai_widget']         = $this->config->get('module_ovebotai_widget');
        $this->request->post['module_ovebotai_products_enabled']   = $this->config->get('module_ovebotai_products_enabled');
        $this->request->post['module_ovebotai_order_enabled']      = $this->config->get('module_ovebotai_order_enabled');
        $this->request->post['module_ovebotai_products_recommend'] = $this->config->get('module_ovebotai_products_recommend');
        $this->request->post['module_ovebotai_add_to_cart']        = $this->config->get('module_ovebotai_add_to_cart');

        return !$this->error;
    }

    private function isSetupComplete() {
        return $this->config->get('module_ovebotai_setup_complete') == '1'
            && (bool)$this->config->get('module_ovebotai_refresh_token')
            && (bool)$this->config->get('module_ovebotai_workspace');
    }

    // Opens the storefront's own chat widget (auto-opened), not Ovebot.ai's
    // hosted UI - the storefront's footer event handler
    // (catalog/controller/extension/module/ovebotai.php index()) only honours
    // ?auto-open-chat=true when the visitor's session also carries this same
    // admin's login token, so this link is meaningless outside an active
    // admin session. Falls back to ovebot.ai when there's no workspace yet
    // (nothing to preview) or no storefront base URL configured.
    private function chatUrl($ovebotai) {
        return HTTPS_CATALOG . 'index.php?auto-open-chat=true';
    }

    private function pullSession($key) {
        $value = isset($this->session->data[$key]) ? $this->session->data[$key] : '';
        unset($this->session->data[$key]);
        return $value;
    }

    // ── OpenCart 2.3 / 3.x compatibility ─────────────────────────────────────

    private function extensionListRoute() {
        return version_compare(VERSION, '3.0', '<') ? 'extension/extension' : 'marketplace/extension';
    }

    // Builds an admin link carrying the session token under whichever key this
    // OpenCart version uses.
    private function link($route, $args = '') {
        return $this->url->link($route, $this->tokenQs() . $args, true);
    }

    // The token query-string fragment for the current OpenCart version -
    // "token=XXX" on 2.3, "user_token=XXX" on 3.x. Everything that builds an
    // admin URL (here or in a template) appends this instead of hardcoding a
    // key, so the module stays correct across both versions.
    private function tokenQs() {
        $key = version_compare(VERSION, '3.0', '<') ? 'token' : 'user_token';
        return $key . '=' . $this->session->data[$key];
    }

    private function withTemplateSupport($cb)
    {
        $isOCart3 = version_compare(VERSION, '3.0', '>');
        if ($isOCart3) {
            $this->config->set('template_engine', 'template');
        }

        $result = $cb();

        if ($isOCart3) {
            $this->config->set('template_engine', 'twig');
        }

        return $result;
    }
}
