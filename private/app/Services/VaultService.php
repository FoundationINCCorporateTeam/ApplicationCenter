<?php
namespace App\Services;

/**
 * VaultService — TEST MODE with hard-coded Vault address & token
 *
 * WARNING: Token is embedded for testing only. Replace with AppRole or proper secrets handling in production.
 */

/* --- TEST / DEV hard-coded Vault config (do NOT commit to VCS) --- */
$TEST_VAULT_ADDR = 'https://secure.bulletproof.astroyds.com:8200';
$TEST_VAULT_AUTH_TOKEN = 'hvs.N5qMLSnBjwI4HaGjIsX8eObG';
$TEST_VAULT_TIMEOUT = 10;
/* ---------------------------------------------------------------- */

class VaultService
{
    protected string $addr;
    protected string $token;

    public function __construct()
    {
        $this->addr = $GLOBALS['TEST_VAULT_ADDR'] ?? 'https://secure.bulletproof.astroyds.com:8200';
        $this->token = $GLOBALS['TEST_VAULT_AUTH_TOKEN'] ?? '';
        if (empty($this->token)) {
            error_log('[VaultService] Vault token not set (TEST MODE).');
        }
    }

    public function storeSecret(string $path, array $data): bool
    {
        $url = rtrim($this->addr, '/') . '/v1/' . ltrim($path, '/');
        $payload = json_encode(['data' => $data]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Vault-Token: ' . $this->token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, intval($GLOBALS['TEST_VAULT_TIMEOUT'] ?? 10));
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $code >= 400) {
            throw new \Exception("Vault store failed: {$err} (HTTP {$code})");
        }
        return true;
    }

    public function getSecret(string $path): array
    {
        $url = rtrim($this->addr, '/') . '/v1/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Vault-Token: ' . $this->token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, intval($GLOBALS['TEST_VAULT_TIMEOUT'] ?? 10));
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $code >= 400) {
            throw new \Exception("Vault get failed: {$err} (HTTP {$code})");
        }
        $decoded = json_decode($resp, true);
        if (isset($decoded['data']['data'])) {
            return $decoded['data']['data'];
        }
        return [];
    }
}