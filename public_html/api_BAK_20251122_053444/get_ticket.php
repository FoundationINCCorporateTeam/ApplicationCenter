<?php
require_once __DIR__ . '/config.php';

// GET: ?ticket_id=...
$ticket_id = isset($_GET['ticket_id']) ? preg_replace('/[^a-zA-Z0-9\-\_]/','', $_GET['ticket_id']) : '';
if(!$ticket_id) json_error('ticket_id required', 400);

$tickets = read_json_file(TICKETS_FILE) ?: [];
if(!isset($tickets[$ticket_id])) json_error('Ticket not found', 404);

json_ok(['ticket' => $tickets[$ticket_id]]);