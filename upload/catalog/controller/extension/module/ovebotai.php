<?php

// Storefront-facing endpoints for Ovebot.ai. Phase 1 only needs these to exist
// and answer safely — the setup wizard registers their URLs with Ovebot.ai
// (products feed_url + order_info api_url) even though the real feed/order
// payloads land in a later phase. Each is access-controlled now so the
// contract (hash for the feed, HTTP Basic for orders) is already in place.
class ControllerExtensionModuleOvebotai extends Controller {

    // Storefront chat widget injection lands in a later phase; nothing to
    // render yet.
    public function index() {
        return '';
    }

    // GET .../module_ovebotai/feed&hash=XXX — product feed. Stubbed to an empty
    // but valid feed, gated by the hash generated at install.
    public function feed() {
        $expected = (string)$this->config->get('module_ovebotai_feed_hash');
        $given    = isset($this->request->get['hash']) ? (string)$this->request->get['hash'] : '';

        if ($expected === '' || !hash_equals($expected, $given)) {
            $this->response->addHeader('HTTP/1.1 403 Forbidden');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Forbidden')));
            return;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(array(
            'currency' => (string)$this->config->get('config_currency'),
            'products' => array(),
        )));
    }

    // POST/GET .../module_ovebotai/orders — order lookup for the agent, guarded
    // by HTTP Basic against the credentials shared during setup. Stubbed to an
    // empty result set for now.
    public function orders() {
        $user = isset($this->request->server['PHP_AUTH_USER']) ? $this->request->server['PHP_AUTH_USER'] : '';
        $pass = isset($this->request->server['PHP_AUTH_PW']) ? $this->request->server['PHP_AUTH_PW'] : '';

        $expectedUser = (string)$this->config->get('module_ovebotai_order_user');
        $expectedPass = (string)$this->config->get('module_ovebotai_order_pass');

        $ok = $expectedUser !== '' && $expectedPass !== ''
            && hash_equals($expectedUser, (string)$user)
            && hash_equals($expectedPass, (string)$pass);

        if (!$ok) {
            $this->response->addHeader('HTTP/1.1 401 Unauthorized');
            $this->response->addHeader('WWW-Authenticate: Basic realm="Ovebot.ai"');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(array('error' => 'Unauthorized')));
            return;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(array('orders' => array())));
    }
}
