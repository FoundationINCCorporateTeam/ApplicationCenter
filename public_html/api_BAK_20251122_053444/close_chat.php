<?php
require_once __DIR__ . '/config.php';

// POST: session_id
$input = json_decode(file_get_contents('php://input'), true);
$session_id = isset($input['session_id']) ? preg_replace('/[^a-zA-Z0-9\-]/','', $input['session_id']) : '';
if(!$session_id) json_error('session_id required', 400);

$index = read_json_file(CHAT_INDEX) ?: [];
if(!isset($index[$session_id])) json_error('Session not found', 404);

$index[$session_id]['status'] = 'closed';
$index[$session_id]['closed_at'] = time();

if(!write_json_file(CHAT_INDEX, $index)) json_error('Failed to update session', 500);

json_ok(['session_id' => $session_id]);