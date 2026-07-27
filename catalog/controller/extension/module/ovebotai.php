<?php

// Storefront-facing endpoints for Ovebot.ai, modelled on the aquatime store's
// ControllerModuleOvebotai (OpenCart 1.5.x):
//   - Product feed:  index.php?route=extension/module/ovebotai/feed&hash=XXX
//   - Order lookup:  index.php?route=extension/module/ovebotai/orders (POST, HTTP Basic)
// Both URLs and their credentials/hash are the same ones shown on the admin
// settings screen (module_ovebotai_feed_hash / module_ovebotai_order_user /
// module_ovebotai_order_pass) - see system/library/ovebotai.php getFeedUrl()/
// getOrderUrl() and regenerateFeedHash()/regenerateOrderCreds(). All actual
// data building lives in the model (catalog/model/extension/module/ovebotai.php).
class ControllerExtensionModuleOvebotai extends Controller {

    // Fired by the 'catalog/controller/common/footer/after' event registered
    // on install (see admin/model/.../ovebotai.php addEvents()) - OC's
    // controller/after signature passes $route/$args/$output by reference so
    // this can append markup to the already-rendered page instead of
    // returning a value. Injects the two Ovebot.ai widget script tags right
    // before </body>: an options push (widget appearance) and the
    // per-workspace chat-loader.js.
    public function index(&$route, &$args, &$output) {
        if ((string)$this->config->get('module_ovebotai_chat_status') !== '1') {
            return;
        }
        if ((string)$this->config->get('module_ovebotai_setup_complete') !== '1') {
            return;
        }

        $workspace = (string)$this->config->get('module_ovebotai_workspace');
        if ($workspace === '' || !preg_match('/^[a-z0-9-]+$/i', $workspace)) {
            return;
        }

        $options = $this->widgetOptions();

        $options_json = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $snippet = "\n<script>\n"
            . "  var ovebot_ai = ovebot_ai || [];\n"
            . "  ovebot_ai.push(['chat', " . $options_json . "]);\n"
            . "</script>\n"
            . '<script src="https://' . $workspace . '.ovebot.ai/widget/chat-loader.js"></script>' . "\n";

        if (strpos($output, '</body>') !== false) {
            $output = str_replace('</body>', $snippet . '</body>', $output);
        } else {
            $output .= $snippet;
        }
    }

    // Only the parameters this module actually collects (from the settings
    // screen's Appearance panel) are forwarded - width/height/offset_x/
    // auto_open have no field there so they're left at chat-loader's own
    // defaults, except auto_open which is set below from the query string.
    // String-valued options are passed through as-is; offset_y/
    // proactive_delay/z_index are cast to int since chat-loader expects
    // numbers.
    private function widgetOptions() {
        $widget = $this->config->get('module_ovebotai_widget');
        $widget = is_array($widget) ? $widget : array();

        $options = array();

        foreach (array('subtitle', 'accent_color', 'proactive_message', 'theme', 'language', 'audio_beep', 'side') as $key) {
            if (isset($widget[$key]) && $widget[$key] !== '') {
                $options[$key] = (string)$widget[$key];
            }
        }

        foreach (array('proactive_delay', 'offset_y', 'z_index') as $key) {
            if (isset($widget[$key]) && $widget[$key] !== '' && is_numeric($widget[$key])) {
                $options[$key] = (int)$widget[$key];
            }
        }

        // Only the workspace's default agent has no public_id - nothing to
        // send there, chat-loader falls back to the default agent on its own.
        $agent = (string)$this->config->get('module_ovebotai_agent');
        if ($agent !== '' && $agent !== 'default') {
            $options['agent'] = $agent;
        }

        // The admin dashboard's "Chat with the AI agent" button links to the
        // storefront home with ?auto-open-chat=true (see admin controller
        // chatUrl()). Only honoured when the visitor's session also carries
        // an active admin login token, so a public visitor can't force-open
        // the widget for everyone just by guessing the query string.
        if (isset($this->request->get['auto-open-chat']) && $this->request->get['auto-open-chat'] === 'true') {
            if (!empty($this->session->data['token']) || !empty($this->session->data['user_token'])) {
                $options['auto_open'] = 'true';
            }
        }

        return $options;
    }

    // Fired by 'catalog/controller/checkout/success/index/before' - the
    // success controller's own $this->session->data['order_id'] is only
    // guaranteed readable up to this point, so it's stashed on the registry
    // for purchaseEvent() (common/success/after) to pick up once the
    // template has rendered.
    public function captureOrderId(&$route, &$args) {
        if (!empty($this->session->data['order_id'])) {
            $this->registry->set('ovebotai_order_id', (int)$this->session->data['order_id']);
        }
    }

    // Fired by 'catalog/view/common/success/after' - appends the
    // purchase-conversion pixel right before </body>, mirroring index()'s
    // own snippet injection. Consumes (clears) the registry key set by
    // captureOrderId() so a later page render on the same request/session
    // doesn't fire the event twice.
    public function purchaseEvent($route, &$data, &$output) {
        $order_id = $this->registry->get('ovebotai_order_id');

        if (!$order_id) {
            return;
        }

        $this->registry->set('ovebotai_order_id', null);

        // Same master switch as the widget itself (see index()): no chat on the
        // storefront means no Ovebot scripts at all, so the purchase-conversion
        // pixel is not injected either when chat is disabled.
        if ((string)$this->config->get('module_ovebotai_chat_status') !== '1') {
            return;
        }

        if ((string)$this->config->get('module_ovebotai_setup_complete') !== '1') {
            return;
        }

        $workspace = (string)$this->config->get('module_ovebotai_workspace');
        if ($workspace === '' || !preg_match('/^[a-z0-9-]+$/i', $workspace)) {
            return;
        }

        $order = $this->getPurchaseOrderData($order_id);
        if (!$order) {
            return;
        }

        $payload = json_encode(array(
            'transaction_id' => $order_id,
            'total'          => $order['total'],
            'currency'       => $order['currency'],
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $snippet = "\n<script type=\"text/javascript\">\n"
            . "  var ovebot_ai = ovebot_ai || [];\n"
            . "  ovebot_ai.push(['purchase', " . $payload . "]);\n"
            . "</script>\n"
            . '<script src="https://' . $workspace . '.ovebot.ai/widget/event.js"></script>' . "\n";

        if (strpos($output, '</body>') !== false) {
            $output = str_replace('</body>', $snippet . '</body>', $output);
        } else {
            $output .= $snippet;
        }
    }

    private function getPurchaseOrderData($order_id) {
        $query = $this->db->query(
            "SELECT `total`, `currency_code` FROM `" . DB_PREFIX . "order`
             WHERE `order_id` = '" . (int)$order_id . "'
             LIMIT 1"
        );

        if (!$query->num_rows) {
            return null;
        }

        return array(
            'total'    => round((float)$query->row['total'], 2),
            'currency' => $query->row['currency_code'],
        );
    }

    // GET .../module_ovebotai/feed&hash=XXX - product feed, gated by the hash
    // generated at install (and rotatable from the settings screen).
    public function feed() {
        ini_set('memory_limit', '-1'); // in case the catalog is large

        // The chat status (module_ovebotai_chat_status) is the module's master
        // on/off - when it's disabled the storefront widget doesn't render
        // (see index()), and the feed is turned off in lockstep: a disabled
        // module exposes no product data, so this 403s regardless of the hash.
        $chatStatus = (string)$this->config->get('module_ovebotai_chat_status');

        // Merchant chose "I'll provide my own feed" on the setup wizard /
        // settings screen (module_ovebotai_products_enabled = '0') - our own
        // feed is intentionally not the one Ovebot.ai should be reading, so
        // it 403s here regardless of the hash, same as an invalid one.
        $productsEnabled = (string)$this->config->get('module_ovebotai_products_enabled');

        // "Recommend products" master (module_ovebotai_products_recommend,
        // mirrored from the account and synced). With recommendation turned off
        // the agent shouldn't be pulling our catalog at all, so the feed 403s
        // too. Empty / never-set defaults to on, same rule as getProductsRecommend().
        $productsRecommend = (string)$this->config->get('module_ovebotai_products_recommend');

        $expected = (string)$this->config->get('module_ovebotai_feed_hash');
        $given    = isset($this->request->get['hash']) ? (string)$this->request->get['hash'] : '';

        if ($chatStatus !== '1' || $productsEnabled === '0' || $productsRecommend === '0' || $expected === '' || !hash_equals($expected, $given)) {
            $this->response->addHeader('HTTP/1.1 403 Forbidden');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Forbidden')));
            return;
        }

        $this->load->model('extension/module/ovebotai');

        $language_id       = (int)$this->config->get('config_language_id');
        $store_id          = (int)$this->config->get('config_store_id');
        $customer_group_id = (int)$this->config->get('config_customer_group_id');

        $data = $this->model_extension_module_ovebotai->getProductsFeed($language_id, $store_id, $customer_group_id);

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // POST .../module_ovebotai/orders - order lookup for the agent.
    // Body (JSON or form): { "id": <order_id>, "email": "<email>" }
    //                 or : { "id": <order_id>, "phone": "<phone>" }
    // Exactly one of email/phone must be sent, never both.
    //
    // Guarded by HTTP Basic against module_ovebotai_order_user/_pass
    // (regenerable from the settings screen). Unlike the aquatime store, this
    // module does not track AWB/carrier/tracking numbers, so the response
    // never includes them; estimated_delivery is always null (no delivery
    // estimate settings in this module).
    public function orders() {
        // Two gates, both before any auth check, both answered with 403 (not
        // 401) so Ovebot treats it as "off", not "bad credentials", and simply
        // shows the visitor "order not found":
        //   - chat status (module_ovebotai_chat_status): the module's master
        //     on/off. A disabled module exposes no data at all - the widget
        //     doesn't render (index()) and the feed 403s (feed()).
        //   - order switch (module_ovebotai_order_enabled): the per-feature
        //     toggle. When the merchant turns order tracking off, this endpoint
        //     is closed even while chat still runs. Empty / never-set defaults
        //     to on, same rule as getOrderEnabled() / the feed() check.
        $chatOn  = (string)$this->config->get('module_ovebotai_chat_status') === '1';
        $orderOn = (string)$this->config->get('module_ovebotai_order_enabled') !== '0';

        if (!$chatOn || !$orderOn) {
            $this->response->addHeader('HTTP/1.1 403 Forbidden');
            $this->respondApi(false, null, 'Forbidden');
            return;
        }

        if (!$this->checkApiAuth()) {
            $this->response->addHeader('HTTP/1.1 401 Unauthorized');
            $this->response->addHeader('WWW-Authenticate: Basic realm="Ovebot.ai"');
            $this->respondApi(false, null, 'Unauthorized');
            return;
        }

        $input = $this->getApiInput();

        $order_id = isset($input['id']) ? (int)preg_replace('/\D/', '', (string)$input['id']) : 0;
        $email    = isset($input['email']) ? trim((string)$input['email']) : '';
        $phone    = isset($input['phone']) ? trim((string)$input['phone']) : '';

        if ($order_id <= 0) {
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
            $this->respondApi(false, null, 'Invalid request.');
            return;
        }

        // Exactly one of email/phone - never both, never neither.
        if (($email === '') === ($phone === '')) {
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
            $this->respondApi(false, null, 'Invalid request.');
            return;
        }

        $this->load->model('extension/module/ovebotai');

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->response->addHeader('HTTP/1.1 400 Bad Request');
                $this->respondApi(false, null, 'Invalid email.');
                return;
            }

            $data = $this->model_extension_module_ovebotai->getOrderData($order_id, 'email', $email);
        } else {
            $phone_core = $this->normalizePhone($phone);

            if (strlen($phone_core) < 9) {
                $this->response->addHeader('HTTP/1.1 400 Bad Request');
                $this->respondApi(false, null, 'Invalid phone.');
                return;
            }

            $data = $this->model_extension_module_ovebotai->getOrderData($order_id, 'phone', $phone_core);
        }

        if (!$data) {
            $this->respondApi(false, null, 'Order not found.');
            return;
        }

        $this->respondApi(true, $data);
    }

    private function checkApiAuth() {
        $user = trim((string)$this->config->get('module_ovebotai_order_user'));
        $pass = trim((string)$this->config->get('module_ovebotai_order_pass'));

        if ($user === '' || $pass === '') {
            return false;
        }

        $given_user = isset($this->request->server['PHP_AUTH_USER']) ? $this->request->server['PHP_AUTH_USER'] : '';
        $given_pass = isset($this->request->server['PHP_AUTH_PW']) ? $this->request->server['PHP_AUTH_PW'] : '';

        // Fallback for servers where PHP_AUTH_* isn't populated (CGI/FastCGI).
        if ($given_user === '') {
            $header = '';
            if (isset($this->request->server['HTTP_AUTHORIZATION'])) {
                $header = $this->request->server['HTTP_AUTHORIZATION'];
            } elseif (isset($this->request->server['REDIRECT_HTTP_AUTHORIZATION'])) {
                $header = $this->request->server['REDIRECT_HTTP_AUTHORIZATION'];
            }

            if (stripos($header, 'Basic ') === 0) {
                $decoded = base64_decode(substr($header, 6));
                if ($decoded !== false && strpos($decoded, ':') !== false) {
                    list($given_user, $given_pass) = explode(':', $decoded, 2);
                }
            }
        }

        return hash_equals($user, (string)$given_user) && hash_equals($pass, (string)$given_pass);
    }

    private function getApiInput() {
        $raw = file_get_contents('php://input');

        if ($raw !== '' && $raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $this->request->post;
    }

    // Normalizes a phone number to its national core, no separators/prefix:
    // strips everything but digits/+, drops a leading +4 / 004, then any
    // remaining leading zero. E.g. "+40 721-234.567" -> "721234567".
    private function normalizePhone($phone) {
        $p = preg_replace('/[^\d+]/', '', (string)$phone);

        if (strpos($p, '+4') === 0) {
            $p = substr($p, 2);
        } elseif (strpos($p, '004') === 0) {
            $p = substr($p, 3);
        }

        $p = preg_replace('/\D/', '', $p);
        $p = ltrim($p, '0');

        return $p;
    }

    private function respondApi($success, $data = null, $error = null) {
        $payload = $success ? array('success' => true, 'data' => $data) : array('success' => false, 'error' => $error);

        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
