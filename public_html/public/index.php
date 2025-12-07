<?php
declare(strict_types=1);

// ---- Configuration ----
$APP_BASE = __DIR__ . '/../../private/app';

require_once $APP_BASE . '/Controllers/ApplicationController.php';
require_once $APP_BASE . '/Services/AstParser.php';
require_once $APP_BASE . '/Services/MistralShortAnswerGrader.php';
require_once $APP_BASE . '/Services/VaultService.php';
require_once $APP_BASE . '/Services/RobloxPromotionService.php';
require_once $APP_BASE . '/Services/SubmissionService.php';

use App\Controllers\ApplicationController;

// ---- Helpers ----
function isJsonRequest(): bool {
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) return true;
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($accept, 'application/json') !== false;
}

/**
 * Recursively search for 'model_response' keys in the response payload.
 */
function payloadContainsModelResponse(mixed $data): bool {
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            if ($k === 'model_response') return true;
            if (payloadContainsModelResponse($v)) return true;
        }
    } elseif (is_object($data)) {
        foreach (get_object_vars($data) as $k => $v) {
            if ($k === 'model_response') return true;
            if (payloadContainsModelResponse($v)) return true;
        }
    }
    return false;
}

function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    if (payloadContainsModelResponse($data)) {
        header('X-Model-Response-Included: 1');
    }

    $debug = getenv('DEBUG_ENV') === '1';
    $options = JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR;
    if ($debug) $options |= JSON_PRETTY_PRINT;

    $json = json_encode($data, $options);
    if ($json === false) {
        echo json_encode(['error' => 'Failed to encode response to JSON']);
    } else {
        echo $json;
    }
    exit;
}

function errorResponse(string $message, int $status = 500, ?Throwable $e = null): void {
    if ($e) {
        error_log(sprintf("[%s] %s in %s:%d\nStack: %s\n", date('c'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));
    } else {
        error_log(sprintf("[%s] %s", date('c'), $message));
    }

    $debug = getenv('DEBUG_ENV') === '1';
    $body = ['error' => $message];
    if ($debug && $e) {
        $body['exception'] = $e->getMessage();
        $body['trace'] = $e->getTrace();
    }
    jsonResponse($body, $status);
}

// Minimal CORS for development
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

// ---- Query params ----
$flow = $_GET['flow'] ?? '';
$action = $_GET['action'] ?? '';
$appId = $_GET['app_id'] ?? $_GET['id'] ?? '';

// Default actions
if ($flow === 'application' && $action === '') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') $action = 'get';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $action = 'save';
}

// Require appId for application flows
if ($flow === 'application' && in_array($action, ['get','save','submission']) && empty($appId)) {
    errorResponse('app_id required', 400);
}

// ---- Instantiate controller ----
try {
    $controller = new ApplicationController();
} catch (Throwable $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'could not find driver') !== false || stripos($msg, 'PDOException') !== false) {
        errorResponse('Server misconfiguration: PDO driver missing', 500, $e);
    }
    errorResponse('Server initialization failed', 500, $e);
}

// ---- Dispatch ----
try {
    if ($flow === 'application') {
        if ($action === 'get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->getApplication($appId);
            exit;
        }

        if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->saveApplication($appId);
            exit;
        }

        if ($action === 'submission' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Ensure controller includes 'model_response' for all short-answer questions
            $controller->postSubmission($appId);
            exit;
        }

        errorResponse('Unknown application action or method', 400);
    }

    if ($flow === 'vault') {
        if ($action === 'save-key' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (method_exists($controller, 'saveVaultKey')) {
                $controller->saveVaultKey();
                exit;
            } else {
                errorResponse('Vault save not implemented', 501);
            }
        }
        errorResponse('Unknown vault action or method', 400);
    }

    if (isJsonRequest()) {
        jsonResponse(['error' => 'Not found'], 404);
    } else {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>404 Not Found</title></head><body><h1>Page Not Found</h1></body></html>';
        exit;
    }
} catch (Throwable $e) {
    error_log('[UNHANDLED] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    errorResponse('Server error', 500, $e);
}
