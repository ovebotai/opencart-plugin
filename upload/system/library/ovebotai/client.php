<?php

namespace Ovebotai;

use Ovebotai\Exceptions\AuthException;
use Ovebotai\Exceptions\ConnectionException;

// Thin transport for Ovebot.ai: pure HTTP, no knowledge of where tokens are
// stored. Holds one access token in memory and speaks the account host (OAuth)
// and the api host (/v1/*). Token lifecycle lives in the Ovebotai orchestrator.
class Client {
    const DEFAULT_ACCOUNT_HOST = 'account.ovebot.ai';
    const DEFAULT_API_HOST     = 'api.ovebot.ai';

    // Same scopes as the WordPress plugin, so grants match across platforms.
    const SCOPES = 'workspaces:read setup:widget:write setup:products:write setup:order-info:write kb:write';

    private $accountHost;
    private $apiHost;
    private $accessToken;

    public function __construct($accessToken = '', $accountHost = '', $apiHost = '') {
        $this->accessToken = (string)$accessToken;
        $this->accountHost = $accountHost !== '' ? $accountHost : self::DEFAULT_ACCOUNT_HOST;
        $this->apiHost     = $apiHost !== '' ? $apiHost : self::DEFAULT_API_HOST;
    }

    public function setAccessToken($accessToken) {
        $this->accessToken = (string)$accessToken;
        return $this;
    }

    // ── PKCE / authorization URL ─────────────────────────────────────────────

    // Random code_verifier. random_bytes/random_compat needs PHP 7+ (or the
    // random_compat polyfill); calling a function that doesn't exist at all is
    // an uncatchable fatal on PHP 5 (no Throwable, and there's nothing to
    // catch — the call never returns), so this must check function_exists()
    // rather than rely on try/catch.
    public static function generateVerifier() {
        return self::b64url(self::randomBytes(48));
    }

    public static function generateState() {
        return bin2hex(self::randomBytes(8));
    }

    private static function randomBytes($bytes) {
        if (function_exists('random_bytes')) {
            try {
                return random_bytes($bytes);
            } catch (\Exception $e) {
                // fall through to the weaker fallback below
            }
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $result = openssl_random_pseudo_bytes($bytes, $strong);
            if ($result !== false) {
                return $result;
            }
        }

        return hash('sha256', uniqid('ove', true) . microtime(true), true);
    }

    public function buildAuthUrl($siteDomain, $callbackUrl, $verifier, $state) {
        $challenge = self::b64url(hash('sha256', $verifier, true));

        return 'https://' . $this->accountHost . '/oauth/authorize?' . http_build_query(array(
            'site_domain'           => $siteDomain,
            'callback_url'          => $callbackUrl,
            'scopes'                => self::SCOPES,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'state'                 => $state,
        ));
    }

    // ── Token endpoints ──────────────────────────────────────────────────────

    // Returns the decoded token payload (access_token, refresh_token, workspace,
    // agent, …). Throws AuthException on failure — nothing to return without tokens.
    public function exchangeCode($code, $verifier) {
        return $this->tokenRequest(array(
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'code_verifier' => $verifier,
        ));
    }

    public function refreshToken($refreshToken) {
        return $this->tokenRequest(array(
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ));
    }

    private function tokenRequest(array $payload) {
        $result = $this->request(
            'POST',
            'https://' . $this->accountHost . '/oauth/token',
            array('Content-Type: application/json', 'Accept: application/json'),
            json_encode($payload)
        );

        if ($result['status'] !== 200 || empty($result['body']['access_token'])) {
            $message = isset($result['body']['error_description']) ? $result['body']['error_description'] : '';
            if ($message === '' && isset($result['body']['error'])) {
                $message = is_string($result['body']['error']) ? $result['body']['error'] : '';
            }
            if ($message === '') {
                $message = 'Token request failed (HTTP ' . $result['status'] . ').';
            }
            throw new AuthException($message, $result['status']);
        }

        return $result['body'];
    }

    // ── Authenticated API ────────────────────────────────────────────────────

    // One round trip against the API host with the current token. No refresh/retry
    // here — that's the orchestrator's job (so it can persist rotated tokens).
    public function apiRequest($method, $path, $body = null) {
        $headers = array(
            'Authorization: Bearer ' . $this->accessToken,
            'Accept: application/json',
        );

        $payload = null;
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $payload = json_encode($body);
        }

        return $this->request($method, 'https://' . $this->apiHost . $path, $headers, $payload);
    }

    // ── Low-level HTTP ───────────────────────────────────────────────────────

    private function request($method, $url, array $headers, $body = null) {
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new ConnectionException('Ovebot.ai connection error: ' . $error);
        }

        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decoded = json_decode($response, true);

        return array(
            'status' => $status,
            'body'   => is_array($decoded) ? $decoded : array(),
        );
    }

    private static function b64url($bin) {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }
}
