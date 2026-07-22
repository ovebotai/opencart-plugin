<?php

namespace Ovebotai;

use Ovebotai\Exceptions\ApiException;
use Ovebotai\Exceptions\AuthException;
use Ovebotai\Exceptions\ConnectionException;

// Thin transport layer for Ovebot.ai — pure HTTP with no knowledge of where
// tokens are stored or how they're persisted. It holds one access token in
// memory (for the duration of a request) and speaks two hosts:
//   - account host: the OAuth authorize/token endpoints
//   - api host:     the authenticated /v1/* API
// Token *lifecycle* (proactive refresh, rotation, persistence to the setting
// table) lives one level up in the Ovebotai orchestrator, mirroring how the
// Typesense library keeps its Client dumb and its Model smart.
class Client {
    const DEFAULT_ACCOUNT_HOST = 'account.ovebot.ai';
    const DEFAULT_API_HOST     = 'api.ovebot.ai';

    // Same scope set the WordPress plugin requests, so a workspace connected
    // from either platform ends up with identical grants.
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

    // Cryptographically random code_verifier. random_bytes needs PHP 7+; the
    // fallback keeps this from fataling on an older OpenCart 2.3 host still on
    // PHP 5.6 (the connect step just gets a weaker verifier there).
    public static function generateVerifier() {
        try {
            return self::b64url(random_bytes(48));
        } catch (\Exception $e) {
            return self::b64url(hash('sha256', uniqid('ove', true) . microtime(true), true));
        } catch (\Throwable $e) {
            return self::b64url(hash('sha256', uniqid('ove', true) . microtime(true), true));
        }
    }

    public static function generateState() {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Exception $e) {
            return substr(md5(uniqid('ove', true)), 0, 16);
        } catch (\Throwable $e) {
            return substr(md5(uniqid('ove', true)), 0, 16);
        }
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

    // Returns the decoded token payload (access_token, refresh_token,
    // expires_in, workspace, agent, …). Throws AuthException on any failure —
    // the caller can't proceed without tokens, so there's nothing to return.
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

    // Single round trip against the API host using whatever access token this
    // client currently holds. Returns array('status' => int, 'body' => array)
    // — no refresh/retry here; that's the orchestrator's job so it can persist
    // rotated tokens.
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

    // Lightweight connectivity/credentials probe used both by the settings
    // "test connection" affordance and internally. Returns true when the token
    // is accepted, false when it's rejected (401/403); a transport failure
    // still throws, because "couldn't reach Ovebot.ai" is not the same answer
    // as "your token is invalid".
    public function test() {
        $result = $this->apiRequest('GET', '/v1/integration/status');

        if ($result['status'] >= 200 && $result['status'] < 300) {
            return true;
        }

        if ($result['status'] === 401 || $result['status'] === 403) {
            return false;
        }

        throw new ApiException('Unexpected response from Ovebot.ai (HTTP ' . $result['status'] . ').', $result['status']);
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
