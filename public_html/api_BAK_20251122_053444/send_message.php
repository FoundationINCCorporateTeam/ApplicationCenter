<?php
require_once __DIR__ . '/config.php';

// Accepts JSON POST: session_id, sender, text
$input = json_decode(file_get_contents('php://input'), true);
$session_id = isset($input['session_id']) ? preg_replace('/[^a-zA-Z0-9\-]/','', $input['session_id']) : '';
$sender = isset($input['sender']) && $input['sender']==='agent' ? 'agent' : 'user';
$text = isset($input['text']) ? trim($input['text']) : '';

if(!$session_id || $text === '') {
    json_error('session_id and text are required', 400);
}

// Basic rate limit per-session (prevent spam)
if(!rate_limit_allowed($session_id, 1)) {
    json_error('Rate limit: too many messages', 429);
}

$messages_file = MESSAGES_DIR . '/' . $session_id . '.json';
$messages = read_json_file($messages_file);
if($messages === null) $messages = [];

// Compose message
$msg = [
    'id' => uuidv4(),
    'sender' => $sender,
    'text' => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'timestamp' => time()
];

$messages[] = $msg;

// Save with locking
if(!write_json_file($messages_file, $messages)) {
    json_error('Failed to append message', 500);
}

// Update index last_message_at
$index = read_json_file(CHAT_INDEX) ?: [];
if(isset($index[$session_id])) {
    $index[$session_id]['last_message_at'] = time();
    write_json_file(CHAT_INDEX, $index);
}

json_ok(['message' => $msg]);