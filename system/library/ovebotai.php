<?php

namespace Ovebotai;

require_once DIR_SYSTEM . 'library/ovebotai/exceptions/OvebotaiException.php';
require_once DIR_SYSTEM . 'library/ovebotai/exceptions/ConnectionException.php';
require_once DIR_SYSTEM . 'library/ovebotai/exceptions/ApiException.php';
require_once DIR_SYSTEM . 'library/ovebotai/exceptions/AuthException.php';
require_once DIR_SYSTEM . 'library/ovebotai/client.php';

use Ovebotai\Exceptions\ApiException;
use Ovebotai\Exceptions\OvebotaiException;

// Orchestrator for Ovebot.ai: owns token storage/refresh and the local config,
// delegates raw HTTP/OAuth to the dumb Client. Extends \Model for registry
// access; construct directly ($this->load->library() can't resolve a namespace).
class Ovebotai extends \Model {
    // Plugin version - bump here on release; surfaced via getModuleVersion().
    const VERSION = '1.0.0';

    private $client;

    // Memoized GET /v1/integration/status body for this request (that call also
    // acts as the live connection probe). null = not fetched; array() = empty/failed.
    private $statusBody = null;

    public function __construct($registry) {
        parent::__construct($registry);

        $accountHost = (string)$this->config->get('module_ovebotai_account_host');
        $apiHost     = (string)$this->config->get('module_ovebotai_api_host');

        $this->client = new Client(
            (string)$this->config->get('module_ovebotai_access_token'),
            $accountHost,
            $apiHost
        );
    }

    public function getModuleVersion() {
        return self::VERSION;
    }

    // ── OAuth: start ─────────────────────────────────────────────────────────

    // Builds the authorize URL and stashes the PKCE verifier under the one-time
    // state, so a second Connect click doesn't kill an in-flight authorization.
    public function getAuthUrl($callbackUrl) {
        $verifier = Client::generateVerifier();
        $state    = Client::generateState();

        $this->session->data['module_ovebotai_pkce_' . $state] = $verifier;

        return $this->client->buildAuthUrl($this->siteDomain(), $callbackUrl, $verifier, $state);
    }

    // ── OAuth: return ────────────────────────────────────────────────────────

    // Exchanges the auth code for tokens and persists them. Never throws -
    // returns array('success' => true) or array('error' => '...') for inline display.
    public function handleCallback($code, $state) {
        $key      = 'module_ovebotai_pkce_' . $state;
        $verifier = isset($this->session->data[$key]) ? $this->session->data[$key] : '';
        unset($this->session->data[$key]);

        if (!$verifier) {
            return array('error' => 'State mismatch. Please try again.');
        }

        try {
            $response = $this->client->exchangeCode($code, $verifier);
        } catch (OvebotaiException $e) {
            return array('error' => $e->getMessage());
        }

        // Read the old agent BEFORE storeTokens() - it may persist a new agent
        // right away, which would otherwise mask a real agent change.
        $previousAgent = (string)$this->config->get('module_ovebotai_agent');

        $this->storeTokens($response);
        $this->syncAgentFromMe($previousAgent);

        return array('success' => true);
    }

    // After connecting, GET /v1/me to persist the token's real bound agent (the
    // token response's `agent` is null for the default agent). Best-effort.
    private function syncAgentFromMe($previousAgent) {
        try {
            $result = $this->apiRequest('GET', '/v1/me');
        } catch (OvebotaiException $e) {
            return;
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            return;
        }

        // '' is a legit value for the default agent, so treat a MISSING `agent`
        // key (not an empty one) as "nothing usable" and bail.
        if (!isset($result['body']['agent'])) {
            return;
        }

        $agent = $result['body']['agent'];

        $slug = is_array($agent)
            ? (isset($agent['public_id']) ? (string)$agent['public_id'] : '')
            : (string)$agent;

        $partial = array('module_ovebotai_agent' => $slug);

        // Landed on a different agent than the wizard last finished for: the
        // synced pages belong to the OLD agent, so force the wizard to re-run and
        // clear the page selection (empty = all checked). The KB entries
        // themselves are reconciled by slug against the new agent's list on sync.
        if ((string)$previousAgent !== $slug) {
            $partial['module_ovebotai_setup_complete'] = '0';
            $partial['module_ovebotai_kb_page_ids']    = array();
        }

        $this->persist($partial);
    }

    // ── Authenticated API with auto-refresh ──────────────────────────────────

    // All API calls funnel here for uniform token refresh: proactive refresh if
    // near expiry, then one reactive refresh+retry on a 401.
    public function apiRequest($method, $path, $body = null) {
        $expires = (int)$this->config->get('module_ovebotai_token_expires');
        if ($expires && $expires <= time() + 300) {
            $this->refresh();
        }

        $result = $this->client->apiRequest($method, $path, $body);

        if ($result['status'] === 401 && $this->refresh()) {
            $result = $this->client->apiRequest($method, $path, $body);
        }

        return $result;
    }

    private function refresh() {
        $refreshToken = (string)$this->config->get('module_ovebotai_refresh_token');
        if (!$refreshToken) {
            return false;
        }

        try {
            $response = $this->client->refreshToken($refreshToken);
        } catch (OvebotaiException $e) {
            // Refresh failed / family revoked: clear only the OAuth creds (keep
            // workspace/agent/chat) so the widget keeps working until reconnect.
            $this->expireTokens();
            return false;
        }

        $this->storeTokens($response);
        return true;
    }

    // ── Knowledge base ───────────────────────────────────────────────────────

    // Bulk sync for wizard step 2. Fetches the agent's KB list once, then syncs
    // each page independently and returns 'failed' (id => message for pages that
    // couldn't be activated), 'kb_limit' (quota message, shown once) and
    // 'kb_limit_ids' (pages that hit the quota).
    // Does NOT stop at the first kb_limit: a page that already exists (matched by
    // slug → PUT/update) doesn't consume quota and must still go through.
    public function syncKbPages(array $information_ids, $active = true) {
        $failed      = array();
        $kbLimit     = '';
        $kbLimitIds  = array();

        if (!$information_ids) {
            return array('failed' => $failed, 'kb_limit' => $kbLimit, 'kb_limit_ids' => $kbLimitIds);
        }

        $remoteBySlug = $this->fetchRemoteKbSlugs();

        foreach ($information_ids as $information_id) {
            $information_id = (int)$information_id;
            $slug  = 'information-' . $information_id;
            $kb_id = isset($remoteBySlug[$slug]) ? (int)$remoteBySlug[$slug] : 0;

            try {
                if ($kb_id) {
                    $this->updateKbPage($kb_id, $information_id, $active, $failed);
                } elseif ($active) {
                    $this->insertKbPage($information_id, $failed);
                }
                // No slug match + being unchecked → nothing to do.
            } catch (OvebotaiException $e) {
                if ($e->getCode() === 409 || stripos($e->getMessage(), 'kb_limit') !== false) {
                    $kbLimit      = $e->getMessage();
                    $kbLimitIds[] = $information_id;
                    continue;
                }
                $failed[$information_id] = $e->getMessage();
            }
        }

        return array('failed' => $failed, 'kb_limit' => $kbLimit, 'kb_limit_ids' => $kbLimitIds);
    }

    // Update an existing KB entry (matched by slug) in place.
    private function updateKbPage($kb_id, $information_id, $active, array &$failed) {
        $payload = $this->buildKbPayload($information_id, $active, $failed);
        if ($payload === null) {
            return;
        }

        $result = $this->apiRequest('PUT', $this->kbApiPath() . '/' . (int)$kb_id, $payload);

        // Entry deleted on Ovebot's side since the list was fetched: recreate it
        // when activating, otherwise there's simply nothing left to deactivate.
        if ($result['status'] === 404) {
            if ($active) {
                $this->insertKbPage($information_id, $failed);
            }
            return;
        }

        $this->assertKbResult($result, $payload['title']);
    }

    // Create a new KB entry (no slug match). Only ever called when activating.
    private function insertKbPage($information_id, array &$failed) {
        $payload = $this->buildKbPayload($information_id, true, $failed);
        if ($payload === null) {
            return;
        }

        $result = $this->apiRequest('POST', $this->kbApiPath(), $payload);

        $this->assertKbResult($result, $payload['title']);
    }

    // Loads the OpenCart page and builds the API payload, or returns null (and
    // records a skip reason in $failed) when the page can't/shouldn't be synced:
    // missing, disabled, or too little text for the API's 10-char minimum.
    private function buildKbPayload($information_id, $active, array &$failed) {
        $page = $this->getInformationPage($information_id);

        if (!$page) {
            return null;
        }

        if (empty($page['status']) && $active) {
            $failed[$information_id] = sprintf('Skipped "%s" - page is not enabled.', $page['title']);
            return null;
        }

        $title = $page['title'];
        $body  = $this->buildKbBody($page);

        $length = function_exists('mb_strlen') ? mb_strlen($body) : strlen($body);
        if ($active && $length < 10) {
            $failed[$information_id] = sprintf('Skipped "%s" - not enough text content to sync (minimum 10 characters).', $title);
            return null;
        }

        return array(
            'title'      => $title,
            'body'       => $body,
            'is_active'  => (bool)$active,
            'slug'       => 'information-' . (int)$information_id,
            'source_url' => $this->getInformationPageUrl($information_id),
        );
    }

    // Throws on a non-2xx KB write, mapping the API's kb_limit_reached to a 409
    // (which syncKbPages treats as a quota hit rather than a plain failure).
    private function assertKbResult($result, $title) {
        if ($result['status'] >= 200 && $result['status'] < 300) {
            return;
        }

        $errCode = isset($result['body']['error']['code']) ? $result['body']['error']['code'] : '';
        if ($errCode === 'kb_limit_reached') {
            $msg = isset($result['body']['error']['message']) ? $result['body']['error']['message'] : 'Knowledge base limit reached.';
            throw new ApiException($msg, 409);
        }

        throw new ApiException(sprintf('Could not sync knowledge base entry for "%s". %s', $title, $this->apiErrorMessage($result)), (int)$result['status']);
    }

    // Fetches the agent's full KB list (paginated) as a slug => id map, so a
    // page's deterministic slug 'information-{id}' can be matched to an existing
    // entry. The API does NOT upsert on create, so this reconciliation is done
    // client-side: slug match → PUT/update, no match → POST/insert.
    private function fetchRemoteKbSlugs() {
        $by_slug = array();
        $page    = 1;
        $fetched = 0;
        $total   = 0;

        do {
            $result = $this->apiRequest('GET', $this->kbApiPath() . '?' . http_build_query(array(
                'page'     => $page,
                'per_page' => 100,
            )));

            if ($result['status'] < 200 || $result['status'] >= 300) {
                break;
            }

            $entries = isset($result['body']['entries']) && is_array($result['body']['entries']) ? $result['body']['entries'] : array();

            foreach ($entries as $entry) {
                if (!isset($entry['id'], $entry['slug'])) {
                    continue;
                }
                $by_slug[(string)$entry['slug']] = (int)$entry['id'];
            }

            $total   = isset($result['body']['total']) ? (int)$result['body']['total'] : 0;
            $fetched += count($entries);
            $page++;
        } while ($entries && $fetched < $total);

        return $by_slug;
    }

    // ── Setup (widget + products feed + order lookup) ────────────────────────

    // Pushes local config to /setup (partial update - products/order always sent).
    // Returns array('success' => bool, 'error' => Ovebot's own message).
    public function resyncSetup() {
        $result = $this->apiRequest('PUT', $this->setupApiPath(), $this->buildSetupPayload());
        $success = $result['status'] >= 200 && $result['status'] < 300;

        return array('success' => $success, 'error' => $success ? '' : $this->apiErrorMessage($result));
    }

    // Flattens Ovebot's error body ({error:{message, fields}}) into one readable
    // string; falls back to "HTTP <status>" when the body isn't the expected shape.
    private function apiErrorMessage($result) {
        $body    = isset($result['body']) && is_array($result['body']) ? $result['body'] : array();
        $error   = isset($body['error']) && is_array($body['error']) ? $body['error'] : array();
        $message = isset($error['message']) ? (string)$error['message'] : '';
        $fields  = isset($error['fields']) && is_array($error['fields']) ? $error['fields'] : array();

        $details = array();
        foreach ($fields as $messages) {
            foreach ((array)$messages as $field_message) {
                $details[] = (string)$field_message;
            }
        }

        $text = trim($message . ($details ? ' ' . implode(' ', $details) : ''));

        if ($text === '') {
            $text = 'HTTP ' . (isset($result['status']) ? (int)$result['status'] : 0);
        }

        return $text;
    }

    // $overrides swaps individual order_info/products fields into the payload
    // before sending, without persisting them first (so a failed sync keeps the
    // old, still-working value in config).
    public function buildSetupPayload(array $overrides = array()) {
        // /setup's widget section only accepts `language` (the rest of the widget
        // config is local-only, for the storefront embed). Sending a widget object
        // without language → 422.
        $widgetConfig = $this->config->get('module_ovebotai_widget');
        $widgetConfig = is_array($widgetConfig) ? $widgetConfig : array();
        $language     = (isset($widgetConfig['language']) && $widgetConfig['language'] !== '') ? (string)$widgetConfig['language'] : 'auto';

        $widget = array('language' => $language);
        if (isset($overrides['widget'])) {
            $widget = array_merge($widget, $overrides['widget']);
        }

        $base     = $this->catalogBase();
        $feedHash = (string)$this->config->get('module_ovebotai_feed_hash');

        $orderInfo = array(
            'enabled'       => $this->getOrderEnabled(),
            'api_url'       => $base . 'index.php?route=extension/module/ovebotai/orders',
            'api_user'      => (string)$this->config->get('module_ovebotai_order_user'),
            'api_password'  => (string)$this->config->get('module_ovebotai_order_pass'),
            'lookup_method' => 'email',
        );
        if (isset($overrides['order_info'])) {
            $orderInfo = array_merge($orderInfo, $overrides['order_info']);
        }

        $payload = array(
            'widget'     => $widget,
            'order_info' => $orderInfo,
        );

        // products.enabled mirrors the account recommendation master
        // (module_ovebotai_products_recommend) and is always sent. feed_url +
        // currency only when OUR feed is the source; for a merchant's own feed we
        // send `enabled` alone (the API keeps their feed_url).
        $products = array('enabled' => $this->getProductsRecommend());

        if ($this->getProductsEnabled()) {
            $products['feed_url'] = $base . 'index.php?route=extension/module/ovebotai/feed&hash=' . urlencode($feedHash);
            $products['currency'] = (string)$this->config->get('config_currency');
        }

        if (isset($overrides['products'])) {
            $products = array_merge($products, $overrides['products']);
        }

        $payload['products'] = $products;

        return $payload;
    }

    // Persists the wizard's page selection so a re-run shows the same boxes ticked.
    public function saveKbPageIds(array $information_ids) {
        $this->persist(array('module_ovebotai_kb_page_ids' => array_values(array_map('intval', $information_ids))));
    }

    // Flips setup_complete + chat_status on. Called only after finish succeeds.
    public function markComplete() {
        $this->persist(array(
            'module_ovebotai_setup_complete' => '1',
            'module_ovebotai_chat_status'    => '1',
        ));
    }

    // ── Connection state ─────────────────────────────────────────────────────

    public function isConnected() {
        return (bool)$this->config->get('module_ovebotai_refresh_token') && $this->getWorkspace() !== '';
    }

    // A stored refresh token can't tell us it was revoked server-side, so make
    // one memoized integration/status probe to catch a lapsed connection now.
    public function isConnectedLive() {
        if (!$this->isConnected() || !$this->integrationStatus()) {
            return false;
        }

        return $this->isConnected();
    }

    // Fetches GET /v1/integration/status once per request and caches it (even to
    // array()), so a cleared/revoked token still counts as "fetched".
    private function integrationStatus() {
        if ($this->statusBody !== null) {
            return $this->statusBody;
        }

        $this->statusBody = array();

        if ($this->isConnected()) {
            $result = $this->apiRequest('GET', '/v1/integration/status');
            if ($result['status'] >= 200 && $result['status'] < 300
                && isset($result['body']) && is_array($result['body'])) {
                $this->statusBody = $result['body'];
            }
        }

        return $this->statusBody;
    }

    public function getWorkspace() {
        $workspace = (string)$this->config->get('module_ovebotai_workspace');
        return preg_match('/^[a-z0-9-]+$/i', $workspace) ? $workspace : '';
    }

    public function getAgent() {
        $agent = (string)$this->config->get('module_ovebotai_agent');
        return $agent !== '' ? $agent : 'default';
    }

    // ── Dashboard data (live from Ovebot.ai) ─────────────────────────────────

    // Product count on Ovebot's side (from the memoized status body). Best-effort:
    // 0 when non-2xx / disconnected.
    public function getIndexedProductCount() {
        $body = $this->integrationStatus();

        return isset($body['integration']['counts']['products'])
            ? (int)$body['integration']['counts']['products']
            : 0;
    }

    // The agent's KB as Ovebot currently holds it. Returns array('entries' => [...],
    // 'error' => bool); each entry is title / is_active / edit_url.
    public function getKbEntries() {
        if (!$this->isConnected()) {
            return array('entries' => array(), 'error' => false);
        }

        $result = $this->apiRequest('GET', $this->kbApiPath() . '?per_page=100');

        if ($result['status'] < 200 || $result['status'] >= 300) {
            return array('entries' => array(), 'error' => true);
        }

        $workspace = $this->getWorkspace();
        $raw       = isset($result['body']['entries']) && is_array($result['body']['entries'])
            ? $result['body']['entries']
            : array();

        $entries = array();
        foreach ($raw as $entry) {
            if (empty($entry['id'])) {
                continue;
            }
            $entries[] = array(
                'title'     => isset($entry['title']) ? (string)$entry['title'] : '',
                'is_active' => !empty($entry['is_active']),
                'edit_url'  => 'https://' . $workspace . '.ovebot.ai/knowledge-base/' . (int)$entry['id'] . '/edit',
            );
        }

        return array('entries' => $entries, 'error' => false);
    }

    // Workspace-scoped ovebot.ai URLs for the dashboard links; '' when there's no
    // valid workspace, so the caller can hide a broken link.
    public function getAccountUrl() {
        $ws = $this->getWorkspace();
        return $ws !== '' ? 'https://' . $ws . '.ovebot.ai' : '';
    }

    public function getProductsUrl() {
        $ws = $this->getWorkspace();
        return $ws !== '' ? 'https://' . $ws . '.ovebot.ai/products' : '';
    }

    public function getKbCreateUrl() {
        $ws = $this->getWorkspace();
        return $ws !== '' ? 'https://' . $ws . '.ovebot.ai/knowledge-base/create' : '';
    }

    // "Start Free" target: the account register page with the OpenCart freemium
    // plan slug (oc-freemium; wp-/spfy- on the other platforms) and the store's
    // domain pre-filled. The account side checks the slug against the active
    // freemium plan - if it's closed, it falls back to a normal register and
    // shows the "not accepting free plans right now" notice.
    public function getRegisterUrl() {
        $host = (string)$this->config->get('module_ovebotai_account_host');
        $host = $host !== '' ? $host : 'account.ovebot.ai';

        return 'https://' . $host . '/register?' . http_build_query(array(
            'plan'   => 'oc-freemium',
            'domain' => $this->siteDomain(),
        ));
    }

    // Where "I'll provide my own feed" sends the merchant to configure products.
    public function getSetupUrl() {
        $ws = $this->getWorkspace();
        return $ws !== '' ? 'https://' . $ws . '.ovebot.ai/setup' : '';
    }

    // ── Settings page data ───────────────────────────────────────────────────

    public function getChatStatus() {
        return (string)$this->config->get('module_ovebotai_chat_status') === '1';
    }

    // Whether OUR integrated feed is the source Ovebot reads. Default (never set)
    // = true. When false, feed() 403s and /setup omits our feed so the merchant's
    // own Ovebot-side config takes over.
    public function getProductsEnabled() {
        $raw = $this->config->get('module_ovebotai_products_enabled');
        return $raw === null || $raw === '' ? true : ($raw === '1' || $raw === 1 || $raw === true);
    }

    public function setProductsEnabled($enabled) {
        $this->persist(array('module_ovebotai_products_enabled' => $enabled ? '1' : '0'));
    }

    // Local mirror of the account's products.enabled (recommendation master): the
    // switch writes here + pushes via buildSetupPayload, syncSettings pulls it
    // back. Default never-set = on.
    public function getProductsRecommend() {
        $raw = $this->config->get('module_ovebotai_products_recommend');
        return $raw === null || $raw === '' ? true : ($raw === '1' || $raw === 1 || $raw === true);
    }

    public function setProductsRecommend($enabled) {
        $this->persist(array('module_ovebotai_products_recommend' => $enabled ? '1' : '0'));
    }

    // Local mirror of the account's order_info.enabled: the switch writes here +
    // pushes via buildSetupPayload, syncSettings pulls it back. Default never-set = on.
    public function getOrderEnabled() {
        $raw = $this->config->get('module_ovebotai_order_enabled');
        return $raw === null || $raw === '' ? true : ($raw === '1' || $raw === 1 || $raw === true);
    }

    public function setOrderEnabled($enabled) {
        $this->persist(array('module_ovebotai_order_enabled' => $enabled ? '1' : '0'));
    }

    // The 'integration' object from the memoized status body ({products,
    // order_info, counts, ...}); empty array when disconnected / fetch failed.
    public function getIntegration() {
        $body = $this->integrationStatus();
        return isset($body['integration']) && is_array($body['integration']) ? $body['integration'] : array();
    }

    // Whether product recommendation is on in the account. Absent (disconnected /
    // fetch failed) is treated as ON so the dashboard doesn't flash a false warning.
    public function isProductRecommendationEnabled() {
        $integration = $this->getIntegration();
        return !isset($integration['products']) || !empty($integration['products']);
    }

    // Whether order lookup is on in the account. Same "absent = on" rule as above.
    public function isOrderApiEnabled() {
        $integration = $this->getIntegration();
        return !isset($integration['order_info']) || !empty($integration['order_info']);
    }

    // Reconciles the local order / product switches with the account's live state
    // (read on the dashboard), so a change made on either side stays truthful.
    public function syncSettings() {
        $integration = $this->getIntegration();

        if (isset($integration['order_info'])) {
            $remote = !empty($integration['order_info']);
            if ($remote !== $this->getOrderEnabled()) {
                $this->setOrderEnabled($remote);
            }
        }

        if (isset($integration['products'])) {
            $remote = !empty($integration['products']);
            if ($remote !== $this->getProductsRecommend()) {
                $this->setProductsRecommend($remote);
            }
        }
    }

    public function getWidget() {
        $widget = $this->config->get('module_ovebotai_widget');
        return is_array($widget) ? $widget : array();
    }

    public function getFeedUrl() {
        $hash = (string)$this->config->get('module_ovebotai_feed_hash');
        return $this->catalogBase() . 'index.php?route=extension/module/ovebotai/feed&hash=' . urlencode($hash);
    }

    // Public storefront URL for one information page (wizard step 2 "View page").
    public function getInformationPageUrl($information_id) {
        return $this->catalogBase() . 'index.php?route=information/information&information_id=' . (int)$information_id;
    }

    public function getOrderUrl() {
        return $this->catalogBase() . 'index.php?route=extension/module/ovebotai/orders';
    }

    public function getOrderUser() {
        return (string)$this->config->get('module_ovebotai_order_user');
    }

    public function getOrderPass() {
        return (string)$this->config->get('module_ovebotai_order_pass');
    }

    // ── Settings page: save ──────────────────────────────────────────────────

    // Saves chat + widget + switches locally, then pushes to /setup when connected
    // (skipped while disconnected). Returns needs_reconnect / sync_error.
    public function saveSettings($chatStatus, array $widget, $productsEnabled = true, $orderEnabled = true, $productsRecommend = true) {
        $partial = array(
            'module_ovebotai_chat_status'        => $chatStatus ? '1' : '0',
            'module_ovebotai_widget'             => $widget,
            'module_ovebotai_products_enabled'   => $productsEnabled ? '1' : '0',
            'module_ovebotai_order_enabled'      => $orderEnabled ? '1' : '0',
            'module_ovebotai_products_recommend' => $productsRecommend ? '1' : '0',
        );

        if (!$this->isConnected()) {
            $this->persist($partial);
            return array('needs_reconnect' => true, 'sync_error' => false, 'sync_error_message' => '');
        }

        $this->persist($partial);

        $resync = $this->resyncSetup();

        return array(
            'needs_reconnect'    => false,
            'sync_error'         => !$resync['success'],
            'sync_error_message' => $resync['success'] ? '' : $resync['error'],
        );
    }

    // ── Settings page: regenerate feed hash ──────────────────────────────────

    // Syncs the new feed URL to Ovebot FIRST, persists locally only on success,
    // so a failed sync leaves the old (working) hash in place.
    public function regenerateFeedHash() {
        $hash = $this->randomToken(16);
        $url  = $this->catalogBase() . 'index.php?route=extension/module/ovebotai/feed&hash=' . urlencode($hash);

        $payload = $this->buildSetupPayload(array('products' => array('feed_url' => $url)));
        $result  = $this->apiRequest('PUT', $this->setupApiPath(), $payload);

        if ($result['status'] < 200 || $result['status'] >= 300) {
            return array('success' => false, 'error' => $this->apiErrorMessage($result));
        }

        $this->persist(array('module_ovebotai_feed_hash' => $hash));

        return array('success' => true, 'hash' => $hash, 'url' => $url);
    }

    // ── Settings page: regenerate order-lookup credentials ───────────────────

    // Same confirm-before-persist ordering as regenerateFeedHash().
    public function regenerateOrderCreds() {
        $user = $this->generateOrderUser();
        $pass = $this->randomToken(12);

        $payload = $this->buildSetupPayload(array('order_info' => array('api_user' => $user, 'api_password' => $pass)));
        $result  = $this->apiRequest('PUT', $this->setupApiPath(), $payload);

        if ($result['status'] < 200 || $result['status'] >= 300) {
            return array('success' => false, 'error' => $this->apiErrorMessage($result));
        }

        $this->persist(array(
            'module_ovebotai_order_user' => $user,
            'module_ovebotai_order_pass' => $pass,
        ));

        return array('success' => true, 'user' => $user, 'pass' => $pass);
    }

    // random_bytes needs PHP 7+; calling an undefined function is an
    // uncatchable fatal on PHP 5 (there's no Throwable and nothing ever
    // throws), so this must check function_exists() rather than try/catch.
    private function randomToken($bytes) {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes($bytes));
            } catch (\Exception $e) {
                // fall through to the weaker fallback below
            }
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $result = openssl_random_pseudo_bytes($bytes, $strong);
            if ($result !== false) {
                return bin2hex($result);
            }
        }

        return md5(uniqid('ovebotai_', true) . microtime(true));
    }

    private function generateOrderUser() {
        $host = preg_replace('/^www\./i', '', $this->siteDomain());
        $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $host)), '_');

        return ($slug !== '' ? $slug : 'store') . '_' . substr($this->randomToken(4), 0, 8);
    }

    // Best-effort remote revoke, then clear tokens + workspace locally. Keeps
    // setup_complete / KB map / feed hash / order creds / agent so a reconnect
    // goes straight back to the dashboard (and syncAgentFromMe can spot an agent change).
    public function disconnect() {
        try {
            $this->client->setAccessToken((string)$this->config->get('module_ovebotai_access_token'))
                ->apiRequest('POST', '/v1/disconnect');
        } catch (OvebotaiException $e) {
            // Ignore - local cleanup below must happen regardless.
        }

        $this->persist(array(
            'module_ovebotai_access_token'  => '',
            'module_ovebotai_refresh_token' => '',
            'module_ovebotai_token_expires' => '',
            'module_ovebotai_workspace'     => '',
        ));
    }

    // ── Token persistence ────────────────────────────────────────────────────

    private function storeTokens(array $response) {
        $partial = array(
            'module_ovebotai_access_token'  => (string)$response['access_token'],
            'module_ovebotai_token_expires' => time() + (isset($response['expires_in']) ? (int)$response['expires_in'] : 3600),
        );

        if (!empty($response['refresh_token'])) {
            $partial['module_ovebotai_refresh_token'] = (string)$response['refresh_token'];
        }

        // Strict slug only - this is concatenated into storefront script-src hosts.
        if (!empty($response['workspace']['slug']) && preg_match('/^[a-z0-9-]+$/i', $response['workspace']['slug'])) {
            $partial['module_ovebotai_workspace'] = $response['workspace']['slug'];
        }

        // '' (not 'default') for the default agent; syncAgentFromMe resolves the
        // real value right after. Only matters if the response carries an agent.
        if (isset($response['agent'])) {
            $partial['module_ovebotai_agent'] = is_array($response['agent'])
                ? (isset($response['agent']['public_id']) ? (string)$response['agent']['public_id'] : '')
                : (string)$response['agent'];
        }

        $this->persist($partial);
    }

    private function expireTokens() {
        $this->persist(array(
            'module_ovebotai_access_token'  => '',
            'module_ovebotai_refresh_token' => '',
            'module_ovebotai_token_expires' => '',
        ));
    }

    // Read-merge-write the `module_ovebotai` setting group (editSetting replaces
    // the whole group), and mirror changes into live $this->config + the Client.
    private function persist(array $partial) {
        $this->load->model('setting/setting');

        $settings = $this->model_setting_setting->getSetting('module_ovebotai');
        $settings = array_merge($settings, $partial);
        $this->model_setting_setting->editSetting('module_ovebotai', $settings);

        foreach ($partial as $key => $value) {
            $this->config->set($key, $value);
        }

        if (isset($partial['module_ovebotai_access_token'])) {
            $this->client->setAccessToken($partial['module_ovebotai_access_token']);
        }
    }

    // ── Information page content ─────────────────────────────────────────────

    private function getInformationPage($information_id) {
        $language_id = (int)$this->config->get('config_language_id');

        $query = $this->db->query(
            "SELECT i.status, id.title, id.description
             FROM `" . DB_PREFIX . "information` i
             LEFT JOIN `" . DB_PREFIX . "information_description` id
                ON (i.information_id = id.information_id)
             WHERE i.information_id = '" . (int)$information_id . "'
               AND id.language_id = '" . $language_id . "'"
        );

        if (!$query->num_rows) {
            return null;
        }

        // OpenCart stores titles/descriptions HTML-entity-encoded; decode so the
        // API gets real text, not entity soup.
        return array(
            'status' => (int)$query->row['status'],
            'title'  => html_entity_decode((string)$query->row['title'], ENT_QUOTES, 'UTF-8'),
            'body'   => (string)$query->row['description'],
        );
    }

    private function buildKbBody(array $page) {
        $content = html_entity_decode(strip_tags($page['body']), ENT_QUOTES, 'UTF-8');
        $content = trim(preg_replace('/\s+/', ' ', $content));

        $title = $page['title'];
        return trim($title . ($content !== '' ? "\n\n" . $content : ''));
    }

    // ── API paths ────────────────────────────────────────────────────────────

    private function setupApiPath() {
        return '/v1/workspaces/' . $this->getWorkspace() . '/agents/' . $this->getAgent() . '/setup';
    }

    private function kbApiPath() {
        return '/v1/workspaces/' . $this->getWorkspace() . '/agents/' . $this->getAgent() . '/knowledge-base';
    }

    // ── URL helpers ──────────────────────────────────────────────────────────

    private function catalogBase() {
        if (defined('HTTPS_CATALOG')) {
            return HTTPS_CATALOG;
        }
        if (defined('HTTP_CATALOG')) {
            return HTTP_CATALOG;
        }
        return '';
    }

    private function siteDomain() {
        $host = parse_url($this->catalogBase(), PHP_URL_HOST);
        return $host ? $host : '';
    }
}
