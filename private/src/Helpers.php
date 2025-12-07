<?php
/**
 * Helper Functions
 * 
 * Common utility functions used throughout the application
 */

/**
 * Generate a unique ID
 * 
 * @return string Unique identifier
 */
function generateId() {
    return bin2hex(random_bytes(16));
}

/**
 * Sanitize input
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Return JSON response and exit
 * 
 * @param mixed $data Data to return
 * @param int $status HTTP status code
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Return error JSON response and exit
 * 
 * @param string $message Error message
 * @param int $status HTTP status code
 */
function jsonError($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}

/**
 * Load JSON file
 * 
 * @param string $path File path
 * @return mixed Decoded JSON data or null
 */
function loadJson($path) {
    if (!file_exists($path)) {
        return null;
    }
    
    $content = file_get_contents($path);
    return json_decode($content, true);
}

/**
 * Save JSON file
 * 
 * @param string $path File path
 * @param mixed $data Data to save
 * @return bool Success status
 */
function saveJson($path, $data) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Validate required parameters
 * 
 * @param array $params Parameters to check
 * @param array $required Required parameter names
 * @return bool|string True if valid, error message if not
 */
function validateRequired($params, $required) {
    foreach ($required as $field) {
        if (!isset($params[$field])) {
            return "Missing required field: $field";
        }

        $value = $params[$field];

        if (is_array($value)) {
            // For arrays (checkboxes), ensure it's not empty
            if (empty($value)) {
                return "Missing required field: $field";
            }
        } else {
            // For strings / single values
            if (trim($value) === '') {
                return "Missing required field: $field";
            }
        }
    }
    return true;
}

/**
 * CORS headers for cross-origin requests from Roblox
 */
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
