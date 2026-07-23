<?php

// Storefront-facing endpoints for Ovebot.ai, modelled on the aquatime store's
// ControllerModuleOvebotai (OpenCart 1.5.x):
//   - Product feed:  index.php?route=extension/module/ovebotai/feed&hash=XXX
//   - Order lookup:  index.php?route=extension/module/ovebotai/orders (POST, HTTP Basic)
// Both URLs and their credentials/hash are the same ones shown on the admin
// settings screen (module_ovebotai_feed_hash / module_ovebotai_order_user /
// module_ovebotai_order_pass) — see system/library/ovebotai.php getFeedUrl()/
// getOrderUrl() and regenerateFeedHash()/regenerateOrderCreds(). All actual
// data building lives in the model (catalog/model/extension/module/ovebotai.php).
class ControllerExtensionModuleOvebotai extends Controller {

    // Storefront chat widget injection lands in a later phase; nothing to
    // render yet.
    public function index() {
        return '';
    }

    // GET .../module_ovebotai/feed&hash=XXX — product feed, gated by the hash
    // generated at install (and rotatable from the settings screen).
    public function feed() {
        ini_set('memory_limit', '-1'); // in case the catalog is large

        $expected = (string)$this->config->get('module_ovebotai_feed_hash');
        $given    = isset($this->request->get['hash']) ? (string)$this->request->get['hash'] : '';

        if ($expected === '' || !hash_equals($expected, $given)) {
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

    // POST .../module_ovebotai/orders — order lookup for the agent.
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

        // Exactly one of email/phone — never both, never neither.
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
