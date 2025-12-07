<?php
require_once __DIR__ . '/config.php';

// Simple list for agent UI - returns all tickets
$tickets = read_json_file(TICKETS_FILE);
if($tickets === null) $tickets = [];

json_ok(['tickets' => array_values($tickets)]);