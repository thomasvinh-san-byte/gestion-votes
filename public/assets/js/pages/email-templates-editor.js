    const API_BASE = '/api/v1';
    let templates = [];
    let availableVariables = {};

    // DOM elements
    const templatesGrid = document.getElementById('templatesGrid');
    const emptyState = document.getElementById('emptyState');
    const filterType = document.getElementById('filterType');
    const templateEditor = document.getElementById('templateEditor');
    const previewFrame = document.getElementById('previewFrame');
    const variablesList = document.getElementById('variablesList');

    // Load templates
    async function loadTemplates() {
      try {
        const type = filterType.value;
        const url = type
          ? `${API_BASE}/email_templates.php?type=${type}&include_variables=1`
          : `${API_BASE}/email_templates.php?include_variables=1`;

        const resp = await fetch(url, { credentials: 'include' });
        const data = await resp.json();

        if (!data.ok) throw new Error(data.error || 'Erreur chargement');

        templates = data.data.templates || [];
        availableVariables = data.data.available_variables || {};

        renderTemplates();
        renderVariables();
      } catch (err) {
        console.error('Load templates error:', err);
        window.showToast?.('Erreur chargement templates', 'error');
      }
    }

    // Render templates grid
    function renderTemplates() {
      if (templates.length === 0) {
        templatesGrid.innerHTML = '';
        emptyState.style.display = 'block';
        return;
      }

      emptyState.style.display = 'none';

      templatesGrid.innerHTML = templates.map(t => `
        <div class="template-card ${t.is_default ? 'is-default' : ''}" data-id="${escapeHtml(t.id)}">
          <div class="template-card-header">
            <div class="template-card-title">
              ${escapeHtml(t.name)}
              ${t.is_default ? '<span class="badge badge-sm ml-2">Par d\u00e9faut</span>' : ''}
            </div>
            <span class="template-card-type ${escapeHtml(t.template_type)}">${escapeHtml(t.template_type)}</span>
          </div>
          <div class="template-card-body">
            <div class="template-card-subject">
              <strong>Sujet:</strong> ${escapeHtml(t.subject)}
            </div>
            <div class="template-card-preview">
              ${escapeHtml(stripHtml(t.body_html).substring(0, 150))}...
            </div>
          </div>
          <div class="template-card-footer">
            <button class="btn btn-sm btn-secondary flex-1" data-action="edit" data-id="${escapeHtml(t.id)}">
              <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-edit"></use></svg>
              Modifier
            </button>
            <button class="btn btn-sm btn-secondary" data-action="duplicate" data-id="${escapeHtml(t.id)}" title="Dupliquer">
              <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-copy"></use></svg>
            </button>
            <button class="btn btn-sm btn-danger" data-action="delete" data-id="${escapeHtml(t.id)}" ${t.is_default ? 'disabled title="Impossible de supprimer le template par d\u00e9faut"' : ''}>
              <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-trash"></use></svg>
            </button>
          </div>
        </div>
      `).join('');
    }

    // Render variables helper
    function renderVariables() {
      variablesList.innerHTML = Object.entries(availableVariables).map(([v, desc]) => `
        <span class="variable-tag" data-action="insert-var" data-var="${escapeHtml(v)}" title="${escapeHtml(desc)}">${escapeHtml(v)}</span>
      `).join('');
    }

    // Insert variable at cursor
    function insertVariable(variable) {
      const textarea = document.getElementById('templateBody');
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const text = textarea.value;

      textarea.value = text.substring(0, start) + variable + text.substring(end);
      textarea.focus();
      textarea.selectionStart = textarea.selectionEnd = start + variable.length;
    }

    // Open editor for new template
    function openNewEditor() {
      document.getElementById('templateId').value = '';
      document.getElementById('templateName').value = '';
      document.getElementById('templateType').value = 'invitation';
      document.getElementById('templateSubject').value = 'Invitation de vote - {{meeting_title}}';
      document.getElementById('templateBody').value = '';
      document.getElementById('templateIsDefault').checked = false;
      document.getElementById('editorTitle').textContent = 'Nouveau template';
      document.getElementById('editorStatus').textContent = '';

      templateEditor.classList.add('active');
      updatePreview();
    }

    // Edit existing template
    async function editTemplate(id) {
      try {
        const resp = await fetch(`${API_BASE}/email_templates.php?id=${id}`, { credentials: 'include' });
        const data = await resp.json();

        if (!data.ok) throw new Error(data.error || 'Template non trouve');

        const t = data.data.template;

        document.getElementById('templateId').value = t.id;
        document.getElementById('templateName').value = t.name;
        document.getElementById('templateType').value = t.template_type;
        document.getElementById('templateSubject').value = t.subject;
        document.getElementById('templateBody').value = t.body_html;
        document.getElementById('templateIsDefault').checked = t.is_default;
        document.getElementById('editorTitle').textContent = 'Modifier: ' + t.name;
        document.getElementById('editorStatus').textContent = '';

        templateEditor.classList.add('active');
        updatePreview();
      } catch (err) {
        window.showToast?.('Erreur: ' + err.message, 'error');
      }
    }

    // Save template
    async function saveTemplate() {
      const id = document.getElementById('templateId').value;
      const name = document.getElementById('templateName').value.trim();
      const type = document.getElementById('templateType').value;
      const subject = document.getElementById('templateSubject').value.trim();
      const bodyHtml = document.getElementById('templateBody').value.trim();
      const isDefault = document.getElementById('templateIsDefault').checked;

      if (!name || !subject || !bodyHtml) {
        window.showToast?.('Veuillez remplir tous les champs obligatoires', 'error');
        return;
      }

      document.getElementById('editorStatus').textContent = 'Enregistrement...';

      try {
        const method = id ? 'PUT' : 'POST';
        const url = id ? `${API_BASE}/email_templates.php?id=${id}` : `${API_BASE}/email_templates.php`;

        const resp = await fetch(url, {
          method,
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            name,
            template_type: type,
            subject,
            body_html: bodyHtml,
            is_default: isDefault
          })
        });

        const data = await resp.json();
        if (!data.ok) throw new Error(data.message || data.error || 'Erreur sauvegarde');

        window.showToast?.('Template enregistr\u00e9', 'success');
        templateEditor.classList.remove('active');
        loadTemplates();
      } catch (err) {
        document.getElementById('editorStatus').textContent = 'Erreur: ' + err.message;
        window.showToast?.('Erreur: ' + err.message, 'error');
      }
    }

    // Delete template
    function deleteTemplate(id) {
      const tpl = templates.find(t => t.id === id);
      const tplName = tpl ? tpl.name : 'ce template';

      Shared.openModal({
        title: 'Supprimer le template',
        body: '<p>Supprimer le template \u00ab <strong>' + escapeHtml(tplName) + '</strong> \u00bb ?</p>',
        confirmText: 'Supprimer',
        confirmClass: 'btn btn-danger',
        onConfirm: async function() {
          try {
            const resp = await fetch(`${API_BASE}/email_templates.php?id=${encodeURIComponent(id)}`, {
              method: 'DELETE',
              credentials: 'include'
            });
            const data = await resp.json();
            if (!data.ok) throw new Error(data.message || data.error || 'Erreur suppression');
            window.showToast?.('Template supprim\u00e9', 'success');
            loadTemplates();
          } catch (err) {
            window.showToast?.('Erreur: ' + err.message, 'error');
          }
        }
      });
    }

    // Duplicate template
    function duplicateTemplate(id) {
      const tpl = templates.find(t => t.id === id);
      const defaultName = tpl ? tpl.name + ' (copie)' : '';

      Shared.openModal({
        title: 'Dupliquer le template',
        body: '<div class="form-group"><label class="form-label">Nom du nouveau template</label>' +
          '<input class="form-input" type="text" id="dupTemplateName" value="' + escapeHtml(defaultName) + '"></div>',
        confirmText: 'Dupliquer',
        onConfirm: async function(modal) {
          var newName = modal.querySelector('#dupTemplateName').value.trim();
          if (!newName) { window.showToast?.('Nom requis', 'error'); return false; }
          try {
            const resp = await fetch(`${API_BASE}/email_templates.php`, {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'duplicate', source_id: id, new_name: newName })
            });
            const data = await resp.json();
            if (!data.ok) throw new Error(data.message || data.error || 'Erreur duplication');
            window.showToast?.('Template dupliqu\u00e9', 'success');
            loadTemplates();
          } catch (err) {
            window.showToast?.('Erreur: ' + err.message, 'error');
          }
        }
      });
    }

    // Create default templates
    function createDefaults() {
      Shared.openModal({
        title: 'Cr\u00e9er les templates par d\u00e9faut',
        body: '<p>Cr\u00e9er les templates par d\u00e9faut (invitation et rappel) ?</p>',
        confirmText: 'Cr\u00e9er',
        onConfirm: async function() {
          try {
            const resp = await fetch(`${API_BASE}/email_templates.php`, {
              method: 'POST',
              credentials: 'include',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'create_defaults' })
            });
            const data = await resp.json();
            if (!data.ok) throw new Error(data.message || data.error || 'Erreur creation');
            window.showToast?.((data.data.count || 0) + ' templates cr\u00e9\u00e9s', 'success');
            loadTemplates();
          } catch (err) {
            window.showToast?.('Erreur: ' + err.message, 'error');
          }
        }
      });
    }

    // Update preview
    async function updatePreview() {
      const bodyHtml = document.getElementById('templateBody').value;
      if (!bodyHtml) {
        previewFrame.srcdoc = '<p style="padding:20px;color:#666;">Entrez du contenu HTML pour voir la pr\u00e9visualisation</p>';
        return;
      }

      try {
        const resp = await fetch(`${API_BASE}/email_templates_preview.php`, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ body_html: bodyHtml })
        });

        const data = await resp.json();
        if (data.ok) {
          previewFrame.srcdoc = data.data.preview_html;
        }
      } catch (err) {
        console.error('Preview error:', err);
      }
    }

    // Helpers
    function escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }

    function stripHtml(html) {
      if (!html) return '';
      return html.replace(/<[^>]*>/g, '');
    }

    // Event delegation for template cards and variable tags
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('[data-action]');
      if (!btn) return;
      var action = btn.dataset.action;
      var id = btn.dataset.id;

      switch (action) {
        case 'edit': editTemplate(id); break;
        case 'duplicate': duplicateTemplate(id); break;
        case 'delete': deleteTemplate(id); break;
        case 'insert-var': insertVariable(btn.dataset.var); break;
      }
    });

    // Event listeners
    document.getElementById('btnNewTemplate').addEventListener('click', openNewEditor);
    document.getElementById('btnEmptyCreate').addEventListener('click', openNewEditor);
    document.getElementById('btnCloseEditor').addEventListener('click', () => templateEditor.classList.remove('active'));
    document.getElementById('btnCancelEdit').addEventListener('click', () => templateEditor.classList.remove('active'));
    document.getElementById('btnSaveTemplate').addEventListener('click', saveTemplate);
    document.getElementById('btnCreateDefaults').addEventListener('click', createDefaults);
    document.getElementById('btnRefreshPreview').addEventListener('click', updatePreview);
    filterType.addEventListener('change', loadTemplates);

    // Debounced preview update
    let previewTimeout;
    document.getElementById('templateBody').addEventListener('input', () => {
      clearTimeout(previewTimeout);
      previewTimeout = setTimeout(updatePreview, 500);
    });

    // Close modal on escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && templateEditor.classList.contains('active')) {
        templateEditor.classList.remove('active');
      }
    });

    // Close modal on backdrop click
    templateEditor.addEventListener('click', (e) => {
      if (e.target === templateEditor) {
        templateEditor.classList.remove('active');
      }
    });

    // Init
    loadTemplates();
