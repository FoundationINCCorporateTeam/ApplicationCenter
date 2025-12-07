<?php
namespace App\Services;

/**
 * RobloxPromotionService — TEST MODE with hard-coded config
 *
 * WARNING: For testing only — API keys are retrieved from VaultService which uses hard-coded Vault token in test files.
 */

/* --- TEST / DEV defaults (do NOT commit to VCS) --- */
$TEST_DEFAULT_GROUP_ID = 13583995;
$TEST_DEFAULT_MEMBERSHIP_ID = 4610775570;
$TEST_DEFAULT_ROLE_ID = 218;
/* ---------------------------------------------------------------- */

class RobloxPromotionService
{
    protected VaultService $vault;

    public function __construct(VaultService $vault)
    {
        $this->vault = $vault;
    }

    /**
     * Promote a user in a group to the specified role string (e.g., "groups/7/roles/99513316")
     */
    public function promoteUsingCreatorKey(string $creatorId, int $groupId, int $membershipId, string $rolePath): array
    {
        if (is_numeric($rolePath)) {
            $roleId = intval($rolePath);
            $rolePath = "groups/{$groupId}/roles/{$roleId}";
        }

        $vaultPath = 'secret/data/roblox_keys/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $creatorId);
        $secret = $this->vault->getSecret($vaultPath);
        if (!isset($secret['api_key'])) {
            throw new \Exception('Creator API key not found in Vault');
        }
        $apiKey = $secret['api_key'];

        $url = "https://apis.roblox.com/cloud/v2/groups/{$groupId}/memberships/{$membershipId}";
        $payload = json_encode(['role' => $rolePath]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, intval(10));

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            throw new \Exception("Roblox API error: " . $err);
        }
        if ($code >= 400) {
            throw new \Exception("Roblox API returned HTTP {$code}: {$resp}");
        }

        $decoded = json_decode($resp, true) ?: ['raw' => $resp];
        return $decoded;
    }

    /**
     * Promote using default values when inputs are missing.
     */
    public function promoteUsingDefaultsIfPossible(string $creatorId, ?int $groupId = null, ?int $membershipId = null, ?int $roleId = null): array
    {
        global $TEST_DEFAULT_GROUP_ID, $TEST_DEFAULT_MEMBERSHIP_ID, $TEST_DEFAULT_ROLE_ID;
        $groupId = $groupId ?? (int)($TEST_DEFAULT_GROUP_ID);
        $membershipId = $membershipId ?? (int)($TEST_DEFAULT_MEMBERSHIP_ID);
        $roleId = $roleId ?? (int)($TEST_DEFAULT_ROLE_ID);

        $rolePath = "groups/{$groupId}/roles/{$roleId}";
        return $this->promoteUsingCreatorKey($creatorId, $groupId, $membershipId, $rolePath);
    }
}