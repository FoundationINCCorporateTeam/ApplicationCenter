<?php
require_once __DIR__ . '/_auth.php';
session_start();
if(!isset($_SESSION['helpdesk_user'])) {
  header('Location: /helpdesk/dashboard.php');
  exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Chat Console</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold">Chat Console</h1>
      <a href="/helpdesk/dashboard.php" class="text-sm text-blue-600 underline">Back</a>
    </div>

    <div class="grid grid-cols-12 gap-4">
      <div class="col-span-4">
        <div class="bg-white p-4 rounded shadow h-[60vh] overflow-y-auto" id="sessions">
          Loading sessions...
        </div>
      </div>
      <div class="col-span-8">
        <div class="bg-white p-4 rounded shadow h-[60vh] flex flex-col">
          <div class="flex-1 overflow-y-auto p-2" id="chat-panel">Select a session to view messages</div>
          <div class="mt-2">
            <form id="agent-reply" class="flex space-x-2">
              <input id="agent-input" class="flex-1 border rounded px-3 py-2" placeholder="Reply as agent..." />
              <button class="bg-teal-500 text-white px-4 py-2 rounded">Send</button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </div>

  <script>
    // Basic client to show chat_sessions.json (via a lightweight API not provided in earlier files).
    // For this demo we'll fetch /data/chat_sessions.json directly - in a production deploy the webserver should restrict access to /data
    async function loadSessions(){
      try {
        const r = await fetch('/api/list_tickets.php'); // placeholder to avoid direct data leak
        const tickets = await r.json();
        document.getElementById('sessions').innerHTML = '<div class="text-sm text-gray-600">Use data/chat_sessions.json to manage sessions server-side.</div>';
      } catch(e){
        document.getElementById('sessions').innerHTML = '<div class="text-sm text-red-600">Unable to load sessions via API.</div>';
      }
    }
    loadSessions();

    // Agent reply: demo send to send_message.php with session_id input prompt
    document.getElementById('agent-reply').addEventListener('submit', async (e)=>{
      e.preventDefault();
      const text = document.getElementById('agent-input').value.trim();
      if(!text) return;
      const session_id = prompt('Reply to session_id:');
      if(!session_id) return;
      const res = await fetch('/api/send_message.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ session_id, sender: 'agent', text })
      });
      const json = await res.json();
      if(json.success) {
        alert('Sent');
        document.getElementById('agent-input').value = '';
      } else {
        alert('Error: ' + (json.error || 'unknown'));
      }
    });
  </script>
</body>
</html>