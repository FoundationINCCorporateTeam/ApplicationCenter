// assets/js/builder.js
// Complete updated AppBuilder
// - Unified modal for add/edit questions (single modal at a time)
// - Enforces short-answer max length range (10..300) when saving/adding
// - Enforces maximum of 3 short-answer questions
// - Color swatches update live
// - Import from Polaris does NOT collapse the widget
// - Polished toasts and focus handling for modal
// - Keeps previous behaviors (drag/drop, preview, save/load)

(function () {
  if (typeof window.AppBuilder !== 'undefined') return;

  window.AppBuilder = class AppBuilder {
    constructor() {
      this.currentApp = {
        id: null,
        name: '',
        description: '',
        group_id: '',
        target_role: '',
        pass_score: 70,
        style: { primary_color: '#ff4b6e', secondary_color: '#1f2933' },
        questions: []
      };

      this.questions = [];
      this.draggedElement = null;
      this.saving = false;
      this.generating = false;

      this.MAX_SHORT_ANSWERS = 3;
      this.SHORT_ANSWER_MIN = 10;
      this.SHORT_ANSWER_MAX = 300;

      this.init();
    }

    /* Initialization */
    init() {
      this.setupThemeToggle();
      this.setupEventListeners();
      this.setupPolarisBindings();
      this.setupColorSwatches();
      this.loadAppList();

      const hadStatic = this.bindExistingBuilderUI();
      if (!hadStatic) this.renderBuilder();

      this.updatePreview();
    }

    /* Theme toggle */
    setupThemeToggle() {
      const savedTheme = localStorage.getItem('theme') || 'light';
      document.documentElement.setAttribute('data-theme', savedTheme);
      const toggle = document.getElementById('theme-toggle');
      if (toggle) toggle.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
      });
    }

    /* Top-level event bindings */
    setupEventListeners() {
      document.getElementById('new-app-btn')?.addEventListener('click', () => this.createNewApp?.());
      document.getElementById('save-app-btn')?.addEventListener('click', () => this.saveApp());

      document.getElementById('polaris-send-prompt')?.addEventListener('click', () => this._sendPolarisPrompt());
      document.getElementById('polaris-suggest-structure')?.addEventListener('click', () => this._suggestStructure());
      document.getElementById('polaris-import-btn')?.addEventListener('click', () => {
        const preview = document.getElementById('polaris-gen-preview');
        if (!preview || preview.classList.contains('hidden') || !preview.textContent) {
          this.showToast('No generated JSON to import', 'info');
          return;
        }
        try {
          const form = JSON.parse(preview.textContent);
          // intentionally keep Polaris widget open (do not collapse)
          this.importGeneratedForm(form, { replace: true });
        } catch {
          this.showToast('Invalid preview JSON', 'error');
        }
      });

      this.setupLivePreview();
    }

    /* Live color swatches */
    setupColorSwatches() {
      const bind = (inputId, swatchId, hexInputId) => {
        const input = document.getElementById(inputId);
        const sw = document.getElementById(swatchId);
        const hex = hexInputId ? document.getElementById(hexInputId) : null;
        if (!input || !sw) return;
        const update = () => {
          try {
            sw.style.background = input.value;
            if (hex) hex.value = input.value.toUpperCase();
          } catch (e) {}
        };
        update();
        input.addEventListener('input', update);
        if (hex) {
          hex.addEventListener('input', () => {
            const v = String(hex.value || '').trim();
            if (/^#([0-9A-Fa-f]{6})$/.test(v)) {
              input.value = v;
              update();
            }
          });
        }
      };

      bind('primary-color', 'primary-color-swatch', 'primary-color-hex');
      bind('secondary-color', 'secondary-color-swatch', 'secondary-color-hex');
      bind('polaris-primary-color', 'polaris-primary-color-swatch', 'polaris-primary-color-hex');
      bind('polaris-secondary-color', 'polaris-secondary-color-swatch', 'polaris-secondary-color-hex');
    }

    /* Live preview wiring */
    setupLivePreview() {
      const ids = ['app-name', 'app-description', 'primary-color', 'secondary-color', 'group-id', 'target-role', 'pass-score'];
      ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', () => {
          const builtEl = document.getElementById('built-target-role');
          if (builtEl) builtEl.textContent = this._buildTargetRolePreview() || '(incomplete - provide Group ID and Role)';
          this.updatePreview();
        });
      });
    }

    /* Bind to static markup if present */
    bindExistingBuilderUI() {
      const builderArea = document.getElementById('builder-area');
      if (!builderArea) return false;
      const hasAppName = !!document.getElementById('app-name');
      const hasGroupId = !!document.getElementById('group-id');
      const hasQuestionsContainer = !!document.getElementById('questions-container');
      if (!hasAppName && !hasGroupId && !hasQuestionsContainer) return false;

      document.getElementById('add-question-btn')?.addEventListener('click', () => this.showQuestionModal());
      this.setupLivePreview();
      this.renderQuestions();
      return true;
    }

    /* App list/load/save */
    async loadAppList() {
      try {
        const res = await fetch('index.php?action=listApps');
        const data = await res.json();
        if (data.success) this.renderAppList(data.apps || []);
      } catch (err) {
        console.error('loadAppList error', err);
      }
    }

    renderAppList(apps) {
      const list = document.getElementById('app-list');
      if (!list) return;
      list.innerHTML = '';
      apps.forEach(app => {
        const li = document.createElement('li');
        li.className = 'sidebar-item';
        li.innerHTML = `<span>📋</span><span>${this.escapeHtml(app.name || '')}</span>`;
        li.addEventListener('click', () => this.loadApp(app.id));
        list.appendChild(li);
      });
    }

    async loadApp(id) {
      try {
        const res = await fetch(`index.php?action=loadApp&id=${encodeURIComponent(id)}`);
        const data = await res.json();
        if (!data.success) { this.showToast(data.error || 'Failed to load application', 'error'); return; }

        const payload = data.data || {};
        const appSource = payload.app ? payload.app : payload;

        this.currentApp = {
          id: appSource.id ?? id ?? null,
          name: appSource.name ?? '',
          description: appSource.description ?? '',
          group_id: appSource.group_id ?? '',
          target_role: appSource.target_role ?? '',
          pass_score: appSource.pass_score ?? 70,
          style: payload.style ?? appSource.style ?? { primary_color: '#ff4b6e', secondary_color: '#1f2933' },
          questions: Array.isArray(payload.questions) ? payload.questions : (Array.isArray(appSource.questions) ? appSource.questions : [])
        };

        this.questions = Array.isArray(this.currentApp.questions) ? [...this.currentApp.questions] : [];

        if (document.getElementById('app-name')) {
          document.getElementById('app-name').value = this.currentApp.name;
          document.getElementById('app-description').value = this.currentApp.description;
          document.getElementById('group-id').value = this.currentApp.group_id;
          document.getElementById('target-role').value = this._extractRankFromTargetRole(this.currentApp.target_role) || this.currentApp.target_role;
          document.getElementById('pass-score').value = this.currentApp.pass_score;
          if (document.getElementById('primary-color')) document.getElementById('primary-color').value = this.currentApp.style.primary_color;
          if (document.getElementById('secondary-color')) document.getElementById('secondary-color').value = this.currentApp.style.secondary_color;
        }

        this.setupColorSwatches();
        this.renderQuestions();
        this.updatePreview();
        this.showToast('Application loaded successfully', 'success');
      } catch (err) {
        console.error('loadApp error', err);
        this.showToast('Failed to load application', 'error');
      }
    }

    async saveApp() {
      if (this.saving) { this.showToast('Save already in progress', 'info'); return; }
      this.saving = true;
      const saveBtn = document.getElementById('save-app-btn');
      const prevHtml = saveBtn?.innerHTML;
      if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = 'Saving...'; }

      try {
        this.currentApp.name = (document.getElementById('app-name')?.value || '').toString();
        this.currentApp.description = (document.getElementById('app-description')?.value || '').toString();

        const groupRaw = (document.getElementById('group-id')?.value || '').toString().trim();
        const parsedGroup = parseInt(groupRaw || '0', 10);
        this.currentApp.group_id = (!isNaN(parsedGroup) && parsedGroup > 0) ? parsedGroup : '';

        const targetInput = (document.getElementById('target-role')?.value || '').toString().trim();
        if (/^groups\/\d+\/roles\/\d+$/i.test(targetInput)) {
          this.currentApp.target_role = targetInput;
        } else {
          const rankNum = parseInt(targetInput || '', 10);
          if (!isNaN(rankNum) && this.currentApp.group_id) {
            this.currentApp.target_role = `groups/${this.currentApp.group_id}/roles/${rankNum}`;
          } else {
            this.currentApp.target_role = '';
          }
        }

        this.currentApp.pass_score = parseInt(document.getElementById('pass-score')?.value || '70', 10) || 0;
        this.currentApp.style = this.currentApp.style || {};
        this.currentApp.style.primary_color = document.getElementById('primary-color')?.value || this.currentApp.style.primary_color || '#ff4b6e';
        this.currentApp.style.secondary_color = document.getElementById('secondary-color')?.value || this.currentApp.style.secondary_color || '#1f2933';

        this.currentApp.questions = this.questions || [];

        if (!this.currentApp.name) { this.showToast('Application name is required', 'error'); return; }
        if (!this.currentApp.group_id) { this.showToast('Group ID must be a positive number', 'error'); return; }

        const payload = {
          ...(this.currentApp.id ? { id: this.currentApp.id } : {}),
          name: this.currentApp.name,
          description: this.currentApp.description,
          group_id: this.currentApp.group_id,
          target_role: this.currentApp.target_role,
          pass_score: this.currentApp.pass_score,
          style: { primary_color: this.currentApp.style.primary_color, secondary_color: this.currentApp.style.secondary_color },
          questions: this.currentApp.questions,
          app: {
            id: this.currentApp.id ?? null,
            name: this.currentApp.name,
            description: this.currentApp.description,
            group_id: this.currentApp.group_id,
            target_role: this.currentApp.target_role,
            pass_score: this.currentApp.pass_score
          }
        };

        const res = await fetch('index.php?action=saveApp', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
          if (data.id) this.currentApp.id = data.id;
          this.showToast('Application saved successfully', 'success');
          this.loadAppList();
        } else {
          let errMsg = data.error || 'Failed to save application';
          if (data.errors) { try { errMsg = JSON.stringify(data.errors); } catch (e) { errMsg = String(data.errors); } }
          this.showToast(errMsg, 'error');
        }
      } catch (err) {
        console.error('saveApp error', err);
        this.showToast('Failed to save application', 'error');
      } finally {
        this.saving = false;
        if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = prevHtml ?? '💾 Save'; }
      }
    }

    /* Target role helpers */
    _isNumericRank(v) { return typeof v === 'string' && /^\d+$/.test(v); }
    _extractRankFromTargetRole(v) { if (!v) return ''; if (this._isNumericRank(v)) return v; const m = String(v).match(/roles\/(\d+)$/i); return m ? m[1] : ''; }
    _buildTargetRolePreview() {
      const groupVal = (document.getElementById('group-id')?.value ?? this.currentApp.group_id ?? '').toString().trim();
      const roleVal = (document.getElementById('target-role')?.value ?? this.currentApp.target_role ?? '').toString().trim();
      const groupNum = parseInt(groupVal || '0', 10);
      if (/^groups\/\d+\/roles\/\d+$/i.test(roleVal)) return roleVal;
      if (/^\d+$/.test(roleVal) && groupNum && !isNaN(groupNum) && groupNum > 0) return `groups/${groupNum}/roles/${roleVal}`;
      if (this._isNumericRank(this.currentApp.target_role) && this.currentApp.group_id) return `groups/${this.currentApp.group_id}/roles/${this.currentApp.target_role}`;
      return '';
    }

    /* Unified add/edit question modal (single modal) */
    showQuestionModal(editIndex = null) {
      // Ensure there's only one modal open
      const existing = document.querySelector('.modal-overlay');
      if (existing) existing.remove();

      const isEdit = (typeof editIndex === 'number' && this.questions[editIndex]);
      const q = isEdit ? this.questions[editIndex] : null;

      const modalId = 'modal_' + Date.now();
      const modalOverlay = document.createElement('div');
      modalOverlay.className = 'modal-overlay';
      modalOverlay.id = modalId;

      modalOverlay.innerHTML = `
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="${modalId}_title">
          <div class="modal-header">
            <h3 id="${modalId}_title" class="modal-title">${isEdit ? 'Edit Question' : 'Add Question'}</h3>
            <button class="modal-close" aria-label="Close">×</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Question Type</label>
              <select id="${modalId}_type" class="form-input">
                <option value="multiple_choice">Multiple Choice</option>
                <option value="short_answer">Short Answer</option>
                <option value="checkboxes">Checkboxes</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Question Text <span class="required" aria-hidden="true">*</span></label>
              <textarea id="${modalId}_text" class="form-textarea" placeholder="Enter your question..."></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Points</label>
              <input type="number" id="${modalId}_points" class="form-input" value="10" min="1">
            </div>

            <div id="${modalId}_options_container" class="form-group hidden">
              <label class="form-label">Options</label>
              <div id="${modalId}_options_list"></div>
              <button id="${modalId}_add_option_btn" class="btn btn-secondary" type="button">+ Add Option</button>
            </div>

            <div id="${modalId}_short_answer_container" class="form-group hidden">
              <label class="form-label">Max Length</label>
              <input type="number" id="${modalId}_max_length" class="form-input" value="${this.SHORT_ANSWER_MAX}" min="${this.SHORT_ANSWER_MIN}" max="${this.SHORT_ANSWER_MAX}">
              <div id="${modalId}_max_length_hint" class="text-muted" style="font-size:0.9rem;margin-top:6px;">Allowed: ${this.SHORT_ANSWER_MIN}–${this.SHORT_ANSWER_MAX} characters</div>
            </div>

          </div>

          <div class="modal-actions">
            <button id="${modalId}_save" class="btn btn-primary">${isEdit ? 'Save Changes' : 'Add Question'}</button>
            <button class="btn btn-ghost modal-cancel">Cancel</button>
          </div>
        </div>
      `;

      document.body.appendChild(modalOverlay);
      document.body.classList.add('modal-open');

      const closeModal = () => {
        modalOverlay.remove();
        document.body.classList.remove('modal-open');
      };

      modalOverlay.querySelector('.modal-close')?.addEventListener('click', closeModal);
      modalOverlay.querySelector('.modal-cancel')?.addEventListener('click', closeModal);

      const typeEl = document.getElementById(`${modalId}_type`);
      const textEl = document.getElementById(`${modalId}_text`);
      const pointsEl = document.getElementById(`${modalId}_points`);
      const optionsContainer = document.getElementById(`${modalId}_options_container`);
      const optionsList = document.getElementById(`${modalId}_options_list`);
      const addOptionBtn = document.getElementById(`${modalId}_add_option_btn`);
      const shortAnswerContainer = document.getElementById(`${modalId}_short_answer_container`);
      const maxLenEl = document.getElementById(`${modalId}_max_length`);
      const maxLenHint = document.getElementById(`${modalId}_max_length_hint`);
      const saveBtn = document.getElementById(`${modalId}_save`);

      // Prefill when editing
      if (isEdit && q) {
        typeEl.value = q.type || 'short_answer';
        textEl.value = q.text || '';
        pointsEl.value = q.points ?? 5;
        if (q.type === 'short_answer') maxLenEl.value = q.max_length ?? this.SHORT_ANSWER_MAX;
        if (Array.isArray(q.options)) {
          optionsList.innerHTML = '';
          q.options.forEach(opt => this._appendOptionRow(optionsList, modalId, opt.text || '', !!opt.correct));
        }
      } else {
        // default option rows for new multiple choice
        optionsList.innerHTML = '';
      }

      const onTypeChange = () => {
        const t = typeEl.value;
        if (t === 'multiple_choice' || t === 'checkboxes') {
          optionsContainer.classList.remove('hidden');
          shortAnswerContainer.classList.add('hidden');
          if (optionsList.children.length === 0) {
            this._appendOptionRow(optionsList, modalId, 'Option A', true);
            this._appendOptionRow(optionsList, modalId, 'Option B', false);
          }
        } else if (t === 'short_answer') {
          optionsContainer.classList.add('hidden');
          shortAnswerContainer.classList.remove('hidden');
        } else {
          optionsContainer.classList.add('hidden');
          shortAnswerContainer.classList.add('hidden');
        }
      };

      typeEl.addEventListener('change', onTypeChange);
      onTypeChange();

      addOptionBtn?.addEventListener('click', () => this._appendOptionRow(optionsList, modalId, '', false));

      // Live validation for max length
      maxLenEl?.addEventListener('input', () => {
        const v = parseInt(maxLenEl.value || '0', 10);
        if (isNaN(v) || v < this.SHORT_ANSWER_MIN || v > this.SHORT_ANSWER_MAX) {
          maxLenHint.textContent = `Value must be between ${this.SHORT_ANSWER_MIN} and ${this.SHORT_ANSWER_MAX}.`;
          maxLenHint.style.color = 'var(--accent-1)';
        } else {
          maxLenHint.textContent = `Allowed: ${this.SHORT_ANSWER_MIN}–${this.SHORT_ANSWER_MAX} characters`;
          maxLenHint.style.color = 'var(--text-muted)';
        }
      });

      saveBtn.addEventListener('click', () => {
        const type = typeEl.value || 'short_answer';
        const text = (textEl.value || '').trim();
        const points = parseInt(pointsEl.value || '10', 10);

        if (!text) { this.showToast('Question text is required', 'error'); return; }
        if (isNaN(points) || points < 1) { this.showToast('Points must be a positive number', 'error'); return; }

        // enforce short answer count limit (exclude edited index)
        const shortCount = this.questions.reduce((n, qq, idx) => {
          if (qq.type === 'short_answer') {
            if (isEdit && idx === editIndex) return n;
            return n + 1;
          }
          return n;
        }, 0);
        if (type === 'short_answer' && (shortCount >= this.MAX_SHORT_ANSWERS) && !isEdit) {
          this.showToast(`You may have a maximum of ${this.MAX_SHORT_ANSWERS} short-answer questions.`, 'error');
          return;
        }

        const question = { id: isEdit && q && q.id ? q.id : ('q' + Date.now()), type, text, points };

        if (type === 'multiple_choice' || type === 'checkboxes') {
          const optionRows = Array.from(optionsList.children || []);
          const options = optionRows.map((row, i) => {
            const input = row.querySelector('input[type="text"]');
            const correctInput = row.querySelector('.option-correct');
            return {
              id: 'opt' + i + '_' + Date.now(),
              text: (input?.value || '').trim() || `Option ${i + 1}`,
              correct: !!(correctInput?.checked)
            };
          }).filter(Boolean);

          if (options.length < 2) { this.showToast('Please provide at least two options', 'error'); return; }

          if (type === 'multiple_choice') {
            const correctCount = options.filter(o => o.correct).length;
            if (correctCount === 0) options[0].correct = true;
            if (correctCount > 1) {
              let seen = false;
              options.forEach(o => { if (o.correct) { if (!seen) seen = true; else o.correct = false; }});
            }
          }

          question.options = options;
          if (type === 'checkboxes') {
            question.max_score = points;
            question.scoring = { points_per_correct: Math.ceil(points / Math.max(1, options.filter(o => o.correct).length)), penalty_per_incorrect: 0 };
          }
        } else if (type === 'short_answer') {
          const maxLength = parseInt(maxLenEl.value || String(this.SHORT_ANSWER_MAX), 10);
          if (isNaN(maxLength) || maxLength < this.SHORT_ANSWER_MIN || maxLength > this.SHORT_ANSWER_MAX) {
            this.showToast(`Max length must be between ${this.SHORT_ANSWER_MIN} and ${this.SHORT_ANSWER_MAX} characters.`, 'error');
            return;
          }
          question.max_length = maxLength;
          question.grading_criteria = q?.grading_criteria || 'Grade based on relevance and quality';
        }

        if (isEdit) {
          this.questions.splice(editIndex, 1, question);
          this.showToast('Question updated', 'success');
        } else {
          this.questions.push(question);
          this.showToast('Question added', 'success');
        }

        this.renderQuestions();
        this.updatePreview();
        closeModal();
      });

      // Focus first field
      setTimeout(() => {
        const first = document.getElementById(`${modalId}_text`) || document.getElementById(`${modalId}_type`);
        first?.focus();
      }, 40);
    }

    /* Option row helper */
    _appendOptionRow(optionsListEl, modalId, text = '', correct = false) {
      const idx = optionsListEl.children.length;
      const wrapper = document.createElement('div');
      wrapper.className = 'option-row';
      wrapper.innerHTML = `
        <input type="text" class="form-input" style="flex:1" value="${this.escapeHtml(text)}" placeholder="Option text">
        <label style="display:flex; align-items:center; gap:0.25rem;">
          <input type="checkbox" class="option-correct" ${correct ? 'checked' : ''}>
          <span style="font-size:0.85rem; color:var(--text-muted)">Correct</span>
        </label>
        <button type="button" class="btn btn-ghost remove-opt" style="padding:0.4rem 0.6rem">Remove</button>
      `;
      wrapper.querySelector('.remove-opt')?.addEventListener('click', () => wrapper.remove());
      optionsListEl.appendChild(wrapper);
    }

    /* Rendering */
    renderBuilder() {
      this.setupLivePreview();
      this.setupColorSwatches();
      this.renderQuestions();
    }

    renderQuestions() {
      const container = document.getElementById('questions-container');
      if (!container) return;
      if (!this.questions || this.questions.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No questions yet. Click "Add Question" to get started.</p>';
        return;
      }
      container.innerHTML = '<div class="question-list" id="question-list"></div>';
      const list = document.getElementById('question-list');
      this.questions.forEach((q, idx) => list.appendChild(this.createQuestionCard(q, idx)));
      this.setupDragAndDrop();
    }

    createQuestionCard(question, index) {
      const card = document.createElement('div');
      card.className = 'question-card';
      card.draggable = true;
      card.dataset.index = index;

      let optionsSummary = '';
      if (question.options && Array.isArray(question.options)) {
        const opts = question.options.map(o => `${this.escapeHtml(o.text)}${o.correct ? ' ✓' : ''}`);
        optionsSummary = `<p class="text-muted" style="font-size:0.9rem; margin-top:0.5rem">${opts.join(' • ')}</p>`;
      }

      card.innerHTML = `
        <div class="question-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px">
          <div style="display:flex;align-items:center;gap:12px">
            <span class="question-type-badge" style="font-size:0.85rem;padding:6px 8px;border-radius:8px;background:var(--bg-muted)">${this.escapeHtml(question.type.replace('_',' '))}</span>
            <div style="font-weight:700">${this.escapeHtml(question.text)}</div>
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <button class="btn btn-secondary edit-btn">Edit</button>
            <button class="btn btn-ghost delete-btn">Delete</button>
          </div>
        </div>
        <p class="text-muted" style="margin:6px 0 0 0;font-size:0.9rem">Points: ${question.points}</p>
        ${optionsSummary}
      `;

      card.querySelector('.edit-btn')?.addEventListener('click', () => this.showQuestionModal(index));
      card.querySelector('.delete-btn')?.addEventListener('click', () => this.deleteQuestion(index));
      return card;
    }

    setupDragAndDrop() {
      const cards = document.querySelectorAll('.question-card');
      cards.forEach(card => {
        card.addEventListener('dragstart', () => { card.classList.add('dragging'); this.draggedElement = card; });
        card.addEventListener('dragend', () => { card.classList.remove('dragging'); this.draggedElement = null; });
        card.addEventListener('dragover', (e) => {
          e.preventDefault();
          const after = this.getDragAfterElement(e.clientY);
          const list = document.getElementById('question-list');
          if (!list) return;
          if (after == null) list.appendChild(this.draggedElement);
          else list.insertBefore(this.draggedElement, after);
        });
        card.addEventListener('drop', (e) => { e.preventDefault(); this.reorderQuestions(); });
      });
    }

    getDragAfterElement(y) {
      const cards = [...document.querySelectorAll('.question-card:not(.dragging)')];
      return cards.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) return { offset, element: child };
        return closest;
      }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    reorderQuestions() {
      const cards = document.querySelectorAll('.question-card');
      const newOrder = [];
      cards.forEach(card => newOrder.push(this.questions[parseInt(card.dataset.index, 10)]));
      this.questions = newOrder;
      this.renderQuestions();
      this.updatePreview();
    }

    deleteQuestion(index) {
      if (!confirm('Are you sure you want to delete this question?')) return;
      this.questions.splice(index, 1);
      this.renderQuestions();
      this.updatePreview();
      this.showToast('Question deleted', 'success');
    }

    /* Preview */
    updatePreview() {
      const preview = document.getElementById('preview-content');
      if (!preview) return;
      const appName = document.getElementById('app-name')?.value || this.currentApp.name || 'Your Application';
      const appDesc = document.getElementById('app-description')?.value || this.currentApp.description || 'Application description';
      const builtRole = this._buildTargetRolePreview() || this.currentApp.target_role || '(not set)';

      let questionsHtml = '';
      (this.questions || []).forEach((q, i) => {
        let details = '';
        if (q.options && Array.isArray(q.options)) {
          details = q.options.map(o => `<div style="font-size:0.85rem; color:var(--text-muted); margin-top:0.25rem">${this.escapeHtml(o.text)} ${o.correct ? '<strong style="color:var(--accent-1)">✓</strong>' : ''}</div>`).join('');
        } else if (q.type === 'short_answer') {
          details = `<div style="font-size:0.85rem; color:var(--text-muted); margin-top:0.25rem">Short answer (max ${q.max_length || 300} chars)</div>`;
        }
        questionsHtml += `
          <div style="margin-bottom: 1rem; padding: 1rem; background: var(--bg-card); border-radius: var(--radius-md);">
            <strong>Q${i + 1}. ${this.escapeHtml(q.text)}</strong>
            <p class="text-muted" style="font-size: 0.875rem;">Type: ${q.type.replace('_', ' ')} • Points: ${q.points}</p>
            ${details}
          </div>
        `;
      });

      preview.innerHTML = `
        <h3 style="margin-bottom: 0.5rem;">${this.escapeHtml(appName)}</h3>
        <p class="text-muted" style="margin-bottom: 0.5rem;">${this.escapeHtml(appDesc)}</p>
        <p class="text-muted" style="margin-bottom: 1rem; font-size:0.9rem;">Target Role: ${this.escapeHtml(builtRole)}</p>
        ${questionsHtml || '<p class="text-muted">No questions added yet</p>'}
      `;
    }

    /* Polaris bindings */
    setupPolarisBindings() {
      document.addEventListener('click', (e) => {
        if (e.target && (e.target.id === 'polaris-open-btn' || e.target.id === 'polaris-launch-btn')) {
          document.getElementById('polaris-widget')?.classList.remove('collapsed');
          this._prefillPolarisModal();
        }
      });
      document.getElementById('polaris-send-prompt')?.addEventListener('click', () => this._sendPolarisPrompt());
      document.getElementById('polaris-suggest-structure')?.addEventListener('click', () => this._suggestStructure());
    }

    _prefillPolarisModal() {
      const app = this.currentApp || {};
      const nameEl = document.getElementById('polaris-app-name');
      const descEl = document.getElementById('polaris-app-description');
      const groupEl = document.getElementById('polaris-group-id');
      const rankEl = document.getElementById('polaris-target-rank');
      const primaryEl = document.getElementById('polaris-primary-color');
      const secondaryEl = document.getElementById('polaris-secondary-color');

      if (nameEl) nameEl.value = app.name || '';
      if (descEl) descEl.value = app.description || '';
      if (groupEl) groupEl.value = app.group_id || '';
      if (rankEl) {
        const rank = (typeof app.target_role === 'string') ? (app.target_role.match(/roles\/(\d+)$/) || [null, ''])[1] : '';
        rankEl.value = rank || '';
      }
      if (primaryEl) primaryEl.value = app.style?.primary_color || '#ff4b6e';
      if (secondaryEl) secondaryEl.value = app.style?.secondary_color || '#1f2933';

      this.setupColorSwatches();
      const status = document.getElementById('polaris-gen-status'); if (status) status.textContent = 'Status: ready';
      const preview = document.getElementById('polaris-gen-preview'); if (preview) { preview.classList.add('hidden'); preview.innerText = ''; }
      const chat = document.getElementById('polaris-chat'); if (chat) chat.innerHTML = '';
      const controls = document.getElementById('polaris-gen-controls'); if (controls) controls.innerHTML = '';
    }

    /* Generate prompt flow */
    async _sendPolarisPrompt() {
      const sendBtn = document.getElementById('polaris-send-prompt');
      if (this.generating || (sendBtn && sendBtn.dataset.polarisBusy === '1')) {
        this.showToast('Generation already in progress', 'info');
        return;
      }
      this.generating = true;
      if (sendBtn) { sendBtn.disabled = true; sendBtn.dataset.polarisBusy = '1'; }

      try {
        const promptText = document.getElementById('polaris-prompt')?.value?.trim() || '';
        const name = document.getElementById('polaris-app-name')?.value?.trim() || '';
        const description = document.getElementById('polaris-app-description')?.value?.trim() || '';
        const group_id = document.getElementById('polaris-group-id')?.value?.trim() || '';
        const rank = document.getElementById('polaris-target-rank')?.value?.trim() || '';
        const questions = parseInt(document.getElementById('polaris-questions-count')?.value || '6', 10) || 6;
        const vibe = document.getElementById('polaris-vibe')?.value?.trim() || 'friendly and professional';
        const primary = document.getElementById('polaris-primary-color')?.value || '#ff4b6e';
        const secondary = document.getElementById('polaris-secondary-color')?.value || '#1f2933';

        if (!promptText && !name) { this.showToast('Provide a prompt or application name to generate', 'error'); return; }

        const userMsg = promptText || `Generate a ${questions}-question application named "${name}"`;
        this._appendPolarisChat(userMsg, 'user');
        const statusEl = document.getElementById('polaris-gen-status');
        if (statusEl) statusEl.textContent = 'Status: Generating...';

        const payload = { name, description, group_id, rank, questions, vibe, primary_color: primary, secondary_color: secondary, instructions: promptText };

        const res = await fetch('index.php?action=generateForm', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const data = await res.json().catch(() => null);
        if (!data) {
          this._appendPolarisChat('Invalid JSON response from server', 'bot');
          if (statusEl) statusEl.textContent = 'Status: Error - Invalid server response';
          return;
        }
        if (!data.success) {
          this._appendPolarisChat('Polaris error: ' + (data.error || 'unknown'), 'bot');
          if (statusEl) statusEl.textContent = 'Status: Error - ' + (data.error || 'unknown');
          return;
        }

        const form = data.form;
        const previewEl = document.getElementById('polaris-gen-preview');
        if (previewEl) { previewEl.classList.add('hidden'); previewEl.innerText = ''; }
        this._appendPolarisGeneratedFormInChat(form);
        if (statusEl) statusEl.textContent = 'Status: Preview ready';
      } catch (err) {
        console.error(err);
        this._appendPolarisChat('Network or server error: ' + (err.message || err), 'bot');
        const statusEl = document.getElementById('polaris-gen-status');
        if (statusEl) statusEl.textContent = 'Status: Network error';
      } finally {
        this.generating = false;
        if (sendBtn) { sendBtn.disabled = false; delete sendBtn.dataset.polarisBusy; }
      }
    }

    /* Chat helpers */
    _appendPolarisChat(text, who = 'bot') {
      const area = document.getElementById('polaris-chat');
      if (!area) return;
      const node = document.createElement('div');
      node.className = 'polaris-chat-msg ' + (who === 'bot' ? 'polaris-chat-bot' : 'polaris-chat-user');
      node.style.marginBottom = '0.75rem';

      const header = document.createElement('div');
      header.style.fontWeight = '600';
      header.style.fontSize = '0.88rem';
      header.style.marginBottom = '6px';
      header.textContent = who === 'bot' ? 'Polaris Pilot' : 'You';

      const body = document.createElement('div');
      body.style.fontSize = '0.95rem';
      body.style.whiteSpace = 'pre-wrap';
      body.textContent = text;

      node.appendChild(header);
      node.appendChild(body);

      area.appendChild(node);
      area.scrollTop = area.scrollHeight;
      return node;
    }

    _appendPolarisGeneratedFormInChat(form) {
      const area = document.getElementById('polaris-chat');
      if (!area) return;
      const node = document.createElement('div');
      node.className = 'polaris-chat-msg polaris-chat-bot';
      node.style.marginBottom = '0.75rem';

      const header = document.createElement('div');
      header.style.display = 'flex';
      header.style.justifyContent = 'space-between';
      header.style.alignItems = 'center';
      header.style.marginBottom = '6px';

      const title = document.createElement('div');
      title.style.fontWeight = '700';
      title.style.fontSize = '0.95rem';
      title.textContent = 'Polaris Pilot — Generated Preview';

      const meta = document.createElement('div');
      meta.style.fontSize = '0.82rem';
      meta.style.color = 'var(--text-muted)';
      meta.textContent = form.app && form.app.name ? `Application: ${form.app.name}` : '';

      header.appendChild(title);
      header.appendChild(meta);

      const body = document.createElement('div');
      body.className = 'body';
      body.style.fontSize = '0.95rem';
      body.style.whiteSpace = 'pre-wrap';
      body.style.color = 'var(--text-strong)';

      const lines = [];
      if (form.app && form.app.name) lines.push(`Title: ${form.app.name}`);
      if (form.app && form.app.description) lines.push(`Description: ${form.app.description}`);
      if (form.app && (form.app.group_id || form.app.target_role)) {
        const g = form.app.group_id ? `Group ID: ${form.app.group_id}` : null;
        const r = form.app.target_role ? `Target Role: ${form.app.target_role}` : null;
        [g, r].forEach(x => { if (x) lines.push(x); });
      }
      if (Array.isArray(form.questions) && form.questions.length > 0) {
        lines.push('');
        lines.push(`Top ${Math.min(6, form.questions.length)} question(s):`);
        form.questions.slice(0, 6).forEach((q, i) => {
          const qText = q.text || q.prompt || `Question ${i + 1}`;
          lines.push(`${i + 1}. ${qText}`);
        });
        if (form.questions.length > 6) lines.push(`...and ${form.questions.length - 6} more.`);
      } else {
        lines.push('');
        lines.push('No questions found in the preview.');
      }
      body.textContent = lines.join('\n');

      const controls = document.createElement('div');
      controls.className = 'polaris-gen-controls';

      const importBtn = document.createElement('button');
      importBtn.className = 'btn btn-primary';
      importBtn.type = 'button';
      importBtn.textContent = 'Import (Replace)';
      importBtn.addEventListener('click', () => {
        try {
          this.importGeneratedForm(form, { replace: true });
          this.showToast('Imported generated form', 'success');
        } catch (e) {
          console.error(e);
          this.showToast('Import failed', 'error');
        }
      });

      const mergeBtn = document.createElement('button');
      mergeBtn.className = 'btn btn-secondary';
      mergeBtn.type = 'button';
      mergeBtn.textContent = 'Merge Questions';
      mergeBtn.addEventListener('click', () => {
        try {
          this.importGeneratedForm(form, { replace: false });
          this.showToast('Merged generated questions', 'success');
        } catch (e) {
          console.error(e);
          this.showToast('Merge failed', 'error');
        }
      });

      const copyBtn = document.createElement('button');
      copyBtn.className = 'btn btn-ghost';
      copyBtn.type = 'button';
      copyBtn.textContent = 'Copy JSON';
      copyBtn.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(JSON.stringify(form, null, 2));
          this.showToast('JSON copied to clipboard', 'success');
        } catch (err) {
          console.error(err);
          this.showToast('Unable to copy to clipboard', 'error');
        }
      });

      const showRawBtn = document.createElement('button');
      showRawBtn.className = 'btn btn-ghost btn-show-raw';
      showRawBtn.type = 'button';
      showRawBtn.textContent = 'Show full JSON';
      showRawBtn.addEventListener('click', () => {
        const previewEl = document.getElementById('polaris-gen-preview');
        if (previewEl) {
          previewEl.innerText = JSON.stringify(form, null, 2);
          previewEl.classList.remove('hidden');
          previewEl.setAttribute('aria-hidden', 'false');
          previewEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
          this.showToast('Preview area not found', 'error');
        }
      });

      controls.appendChild(importBtn);
      controls.appendChild(mergeBtn);
      controls.appendChild(copyBtn);
      controls.appendChild(showRawBtn);

      node.appendChild(header);
      node.appendChild(body);
      node.appendChild(controls);

      area.appendChild(node);
      area.scrollTop = area.scrollHeight;

      const globalCtr = document.getElementById('polaris-gen-controls');
      if (globalCtr) globalCtr.innerHTML = '';
    }

    _renderPolarisImportControls(form) {
      const ctr = document.getElementById('polaris-gen-controls');
      if (!ctr) return;
      ctr.innerHTML = '';
      const importBtn = document.createElement('button'); importBtn.className = 'btn btn-primary'; importBtn.textContent = 'Import (Replace)';
      importBtn.addEventListener('click', () => { this.importGeneratedForm(form, { replace: true }); this.showToast('Imported generated form', 'success'); });
      const mergeBtn = document.createElement('button'); mergeBtn.className = 'btn btn-secondary'; mergeBtn.textContent = 'Merge Questions';
      mergeBtn.addEventListener('click', () => { this.importGeneratedForm(form, { replace: false }); this.showToast('Merged generated questions', 'success'); });
      const copyBtn = document.createElement('button'); copyBtn.className = 'btn btn-ghost'; copyBtn.textContent = 'Copy JSON';
      copyBtn.addEventListener('click', async () => { try { await navigator.clipboard.writeText(JSON.stringify(form, null, 2)); this.showToast('JSON copied to clipboard', 'success'); } catch { this.showToast('Unable to copy to clipboard', 'error'); } });
      ctr.appendChild(importBtn); ctr.appendChild(mergeBtn); ctr.appendChild(copyBtn);
    }

    importGeneratedForm(form, { replace = true } = {}) {
      if (!form || typeof form !== 'object' || !form.app || !Array.isArray(form.questions)) {
        this.showToast('Invalid generated form', 'error');
        return;
      }

      const normalizedQuestions = form.questions.map((q, idx) => this._normalizeQuestion(q, idx));

      if (replace) {
        this.currentApp.id = form.app.id ?? this.currentApp.id;
        this.currentApp.name = form.app.name ?? this.currentApp.name;
        this.currentApp.description = form.app.description ?? this.currentApp.description;
        this.currentApp.group_id = form.app.group_id ?? this.currentApp.group_id;
        this.currentApp.target_role = form.app.target_role ?? this.currentApp.target_role;
        this.currentApp.pass_score = form.app.pass_score ?? this.currentApp.pass_score;
        this.currentApp.style = form.style ?? this.currentApp.style;
        this.questions = normalizedQuestions;
      } else {
        this.questions = (this.questions || []).concat(normalizedQuestions);
        if (form.style) this.currentApp.style = Object.assign({}, this.currentApp.style || {}, form.style);
        if (form.app.name) this.currentApp.name = form.app.name;
        if (form.app.description) this.currentApp.description = form.app.description;
      }

      if (document.getElementById('app-name')) {
        document.getElementById('app-name').value = this.currentApp.name;
        document.getElementById('app-description').value = this.currentApp.description;
        document.getElementById('group-id').value = this.currentApp.group_id;
        document.getElementById('target-role').value = this._extractRankFromTargetRole(this.currentApp.target_role) || this.currentApp.target_role;
        document.getElementById('pass-score').value = this.currentApp.pass_score;
        if (document.getElementById('primary-color')) document.getElementById('primary-color').value = this.currentApp.style.primary_color;
        if (document.getElementById('secondary-color')) document.getElementById('secondary-color').value = this.currentApp.style.secondary_color;
      }

      this.setupColorSwatches();
      this.renderQuestions();
      this.updatePreview();
    }

    _normalizeQuestion(q, idx = 0) {
      const id = q.id ? String(q.id) : ('q' + (Date.now() + idx));
      const type = q.type || 'short_answer';
      const text = q.text || `Question ${idx + 1}`;
      const points = Number.isFinite(q.points) ? q.points : (q.points ? parseInt(q.points, 10) : 5);
      const options = Array.isArray(q.options) ? q.options.map((o, i) => ({ id: o.id || ('opt' + i), text: o.text || `Option ${i + 1}`, correct: !!o.correct })) : [];
      const normalized = { id, type, text, points, options };
      if (type === 'short_answer') normalized.max_length = q.max_length || 300;
      if (type === 'checkboxes' && !normalized.max_score) normalized.max_score = q.max_score || points;
      return normalized;
    }

    async _suggestStructure() {
      const prompt = 'Suggest a concise question structure (type + short prompt + points) for the requested application.';
      const promptEl = document.getElementById('polaris-prompt');
      if (promptEl) promptEl.value = prompt;
      if (window._polarisAPI && typeof window._polarisAPI.sendPrompt === 'function') return window._polarisAPI.sendPrompt();
      return this._sendPolarisPrompt();
    }

    /* Toasts */
    showToast(message, type = 'info', ttl = 3600) {
      let container = document.getElementById('toast-container');
      if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.right = '20px';
        container.style.bottom = '24px';
        container.style.zIndex = '6800';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '10px';
        container.style.alignItems = 'flex-end';
        document.body.appendChild(container);
      }

      const t = document.createElement('div');
      t.className = `toast ${type}`;
      t.style.display = 'flex';
      t.style.gap = '12px';
      t.style.alignItems = 'flex-start';
      t.style.padding = '12px 14px';
      t.style.borderRadius = '12px';
      t.style.minWidth = '220px';
      t.style.maxWidth = '420px';
      t.style.boxShadow = '0 12px 30px rgba(16,24,40,0.08)';
      t.style.fontWeight = '600';
      t.style.color = 'var(--text-strong)';
      t.style.background = 'linear-gradient(180deg,#fff,#fcfdff)';
      if (type === 'success') t.style.borderLeft = '4px solid var(--accent-3)';
      if (type === 'error') { t.style.borderLeft = '4px solid var(--accent-1)'; t.style.background = 'linear-gradient(180deg,#fff6f6,#fff2f2)'; }
      if (type === 'info') t.style.borderLeft = '4px solid var(--accent-2)';

      const msg = document.createElement('div');
      msg.className = 'msg';
      msg.style.flex = '1';
      msg.style.fontSize = '0.95rem';
      msg.innerHTML = this.escapeHtml(message);

      const close = document.createElement('button');
      close.className = 'close';
      close.style.background = 'transparent';
      close.style.border = 'none';
      close.style.cursor = 'pointer';
      close.style.opacity = '0.8';
      close.style.fontSize = '16px';
      close.textContent = '×';
      close.addEventListener('click', () => {
        t.style.opacity = '0';
        setTimeout(() => t.remove(), 240);
      });

      t.appendChild(msg);
      t.appendChild(close);
      container.appendChild(t);

      setTimeout(() => {
        t.style.opacity = '0';
        setTimeout(() => t.remove(), 240);
      }, ttl);
    }

    escapeHtml(t) {
      if (t === undefined || t === null) return '';
      const d = document.createElement('div'); d.textContent = String(t); return d.innerHTML;
    }
  }; // end class AppBuilder

  if (typeof window.appBuilder === 'undefined') {
    try { window.appBuilder = new window.AppBuilder(); } catch (err) { console.error('Failed to initialize appBuilder:', err); }
  }
})();