// assets/js/polaris-toggle.js
// Updated widget controller: ensures polaris-left shows first if details missing
// and exposes setMode/open/close methods. Also injects/removes Edit Details button in header.

document.addEventListener('DOMContentLoaded', () => {
  const wrapper = document.getElementById('polaris-widget');
  if (!wrapper) return;

  const panel = wrapper.querySelector('.polaris-panel');
  const toggleBtn = document.getElementById('polaris-toggle-btn');
  const minimizeBtn = document.getElementById('polaris-minimize-btn');
  const closeBtn = document.getElementById('polaris-close-btn');
  const headerActions = panel.querySelector('.polaris-header-actions');

  const left = panel.querySelector('.polaris-left');
  const right = panel.querySelector('.polaris-right');
  const saveBtnId = 'polaris-save-details';
  const editBtnId = 'polaris-edit-details';

  // Utility: whether required details are filled
  function detailsFilled() {
    const name = (document.getElementById('polaris-app-name')?.value || '').trim();
    const group = (document.getElementById('polaris-group-id')?.value || '').trim();
    // Treat "filled" as both name and group id being present
    return name.length > 0 && group.length > 0;
  }

  // Setup mode functions
  function setMode(mode) {
    if (!wrapper) return;
    wrapper.classList.remove('mode-edit', 'mode-chat');
    if (mode === 'edit') {
      wrapper.classList.add('mode-edit');
      ensureSaveButton();
      removeEditHeaderButton();
      // focus first input in left
      const first = left.querySelector('input, textarea, select');
      if (first) first.focus();
    } else {
      wrapper.classList.add('mode-chat');
      ensureEditHeaderButton();
      // focus chat input
      const chatInput = document.getElementById('polaris-prompt');
      if (chatInput) chatInput.focus();
    }
  }

  // Ensure Save button in left area (only add once)
  function ensureSaveButton() {
    if (!left) return;
    if (left.querySelector('#' + saveBtnId)) return;
    const actions = document.createElement('div');
    actions.className = 'polaris-left-actions';
    const saveBtn = document.createElement('button');
    saveBtn.id = saveBtnId;
    saveBtn.className = 'btn btn-primary';
    saveBtn.type = 'button';
    saveBtn.textContent = 'Save & Start Chat';
    saveBtn.addEventListener('click', () => {
      // mark saved and switch to chat mode
      try { localStorage.setItem('polaris.saved', 'true'); } catch (e) {}
      setMode('chat');
    });
    actions.appendChild(saveBtn);

    // lightweight secondary action - "Save & stay" (optional)
    const saveStay = document.createElement('button');
    saveStay.className = 'btn btn-secondary';
    saveStay.type = 'button';
    saveStay.textContent = 'Save';
    saveStay.addEventListener('click', () => {
      try { localStorage.setItem('polaris.saved', 'true'); } catch (e) {}
      // keep in edit mode; optionally show toast via builder (if available)
      if (window.appBuilder && typeof window.appBuilder.showToast === 'function') {
        window.appBuilder.showToast('Details saved locally', 'success');
      }
    });
    actions.appendChild(saveStay);

    left.appendChild(actions);
  }

  // Ensure "Edit Details" header button exists (shown in chat mode)
  function ensureEditHeaderButton() {
    if (!headerActions) return;
    if (document.getElementById(editBtnId)) return;
    const btn = document.createElement('button');
    btn.id = editBtnId;
    btn.className = 'polaris-edit-btn';
    btn.type = 'button';
    // optional icon element
    const icon = document.createElement('span');
    icon.className = 'icon';
    icon.textContent = '✏️';
    btn.appendChild(icon);
    const lbl = document.createElement('span');
    lbl.textContent = 'Edit Details';
    btn.appendChild(lbl);

    btn.addEventListener('click', () => {
      setMode('edit');
    });

    headerActions.insertBefore(btn, headerActions.firstChild || null);
  }

  function removeEditHeaderButton() {
    const b = document.getElementById(editBtnId);
    if (b) b.remove();
  }

  // Open widget: decide which mode to show based on whether details are filled
  function openWidget() {
    wrapper.classList.add('open');
    wrapper.classList.remove('collapsed');
    if (panel) panel.setAttribute('aria-hidden', 'false');
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');

    // If fields missing, show edit. Otherwise prefer saved state or chat.
    const saved = (function(){ try { return localStorage.getItem('polaris.saved') === 'true'; } catch(e){ return false; } })();
    if (!detailsFilled()) {
      setMode('edit');
    } else if (saved) {
      setMode('chat');
    } else {
      // default to edit if user hasn't saved
      setMode('edit');
    }
  }

  function closeWidget() {
    wrapper.classList.remove('open');
    wrapper.classList.add('collapsed');
    if (panel) panel.setAttribute('aria-hidden', 'true');
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
  }

  // Wire toggle
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (wrapper.classList.contains('open')) closeWidget();
      else openWidget();
    });
  }

  // Minimize and close
  if (minimizeBtn) {
    minimizeBtn.addEventListener('click', () => closeWidget());
  }
  if (closeBtn) {
    closeBtn.addEventListener('click', () => closeWidget());
  }

  // On initial load, if widget is open in markup ensure correct mode
  if (wrapper.classList.contains('open')) {
    if (!detailsFilled()) setMode('edit');
    else {
      const saved = (function(){ try { return localStorage.getItem('polaris.saved') === 'true'; } catch(e){ return false; } })();
      setMode(saved ? 'chat' : 'edit');
    }
  }

  // Expose to window for other scripts
  window.polarisWidget = window.polarisWidget || {};
  window.polarisWidget.open = openWidget;
  window.polarisWidget.close = closeWidget;
  window.polarisWidget.setMode = setMode;
  window.polarisWidget.detailsFilled = detailsFilled;
});