<?php
require_once __DIR__ . '/_auth.php';
session_start();
if(!isset($_SESSION['helpdesk_user'])) {
  header('Location: /helpdesk/dashboard.php');
  exit;
}
$ticket_id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9\-\_]/','', $_GET['id']) : '';
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ticket View</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-4xl mx-auto">
  <a href="/helpdesk/dashboard.php" class="text-sm text-blue-600 underline">Back</a>
  <div id="ticket-area" class="bg-white p-4 rounded shadow mt-4">
    Loading ticket...
  </div>
</div>

<script>
  async function loadTicket(){
    if(!"<?php echo $ticket_id; ?>") {
      document.getElementById('ticket-area').textContent = 'No ticket specified';
      return;
    }
    const res = await fetch('/api/get_ticket.php?ticket_id=' + encodeURIComponent("<?php echo $ticket_id; ?>"));
    const json = await res.json();
    const area = document.getElementById('ticket-area');
    if(!json.success) { area.textContent = 'Error loading ticket'; return; }
    const t = json.ticket;
    let html = `<h2 class="text-lg font-semibold">${t.subject}</h2>
      <div class="text-sm text-gray-500">From: ${t.name} &lt;${t.email}&gt; • ${new Date(t.created_at*1000).toLocaleString()}</div>
      <div class="mt-4 space-y-3">`;
    for(const m of t.messages){
      html += `<div class="p-2 rounded border ${m.sender==='agent'?'bg-gray-50':'bg-gray-100'}"><div class="text-xs text-gray-500">${m.sender} • ${new Date(m.timestamp*1000).toLocaleString()}</div><div class="mt-1">${m.text}</div></div>`;
    }
    html += `</div>
    <form id="reply-form" class="mt-4">
      <textarea id="reply-text" class="w-full border rounded p-2" rows="4" placeholder="Write a reply..."></textarea>
      <div class="mt-2 text-right">
        <button type="submit" class="bg-teal-500 text-white px-4 py-2 rounded">Reply</button>
      </div>
    </form>`;
    area.innerHTML = html;

    document.getElementById('reply-form').addEventListener('submit', async (e)=>{
      e.preventDefault();
      const text = document.getElementById('reply-text').value.trim();
      if(!text) return;
      const r = await fetch('/api/reply_ticket.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ ticket_id: t.id, sender: 'agent', text, csrf: '<?php echo $_SESSION['csrf_token']; ?>' })
      });
      const jr = await r.json();
      if(jr.success) {
        alert('Reply saved');
        loadTicket();
      } else {
        alert('Error: ' + (jr.error || 'unknown'));
      }
    });
  }
  loadTicket();
</script>
</body>
</html>