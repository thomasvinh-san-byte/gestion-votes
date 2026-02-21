/**
 * operator-attendance.js — Attendance & proxy sub-module for the operator console.
 * Requires: utils.js, shared.js, operator-tabs.js (OpS bridge)
 */
(function() {
  'use strict';

  const O = window.OpS;

  // =========================================================================
  // TAB: PRÉSENCES - Attendance
  // =========================================================================

  async function loadAttendance() {
    try {
      const { body } = await api(`/api/v1/attendances.php?meeting_id=${O.currentMeetingId}`);
      O.attendanceCache = body?.data?.attendances || [];
      renderAttendance();
    } catch (err) {
      setNotif('error', 'Erreur chargement présences');
    }
  }

  function renderAttendance() {
    const present = O.attendanceCache.filter(a => a.mode === 'present').length;
    const remote = O.attendanceCache.filter(a => a.mode === 'remote').length;
    const proxyCount = O.proxiesCache.filter(p => !p.revoked_at).length;
    const absent = O.attendanceCache.filter(a => !a.mode || a.mode === 'absent').length;

    O.setText('presStatPresent', present);
    O.setText('presStatRemote', remote);
    O.setText('presStatProxy', proxyCount);
    O.setText('presStatAbsent', absent);
    O.setText('tabCountPresences', present + remote + proxyCount);

    const searchTerm = (document.getElementById('presenceSearch')?.value || '').toLowerCase();
    let filtered = O.attendanceCache;
    if (searchTerm) {
      filtered = O.attendanceCache.filter(a => (a.full_name || '').toLowerCase().includes(searchTerm));
    }

    // Build a map of proxies for quick lookup
    const proxyByGiver = {};
    O.proxiesCache.filter(p => !p.revoked_at).forEach(p => {
      proxyByGiver[p.giver_member_id] = p;
    });

    // Sort: present first, remote second, proxy third, absent last
    filtered = [...filtered].sort((a, b) => {
      const hasProxyA = !!proxyByGiver[a.member_id];
      const hasProxyB = !!proxyByGiver[b.member_id];
      const orderA = a.mode === 'present' ? 0 : a.mode === 'remote' ? 1 : hasProxyA ? 2 : 3;
      const orderB = b.mode === 'present' ? 0 : b.mode === 'remote' ? 1 : hasProxyB ? 2 : 3;
      if (orderA !== orderB) return orderA - orderB;
      return (a.full_name || '').localeCompare(b.full_name || '');
    });

    const grid = document.getElementById('attendanceGrid');
    const isLocked = ['validated', 'archived'].includes(O.currentMeetingStatus);

    grid.innerHTML = filtered.map(m => {
      const mode = m.mode || 'absent';
      const disabled = isLocked ? 'disabled' : '';
      const proxy = proxyByGiver[m.member_id];
      const hasProxy = !!proxy;
      const cardClass = hasProxy ? 'proxy' : mode;
      const proxyTitle = hasProxy ? `Procuration: ${proxy.receiver_name || 'mandataire'}` : 'Ajouter procuration';

      return `
        <div class="attendance-card ${cardClass}" data-member-id="${m.member_id}">
          <span class="attendance-name">${escapeHtml(m.full_name || '—')}</span>
          ${hasProxy ? `<span class="proxy-indicator" title="${escapeHtml(proxyTitle)}">${icon('user-check', 'icon-xs')}</span>` : ''}
          <div class="attendance-mode-btns">
            <button class="mode-btn present ${mode === 'present' ? 'active' : ''}" data-mode="present" ${disabled} title="Présent">P</button>
            <button class="mode-btn remote ${mode === 'remote' ? 'active' : ''}" data-mode="remote" ${disabled} title="Distant">D</button>
            <button class="mode-btn absent ${mode === 'absent' ? 'active' : ''}" data-mode="absent" ${disabled} title="Absent">A</button>
          </div>
        </div>
      `;
    }).join('') || '<div class="text-center p-4 text-muted">Aucun membre</div>';

    if (!isLocked) {
      grid.querySelectorAll('.mode-btn:not([disabled])').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          const card = e.target.closest('.attendance-card');
          const memberId = card.dataset.memberId;
          const mode = btn.dataset.mode;
          await updateAttendance(memberId, mode);
        });
      });
    }
  }

  async function updateAttendance(memberId, mode) {
    const m = O.attendanceCache.find(a => String(a.member_id) === String(memberId));
    const prevMode = m ? m.mode : undefined;

    // Optimistic update for instant feedback
    if (m) m.mode = mode;
    renderAttendance();
    O.fn.updateQuickStats();

    try {
      const { body } = await api('/api/v1/attendances_upsert.php', {
        meeting_id: O.currentMeetingId,
        member_id: memberId,
        mode: mode
      });
      if (body?.ok === true) {
        O.fn.loadStatusChecklist();
        O.fn.checkLaunchReady();
      } else {
        // Rollback on API error
        if (m) m.mode = prevMode;
        renderAttendance();
        O.fn.updateQuickStats();
        setNotif('error', getApiError(body, 'Erreur de mise à jour'));
      }
    } catch (err) {
      // Rollback on network error
      if (m) m.mode = prevMode;
      renderAttendance();
      O.fn.updateQuickStats();
      setNotif('error', err.message);
    }
  }

  async function markAllPresent() {
    const confirmed = await O.confirmModal({
      title: 'Marquer tous présents',
      body: '<p>Marquer tous les membres comme <strong>présents</strong> ?</p>'
    });
    if (!confirmed) return;
    try {
      await api('/api/v1/attendances_bulk.php', { meeting_id: O.currentMeetingId, mode: 'present' });
      O.attendanceCache.forEach(m => m.mode = 'present');
      renderAttendance();
      setNotif('success', 'Tous marqués présents');
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  function showImportCSVModal() {
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:600px;width:90%;max-height:90vh;overflow:auto;">
        <h3 style="margin:0 0 1rem;"><svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-download"></use></svg> Importer des membres (CSV)</h3>
        <p class="text-muted text-sm mb-3">
          Format attendu: <code>name,email,voting_power</code> (en-tête requis).<br>
          L'email et le nombre de voix sont optionnels.
        </p>
        <div class="form-group mb-3">
          <label class="form-label">Fichier CSV</label>
          <input type="file" class="form-input" id="csvFileInput" accept=".csv,.txt">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Ou coller le contenu</label>
          <textarea class="form-input" id="csvTextInput" rows="4" placeholder="name,email,voting_power\nJean Dupont,jean@exemple.com,1\nMarie Martin,,2"></textarea>
        </div>
        <div id="csvPreviewContainer" style="display:none;" class="mb-4"></div>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary" id="btnCancelImport">Annuler</button>
          <button class="btn btn-outline" id="btnPreviewCSV">Aperçu</button>
          <button class="btn btn-primary" id="btnConfirmImport" disabled>Importer</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    let parsedData = null;

    const previewContainer = document.getElementById('csvPreviewContainer');
    const btnPreview = document.getElementById('btnPreviewCSV');
    const btnConfirm = document.getElementById('btnConfirmImport');

    async function getCSVContent() {
      const fileInput = document.getElementById('csvFileInput');
      const textInput = document.getElementById('csvTextInput');
      let csvContent = textInput.value.trim();

      if (fileInput.files.length > 0) {
        csvContent = await fileInput.files[0].text();
      }
      return csvContent;
    }

    btnPreview.addEventListener('click', async () => {
      const csvContent = await getCSVContent();

      if (!csvContent) {
        setNotif('error', 'Aucun contenu à prévisualiser');
        return;
      }

      parsedData = Utils.parseCSV(csvContent);
      const previewHtml = Utils.generateCSVPreview(parsedData);

      previewContainer.innerHTML = previewHtml;
      Shared.show(previewContainer, 'block');

      const hasValidRows = parsedData.rows.some(r => r.name);
      btnConfirm.disabled = !hasValidRows;

      if (!hasValidRows) {
        setNotif('warning', 'Aucune donnée valide trouvée');
      }
    });

    document.getElementById('csvFileInput').addEventListener('change', () => {
      btnPreview.click();
    });

    document.getElementById('btnCancelImport').addEventListener('click', () => modal.remove());

    btnConfirm.addEventListener('click', async () => {
      const csvContent = await getCSVContent();

      if (!csvContent) {
        setNotif('error', 'Aucun contenu à importer');
        return;
      }

      try {
        btnConfirm.disabled = true;
        btnConfirm.textContent = 'Import en cours...';

        const formData = new FormData();
        formData.append('csv_content', csvContent);

        const csrfHeaders = (window.Utils && window.Utils.getCsrfToken) ? { 'X-CSRF-Token': window.Utils.getCsrfToken() } : {};
        const resp = await fetch('/api/v1/members_import_csv.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: csrfHeaders
        });
        const data = await resp.json();

        if (data.ok) {
          const count = data.data?.imported || 0;
          setNotif('success', `${count} membre(s) importé(s)`);
          modal.remove();
          O.fn.loadMembers();
          loadAttendance();
          O.fn.loadStatusChecklist();
        } else {
          setNotif('error', data.error || 'Erreur import');
          btnConfirm.disabled = false;
          btnConfirm.textContent = 'Importer';
        }
      } catch (err) {
        setNotif('error', err.message);
        btnConfirm.disabled = false;
        btnConfirm.textContent = 'Importer';
      }
    });
  }

  // =========================================================================
  // TAB: PRÉSENCES - Proxies Management
  // =========================================================================

  async function loadProxies() {
    if (!O.currentMeetingId) return;

    try {
      const { body } = await api(`/api/v1/proxies.php?meeting_id=${O.currentMeetingId}`);
      O.proxiesCache = body?.data?.proxies || body?.proxies || [];
      renderProxies();
    } catch (err) {
      setNotif('error', 'Erreur chargement procurations');
    }
  }

  function renderProxies() {
    const list = document.getElementById('proxyList');
    if (!list) return;

    const searchTerm = (document.getElementById('proxySearch')?.value || '').toLowerCase();
    const activeProxies = O.proxiesCache.filter(p => !p.revoked_at);

    let filtered = activeProxies;
    if (searchTerm) {
      filtered = activeProxies.filter(p =>
        (p.giver_name || '').toLowerCase().includes(searchTerm) ||
        (p.receiver_name || '').toLowerCase().includes(searchTerm)
      );
    }

    const uniqueReceivers = new Set(activeProxies.map(p => p.receiver_member_id));
    const proxyStatActiveEl = document.getElementById('proxyStatActive');
    const proxyStatGiversEl = document.getElementById('proxyStatGivers');
    const proxyStatReceiversEl = document.getElementById('proxyStatReceivers');
    if (proxyStatActiveEl) proxyStatActiveEl.textContent = activeProxies.length;
    if (proxyStatGiversEl) proxyStatGiversEl.textContent = activeProxies.length;
    if (proxyStatReceiversEl) proxyStatReceiversEl.textContent = uniqueReceivers.size;

    const tabCount = document.getElementById('tabCountProxies');
    if (tabCount) tabCount.textContent = activeProxies.length;

    if (filtered.length === 0) {
      list.innerHTML = searchTerm
        ? '<div class="text-center p-4 text-muted">Aucune procuration ne correspond à la recherche</div>'
        : '<div class="text-center p-4 text-muted">Aucune procuration</div>';
      return;
    }

    const isLocked = ['validated', 'archived'].includes(O.currentMeetingStatus);

    list.innerHTML = filtered.map(p => `
      <div class="proxy-item" data-proxy-id="${p.id}" data-giver-id="${p.giver_member_id}">
        <div class="proxy-item-info">
          <span class="proxy-giver">${escapeHtml(p.giver_name || '—')}</span>
          <span class="proxy-arrow">${icon('arrow-right', 'icon-sm')}</span>
          <span class="proxy-receiver">${escapeHtml(p.receiver_name || '—')}</span>
        </div>
        ${!isLocked ? `
          <button class="btn btn-sm btn-ghost text-danger btn-revoke-proxy" data-giver-id="${p.giver_member_id}" title="Révoquer">
            ${icon('x', 'icon-sm')}
          </button>
        ` : ''}
      </div>
    `).join('');

    if (!isLocked) {
      list.querySelectorAll('.btn-revoke-proxy').forEach(btn => {
        btn.addEventListener('click', () => revokeProxy(btn.dataset.giverId));
      });
    }
  }

  async function revokeProxy(giverId) {
    const confirmed = await O.confirmModal({
      title: 'Révoquer la procuration',
      body: '<p>Révoquer cette procuration ?</p>',
      confirmText: 'Révoquer',
      confirmClass: 'btn-danger'
    });
    if (!confirmed) return;

    try {
      const { body } = await api('/api/v1/proxies_upsert.php', {
        meeting_id: O.currentMeetingId,
        giver_member_id: giverId,
        receiver_member_id: ''
      });

      if (body?.ok || body?.revoked) {
        setNotif('success', 'Procuration révoquée');
        await loadProxies();
        renderAttendance();
        O.fn.updateQuickStats();
      } else {
        setNotif('error', getApiError(body, 'Erreur lors de la révocation'));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  function showAddProxyModal() {
    try {
      if (!O.attendanceCache || O.attendanceCache.length === 0) {
        setNotif('warning', 'Données de présence non chargées. Veuillez sélectionner une séance.');
        return;
      }

      const giverIds = new Set(O.proxiesCache.filter(p => !p.revoked_at).map(p => p.giver_member_id));
      const presentIds = new Set(O.attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').map(a => a.member_id));

      const potentialGivers = O.attendanceCache.filter(a =>
        !presentIds.has(a.member_id) && !giverIds.has(a.member_id)
      );

      const potentialReceivers = O.attendanceCache.filter(a =>
        a.mode === 'present' || a.mode === 'remote'
      );

      const modal = document.createElement('div');
      modal.className = 'modal-backdrop';
      modal.setAttribute('role', 'dialog');
      modal.setAttribute('aria-modal', 'true');
      modal.setAttribute('aria-labelledby', 'addProxyModalTitle');
      modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

      modal.innerHTML = `
        <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem 1.75rem;max-width:500px;width:90%;" role="document">
          <h3 id="addProxyModalTitle" style="margin:0 0 1rem;">${icon('user-check', 'icon-sm icon-text')} Nouvelle procuration</h3>
          <p class="text-muted text-sm mb-4">Le mandant (absent) donne procuration au mandataire (présent) pour voter à sa place.</p>

          ${potentialGivers.length === 0 ? `
            <div class="alert alert-warning mb-4">${icon('info', 'icon-sm icon-text')} Tous les membres absents ont déjà une procuration ou sont présents.</div>
          ` : ''}

          ${potentialReceivers.length === 0 ? `
            <div class="alert alert-warning mb-4">${icon('info', 'icon-sm icon-text')} Aucun membre présent pour recevoir une procuration.</div>
          ` : ''}

          <div class="form-group mb-3">
            <label class="form-label">Mandant (qui donne procuration)</label>
            <ag-searchable-select
              id="proxyGiverSelect"
              placeholder="Rechercher un membre absent..."
              empty-text="Aucun membre trouvé"
              ${potentialGivers.length === 0 ? 'disabled' : ''}
            ></ag-searchable-select>
          </div>

          <div class="form-group mb-3">
            <label class="form-label">Mandataire (qui vote à sa place)</label>
            <ag-searchable-select
              id="proxyReceiverSelect"
              placeholder="Rechercher un membre présent..."
              empty-text="Aucun membre trouvé"
              ${potentialReceivers.length === 0 ? 'disabled' : ''}
            ></ag-searchable-select>
          </div>

          <div class="flex gap-2 justify-end">
            <button class="btn btn-secondary" id="btnCancelProxy">Annuler</button>
            <button class="btn btn-primary" id="btnConfirmProxy" ${potentialGivers.length === 0 || potentialReceivers.length === 0 ? 'disabled' : ''}>Créer la procuration</button>
          </div>
        </div>
      `;

      document.body.appendChild(modal);
      modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });

      const giverSelect = document.getElementById('proxyGiverSelect');
      const receiverSelect = document.getElementById('proxyReceiverSelect');

      if (giverSelect && giverSelect.setOptions) {
        const giverOptions = potentialGivers.map(m => ({
          value: m.member_id,
          label: m.full_name || '—',
          sublabel: m.email || ''
        }));
        giverSelect.setOptions(giverOptions);
      }

      if (receiverSelect && receiverSelect.setOptions) {
        const receiverOptions = potentialReceivers.map(m => ({
          value: m.member_id,
          label: m.full_name || '—',
          sublabel: (m.email || '') + (m.mode === 'present' ? ' — Présent' : ' — Distant')
        }));
        receiverSelect.setOptions(receiverOptions);
      }

      const btnCancel = document.getElementById('btnCancelProxy');
      const btnConfirm = document.getElementById('btnConfirmProxy');

      if (btnCancel) btnCancel.addEventListener('click', () => modal.remove());
      if (btnConfirm) btnConfirm.addEventListener('click', async () => {
        const giverId = document.getElementById('proxyGiverSelect').value;
        const receiverId = document.getElementById('proxyReceiverSelect').value;

        if (!giverId || !receiverId) {
          setNotif('error', 'Veuillez sélectionner le mandant et le mandataire');
          return;
        }

        if (giverId === receiverId) {
          setNotif('error', 'Le mandant et le mandataire doivent être différents');
          return;
        }

        btnConfirm.disabled = true;
        btnCancel.disabled = true;
        const originalText = btnConfirm.textContent;
        btnConfirm.textContent = 'Création...';

        try {
          const { body } = await api('/api/v1/proxies_upsert.php', {
            meeting_id: O.currentMeetingId,
            giver_member_id: giverId,
            receiver_member_id: receiverId
          });

          if (body?.ok) {
            setNotif('success', 'Procuration créée');
            modal.remove();
            await loadProxies();
            renderAttendance();
            O.fn.updateQuickStats();
          } else {
            setNotif('error', getApiError(body, 'Erreur lors de la création'));
            btnConfirm.disabled = false;
            btnCancel.disabled = false;
            btnConfirm.textContent = originalText;
          }
        } catch (err) {
          setNotif('error', err.message);
          btnConfirm.disabled = false;
          btnCancel.disabled = false;
          btnConfirm.textContent = originalText;
        }
      });
    } catch (err) {
      setNotif('error', 'Erreur lors de l\'ouverture du formulaire: ' + err.message);
    }
  }

  function showImportProxiesCSVModal() {
    if (!O.currentMeetingId) {
      setNotif('warning', 'Veuillez sélectionner une séance.');
      return;
    }
    if (!O.attendanceCache || O.attendanceCache.length === 0) {
      setNotif('warning', 'Données de présence non chargées. Veuillez patienter ou recharger la page.');
      return;
    }

    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'importProxiesModalTitle');
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:600px;width:90%;max-height:90vh;overflow:auto;" role="document">
        <h3 id="importProxiesModalTitle" style="margin:0 0 1rem;">${icon('download', 'icon-sm icon-text')} Importer des procurations (CSV)</h3>
        <p class="text-muted text-sm mb-3">
          Format attendu: <code>giver_email,receiver_email</code> (en-tête requis).<br>
          Les emails doivent correspondre aux membres existants.
        </p>
        <div class="form-group mb-3">
          <label class="form-label">Fichier CSV</label>
          <input type="file" class="form-input" id="csvProxyFileInput" accept=".csv,.txt">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Ou coller le contenu</label>
          <textarea class="form-input" id="csvProxyTextInput" rows="4" placeholder="giver_email,receiver_email\nabsent@exemple.com,present@exemple.com"></textarea>
        </div>
        <div id="csvProxyPreviewContainer" style="display:none;" class="mb-4"></div>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary" id="btnCancelProxyImport">Annuler</button>
          <button class="btn btn-outline" id="btnPreviewProxyCSV">Aperçu</button>
          <button class="btn btn-primary" id="btnConfirmProxyImport" disabled>Importer</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    const previewContainer = document.getElementById('csvProxyPreviewContainer');
    const btnPreview = document.getElementById('btnPreviewProxyCSV');
    const btnConfirm = document.getElementById('btnConfirmProxyImport');

    async function getCSVContent() {
      const fileInput = document.getElementById('csvProxyFileInput');
      const textInput = document.getElementById('csvProxyTextInput');
      let csvContent = textInput.value.trim();

      if (fileInput.files.length > 0) {
        csvContent = await fileInput.files[0].text();
      }
      return csvContent;
    }

    btnPreview.addEventListener('click', async () => {
      const csvContent = await getCSVContent();
      if (!csvContent) {
        setNotif('error', 'Aucun contenu à prévisualiser');
        return;
      }

      const parsed = Utils.parseCSV(csvContent);
      let html = '<div style="max-height:200px;overflow:auto;"><table class="table table-sm"><thead><tr><th>Mandant</th><th>Mandataire</th></tr></thead><tbody>';
      let validCount = 0;

      for (const row of parsed.rows) {
        const giver = row.giver_email || row[Object.keys(row)[0]] || '';
        const receiver = row.receiver_email || row[Object.keys(row)[1]] || '';
        if (giver && receiver) {
          html += '<tr><td>' + escapeHtml(giver) + '</td><td>' + escapeHtml(receiver) + '</td></tr>';
          validCount++;
        }
      }

      html += '</tbody></table></div>';
      html += '<p class="text-sm text-muted mt-2">' + validCount + ' procuration(s) trouvée(s)</p>';

      previewContainer.innerHTML = html;
      Shared.show(previewContainer, 'block');
      btnConfirm.disabled = validCount === 0;
    });

    document.getElementById('csvProxyFileInput').addEventListener('change', () => btnPreview.click());
    document.getElementById('btnCancelProxyImport').addEventListener('click', () => modal.remove());

    btnConfirm.addEventListener('click', async () => {
      const csvContent = await getCSVContent();
      if (!csvContent) {
        setNotif('error', 'Aucun contenu à importer');
        return;
      }

      try {
        btnConfirm.disabled = true;
        btnConfirm.textContent = 'Import en cours...';

        const formData = new FormData();
        formData.append('meeting_id', O.currentMeetingId);
        formData.append('csv_content', csvContent);

        const csrfHeaders2 = (window.Utils && window.Utils.getCsrfToken) ? { 'X-CSRF-Token': window.Utils.getCsrfToken() } : {};
        const resp = await fetch('/api/v1/proxies_import_csv.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: csrfHeaders2
        });
        const data = await resp.json();

        if (data.ok) {
          const count = data.data?.imported || data.imported || 0;
          setNotif('success', count + ' procuration(s) importée(s)');
          modal.remove();
          await loadProxies();
          renderAttendance();
          O.fn.updateQuickStats();
        } else {
          setNotif('error', data.error || 'Erreur import');
          btnConfirm.disabled = false;
          btnConfirm.textContent = 'Importer';
        }
      } catch (err) {
        setNotif('error', err.message);
        btnConfirm.disabled = false;
        btnConfirm.textContent = 'Importer';
      }
    });
  }

  // Register on OpS — overwrites the stubs from operator-tabs.js
  O.fn.loadAttendance           = loadAttendance;
  O.fn.renderAttendance         = renderAttendance;
  O.fn.updateAttendance         = updateAttendance;
  O.fn.markAllPresent           = markAllPresent;
  O.fn.showImportCSVModal       = showImportCSVModal;
  O.fn.loadProxies              = loadProxies;
  O.fn.renderProxies            = renderProxies;
  O.fn.revokeProxy              = revokeProxy;
  O.fn.showAddProxyModal        = showAddProxyModal;
  O.fn.showImportProxiesCSVModal = showImportProxiesCSVModal;

})();
