<?php
require_once __DIR__ . '/config.php';

/**
 * Create a ticket from widget (offline mode) or helpdesk form.
 * POST JSON:
 * - subject
 * - name
 * - email
 * - message
 *
 * Returns { success:true, ticket_id: 'id' }
 */

$input = json_decode(file_get_contents('php://input'), true);
$subject = isset($input['subject']) ? trim($input['subject']) : 'No subject';
$name = isset($input['name']) ? trim($input['name']) : 'Guest';
$email = isset($input['email']) ? trim($input['email']) : '';
$message = isset($input['message']) ? trim($input['message']) : '';

if($message === '') json_error('message is required', 400);

$tickets = read_json_file(TICKETS_FILE);
if($tickets === null) $tickets = [];

$ticket_id = uuidv4();
$ticket = [
    'id' => $ticket_id,
    'subject' => htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'name' => htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'email' => htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'status' => 'open',
    'priority' => 'normal',
    'created_at' => time(),
    'messages' => [
        [
            'id' => uuidv4(),
            'sender' => 'user',
            'text' => htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'timestamp' => time()
        ]
    ],
    'attachments' => []
];

// Basic file upload handling if present (multipart form)
// Note: if using JSON input, uploads won't be included. For multipart use existing $_FILES logic.
if(!empty($_FILES) && isset($_FILES['attachment'])) {
    $uploadsDir = DATA_DIR . '/uploads';
    if(!is_dir($uploadsDir)) @mkdir($uploadsDir, 0755, true);
    $file = $_FILES['attachment'];
    if($file['error'] === UPLOAD_ERR_OK) {
        $safe = sanitize_filename($file['name']);
        $dest = $uploadsDir . '/' . time() . '_' . $safe;
        if(move_uploaded_file($file['tmp_name'], $dest)) {
            $ticket['attachments'][] = basename($dest);
        }
    }
}

$tickets[$ticket_id] = $ticket;
if(!write_json_file(TICKETS_FILE, $tickets)) {
    json_error('Failed to save ticket', 500);
}

json_ok(['ticket_id' => $ticket_id]);