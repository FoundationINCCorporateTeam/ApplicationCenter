<?php
namespace App\Controllers;

use App\Services\AstParser;
use App\Services\MistralShortAnswerGrader;
use App\Services\VaultService;
use App\Services\RobloxPromotionService;
use App\Services\SubmissionService;

/**
 * Controller for Application endpoints.
 * - GET /api/application/{app_id}
 * - POST /api/application/{app_id}/submission
 * - POST /api/application/{app_id}/save
 * - POST /api/vault/save-key
 *
 * Notes:
 * - Creator endpoints should be protected by authentication (placeholder checks included).
 * - Roblox calls should be authenticated/authorized via an app token or simple HMAC signature in production.
 */
class ApplicationController
{
    protected string $storageDir;

    public function __construct()
    {
        $this->storageDir = __DIR__ . '/../../storage/apps';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0750, true);
        }
    }

    // GET: returns raw .astappcnt or JSON parsed form if ?format=json or Accept: application/json
    public function getApplication(string $appId)
    {
        $file = $this->storageDir . '/' . basename($appId) . '.astappcnt';
        if (!file_exists($file)) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Application not found']);
            return;
        }

        $content = file_get_contents($file);
        $acceptJson = isset($_GET['format']) && $_GET['format'] === 'json';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

        if ($acceptJson || str_contains($acceptHeader, 'application/json')) {
            $parser = new AstParser();
            $json = $parser->parse($content);
            header('Content-Type: application/json');
            echo json_encode($json, JSON_PRETTY_PRINT);
            return;
        }

        // Default: return raw text
        header('Content-Type: text/plain');
        echo $content;
    }

    // POST: Roblox submits answers
    public function postSubmission(string $appId)
    {
        // In production: verify an app token / signature to allow this call from authorized Roblox games
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!$data) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        // Basic required fields
        $appId = basename($appId);
        $appFile = $this->storageDir . '/' . $appId . '.astappcnt';
        if (!file_exists($appFile)) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Application not found']);
            return;
        }

        $parser = new AstParser();
        $config = $parser->parse(file_get_contents($appFile));

        // Submission service handles grading, DB storage, and promotion
        $mistral = new MistralShortAnswerGrader();
        $vault = new VaultService();
        $promotion = new RobloxPromotionService($vault);
        $submissionService = new SubmissionService($mistral, $promotion);

        try {
            $result = $submissionService->handleSubmission($config, $data);
            header('Content-Type: application/json');
            echo json_encode($result);
        } catch (\Exception $e) {
            error_log('Submission handling error: ' . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Server error']);
        }
    }

    // POST: Save application from builder (creator)
    public function saveApplication(string $appId)
    {
        // Basic creator auth placeholder
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$this->authorizeCreator($auth)) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!$data || !isset($data['ast_text'])) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid payload']);
            return;
        }

        // Server-side validation: parse the AST and validate constraints (max 3 short-answer, lengths)
        $parser = new AstParser();
        try {
            $parsed = $parser->parse($data['ast_text']);
            // validate short answer counts
            $shortCount = 0;
            foreach ($parsed['questions'] ?? [] as $q) {
                if ($q['type'] === 'short_answer') $shortCount++;
                if ($q['type'] === 'short_answer' && (!isset($q['max_length']) || $q['max_length'] > 300)) {
                    throw new \Exception('Short answer questions must have max_length <= 300');
                }
            }
            if ($shortCount > 3) {
                throw new \Exception('Maximum of 3 short answer questions allowed');
            }

            // Save raw AST as file
            $appFile = $this->storageDir . '/' . basename($appId) . '.astappcnt';
            file_put_contents($appFile, $data['ast_text'], LOCK_EX);

            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
        } catch (\Exception $e) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // POST: Save creator's Roblox API key into Vault
    public function saveVaultKey()
    {
        // Creator authentication (placeholder)
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$this->authorizeCreator($auth)) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!$data || !isset($data['creator_id']) || !isset($data['api_key'])) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid payload']);
            return;
        }

        $vault = new VaultService();
        $path = 'secret/data/roblox_keys/' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['creator_id']);
        try {
            $vault->storeSecret($path, ['api_key' => $data['api_key']]);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'message' => 'Key stored securely in Vault']);
        } catch (\Exception $e) {
            error_log('Vault store error: ' . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Failed to store key']);
        }
    }

    protected function authorizeCreator(string $authHeader): bool
    {
        // Placeholder: In production, check session/cookie or JWT. Here we accept "Bearer creator-demo-token"
        if (!$authHeader) return false;
        if (preg_match('/Bearer\s+(.+)/', $authHeader, $m)) {
            $token = $m[1];
            return $token === 'creator-demo-token';
        }
        return false;
    }
}