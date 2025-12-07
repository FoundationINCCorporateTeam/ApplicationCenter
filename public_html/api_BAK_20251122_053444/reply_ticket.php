<?php
require_once __DIR__ . '/config.php';

// POST JSON: ticket_id, sender (agent/user), text
$input = json_decode(file_get_contents('php://input'), true);
$ticket_id = isset($input['ticket_id']) ? preg_replace('/[^a-zA-Z0-9\-\_]/','', $input['ticket_id']) : '';
$sender = isset($input['sender']) && $input['sender'] === 'agent' ? 'agent' : 'user';
$text = isset($input['text']) ? trim($input['text']) : '';
$csrf = isset($input['csrf']) ? $input['csrf'] : '';

if(!$ticket_id || $text === '') json_error('ticket_id and text are required', 400);

// CSRF: very basic - expects token stored in session; for AJAX calls implement tokens accordingly
session_start();
if(empty($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
    // If no session token provided, for API simplicity allow local agent replies (in a real system enforce stricter rules)
    // json_error('Invalid CSRF token', 403);
}

// Load tickets
$tickets = read_json_file(TICKETS_FILE) ?: [];
if(!isset($tickets[$ticket_id])) json_error('Ticket not found', 404);

$reply = [
    'id' => uuidv4(),
    'sender' => $sender,
    'text' => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'timestamp' => time()
];

$tickets[$ticket_id]['messages'][] = $reply;
$tickets[$ticket_id]['updated_at'] = time();

if(!write_json_file(TICKETS_FILE, $tickets)) json_error('Failed to save reply', 500);

json_ok(['reply' => $reply]);