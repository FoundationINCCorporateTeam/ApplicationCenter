// assets/js/builder.ai.js
// Lightweight bindings for Polaris widget: send, suggest, import preview.
// This variant delegates generation to window.appBuilder._sendPolarisPrompt (if present).
// Falls back to an internal fetch-based implementation only when the builder isn't available.
//
// Behavior changes requested:
// - If another script handles sending/generation (e.g. AppBuilder._sendPolarisPrompt), use that.
// - Keep UI helpers (appendChat, renderImportControls, prefillFromBuilder) for compatibility.

(function () {
  if (window.__builderAiInit) return;
  window.__builderAiInit = true;

  const $ = id => document.getElementById(id);

  function appendChat(text, who = 'bot') {
    const area = $('polaris-chat');
    if (!area) return;
    const el = document.createElement('div');
    el.className = 'polaris-chat-msg ' + (who === 'bot' ? 'polaris-chat-bot' : 'polaris-chat-user');

    // meta / header
    const meta = document.createElement('div');
    meta.className = 'meta';
    const whoLabel = document.createElement('div');
    whoLabel.textContent = who === 'bot' ? 'Polaris Pilot' : 'You';
    whoLabel.style.fontWeight = '700';
    const timeLabel = document.createElement('div');
    timeLabel.textContent = new Date().toLocaleTimeString();
    timeLabel.style.fontSize = '0.85rem';
    timeLabel.style.color = 'var(--text-muted)';
    meta.appendChild(whoLabel);
    meta.appendChild(timeLabel);

    // body
    const body = document.createElement('div');
    body.className = 'body';
    body.style.whiteSpace = 'pre-wrap';
    body.textContent = text;

    el.appendChild(meta);
    el.appendChild(body);
    area.appendChild(el);
    area.scrollTop = area.scrollHeight;
    return el;
  }

  // Internal fetch-based fallback implementation (kept for environments where AppBuilder isn't loaded)
  async function internalSendPrompt() {
    const sendBtn = $('polaris-send-prompt');
    if (sendBtn) { sendBtn.disabled = true; sendBtn.dataset.busy = '1'; }
    const payload = {
      name: $('polaris-app-name')?.value?.trim() || '',
      description: $('polaris-app-description')?.value?.trim() || '',
      group_id: $('polaris-group-id')?.value?.trim() || '',
      rank: $('polaris-target-rank')?.value?.trim() || '',
      questions: parseInt($('polaris-questions-count')?.value || '6', 10) || 6,
      vibe: $('polaris-vibe')?.value?.trim() || '',
      primary_color: $('polaris-primary-color')?.value || '#ff4b6e',
      secondary_color: $('polaris-secondary-color')?.value || '#1f2933',
      instructions: $('polaris-prompt')?.value?.trim() || ''
    };

    if (!payload.instructions && !payload.name) {
      appendChat('Provide a prompt or application name', 'bot');
      if (sendBtn) { sendBtn.disabled = false; delete sendBtn.dataset.busy; }
      return;
    }

    appendChat(payload.instructions || `Generate a ${payload.questions}-question application named "${payload.name}"`, 'user');
    $('polaris-gen-status') && ($('polaris-gen-status').textContent = 'Status: Generating...');

    try {
      const res = await fetch('index.php?action=generateForm', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
      });
      if (!res.ok) {
        appendChat('Server error: ' + res.status, 'bot');
        $('polaris-gen-status') && ($('polaris-gen-status').textContent = 'Status: Server error');
        return;
      }
      const data = await res.json().catch(()=>null);
      if (!data) { appendChat('Invalid server response', 'bot'); return; }
      if (!data.success) { appendChat('Polaris error: ' + (data.error || 'unknown'), 'bot'); return; }

      const form = data.form;
      // prefer rendering inline via AppBuilder if available
      if (window.appBuilder && typeof window.appBuilder._appendPolarisGeneratedFormInChat === 'function') {
        window.appBuilder._appendPolarisGeneratedFormInChat(form);
      } else {
        appendChat('Generated preview ready — import or merge into the editor.', 'bot');
        const preview = $('polaris-gen-preview');
        if (preview) { preview.textContent = JSON.stringify(form, null, 2); preview.classList.remove('hidden'); preview.setAttribute('aria-hidden','false'); }
        renderImportControls(form);
      }
      $('polaris-gen-status') && ($('polaris-gen-status').textContent = 'Status: Preview ready');
    } catch (err) {
      console.error('polaris send error', err);
      appendChat('Network error: ' + (err.message || err), 'bot');
      $('polaris-gen-status') && ($('polaris-gen-status').textContent = 'Status: Network error');
    } finally {
      if (sendBtn) { sendBtn.disabled = false; delete sendBtn.dataset.busy; }
    }
  }

  // Public send entry point: prefer delegating to AppBuilder if it implements the generation flow.
  async function sendPrompt() {
    // If the main builder is present and exposes the canonical generator, use it.
    try {
      if (window.appBuilder && typeof window.appBuilder._sendPolarisPrompt === 'function') {
        // call builder's method; it manages its own busy state and chat rendering.
        await window.appBuilder._sendPolarisPrompt();
        return;
      }
    } catch (err) {
      // If delegate fails, log and fall back to internal.
      console.warn('Delegated _sendPolarisPrompt failed, falling back to internal send', err);
    }

    // otherwise use the internal fetch implementation
    await internalSendPrompt();
  }

  function renderImportControls(form) {
    // Prefer builder-rendered import controls if available
    if (window.appBuilder && typeof window.appBuilder._renderPolarisImportControls === 'function') {
      try {
        window.appBuilder._renderPolarisImportControls(form);
        return;
      } catch (e) {
        console.warn('appBuilder._renderPolarisImportControls failed, falling back', e);
      }
    }

    const ctr = $('polaris-gen-controls');
    if (!ctr) return;
    ctr.innerHTML = '';
    const importBtn = document.createElement('button'); importBtn.className='btn btn-primary'; importBtn.textContent='Import (Replace)';
    importBtn.addEventListener('click', () => {
      if (window.appBuilder && typeof window.appBuilder.importGeneratedForm === 'function') {
        window.appBuilder.importGeneratedForm(form, { replace: true });
        // close widget (toggle)
        document.getElementById('polaris-toggle-btn')?.click();
      } else appendChat('Builder not loaded to import', 'bot');
    });
    const mergeBtn = document.createElement('button'); mergeBtn.className='btn btn-secondary'; mergeBtn.textContent='Merge Questions';
    mergeBtn.addEventListener('click', () => {
      if (window.appBuilder && typeof window.appBuilder.importGeneratedForm === 'function') {
        window.appBuilder.importGeneratedForm(form, { replace: false });
        document.getElementById('polaris-toggle-btn')?.click();
      } else appendChat('Builder not loaded to merge', 'bot');
    });
    const copyBtn = document.createElement('button'); copyBtn.className='btn btn-ghost'; copyBtn.textContent='Copy JSON';
    copyBtn.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(JSON.stringify(form, null, 2)); appendChat('Copied JSON to clipboard', 'bot'); } catch { appendChat('Unable to copy', 'bot'); }
    });
    ctr.appendChild(importBtn); ctr.appendChild(mergeBtn); ctr.appendChild(copyBtn);
  }

  function suggestStructure() {
    const prompt = 'Suggest a concise 6-question structure for this application (type, short prompt, points).';
    if ($('polaris-prompt')) $('polaris-prompt').value = prompt;

    // Delegate if available
    if (window.appBuilder && typeof window.appBuilder._sendPolarisPrompt === 'function') {
      // pre-fill then delegate
      return window.appBuilder._sendPolarisPrompt();
    }
    return sendPrompt();
  }

  function prefillFromBuilder() {
    if (!window.appBuilder) return;
    try {
      const app = window.appBuilder.currentApp || {};
      if ($('polaris-app-name')) $('polaris-app-name').value = app.name || '';
      if ($('polaris-app-description')) $('polaris-app-description').value = app.description || '';
      if ($('polaris-group-id')) $('polaris-group-id').value = app.group_id || '';
      if ($('polaris-target-rank')) {
        const rank = typeof app.target_role === 'string' ? (app.target_role.match(/roles\/(\d+)$/) || [null,''])[1] : '';
        $('polaris-target-rank').value = rank || '';
      }
      if ($('polaris-primary-color')) $('polaris-primary-color').value = app.style?.primary_color || '#ff4b6e';
      if ($('polaris-secondary-color')) $('polaris-secondary-color').value = app.style?.secondary_color || '#1f2933';
      $('polaris-gen-status') && ($('polaris-gen-status').textContent = 'Status: ready');
      const preview = $('polaris-gen-preview'); if (preview) { preview.classList.add('hidden'); preview.textContent=''; }
      const chat = $('polaris-chat'); if (chat) chat.innerHTML='';
    } catch (e) { console.warn('prefill error', e); }
  }

  function bind() {
    // send button: prefer delegating to appBuilder; otherwise use local handler
    const sendBtn = $('polaris-send-prompt');
    if (sendBtn) {
      sendBtn.addEventListener('click', (ev) => {
        ev.preventDefault();
        // call top-level send which will delegate
        sendPrompt();
      });
    }

    $('polaris-suggest-structure')?.addEventListener('click', (ev) => { ev.preventDefault(); suggestStructure(); });

    $('polaris-import-btn')?.addEventListener('click', () => {
      // If builder provides import UI, let it handle; otherwise fallback to parsing the raw preview JSON
      if (window.appBuilder && typeof window.appBuilder.importGeneratedForm === 'function') {
        const preview = $('polaris-gen-preview');
        if (!preview || preview.classList.contains('hidden') || !preview.textContent) { appendChat('No generated JSON to import', 'bot'); return; }
        try {
          const form = JSON.parse(preview.textContent);
          window.appBuilder.importGeneratedForm(form, { replace: true });
          document.getElementById('polaris-toggle-btn')?.click();
        } catch (e) { appendChat('Invalid preview JSON', 'bot'); }
        return;
      }

      // fallback: use global preview
      const preview = $('polaris-gen-preview');
      if (!preview || preview.classList.contains('hidden')) { appendChat('No generated JSON to import', 'bot'); return; }
      try {
        const form = JSON.parse(preview.textContent);
        appendChat('Importing generated form...', 'bot');
        // attempt to use builder import if it exists
        if (window.appBuilder && typeof window.appBuilder.importGeneratedForm === 'function') {
          window.appBuilder.importGeneratedForm(form, { replace: true });
          document.getElementById('polaris-toggle-btn')?.click();
        } else appendChat('Builder not available to import', 'bot');
      } catch (e) { appendChat('Invalid preview JSON', 'bot'); }
    });
  }

  // expose for the toggle script to call before opening
  window._polarisAPI = {
    prefillFromBuilder,
    appendChat,
    renderImportControls
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();