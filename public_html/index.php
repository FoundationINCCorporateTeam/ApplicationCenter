<?php
/**
 * Application Center - Main Router
 * 
 * Handles all requests via index.php?action=XYZ routing
 */

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors to avoid breaking JSON responses

// Load dependencies
require_once __DIR__ . '/../private/src/Env.php';
require_once __DIR__ . '/../private/src/Helpers.php';
require_once __DIR__ . '/../private/src/AstParser.php';
require_once __DIR__ . '/../private/src/AstSerializer.php';
require_once __DIR__ . '/../private/src/FeatherlessGrader.php';
require_once __DIR__ . '/../private/src/PromotionService.php';
require_once __DIR__ . '/../private/src/AppController.php';
require_once __DIR__ . '/../private/src/SubmissionController.php';

// PolarisPilot server-side wrapper for AI generation
require_once __DIR__ . '/../private/src/PolarisPilot.php';

// Load environment variables
Env::load(__DIR__ . '/../private/.env');

// Enable CORS for Roblox
setCorsHeaders();

// Get action from query string
$action = $_GET['action'] ?? 'home';

try {
    switch ($action) {
        case 'home':
            // Serve the builder UI
            serveBuilderUI();
            break;
            
        case 'createApp':
            handleCreateApp();
            break;
            
        case 'saveApp':
            handleSaveApp();
            break;
            
        case 'loadApp':
            handleLoadApp();
            break;
            
        case 'deleteApp':
            handleDeleteApp();
            break;
            
        case 'listApps':
            handleListApps();
            break;
            
        case 'getConfig':
            handleGetConfig();
            break;
            
        case 'submit':
            handleSubmit();
            break;
            
        case 'getSubmission':
            handleGetSubmission();
            break;
            
        case 'listSubmissions':
            handleListSubmissions();
            break;

        // Polaris Pilot: generate a form via AI
        case 'generateForm':
            handleGenerateForm();
            break;
            
        default:
            jsonError('Unknown action', 404);
    }
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}

/**
 * Serve the builder UI
 */
function serveBuilderUI() {
    $htmlFile = __DIR__ . '/builder.html';
    
    if (file_exists($htmlFile)) {
        readfile($htmlFile);
    } else {
        // Serve inline HTML if file doesn't exist yet
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Center Builder</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div id="app"></div>
    <script src="assets/js/builder.js"></script>
</body>
</html>';
    }
}

/**
 * Handle create application
 */
function handleCreateApp() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        jsonError('Invalid JSON data');
    }
    
    $controller = new AppController();
    $result = $controller->createApp($data);
    
    if (isset($result['error'])) {
        jsonError($result['error']);
    }
    
    jsonResponse($result);
}

/**
 * Handle save application
 */
function handleSaveApp() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        jsonError('Invalid JSON data');
    }
    
    $controller = new AppController();
    $result = $controller->saveApp($data);
    
    if (isset($result['error'])) {
        jsonError($result['error']);
    }
    
    jsonResponse($result);
}

/**
 * Handle load application
 */
function handleLoadApp() {
    $id = $_GET['id'] ?? '';
    
    if (!$id) {
        jsonError('Missing application ID');
    }
    
    $controller = new AppController();
    $result = $controller->loadApp($id);
    
    if (isset($result['error'])) {
        jsonError($result['error'], 404);
    }
    
    jsonResponse($result);
}

/**
 * Handle delete application
 */
function handleDeleteApp() {
    $id = $_GET['id'] ?? '';
    
    if (!$id) {
        jsonError('Missing application ID');
    }
    
    $controller = new AppController();
    $result = $controller->deleteApp($id);
    
    if (isset($result['error'])) {
        jsonError($result['error'], 404);
    }
    
    jsonResponse($result);
}

/**
 * Handle list applications
 */
function handleListApps() {
    $controller = new AppController();
    $result = $controller->listApps();
    
    jsonResponse($result);
}

/**
 * Handle get config for Roblox
 */
function handleGetConfig() {
    $id = $_GET['id'] ?? '';
    
    if (!$id) {
        jsonError('Missing application ID');
    }
    
    $controller = new AppController();
    $result = $controller->getConfig($id);
    
    if (isset($result['error'])) {
        jsonError($result['error'], 404);
    }
    
    jsonResponse($result);
}

/**
 * Handle submit application
 */
function handleSubmit() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        jsonError('Invalid JSON data');
    }
    
    $controller = new SubmissionController();
    $result = $controller->submit($data);
    
    if (isset($result['error'])) {
        jsonError($result['error']);
    }
    
    jsonResponse($result);
}

/**
 * Handle get submission
 */
function handleGetSubmission() {
    $id = $_GET['id'] ?? '';
    
    if (!$id) {
        jsonError('Missing submission ID');
    }
    
    $controller = new SubmissionController();
    $result = $controller->getSubmission($id);
    
    if (isset($result['error'])) {
        jsonError($result['error'], 404);
    }
    
    jsonResponse($result);
}

/**
 * Handle list submissions
 */
function handleListSubmissions() {
    $appId = $_GET['app_id'] ?? null;
    
    $controller = new SubmissionController();
    $result = $controller->listSubmissions($appId);
    
    jsonResponse($result);
}

/**
 * Handle Polaris Pilot form generation via AI
 *
 * Expects POST JSON body with keys:
 *  - name (optional)
 *  - description (optional)
 *  - group_id (optional)
 *  - rank (optional)
 *  - questions (optional, integer)
 *  - vibe (optional)
 *  - primary_color (optional)
 *  - secondary_color (optional)
 *  - instructions (optional)  // free-form prompt to guide generation
 *
 * Returns: { success: true, form: { app, style, questions }, raw: "<model raw output>" }
 */
function handleGenerateForm() {
    // Only accept POST for generation
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Invalid HTTP method, POST required', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        jsonError('Invalid JSON data');
    }

    // Basic rate-limiting / abuse prevention could go here (not implemented)
    try {
        $result = PolarisPilot::generateForm($data);
    } catch (Exception $e) {
        jsonError('PolarisPilot error: ' . $e->getMessage(), 500);
    }

    if (isset($result['error'])) {
        jsonError($result['error']);
    }

    // Ensure we have a form to return
    if (!isset($result['form']) || !is_array($result['form'])) {
        jsonError('PolarisPilot returned an unexpected result');
    }

    // Return the generated form (include raw model output if available for debugging)
    $response = [
        'success' => true,
        'form' => $result['form']
    ];
    if (isset($result['raw'])) $response['raw'] = $result['raw'];

    jsonResponse($response);
}