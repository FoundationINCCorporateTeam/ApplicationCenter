<?php
// api/save_settings.php
// Small API to save settings. Requires the shared helpers.
require_once __DIR__ . '/config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

// Read JSON payload
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['settings'])) {
    json_error('Invalid payload', 400);
}

// Allowed keys and merge
$allowed = ['site_name', 'widget_color', 'welcome_message', 'agents_online'];
$current = read_json_file(SETTINGS_FILE) ?: [];

foreach ($allowed as $key) {
    if (array_key_exists($key, $input['settings'])) {
        if ($key === 'agents_online') {
            $current[$key] = !empty($input['settings'][$key]) && ($input['settings'][$key] === true || $input['settings'][$key] === 'true' || $input['settings'][$key] === '1' || $input['settings'][$key] === 1);
        } else {
            $current[$key] = trim((string)$input['settings'][$key]);
        }
    }
}

// Persist
if (!write_json_file(SETTINGS_FILE, $current)) {
    json_error('Unable to save settings', 500);
}

json_ok(['settings' => $current]);