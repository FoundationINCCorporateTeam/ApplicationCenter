<?php
// api/create_chat_session.php
// Creates (or returns) a chat session. Expects JSON POST body: { name, email }
// Returns JSON: { success:true, session_id: "...", existing: bool } or proper json_error on failure.

// Temporary development helpers (uncomment while debugging only)
// ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

// Include shared helpers - must be the helper file above
require_once __DIR__ . '/config.php';

// Read JSON payload
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    json_error('Invalid JSON payload', 400);
}

$name = isset($input['name']) ? trim($input['name']) : 'Guest';
$email = isset($input['email']) ? trim($input['email']) : '';

if ($name === '') $name = 'Guest';

// Load settings to check agent availability
$settings = load_settings();
if (empty($settings['agents_online'])) {
    // Agents offline — let widget fallback to ticket creation (use 503)
    json_error('No agents online', 503);
}

// Load or init chat index
$index = read_json_file(CHAT_INDEX);
if ($index === null) $index = [];

// If email provided, try to find an existing open session
$existingSession = null;
if ($email !== '') {
    foreach ($index as $sid => $meta) {
        if (isset($meta['email']) && $meta['email'] === $email && ($meta['status'] ?? 'open') === 'open') {
            $existingSession = $sid;
            break;
        }
    }
}

if ($existingSession) {
    json_ok(['session_id' => $existingSession, 'existing' => true]);
}

// Create the new session
$session_id = uuidv4();
$index[$session_id] = [
    'id' => $session_id,
    'name' => htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'email' => htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'created_at' => time(),
    'last_message_at' => time(),
    'status' => 'open'
];

// Save index
if (!write_json_file(CHAT_INDEX, $index)) {
    json_error('Unable to create session (write error)', 500);
}

// Create initial messages file with a welcome message
$welcome = [
    [
        'id' => uuidv4(),
        'sender' => 'agent',
        'text' => 'Hello ' . $index[$session_id]['name'] . ', thanks for contacting support!',
        'timestamp' => time()
    ]
];
$messages_file = MESSAGES_DIR . '/' . $session_id . '.json';
if (!write_json_file($messages_file, $welcome)) {
    // rollback index entry on failure
    $idx = read_json_file(CHAT_INDEX) ?: [];
    unset($idx[$session_id]);
    write_json_file(CHAT_INDEX, $idx);
    json_error('Unable to initialize messages', 500);
}

json_ok(['session_id' => $session_id, 'existing' => false]);