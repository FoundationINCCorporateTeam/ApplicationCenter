/**
 * Simple Helpdesk Chat Widget (Vanilla JS)
 * - Loads as <script src="/chat-widget.js" defer></script>
 * - Communicates with the PHP API endpoints via fetch()
 * - Long polling implemented to poll /api/poll_messages.php
 *
 * Behavior:
 * 1. Shows floating bubble.
 * 2. On open: show user form (name/email) if not set.
 * 3. Create chat session via /api/create_chat_session.php
 * 4. Send messages via /api/send_message.php
 * 5. Poll for messages via /api/poll_messages.php?session_id=...
 *
 * Notes:
 * - Keep this file framework-free and small.
 * - Customize color/icon via init options (window.HelpdeskChatConfig).
 */

/* Utility: generate simple UUID (v4-like) */
function uuidv4() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c=>{
    const r = Math.random()*16|0, v = c=='x'?r:(r&0x3|0x8);
    return v.toString(16);
  });
}

/* Default configuration - can be overridden by setting window.HelpdeskChatConfig before script loads */
const DEFAULT_CONFIG = {
  bubbleColor: '#0ea5a4', // teal-500
  icon: '💬',
  welcomeMessage: 'Hi! How can we help you today?',
  apiBase: '/api',
  title: 'Support',
  offlineMessage: 'Our agents are currently offline. Your message will become a ticket.',
  pollingTimeout: 25 // seconds - matched on server
};

(function () {
  const cfg = Object.assign({}, DEFAULT_CONFIG, window.HelpdeskChatConfig || {});
  const storageKey = 'helpdesk_chat_user';

  // State
  let sessionId = null;
  let user = JSON.parse(localStorage.getItem(storageKey) || 'null');
  let lastTimestamp = 0;
  let polling = false;
  let pollAbort = false;

  // Create DOM elements
  const bubble = document.createElement('button');
  bubble.className = 'chat-widget-bubble';
  bubble.style.background = cfg.bubbleColor;
  bubble.setAttribute('aria-label','Open chat');
  bubble.innerHTML = `<span style="font-size:22px">${cfg.icon}</span>`;
  document.body.appendChild(bubble);

  const win = document.createElement('div');
  win.className = 'chat-widget-window';
  win.innerHTML = `
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
      <div class="p-4 flex items-center justify-between bg-gradient-to-r from-teal-500 to-teal-600 text-white">
        <div>
          <div class="font-semibold">${cfg.title}</div>
          <div class="text-xs opacity-90">${cfg.welcomeMessage}</div>
        </div>
        <button id="wd-close" class="text-white opacity-90 hover:opacity-100">✕</button>
      </div>

      <div id="wd-body" class="p-4 h-64 overflow-y-auto bg-gray-50 chat-history">
        <!-- messages -->
      </div>

      <div id="wd-footer" class="p-3 bg-white">
        <div id="wd-form-area"></div>
      </div>
    </div>`;
  document.body.appendChild(win);

  const bodyEl = win.querySelector('#wd-body');
  const formArea = win.querySelector('#wd-form-area');

  // helpers
  function sanitizeHtml(str){
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function renderMessage(msg){
    // msg: {id,sender,text,timestamp}
    const el = document.createElement('div');
    el.className = 'mb-3';
    if(msg.sender === 'agent'){
      el.innerHTML = `<div class="text-sm text-gray-500">${new Date(msg.timestamp*1000).toLocaleTimeString()}</div>
        <div class="mt-1 inline-block bg-white border border-gray-200 px-3 py-2 rounded-lg shadow-sm text-gray-800 max-w-[80%]">${sanitizeHtml(msg.text)}</div>`;
    } else {
      el.style.textAlign = 'right';
      el.innerHTML = `<div class="text-sm text-gray-400">${new Date(msg.timestamp*1000).toLocaleTimeString()}</div>
        <div class="mt-1 inline-block bg-teal-500 text-white px-3 py-2 rounded-lg shadow-sm max-w-[80%]">${sanitizeHtml(msg.text)}</div>`;
    }
    bodyEl.appendChild(el);
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  function showTypingIndicator(){
    const typing = document.createElement('div');
    typing.className = 'chat-typing-dots mt-2 mb-2';
    typing.innerHTML = '<span></span><span></span><span></span>';
    bodyEl.appendChild(typing);
    bodyEl.scrollTop = bodyEl.scrollHeight;
    return typing;
  }

  // UI: user info form
  function renderUserForm(){
    formArea.innerHTML = `
      <form id="user-form" class="space-y-2">
        <input type="text" name="name" placeholder="Your name" required class="w-full border rounded px-3 py-2" />
        <input type="email" name="email" placeholder="Email (optional)" class="w-full border rounded px-3 py-2" />
        <div class="flex justify-end">
          <button type="submit" class="bg-teal-500 text-white px-4 py-2 rounded">Start Chat</button>
        </div>
      </form>`;
    const f = formArea.querySelector('#user-form');
    f.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(f);
      user = { name: fd.get('name')||'Guest', email: fd.get('email')||'' };
      localStorage.setItem(storageKey, JSON.stringify(user));
      await createSessionAndStart();
    });
  }

  // UI: message composer
  function renderComposer(){
    formArea.innerHTML = `
      <div class="flex space-x-2">
        <input id="msg-input" type="text" placeholder="Write a message..." class="flex-1 border rounded px-3 py-2" />
        <button id="msg-send" class="bg-teal-500 text-white px-4 py-2 rounded">Send</button>
      </div>`;
    const input = formArea.querySelector('#msg-input');
    const btn = formArea.querySelector('#msg-send');

    // Simulated typing indicator to server: we simply show on UI locally
    input.addEventListener('input', ()=>{
      // Could POST to server "typing" status later
    });

    btn.addEventListener('click', async ()=>{
      const text = input.value.trim();
      if(!text) return;
      appendLocalUserMessage(text);
      input.value = '';
      // Rate-limit handled on server
      await sendMessageToServer(text);
    });

    input.addEventListener('keydown', (e)=>{
      if(e.key === 'Enter'){
        e.preventDefault();
        btn.click();
      }
    });
  }

  function appendLocalUserMessage(text){
    const msg = {
      id: uuidv4(),
      sender: 'user',
      text,
      timestamp: Math.floor(Date.now()/1000)
    };
    renderMessage(msg);
    lastTimestamp = msg.timestamp;
  }

  // API calls
  async function api(path, method='GET', data=null){
    const url = cfg.apiBase + path;
    let opts = { method, headers: {} };
    if(data && !(data instanceof FormData)){
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(data);
    } else if(data instanceof FormData){
      opts.body = data;
    }
    const res = await fetch(url, opts);
    if(!res.ok){
      throw new Error('API error ' + res.status);
    }
    const json = await res.json();
    return json;
  }

  async function createSessionAndStart(){
    // create_chat_session expects name/email
    try{
      const res = await api('/create_chat_session.php','POST', {
        name: user.name,
        email: user.email
      });
      if(res && res.session_id){
        sessionId = res.session_id;
        lastTimestamp = 0;
        renderComposer();
        startPolling();
      } else {
        // Agents offline: create ticket fallback
        alert(cfg.offlineMessage);
        await createTicketFallback('No agents online - fallback ticket');
        win.style.display = 'none';
      }
    } catch(err){
      console.error(err);
      alert('Unable to start chat (server). Try again later.');
    }
  }

  async function createTicketFallback(message){
    // send message as ticket via API create_ticket.php
    try{
      await api('/create_ticket.php','POST',{
        subject: 'Offline chat message',
        name: user.name,
        email: user.email,
        message
      });
    } catch(err){ console.error('Ticket fallback failed', err); }
  }

  async function sendMessageToServer(text){
    if(!sessionId) return;
    try{
      await api('/send_message.php','POST',{
        session_id: sessionId,
        sender: 'user',
        text: text
      });
      // immediate poll will pick up agent replies
    } catch(err){
      console.error('sendMessage error', err);
    }
  }

  // Long polling loop
  async function startPolling(){
    if(polling) return;
    polling = true;
    pollAbort = false;
    while(!pollAbort){
      try{
        // pass lastTimestamp to get only new messages
        const url = `${cfg.apiBase}/poll_messages.php?session_id=${encodeURIComponent(sessionId)}&since=${encodeURIComponent(lastTimestamp)}`;
        const res = await fetch(url, { cache: 'no-store' });
        if(!res.ok){
          // server returned error; small delay before next poll
          await new Promise(r=>setTimeout(r, 2000));
          continue;
        }
        const data = await res.json();
        if(Array.isArray(data.messages) && data.messages.length){
          data.messages.forEach(m=>{
            // avoid re-rendering user's own message, server echoes are okay
            renderMessage(m);
            lastTimestamp = Math.max(lastTimestamp, m.timestamp);
          });
        }
        // Immediately loop to achieve near-real-time
      } catch(err){
        console.error('Polling error', err);
        // Backoff on network error
        await new Promise(r=>setTimeout(r, 2000));
      }
    }
    polling = false;
  }

  function stopPolling(){
    pollAbort = true;
  }

  // Bubble interactions
  bubble.addEventListener('click', ()=>{
    if(win.style.display === 'block'){
      win.style.display = 'none';
      stopPolling();
    } else {
      win.style.display = 'block';
      // If user already has session, show composer; else show form
      if(user && user.name && sessionId){
        renderComposer();
        startPolling();
      } else if(user && user.name){
        // create session
        createSessionAndStart();
      } else {
        renderUserForm();
      }
    }
  });

  win.querySelector('#wd-close').addEventListener('click', ()=>{
    win.style.display = 'none';
    stopPolling();
  });

  // On load: if user saved and session present in localStorage we can re-open session when user clicks bubble
  // Optionally, check server for existing session mapping via create_chat_session.php behavior (it can return existing open session).
  // For simplicity we create a fresh session on each new widget load unless server maps via email.
})();