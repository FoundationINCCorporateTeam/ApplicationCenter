<?php
require_once __DIR__ . '/config.php';

/**
 * Long-polling endpoint:
 * GET params:
 * - session_id (required)
 * - since (optional): unix timestamp, return messages with timestamp > since
 *
 * Behavior:
 * - Poll for up to 25 seconds (server-side wait)
 * - Sleep 1s between checks if no new messages
 * - Return JSON: { success:true, messages: [ ... ] }
 */

$session_id = isset($_GET['session_id']) ? preg_replace('/[^a-zA-Z0-9\-]/','', $_GET['session_id']) : '';
$since = isset($_GET['since']) ? intval($_GET['since']) : 0;

if(!$session_id) json_error('session_id required', 400);

$messages_file = MESSAGES_DIR . '/' . $session_id . '.json';
$timeout = 25; // seconds
$start = time();
$found = [];

// Loop until timeout or messages found
while(true) {
    clearstatcache();
    $messages = read_json_file($messages_file);
    if(is_array($messages)) {
        $new = [];
        foreach($messages as $m){
            if(isset($m['timestamp']) && $m['timestamp'] > $since) {
                $new[] = $m;
            }
        }
        if(count($new) > 0) {
            // Return new messages
            json_ok(['messages' => $new]);
        }
    }
    // If timeout reached, return empty array (client will re-poll)
    if(time() - $start >= $timeout) {
        json_ok(['messages' => []]);
    }
    // Sleep a short while to reduce CPU usage
    sleep(1);
}