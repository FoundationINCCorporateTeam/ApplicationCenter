<?php
class PromotionService {
    private $apiKey;

    public function __construct() {
        $this->apiKey = Env::get('ROBLOX_API_KEY');
        if (!$this->apiKey) {
            throw new Exception('ROBLOX_API_KEY not configured');
        }
    }

    /**
     * Promote a user to a target rank (0-255) in the group
     */
    public function promoteUser($groupId, $userId, $targetRank) {
        $membership = $this->getMembership($groupId, $userId);
        if (!$membership || !isset($membership['path'])) {
            return [
                'success' => false,
                'message' => 'User is not a member of the group'
            ];
        }

        $rolesMap = $this->getRolesMap($groupId);
        if (!isset($rolesMap[$targetRank])) {
            return [
                'success' => false,
                'message' => "Target rank {$targetRank} does not exist in the group"
            ];
        }

        $targetRoleId = $rolesMap[$targetRank];

        $result = $this->updateMembershipRole($groupId, $membership['path'], $targetRoleId);

        error_log("[Promotion Debug] User {$userId} in group {$groupId} attempting role {$targetRoleId} (rank {$targetRank})");
        error_log("[Promotion Debug] API result: " . json_encode($result));

        if (!$result['success'] && strpos($result['message'], 'Cannot change the role') !== false) {
            $result['success'] = true;
            $result['message'] = 'User already has the target role';
        }

        return $result;
    }

    /**
     * Get membership info for a user using the EXACT Roblox filter format
     */
    public function getMembership($groupId, $userId) {

        // MUST be: user=='users/123'
        $filterRaw = "user=='users/{$userId}'";

        // URL encode full expression
        $filterEncoded = urlencode($filterRaw);

        $url = "https://apis.roblox.com/cloud/v2/groups/{$groupId}/memberships?maxPageSize=1&filter={$filterEncoded}";

        error_log("[Promotion Debug] getMembership URL: {$url}");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['x-api-key: ' . $this->apiKey],
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("[Promotion Debug] getMembership curl error: {$curlError}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("[Promotion Debug] getMembership HTTP {$httpCode}: {$response}");
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['groupMemberships'][0])) return null;

        $membership = $data['groupMemberships'][0];

        return [
            'path' => $membership['path'] ?? null,
            'role' => $membership['role'] ?? null
        ];
    }

    private function getRolesMap($groupId) {
        $url = "https://groups.roblox.com/v1/groups/{$groupId}/roles";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $map = [];
        if (isset($data['roles']) && is_array($data['roles'])) {
            foreach ($data['roles'] as $role) {
                $map[$role['rank']] = $role['id'];
            }
        }

        error_log("[Promotion Debug] Roles map for group {$groupId}: " . json_encode($map));
        return $map;
    }

    private function updateMembershipRole($groupId, $membershipPath, $targetRoleId) {
        $parts = explode('/', $membershipPath);
        $membershipId = end($parts);

        $url = "https://apis.roblox.com/cloud/v2/groups/{$groupId}/memberships/{$membershipId}";
        $payload = ['role' => "groups/{$groupId}/roles/{$targetRoleId}"];

        error_log("[Promotion Debug] PATCH to {$url}");
        error_log("[Promotion Debug] Payload: " . json_encode($payload));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $success = ($httpCode === 200);
        $respData = json_decode($response, true);

        return [
            'success' => $success,
            'message' => $success ? 'User promoted successfully' : "Promotion failed: HTTP {$httpCode} - " . ($respData['message'] ?? $response),
            'raw_response' => $response
        ];
    }
}

