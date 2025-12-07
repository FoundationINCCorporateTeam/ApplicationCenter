<?php
// api/config.php
// Shared helpers for all API endpoints. This file SHOULD NOT act as a request handler.
// It must be required_once by other API files to provide functions like json_error(), read_json_file(), etc.

header('Content-Type: application/json; charset=utf-8');

// Paths - adjust if your repository layout differs
define('BASE_DIR', dirname(__DIR__));           // project root (one level up from api/)
define('DATA_DIR', BASE_DIR . '/data');
define('MESSAGES_DIR', DATA_DIR . '/messages');
define('CHAT_INDEX', DATA_DIR . '/chat_sessions.json');
define('TICKETS_FILE', DATA_DIR . '/tickets.json');
define('USERS_FILE', DATA_DIR . '/users.json');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');

// Ensure messages dir exists (best effort)
if (!is_dir(MESSAGES_DIR)) {
    @mkdir(MESSAGES_DIR, 0755, true);
}

// ---------------------------
// JSON response helpers
// ---------------------------
function json_ok($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}
function json_error($msg = 'Error', $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ---------------------------
// File-based JSON helpers
// ---------------------------
function read_json_file($path) {
    if (!file_exists($path)) return null;
    $txt = @file_get_contents($path);
    if ($txt === false) return null;
    $data = json_decode($txt, true);
    return is_array($data) ? $data : null;
}

function write_json_file($path, $data) {
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $fp = @fopen($tmp, 'wb');
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
    if (false === fwrite($fp, $json)) { flock($fp, LOCK_UN); fclose($fp); @unlink($tmp); return false; }
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if (!rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}

// ---------------------------
// Utilities
// ---------------------------
function uuidv4() {
    // Requires openssl_random_pseudo_bytes enabled
    $data = openssl_random_pseudo_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function sanitize_filename($name) {
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    return substr($name, 0, 200);
}

// ---------------------------
// Basic rate limiting helpers (file-based)
// ---------------------------
function rate_limit_allowed($session_id, $min_seconds = 1) {
    $index = read_json_file(CHAT_INDEX) ?: [];
    if (!isset($index[$session_id])) return true;
    $last = isset($index[$session_id]['last_message_at']) ? intval($index[$session_id]['last_message_at']) : 0;
    return (time() - $last) >= $min_seconds;
}
function update_last_message_time($session_id) {
    $index = read_json_file(CHAT_INDEX) ?: [];
    if (!isset($index[$session_id])) return false;
    $index[$session_id]['last_message_at'] = time();
    return write_json_file(CHAT_INDEX, $index);
}

// ---------------------------
// Settings loader with defaults
// ---------------------------
function load_settings() {
    $s = read_json_file(SETTINGS_FILE);
    if (!is_array($s)) {
        // default settings for a fresh install
        $s = [
            'agents_online' => true,
            'site_name' => 'Helpdesk Demo',
            'widget_color' => '#0ea5a4',
            'welcome_message' => 'Hi! How can we help you today?'
        ];
        // Try to save defaults if possible
        @write_json_file(SETTINGS_FILE, $s);
    }
    return $s;
}

// ---------------------------
// Validate JSON-like structures
// ---------------------------
function is_valid_json_structure($data) {
    return is_array($data) || is_object($data);
}
