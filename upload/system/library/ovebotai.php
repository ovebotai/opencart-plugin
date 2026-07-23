<?php

namespace Ovebotai;

require_once DIR_SYSTEM . 'library/ovebotai/exceptions/OvebotaiException.php';
require_once DIR_SYSTEM . 'library/ovebotai/exceptions/ConnectionException.php';
require_once DIR_SYSTEM . 'library/ovebotai/exceptions/ApiException.php';
require_once DIR_SYSTEM . 'library/ovebotai/exceptions/AuthException.php';
require_once DIR_SYSTEM . 'library/ovebotai/client.php';
require_once DIR_SYSTEM . 'library/ovebotai/databuilder.php';

use Ovebotai\Exceptions\ApiException;
use Ovebotai\Exceptions\AuthException;
use Ovebotai\Exceptions\OvebotaiException;

// Orchestrator for everything Ovebot.ai. Extends \Model so it has full
// registry access (config, session, db, model loading) exactly like the
// Typesense library it's modelled on. It owns two collaborators:
//   - $client:      a dumb HTTP/OAuth transport (Client)
//   - $dataBuilder: turns catalog data into payloads (stub for now)
// and it owns the parts the Client deliberately does not: where tokens live
// (the `module_ovebotai` setting group), when to refresh them, and how to
// keep the in-memory config consistent after a write within the same request.
//
// Loaded the same way as Typesense — the caller does
//   require_once DIR_SYSTEM . 'library/ovebotai.php';
//   $ovebotai = new \Ovebotai\Ovebotai($this->registry);
// (the framework's $this->load->library() can't resolve a namespaced class, so
// direct construction is the supported path, just like \Typesense\Typesense).
class Ovebotai extends \Model {
    private $client;
    private $dataBuilder;

    // Memoized body of GET /v1/integration/status for this request. That call
    // doubles as the live connection probe (apiRequest refreshes or clears the
    // tokens as needed), so the connection check and the product count share a
    // single fetch. null = not fetched yet; array() = fetched but empty/failed.
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

        $this->dataBuilder = new DataBuilder($registry);
    }

    // ── OAuth: start ─────────────────────────────────────────────────────────

    // Builds the account.ovebot.ai authorize URL and stashes the PKCE verifier
    // in the session keyed by the one-time state, so a second "Connect" click
    // (e.g. after abandoning a prior attempt) doesn't invalidate an earlier
    // still-in-flight authorization.
    public function getAuthUrl($callbackUrl) {
        $verifier = Client::generateVerifier();
        $state    = Client::generateState();

        $this->session->data['module_ovebotai_pkce_' . $state] = $verifier;

        return $this->client->buildAuthUrl($this->siteDomain(), $callbackUrl, $verifier, $state);
    }

    // ── OAuth: return ────────────────────────────────────────────────────────

    // Exchanges the authorization code for tokens and persists them. Returns
    // array('success' => true) or array('error' => '...') — never throws, so
    // the controller can render the error inline on the connect step.
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

        $this->storeTokens($response);

        return array('success' => true);
    }

    // ── Authenticated API with auto-refresh ──────────────────────────────────

    // Every API call funnels through here so token refresh/rotation is applied
    // uniformly. Proactive refresh first (avoid a guaranteed 401 when the
    // 1-hour token is already past due), then one reactive refresh+retry on a
    // 401. Rotated tokens are persisted by storeTokens() inside refresh().
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
            // Refresh failed or the token family was revoked. Clear only the
            // OAuth credentials — not workspace/agent/chat — so the storefront
            // widget keeps working unattended; the admin will prompt for
            // reconnect the next time someone opens the module.
            $this->expireTokens();
            return false;
        }

        $this->storeTokens($response);
        return true;
    }

    // ── Knowledge base ───────────────────────────────────────────────────────

    // Single-page sync — the shape the caller asked for:
    //   $this->ovebotai->syncKbPage($information_id);
    // Does the "diverse operatiuni" (fetch the page, build the payload, resolve
    // whether it's a create or an update) then forwards to the API. Throws on
    // failure (ApiException / ConnectionException), returns the KB entry id on
    // success, or null when the page was intentionally skipped (unpublished /
    // too little text).
    public function syncKbPage($information_id, $active = true) {
        $remote = $this->fetchRemoteKbEntries();
        return $this->syncOneKbPage((int)$information_id, $active, $remote['by_id'], $remote['by_title']);
    }

    // Bulk sync for the wizard's finish step. Fetches the remote entry list
    // once, then syncs each page, aggregating per-page failures into 'errors'
    // and intentional skips into 'warnings' (a warning must not block the
    // overall "setup complete" from succeeding).
    public function syncKbPages(array $information_ids, $active = true) {
        $errors   = array();
        $warnings = array();

        if (!$information_ids) {
            return array('errors' => $errors, 'warnings' => $warnings);
        }

        $remote = $this->fetchRemoteKbEntries();

        foreach ($information_ids as $information_id) {
            try {
                $this->syncOneKbPage((int)$information_id, $active, $remote['by_id'], $remote['by_title'], $warnings);
            } catch (OvebotaiException $e) {
                // kb_limit_reached is a workspace quota ceiling, not a per-page
                // failure — stop sending further pages and surface it as a
                // warning; whatever synced up to now stands.
                if ($e->getCode() === 409 || stripos($e->getMessage(), 'kb_limit') !== false) {
                    $warnings[] = $e->getMessage();
                    break;
                }
                $errors[] = $e->getMessage();
            }
        }

        return array('errors' => $errors, 'warnings' => $warnings);
    }

    private function syncOneKbPage($information_id, $active, array $remoteById, array $remoteByTitle, array &$warnings = array()) {
        $map   = $this->getKbMap();
        $kb_id = isset($map[$information_id]) ? (int)$map[$information_id] : 0;

        // Locally mapped id no longer exists remotely — stale mapping, treat
        // the page as never-synced.
        if ($kb_id && !isset($remoteById[$kb_id])) {
            $kb_id = 0;
        }

        // Never synced and now being unchecked — nothing to deactivate.
        if (!$active && !$kb_id) {
            return null;
        }

        $page = $this->getInformationPage($information_id);

        if (!$page) {
            return null;
        }

        if (empty($page['status']) && $active) {
            $warnings[] = sprintf('Skipped "%s" - page is not enabled.', $page['title']);
            return null;
        }

        $title = $page['title'];
        $body  = $this->buildKbBody($page);

        // The API requires a body of at least 10 characters; skip pages with
        // too little plain text (common with page-builder content that isn't
        // stored as HTML in the description field).
        $length = function_exists('mb_strlen') ? mb_strlen($body) : strlen($body);
        if ($active && $length < 10) {
            $warnings[] = sprintf('Skipped "%s" - not enough text content to sync (minimum 10 characters).', $title);
            return null;
        }

        // No usable id — match a remote entry with the same title before
        // creating a new one, so a title collision reuses the same entry.
        if ($active && !$kb_id && isset($remoteByTitle[$title])) {
            $kb_id = (int)$remoteByTitle[$title];
            $this->setKbMapEntry($information_id, $kb_id);
        }

        $payload = array(
            'title'     => $title,
            'body'      => $body,
            'is_active' => (bool)$active,
        );

        $result = null;

        if ($kb_id) {
            $result = $this->apiRequest('PUT', $this->kbApiPath() . '/' . $kb_id, $payload);
            // Entry gone on Ovebot's side — recreate below (only when activating).
            if ($active && $result['status'] === 404) {
                $kb_id = 0;
            }
        }

        if ($active && !$kb_id) {
            $result = $this->apiRequest('POST', $this->kbApiPath(), $payload);
            $new_id = isset($result['body']['id']) ? (int)$result['body']['id'] : 0;
            if ($new_id) {
                $kb_id = $new_id;
                $this->setKbMapEntry($information_id, $kb_id);
            }
        }

        if (!$result || $result['status'] < 200 || $result['status'] >= 300) {
            $code = $result ? (int)$result['status'] : 0;
            $errCode = isset($result['body']['error']['code']) ? $result['body']['error']['code'] : '';
            if ($errCode === 'kb_limit_reached') {
                $msg = isset($result['body']['error']['message']) ? $result['body']['error']['message'] : 'Knowledge base limit reached.';
                throw new ApiException($msg, 409);
            }
            throw new ApiException(sprintf('Could not sync knowledge base entry for "%s".', $title), $code);
        }

        return $kb_id;
    }

    // Pages through GET .../knowledge-base and returns every remote entry keyed
    // both ways (id => title, title => id) so callers can validate local ids
    // and match on title without a second fetch.
    private function fetchRemoteKbEntries() {
        $by_id    = array();
        $by_title = array();
        $page     = 1;
        $fetched  = 0;
        $total    = 0;

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
                if (!isset($entry['id'], $entry['title'])) {
                    continue;
                }
                $by_id[(int)$entry['id']]        = (string)$entry['title'];
                $by_title[(string)$entry['title']] = (int)$entry['id'];
            }

            $total   = isset($result['body']['total']) ? (int)$result['body']['total'] : 0;
            $fetched += count($entries);
            $page++;
        } while ($entries && $fetched < $total);

        return array('by_id' => $by_id, 'by_title' => $by_title);
    }

    // ── Setup (widget + products feed + order lookup) ────────────────────────

    // Pushes the current local config to Ovebot.ai's /setup endpoint. Returns
    // true on a 2xx. Products/order sections are always sent (never omitted):
    // /setup is a partial update, so omitting a section would leave Ovebot's
    // copy stuck on its previous value.
    public function resyncSetup() {
        $result = $this->apiRequest('PUT', $this->setupApiPath(), $this->buildSetupPayload());
        return $result['status'] >= 200 && $result['status'] < 300;
    }

    // $overrides lets a caller replace individual order_info/products fields
    // (e.g. a freshly generated feed hash) in the payload BEFORE it's sent,
    // without having persisted them locally yet — so a failed sync leaves the
    // old, still-working value in config untouched. See regenerateFeedHash()/
    // regenerateOrderCreds().
    public function buildSetupPayload(array $overrides = array()) {
        $widget = $this->config->get('module_ovebotai_widget');
        if (!is_array($widget)) {
            $widget = array();
        }
        $widget = array_filter($widget, function ($v) { return $v !== '' && $v !== null; });

        $base     = $this->catalogBase();
        $feedHash = (string)$this->config->get('module_ovebotai_feed_hash');

        $orderInfo = array(
            'enabled'       => true,
            'api_url'       => $base . 'index.php?route=extension/module/ovebotai/orders',
            'api_user'      => (string)$this->config->get('module_ovebotai_order_user'),
            'api_password'  => (string)$this->config->get('module_ovebotai_order_pass'),
            'lookup_method' => 'email',
        );
        if (isset($overrides['order_info'])) {
            $orderInfo = array_merge($orderInfo, $overrides['order_info']);
        }

        $products = array(
            'enabled'  => true,
            'feed_url' => $base . 'index.php?route=extension/module/ovebotai/feed&hash=' . urlencode($feedHash),
            'currency' => (string)$this->config->get('config_currency'),
        );
        if (isset($overrides['products'])) {
            $products = array_merge($products, $overrides['products']);
        }

        return array(
            'widget'     => $widget ? $widget : new \stdClass(),
            'order_info' => $orderInfo,
            'products'   => $products,
        );
    }

    // Persists the wizard's page selection so a later re-run of the wizard (or
    // a settings screen) shows the same boxes ticked.
    public function saveKbPageIds(array $information_ids) {
        $this->persist(array('module_ovebotai_kb_page_ids' => array_values(array_map('intval', $information_ids))));
    }

    // Flips the two flags that make isSetupComplete() true and turn the
    // storefront chat on. Called only after the finish step actually succeeded.
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

    // A stored refresh token only tells us we once connected — it can't know
    // the token was revoked server-side. This makes one lightweight call
    // (piggybacking apiRequest's refresh logic) so a lapsed connection is
    // caught on the page load that displays it, not a later action. The probe
    // is memoized, so calling this before routing AND reading the product
    // count during render is still a single API request.
    public function isConnectedLive() {
        if (!$this->isConnected()) {
            return false;
        }
        $this->integrationStatus();
        return $this->isConnected();
    }

    // Fetches GET /v1/integration/status once per request and caches the body.
    // Setting the cache before returning (even to array()) means a revoked
    // token that gets cleared mid-call still counts as "fetched", so no caller
    // re-issues the request.
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

    public function test() {
        return $this->client->setAccessToken((string)$this->config->get('module_ovebotai_access_token'))->test();
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

    // How many products Ovebot.ai actually has indexed for this agent — the
    // count on *their* side, not the local feed count we send. Reads the
    // memoized status body (see integrationStatus), so it reuses the probe made
    // during routing instead of issuing a second call. Best-effort: a non-2xx
    // (or disconnected) yields 0, since the card is informational.
    public function getIndexedProductCount() {
        $body = $this->integrationStatus();

        return isset($body['integration']['counts']['products'])
            ? (int)$body['integration']['counts']['products']
            : 0;
    }

    // The agent's knowledge base as Ovebot.ai currently holds it — the actual
    // state, not just what this store has attempted to sync. Returns
    //   array('entries' => [...], 'error' => bool)
    // where each entry is title / is_active / edit_url. On a failed fetch,
    // 'error' is true and 'entries' is empty so the view can show a notice.
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

    // Workspace-scoped URLs on ovebot.ai for the dashboard's outbound links.
    // Empty string when there's no valid workspace, so the caller can hide the
    // link rather than point at a broken host.
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

    // ── Settings page data ───────────────────────────────────────────────────

    public function getChatStatus() {
        return (string)$this->config->get('module_ovebotai_chat_status') === '1';
    }

    public function getWidget() {
        $widget = $this->config->get('module_ovebotai_widget');
        return is_array($widget) ? $widget : array();
    }

    // The six delivery-estimate keys with the same defaults as the WordPress
    // plugin (1–2 / 2–4 / 5–10 business days for shipped / in-stock / oos).
    public function getDeliveryDays() {
        $defaults = array(
            'days_shipped_min' => 1, 'days_shipped_max' => 2,
            'days_instock_min' => 2, 'days_instock_max' => 4,
            'days_oos_min'     => 5, 'days_oos_max'     => 10,
        );

        $values = array();
        foreach ($defaults as $key => $default) {
            $stored = $this->config->get('module_ovebotai_' . $key);
            $values[$key] = ($stored !== '' && $stored !== null) ? (int)$stored : $default;
        }

        return $values;
    }

    public function getFeedUrl() {
        $hash = (string)$this->config->get('module_ovebotai_feed_hash');
        return $this->catalogBase() . 'index.php?route=extension/module/ovebotai/feed&hash=' . urlencode($hash);
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

    // Chat on/off + widget appearance are always saved locally. Delivery days
    // and the API resync are skipped while disconnected — mirrors the
    // WordPress plugin exactly: a disconnected store can't sync anything
    // API-dependent, so it's not even asked to try.
    // Returns array('needs_reconnect' => bool, 'sync_error' => bool).
    public function saveSettings($chatStatus, array $widget, array $delivery) {
        $partial = array(
            'module_ovebotai_chat_status' => $chatStatus ? '1' : '0',
            'module_ovebotai_widget'      => $widget,
        );

        if (!$this->isConnected()) {
            $this->persist($partial);
            return array('needs_reconnect' => true, 'sync_error' => false);
        }

        foreach ($delivery as $key => $value) {
            $partial['module_ovebotai_' . $key] = (int)$value;
        }
        $this->persist($partial);

        return array('needs_reconnect' => false, 'sync_error' => !$this->resyncSetup());
    }

    // ── Settings page: regenerate feed hash ──────────────────────────────────

    // Syncs the new feed URL to Ovebot.ai FIRST — only persisted locally once
    // confirmed, so a failed sync leaves the old (still working) hash in place
    // rather than clobbering it with one Ovebot.ai never received.
    public function regenerateFeedHash() {
        $hash = $this->randomToken(16);
        $url  = $this->catalogBase() . 'index.php?route=extension/module/ovebotai/feed&hash=' . urlencode($hash);

        $payload = $this->buildSetupPayload(array('products' => array('feed_url' => $url)));
        $result  = $this->apiRequest('PUT', $this->setupApiPath(), $payload);

        if ($result['status'] < 200 || $result['status'] >= 300) {
            return array('success' => false);
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
            return array('success' => false);
        }

        $this->persist(array(
            'module_ovebotai_order_user' => $user,
            'module_ovebotai_order_pass' => $pass,
        ));

        return array('success' => true, 'user' => $user, 'pass' => $pass);
    }

    // ── Settings page: clear feed cache ──────────────────────────────────────

    // Local only, no API dependency — mirrors the WordPress plugin's cache
    // invalidation (which just clears a transient). Bumping the version number
    // gives the real (phase-2) feed a cache key to invalidate against once
    // it's built.
    public function clearFeedCache() {
        $version = (int)$this->config->get('module_ovebotai_cache_version');
        $this->persist(array('module_ovebotai_cache_version' => $version + 1));
        return true;
    }

    private function randomToken($bytes) {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (\Exception $e) {
            return md5(uniqid('ovebotai_', true) . microtime(true));
        } catch (\Throwable $e) {
            return md5(uniqid('ovebotai_', true) . microtime(true));
        }
    }

    private function generateOrderUser() {
        $host = preg_replace('/^www\./i', '', $this->siteDomain());
        $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $host)), '_');

        return ($slug !== '' ? $slug : 'store') . '_' . substr($this->randomToken(4), 0, 8);
    }

    // Best-effort remote revoke, then clear the connection locally (tokens +
    // workspace/agent). We deliberately KEEP module_ovebotai_setup_complete:
    // a store that already finished the wizard once shouldn't be walked
    // through it again after a disconnect. While disconnected, isConnected()/
    // isSetupComplete() are still false (refresh_token + workspace are empty),
    // so the reconnect screen (wizard step 1) shows; but once OAuth restores
    // the tokens + workspace, setup_complete being '1' routes straight back to
    // the dashboard instead of re-running steps 2–4. The KB map, feed hash and
    // order credentials are likewise left intact for the same reason.
    public function disconnect() {
        try {
            $this->client->setAccessToken((string)$this->config->get('module_ovebotai_access_token'))
                ->apiRequest('POST', '/v1/disconnect');
        } catch (OvebotaiException $e) {
            // Ignore — local cleanup below must happen regardless.
        }

        $this->persist(array(
            'module_ovebotai_access_token'  => '',
            'module_ovebotai_refresh_token' => '',
            'module_ovebotai_token_expires' => '',
            'module_ovebotai_workspace'     => '',
            'module_ovebotai_agent'         => '',
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

        // Strict slug format only — this value is concatenated into script-src
        // hosts on the storefront, so a value that fails the check is simply
        // not stored rather than trusted as a hostname part.
        if (!empty($response['workspace']['slug']) && preg_match('/^[a-z0-9-]+$/i', $response['workspace']['slug'])) {
            $partial['module_ovebotai_workspace'] = $response['workspace']['slug'];
        }

        if (isset($response['agent'])) {
            $partial['module_ovebotai_agent'] = is_array($response['agent'])
                ? (isset($response['agent']['slug']) ? $response['agent']['slug'] : 'default')
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

    // Read-merge-write against the `module_ovebotai` setting group. editSetting
    // REPLACES the whole group, so a partial write must merge into the current
    // stored settings first, or every other key would be wiped. Also mirrors
    // the change into the live $this->config (editSetting doesn't refresh it)
    // and into the Client, so any further work in this same request sees the
    // new values.
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

    // ── KB id mapping (information_id => remote kb entry id) ──────────────────

    private function getKbMap() {
        $map = $this->config->get('module_ovebotai_kb_map');
        return is_array($map) ? $map : array();
    }

    private function setKbMapEntry($information_id, $kb_id) {
        $map = $this->getKbMap();
        $map[(int)$information_id] = (int)$kb_id;
        $this->persist(array('module_ovebotai_kb_map' => $map));
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

        return array(
            'status' => (int)$query->row['status'],
            'title'  => (string)$query->row['title'],
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
