<?php
require_once __DIR__ . '/_auth.php';
session_start();
if(!isset($_SESSION['helpdesk_user'])) {
  header('Location: /helpdesk/dashboard.php');
  exit;
}
$settings = json_decode(file_get_contents(__DIR__ . '/../data/settings.json'), true);
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class="bg-gray-100 p-6">
<div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
  <h1 class="text-lg font-semibold mb-4">Settings</h1>

  <form id="settings-form" class="space-y-4">
    <label class="block">
      <div class="text-sm">Site Name</div>
      <input name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" class="w-full border p-2 rounded"/>
    </label>
    <label class="block">
      <div class="text-sm">Widget Color (hex)</div>
      <input name="widget_color" value="<?php echo htmlspecialchars($settings['widget_color'] ?? '#0ea5a4'); ?>" class="w-full border p-2 rounded"/>
    </label>
    <label class="block">
      <div class="text-sm">Welcome Message</div>
      <input name="welcome_message" value="<?php echo htmlspecialchars($settings['welcome_message'] ?? ''); ?>" class="w-full border p-2 rounded"/>
    </label>
    <label class="flex items-center">
      <input type="checkbox" name="agents_online" <?php echo (!empty($settings['agents_online']) ? 'checked' : ''); ?> class="mr-2" />
      Agents Online
    </label>

    <div class="text-right">
      <button class="bg-teal-500 text-white px-4 py-2 rounded">Save</button>
    </div>
  </form>
</div>

<script>
  document.getElementById('settings-form').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const obj = {};
    for(const [k,v] of fd.entries()){
      obj[k] = v;
    }
    obj['agents_online'] = e.target.elements['agents_online'].checked ? true : false;

    try {
      const r = await fetch('/api/save_settings.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ settings: obj })
      });
      // Defensive: if server returned 500 with empty body, attempting res.json() will throw
      const text = await r.text();
      if (!text) {
        // Empty response — treat as error
        alert('Server returned empty response. Check server logs.');
        return;
      }
      const j = JSON.parse(text);
      if (j.success) alert('Saved'); else alert('Error: ' + (j.error || 'Unknown'));
    } catch (err) {
      console.error('Save settings error', err);
      alert('Unable to save settings. Check server logs for details.');
    }
  });
</script>
</body></html>