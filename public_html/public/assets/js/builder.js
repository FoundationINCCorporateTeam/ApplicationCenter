// public_html/assets/js/builder.js
// Builder JS (updated) — uses index.php?flow=application&action=save&app_id=... for save requests.
// When builder.html is served from /public/, the relative 'index.php?...' resolves to /public/index.php?...
// When served from the site root it resolves to /index.php?... (works in both cases).
(() => {
  const paletteItems = document.querySelectorAll('.palette-item');
  const questionsList = document.getElementById('questionsList');
  const template = document.getElementById('question-template');
  const preview = document.getElementById('preview');

  // Helper: read query param
  function getQueryParam(name) {
    const params = new URLSearchParams(window.location.search);
    return params.get(name);
  }

  const state = {
    app: { id: getQueryParam('app_id') || 'sample-app', name: 'New Application', description: '' },
    style: { primary_color: '#0ea5a4', accent_color: '#ff5c7c', button_shape: 'pill', background: 'gradient', font: 'Inter' },
    questions: []
  };

  // Drag/drop palette
  paletteItems.forEach(it => {
    it.addEventListener('dragstart', (e) => {
      e.dataTransfer.setData('text/plain', it.dataset.type);
    });
  });

  // allow dropping (for adding new items)
  questionsList.addEventListener('dragover', (e) => {
    e.preventDefault();
  });
  questionsList.addEventListener('drop', (e) => {
    e.preventDefault();
    const type = e.dataTransfer.getData('text/plain') || 'short_answer';
    addQuestion(type);
  });

  function addQuestion(type, initial = null) {
    const el = template.content.firstElementChild.cloneNode(true);
    const newId = initial && initial.id ? initial.id : 'q' + Date.now();
    el.dataset.id = newId;
    el.querySelector('.q-type').textContent = type;
    const titleInput = el.querySelector('.q-title');
    const descInput = el.querySelector('.q-desc');
    const pointsInput = el.querySelector('.q-points');

    // Set defaults or initial values
    titleInput.value = initial && initial.text ? initial.text : (type === 'short_answer' ? 'Short answer question' : (type === 'multiple_choice' ? 'Multiple choice question' : 'Checkboxes question'));
    descInput.value = initial && initial.description ? initial.description : '';
    pointsInput.value = initial && typeof initial.points !== 'undefined' ? initial.points : 10;

    const optionsDiv = el.querySelector('.q-options');
    if (type === 'multiple_choice' || type === 'checkboxes') {
      // pass the element and any initial options so the controls can wire events to sync state
      renderOptionsControls(optionsDiv, type, el, (initial && initial.options) ? initial.options : []);
    } else {
      optionsDiv.innerHTML = '<label>Max Length: <input class="opt-maxlength" type="number" value="' + ((initial && initial.max_length) ? initial.max_length : 200) + '" min="10" max="300"/></label>';
      const ml = optionsDiv.querySelector('.opt-maxlength');
      ml.addEventListener('input', () => { syncFromElement(el); renderPreview(); });
    }

    el.querySelector('.q-delete').addEventListener('click', () => {
      el.remove();
      const idx = state.questions.findIndex(q => q.id === newId);
      if (idx !== -1) state.questions.splice(idx, 1);
      renderPreview();
    });

    titleInput.addEventListener('input', () => { syncFromElement(el); renderPreview(); });
    descInput.addEventListener('input', () => { syncFromElement(el); renderPreview(); });
    pointsInput.addEventListener('input', () => { syncFromElement(el); renderPreview(); });

    questionsList.appendChild(el);

    const qobj = {
      id: newId,
      type,
      text: titleInput.value,
      description: descInput.value,
      points: parseInt(pointsInput.value, 10) || 0,
      options: (initial && initial.options) ? initial.options.map((o, i) => ({ id: o.id || ('opt' + i), text: o.text || '', correct: !!o.correct })) : []
    };
    if (type === 'short_answer') {
      qobj.max_length = initial && initial.max_length ? initial.max_length : 200;
      qobj.grading_criteria = initial && initial.grading_criteria ? initial.grading_criteria : 'Be concise and relevant.';
    }

    state.questions.push(qobj);
    // Populate any controls from qobj (e.g., options)
    syncElementToState(el, qobj);
    renderPreview();
  }

  // Render options controls. parentEl is the question card element so event handlers can sync state.
  function renderOptionsControls(container, type, parentEl = null, initialOptions = []) {
    container.innerHTML = '';
    const addBtn = document.createElement('button');
    addBtn.textContent = 'Add option';
    addBtn.className = 'btn';
    addBtn.type = 'button';
    container.appendChild(addBtn);
    const list = document.createElement('div');
    list.className = 'opts-list';
    container.appendChild(list);

    function createOptionRow(opt = { text: '', correct: false }) {
      const row = document.createElement('div');
      row.className = 'opt-row';
      row.innerHTML = `<input class="opt-text" placeholder="Option text" value="${escapeHtml(opt.text)}" /> <label>Correct <input type="checkbox" class="opt-correct" ${opt.correct ? 'checked' : ''}/></label> <button class="opt-del btn" type="button">Delete</button>`;
      // Attach listeners that sync to the question element so state.questions stays current
      const textEl = row.querySelector('.opt-text');
      const correctEl = row.querySelector('.opt-correct');
      const delBtn = row.querySelector('.opt-del');

      textEl.addEventListener('input', () => { if (parentEl) syncFromElement(parentEl); renderPreview(); });
      correctEl.addEventListener('change', () => { if (parentEl) syncFromElement(parentEl); renderPreview(); });
      delBtn.addEventListener('click', () => { row.remove(); if (parentEl) syncFromElement(parentEl); renderPreview(); });

      return row;
    }

    // populate initial options if provided
    if (initialOptions && initialOptions.length) {
      initialOptions.forEach(opt => {
        list.appendChild(createOptionRow(opt));
      });
    }

    addBtn.addEventListener('click', () => {
      const row = createOptionRow({ text: '', correct: false });
      list.appendChild(row);
      if (parentEl) syncFromElement(parentEl);
      renderPreview();
    });
  }

  function syncFromElement(el) {
    const id = el.dataset.id;
    const q = state.questions.find(q => q.id === id);
    if (!q) return;
    q.text = el.querySelector('.q-title').value;
    q.description = el.querySelector('.q-desc').value;
    q.points = parseInt(el.querySelector('.q-points').value, 10) || 0;
    const optsList = el.querySelectorAll('.opt-row');
    if (optsList.length > 0) {
      q.options = [];
      optsList.forEach((row, idx) => {
        const textEl = row.querySelector('.opt-text');
        const correctEl = row.querySelector('.opt-correct');
        q.options.push({
          id: 'opt' + idx,
          text: textEl ? textEl.value : '',
          correct: !!(correctEl && correctEl.checked)
        });
      });
      // Remove short-answer specific fields when options exist
      delete q.max_length;
      delete q.grading_criteria;
    } else {
      const ml = el.querySelector('.opt-maxlength');
      if (ml) q.max_length = parseInt(ml.value, 10);
    }
  }

  function syncElementToState(el, qobj) {
    const optsList = el.querySelector('.opts-list');
    if (optsList && qobj.options) {
      optsList.innerHTML = '';
      qobj.options.forEach(opt => {
        const row = document.createElement('div');
        row.className = 'opt-row';
        row.innerHTML = `<input class="opt-text" value="${escapeHtml(opt.text)}" /> <label>Correct <input type="checkbox" class="opt-correct" ${opt.correct ? 'checked' : ''}/></label> <button class="opt-del btn" type="button">Delete</button>`;
        optsList.appendChild(row);

        // Attach listeners to sync state when these fields change
        const textEl = row.querySelector('.opt-text');
        const correctEl = row.querySelector('.opt-correct');
        const delBtn = row.querySelector('.opt-del');

        textEl.addEventListener('input', () => { syncFromElement(el); renderPreview(); });
        correctEl.addEventListener('change', () => { syncFromElement(el); renderPreview(); });
        delBtn.addEventListener('click', () => { row.remove(); syncFromElement(el); renderPreview(); });
      });
    }
  }

  // small helper to escape inserted text values
  function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // Drag reorder
  let dragged = null;
  questionsList.addEventListener('dragstart', (e) => {
    const t = e.target.closest('.draggable');
    if (t) { dragged = t; e.dataTransfer.effectAllowed = 'move'; }
  });
  questionsList.addEventListener('dragover', (e) => {
    e.preventDefault();
    const after = getDragAfterElement(questionsList, e.clientY);
    if (!after) {
      if (dragged) questionsList.appendChild(dragged);
    } else {
      if (dragged) questionsList.insertBefore(dragged, after);
    }
  });
  questionsList.addEventListener('drop', (e) => {
    dragged = null;
    const newOrder = [];
    document.querySelectorAll('#questionsList > .draggable').forEach(el => {
      const id = el.dataset.id;
      const q = state.questions.find(q => q.id === id);
      if (q) newOrder.push(q);
    });
    state.questions = newOrder;
    renderPreview();
  });

  function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.draggable:not(.dragging)')];
    return draggableElements.reduce((closest, child) => {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        return { offset: offset, element: child };
      } else {
        return closest;
      }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
  }

  // Preview rendering
  function renderPreview() {
    preview.innerHTML = '';
    const card = document.createElement('div');
    card.className = 'preview-card';
    const title = document.createElement('h3');
    title.textContent = state.app.name;
    card.appendChild(title);
    state.questions.forEach(q => {
      const f = document.createElement('div');
      f.className = 'preview-field';
      const label = document.createElement('div');
      label.textContent = q.text;
      label.style.fontWeight = '600';
      f.appendChild(label);
      if (q.type === 'multiple_choice') {
        const ops = q.options && q.options.length ? q.options : [{text:'Option 1'},{text:'Option 2'}];
        ops.forEach(o => {
          const r = document.createElement('div');
          r.textContent = (o.correct ? '◉ ' : '◯ ') + o.text;
          f.appendChild(r);
        });
      } else if (q.type === 'checkboxes') {
        const ops = q.options && q.options.length ? q.options : [{text:'A'},{text:'B'}];
        ops.forEach(o => {
          const r = document.createElement('div');
          r.textContent = (o.correct ? '☑ ' : '☐ ') + o.text;
          f.appendChild(r);
        });
      } else {
        const ta = document.createElement('div');
        ta.textContent = '_____ (short answer)';
        ta.style.opacity = 0.7;
        f.appendChild(ta);
      }
      card.appendChild(f);
    });
    preview.appendChild(card);
  }

  // Build AST-ish serializer (kept simple)
  function buildAstText(state) {
    let out = '';
    out += 'APP {\n';
    out += `  id: "${state.app.id}";\n`;
    out += `  name: "${state.app.name}";\n`;
    out += `  description: "${state.app.description}";\n`;
    out += '}\n\n';
    out += 'STYLE {\n';
    out += `  primary_color: "${state.style.primary_color}";\n`;
    out += `  button_shape: "${state.style.button_shape}";\n`;
    out += `  font: "${state.style.font}";\n`;
    out += '}\n\n';
    state.questions.forEach(q => {
      out += `QUESTION "${q.id}" TYPE "${q.type}" {\n`;
      out += `  text: "${(q.text||'').replace(/"/g, '\\"')}";\n`;
      if (q.description) out += `  description: "${(q.description||'').replace(/"/g, '\\"')}";\n`;
      out += `  points: ${q.points};\n`;
      if (q.type === 'short_answer') {
        out += `  max_length: ${q.max_length || 200};\n`;
        out += `  grading_criteria: "${(q.grading_criteria || 'Be concise and relevant.').replace(/"/g, '\\"')}";\n`;
      }
      if (q.options && q.options.length) {
        out += '  options: [\n';
        q.options.forEach(opt => {
          out += `    {id:"${opt.id}", text:"${(opt.text||'').replace(/"/g, '\\"')}", correct:${opt.correct ? 'true' : 'false'}},\n`;
        });
        out += '  ];\n';
      }
      out += '}\n\n';
    });
    return out;
  }

  // Save button handler (uses index.php query parameters)
  document.getElementById('saveBtn').addEventListener('click', async () => {
    // Before saving, ensure we sync any visible DOM changes into state
    document.querySelectorAll('#questionsList > .draggable').forEach(el => syncFromElement(el));

    const astText = buildAstText(state);

    // Use index.php with query params for flow/action/app_id (relative URL)
    const endpoint = 'index.php?flow=application&action=save&app_id=' + encodeURIComponent(state.app.id);

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          // Demo auth header — replace with real auth in production
          'Authorization': 'Bearer creator-demo-token'
        },
        body: JSON.stringify({ ast_text: astText })
      });

      // Robust response handling
      const contentType = (res.headers.get('Content-Type') || '').toLowerCase();
      if (!res.ok) {
        if (contentType.includes('application/json')) {
          const json = await res.json();
          alert('Save failed: ' + (json.error || res.status));
        } else {
          const txt = await res.text();
          alert('Save failed: HTTP ' + res.status + '\n' + (txt.slice(0, 200) || ''));
        }
        return;
      }

      if (contentType.includes('application/json')) {
        const json = await res.json();
        if (json.ok) {
          alert('Saved successfully');
        } else {
          alert('Save failed: ' + (json.error || 'unknown'));
        }
      } else {
        // If server returned HTML (e.g., a 404 page) parse a short snippet and show it
        const txt = await res.text();
        alert('Save returned non-JSON response. HTTP ' + res.status + '\n' + (txt.slice(0, 200) || ''));
      }
    } catch (e) {
      alert('Save error: ' + e.message);
    }
  });

  // Theme toggle
  const toggleBtn = document.getElementById('toggleTheme');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      document.body.classList.toggle('light');
    });
  }

  // Load existing application (if available)
  async function loadApplication(appId) {
    if (!appId) return;
    const endpoint = 'index.php?flow=application&action=get&app_id=' + encodeURIComponent(appId);
    try {
      const res = await fetch(endpoint, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) {
        // Not fatal — just leave blank canvas
        console.warn('Failed to load application:', res.status);
        return;
      }
      const contentType = (res.headers.get('Content-Type') || '').toLowerCase();
      if (!contentType.includes('application/json')) {
        console.warn('Unexpected non-JSON when loading application');
        return;
      }
      const json = await res.json();
      // Expecting shape: { app: {...}, style: {...}, questions: [...] }
      if (json && Array.isArray(json.questions)) {
        // Clear current UI
        questionsList.innerHTML = '';
        state.questions = [];

        if (json.app) {
          state.app.id = json.app.id || state.app.id;
          state.app.name = json.app.name || state.app.name;
          state.app.description = json.app.description || state.app.description;
        }
        if (json.style) {
          state.style = Object.assign(state.style, json.style);
          // update style UI controls if present
          const pcol = document.getElementById('primaryColor');
          const acol = document.getElementById('accentColor');
          const bshape = document.getElementById('buttonShape');
          const bg = document.getElementById('bgStyle');
          const fontSel = document.getElementById('fontSelect');
          if (pcol && state.style.primary_color) pcol.value = state.style.primary_color;
          if (acol && state.style.accent_color) acol.value = state.style.accent_color;
          if (bshape && state.style.button_shape) bshape.value = state.style.button_shape;
          if (bg && state.style.background) bg.value = state.style.background;
          if (fontSel && state.style.font) fontSel.value = state.style.font;
        }

        // Create UI elements for each question
        json.questions.forEach(q => {
          // normalize q to expected shape
          const norm = {
            id: q.id || ('q' + Date.now() + Math.floor(Math.random() * 1000)),
            type: q.type || 'short_answer',
            text: q.text || '',
            description: q.description || '',
            points: typeof q.points !== 'undefined' ? q.points : 10,
            options: Array.isArray(q.options) ? q.options.map((o, idx) => ({ id: o.id || ('opt' + idx), text: o.text || '', correct: !!o.correct })) : [],
            max_length: q.max_length,
            grading_criteria: q.grading_criteria
          };
          addQuestion(norm.type, norm);
        });
      } else {
        console.warn('Application GET returned JSON in unexpected format');
      }
    } catch (e) {
      console.warn('Error loading application:', e);
    }
  }

  // Initialize: render empty preview and attempt to load existing app
  renderPreview();
  loadApplication(state.app.id);

})();