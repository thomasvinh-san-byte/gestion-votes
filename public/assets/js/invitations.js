/** invitations.js — Invitation sending page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
  'use strict';

  const meetingId = new URLSearchParams(location.search).get('meeting_id');

  // Elements
  const meetingTitle = document.getElementById('meetingTitle');
  const kpiTotal = document.getElementById('kpiTotal');
  const kpiSent = document.getElementById('kpiSent');
  const kpiPending = document.getElementById('kpiPending');
  const kpiFailed = document.getElementById('kpiFailed');
  const resultArea = document.getElementById('resultArea');
  const statusBadge = document.getElementById('statusBadge');
  const membersList = document.getElementById('membersList');
  const searchInput = document.getElementById('searchInput');
  const filterSelect = document.getElementById('filterSelect');
  const btnPreview = document.getElementById('btnPreview');
  const btnSend = document.getElementById('btnSend');

  let allMembers = [];

  // Load meeting info
  async function loadMeeting() {
    if (!meetingId) {
      meetingTitle.innerHTML = `${icon('alert-triangle', 'icon-sm icon-warning')} Aucune séance sélectionnée`;
      return;
    }

    const { body } = await api(`/api/v1/meetings.php?id=${meetingId}`);
    if (body?.ok && body.data) {
      meetingTitle.textContent = body.data.title || 'Séance';
    }
  }

  // Load invitations status
  async function loadInvitations() {
    if (!meetingId) {
      membersList.innerHTML = '<div class="alert alert-warning">Sélectionnez une séance (meeting_id manquant)</div>';
      return;
    }

    const { body } = await api(`/api/v1/invitations_list.php?meeting_id=${meetingId}`);

    if (body?.ok) {
      allMembers = body.data || body.invitations || [];
      updateKPIs();
      renderMembers();
    } else {
      membersList.innerHTML = `<div class="alert alert-danger">${body?.error || 'Erreur de chargement'}</div>`;
    }
  }

  function updateKPIs() {
    const total = allMembers.length;
    const sent = allMembers.filter(m => m.invitation_sent_at).length;
    const failed = allMembers.filter(m => m.invitation_failed).length;
    const pending = total - sent - failed;

    kpiTotal.textContent = total;
    kpiSent.textContent = sent;
    kpiPending.textContent = pending;
    kpiFailed.textContent = failed;
  }

  function renderMembers() {
    const search = searchInput.value.toLowerCase();
    const filter = filterSelect.value;

    let filtered = allMembers.filter(m => {
      const matchSearch = !search ||
        (m.full_name || '').toLowerCase().includes(search) ||
        (m.email || '').toLowerCase().includes(search);

      let matchFilter = true;
      if (filter === 'sent') matchFilter = !!m.invitation_sent_at;
      else if (filter === 'pending') matchFilter = !m.invitation_sent_at && !m.invitation_failed;
      else if (filter === 'failed') matchFilter = !!m.invitation_failed;

      return matchSearch && matchFilter;
    });

    if (filtered.length === 0) {
      membersList.innerHTML = `
        <div class="empty-state">
          <div class="empty-state-icon">${icon('users', 'icon-xl')}</div>
          <h3 class="empty-state-title">Aucun membre</h3>
          <p class="empty-state-description">Aucun membre ne correspond aux critères</p>
        </div>
      `;
      return;
    }

    membersList.innerHTML = filtered.map(m => {
      const initials = (m.full_name || 'U').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

      let statusHtml = '';
      if (m.invitation_sent_at) {
        const date = new Date(m.invitation_sent_at).toLocaleDateString('fr-FR');
        statusHtml = `<span class="badge badge-success">✓ Envoyé ${date}</span>`;
      } else if (m.invitation_failed) {
        statusHtml = `<span class="badge badge-danger">✗ Échec</span>`;
      } else {
        statusHtml = `<span class="badge badge-warning">En attente</span>`;
      }

      return `
        <div class="invitation-row">
          <div class="invitation-avatar">${initials}</div>
          <div class="invitation-info">
            <div class="invitation-name">${escapeHtml(m.full_name || 'Inconnu')}</div>
            <div class="invitation-email">${escapeHtml(m.email || '—')}</div>
          </div>
          <div class="invitation-status">
            ${statusHtml}
          </div>
        </div>
      `;
    }).join('');
  }

  // Send invitations
  async function sendInvitations(dryRun = false) {
    if (!meetingId) {
      showResult('error', 'meeting_id manquant');
      return;
    }

    const onlyUnsent = document.getElementById('onlyUnsent').checked;
    const includeProxy = document.getElementById('includeProxy').checked;
    const limit = parseInt(document.getElementById('limitInput').value || '0', 10);
    const subject = document.getElementById('subjectInput').value.trim();

    statusBadge.textContent = dryRun ? 'Aperçu...' : 'Envoi...';
    statusBadge.className = 'badge badge-warning';

    const activeBtn = dryRun ? btnPreview : btnSend;
    Shared.btnLoading(activeBtn, true);

    try {
      const { body } = await api('/api/v1/invitations_send_bulk.php', {
        meeting_id: meetingId,
        dry_run: dryRun,
        only_unsent: onlyUnsent,
        include_proxy: includeProxy,
        limit: limit || 0,
        subject: subject || undefined
      });

      if (body?.ok) {
        statusBadge.textContent = dryRun ? 'Aperçu OK' : 'Envoyé !';
        statusBadge.className = 'badge badge-success';
        showResult('success', body);

        if (!dryRun) {
          // Reload après envoi réel
          setTimeout(loadInvitations, 1000);
        }
      } else {
        statusBadge.textContent = 'Erreur';
        statusBadge.className = 'badge badge-danger';
        showResult('error', body?.error || 'Erreur inconnue');
      }
    } catch (e) {
      statusBadge.textContent = 'Erreur';
      statusBadge.className = 'badge badge-danger';
      showResult('error', e.message);
    } finally {
      Shared.btnLoading(activeBtn, false);
    }
  }

  function showResult(type, data) {
    if (type === 'error') {
      resultArea.innerHTML = `<div class="alert alert-danger">${typeof data === 'string' ? escapeHtml(data) : JSON.stringify(data, null, 2)}</div>`;
    } else {
      const summary = data.data || data;
      resultArea.innerHTML = `
        <div class="alert alert-success mb-4">
          <strong>${data.dry_run ? icon('eye', 'icon-sm icon-text') + 'Aperçu' : icon('check-circle', 'icon-sm icon-text') + 'Envoi terminé'}</strong>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div class="p-3 bg-surface rounded-lg border">
            <div class="text-2xl font-bold text-success">${summary.sent || 0}</div>
            <div class="text-sm text-secondary">Envoyés</div>
          </div>
          <div class="p-3 bg-surface rounded-lg border">
            <div class="text-2xl font-bold text-danger">${summary.failed || 0}</div>
            <div class="text-sm text-secondary">Échecs</div>
          </div>
        </div>
        <details>
          <summary class="cursor-pointer text-sm text-secondary">Détails JSON</summary>
          <pre class="mt-2 p-3 bg-neutral-100 rounded text-xs">${JSON.stringify(data, null, 2)}</pre>
        </details>
      `;
    }
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  // Events
  searchInput.addEventListener('input', renderMembers);
  filterSelect.addEventListener('change', renderMembers);
  btnPreview.addEventListener('click', () => sendInvitations(true));
  btnSend.addEventListener('click', () => {
    if (confirm('Envoyer les invitations ? Cette action enverra des emails réels.')) {
      sendInvitations(false);
    }
  });

  // Init
  loadMeeting();
  loadInvitations();
})();
