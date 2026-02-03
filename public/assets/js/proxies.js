/**
 * proxies.js — Simplified proxy management for AG-VOTE.
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 */
(function() {
  'use strict';

  let currentMeetingId = null;
  let proxiesCache = [];
  let membersCache = [];
  let maxProxiesPerMember = 3;

  const proxiesList = document.getElementById('proxiesList');
  const searchInput = document.getElementById('searchInput');
  const noMeetingAlert = document.getElementById('noMeetingAlert');
  const mainContent = document.getElementById('mainContent');
  const meetingTitle = document.getElementById('meetingTitle');
  const statTotal = document.getElementById('statTotal');
  const statVoices = document.getElementById('statVoices');

  // Get meeting_id from URL
  function getMeetingIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('meeting_id');
  }

  // Get initials
  function getInitials(name) {
    return (name || '?')
      .split(' ')
      .map(w => w[0])
      .join('')
      .substring(0, 2)
      .toUpperCase();
  }

  // Check meeting ID
  currentMeetingId = getMeetingIdFromUrl();
  if (!currentMeetingId) {
    noMeetingAlert.style.display = 'block';
    mainContent.style.display = 'none';
  } else {
    noMeetingAlert.style.display = 'none';
    mainContent.style.display = 'block';
  }

  // Load meeting info
  async function loadMeetingInfo() {
    try {
      const { body } = await api(`/api/v1/meetings.php?id=${currentMeetingId}`);
      if (body && body.ok && body.data) {
        meetingTitle.textContent = body.data.title;
        if (body.data.max_proxies_per_member) {
          maxProxiesPerMember = body.data.max_proxies_per_member;
          document.getElementById('maxProxies').textContent = maxProxiesPerMember;
        }
      }
    } catch (err) {
      console.error('Meeting info error:', err);
    }
  }

  // Load members for selects
  async function loadMembers() {
    try {
      const { body } = await api('/api/v1/members.php');
      membersCache = body?.data?.members || [];

      const giverSelect = document.getElementById('giverSelect');
      const receiverSelect = document.getElementById('receiverSelect');

      const options = membersCache.map(m =>
        `<option value="${m.id}">${escapeHtml(m.full_name || m.name)}</option>`
      ).join('');

      giverSelect.innerHTML = '<option value="">— Sélectionner —</option>' + options;
      receiverSelect.innerHTML = '<option value="">— Sélectionner —</option>' + options;
    } catch (err) {
      console.error('Members error:', err);
    }
  }

  // Render proxies
  function render(proxies) {
    const query = searchInput.value.toLowerCase().trim();

    let filtered = proxies;
    if (query) {
      filtered = proxies.filter(p => {
        const giver = (p.giver_name || '').toLowerCase();
        const receiver = (p.receiver_name || '').toLowerCase();
        return giver.includes(query) || receiver.includes(query);
      });
    }

    // Update stats
    statTotal.textContent = proxies.length;
    const voices = proxies.reduce((sum, p) => sum + (parseFloat(p.voting_power) || 1), 0);
    statVoices.textContent = voices;

    if (filtered.length === 0) {
      proxiesList.innerHTML = `
        <div class="empty-state-inline">
          <p>${query ? 'Aucun résultat' : 'Aucune procuration'}</p>
          ${!query ? '<button class="btn btn-primary btn-sm mt-4" id="btnAddEmpty">➕ Ajouter</button>' : ''}
        </div>
      `;
      document.getElementById('btnAddEmpty')?.addEventListener('click', openModal);
      return;
    }

    proxiesList.innerHTML = filtered.map(p => {
      const giverName = escapeHtml(p.giver_name || '—');
      const receiverName = escapeHtml(p.receiver_name || '—');
      const giverInitials = getInitials(p.giver_name);
      const receiverInitials = getInitials(p.receiver_name);

      return `
        <div class="proxy-row">
          <div class="proxy-member">
            <div class="proxy-avatar giver">${giverInitials}</div>
            <span class="proxy-name">${giverName}</span>
          </div>
          <span class="proxy-arrow">→</span>
          <div class="proxy-member">
            <div class="proxy-avatar receiver">${receiverInitials}</div>
            <span class="proxy-name">${receiverName}</span>
          </div>
          <button class="btn btn-ghost btn-sm btn-delete" data-proxy-id="${p.id}">🗑️</button>
        </div>
      `;
    }).join('');

    // Bind delete buttons
    proxiesList.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', () => deleteProxy(btn.dataset.proxyId));
    });
  }

  // Load proxies
  async function loadProxies() {
    proxiesList.innerHTML = '<div class="text-center p-6 text-muted">Chargement...</div>';

    try {
      const { body } = await api(`/api/v1/proxies.php?meeting_id=${currentMeetingId}`);
      proxiesCache = body?.data?.proxies || [];
      render(proxiesCache);
    } catch (err) {
      proxiesList.innerHTML = `
        <div class="alert alert-danger">Erreur: ${escapeHtml(err.message)}</div>
      `;
    }
  }

  // Delete proxy
  async function deleteProxy(proxyId) {
    if (!confirm('Supprimer cette procuration ?')) return;

    try {
      const { body } = await api('/api/v1/proxies_delete.php', {
        meeting_id: currentMeetingId,
        proxy_id: proxyId
      });

      if (body && body.ok) {
        setNotif('success', 'Procuration supprimée');
        loadProxies();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // Modal
  const modal = document.getElementById('addProxyModal');
  const backdrop = document.getElementById('modalBackdrop');

  function openModal() {
    modal.style.display = 'block';
    backdrop.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.style.display = 'none';
    backdrop.style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('giverSelect').value = '';
    document.getElementById('receiverSelect').value = '';
    document.getElementById('proxyWarning').style.display = 'none';
  }

  document.getElementById('btnAddProxy').addEventListener('click', openModal);
  document.getElementById('btnCloseModal').addEventListener('click', closeModal);
  document.getElementById('btnCancelModal').addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);

  // Check proxy validity
  function checkProxyValidity() {
    const giverId = document.getElementById('giverSelect').value;
    const receiverId = document.getElementById('receiverSelect').value;
    const warning = document.getElementById('proxyWarning');

    if (!giverId || !receiverId) {
      warning.style.display = 'none';
      return true;
    }

    if (giverId === receiverId) {
      warning.style.display = 'block';
      document.getElementById('proxyWarningText').textContent =
        'Un membre ne peut pas se donner procuration à lui-même';
      return false;
    }

    // Check if giver already gave proxy
    const existing = proxiesCache.find(p => String(p.giver_id) === String(giverId));
    if (existing) {
      warning.style.display = 'block';
      document.getElementById('proxyWarningText').textContent =
        'Ce membre a déjà donné procuration';
      return false;
    }

    // Check max proxies
    const receiverProxies = proxiesCache.filter(p => String(p.receiver_id) === String(receiverId)).length;
    if (receiverProxies >= maxProxiesPerMember) {
      warning.style.display = 'block';
      document.getElementById('proxyWarningText').textContent =
        `Ce mandataire a déjà ${receiverProxies} procurations (max: ${maxProxiesPerMember})`;
      return false;
    }

    warning.style.display = 'none';
    return true;
  }

  document.getElementById('giverSelect').addEventListener('change', checkProxyValidity);
  document.getElementById('receiverSelect').addEventListener('change', checkProxyValidity);

  // Save proxy
  document.getElementById('btnSaveProxy').addEventListener('click', async () => {
    const giverId = document.getElementById('giverSelect').value;
    const receiverId = document.getElementById('receiverSelect').value;

    if (!giverId || !receiverId) {
      setNotif('error', 'Sélectionnez un mandant et un mandataire');
      return;
    }

    if (!checkProxyValidity()) {
      setNotif('error', 'Procuration invalide');
      return;
    }

    const btn = document.getElementById('btnSaveProxy');
    Shared.btnLoading(btn, true);
    try {
      const { body } = await api('/api/v1/proxies_upsert.php', {
        meeting_id: currentMeetingId,
        giver_member_id: giverId,
        receiver_member_id: receiverId
      });

      if (body && body.ok !== false) {
        setNotif('success', 'Procuration créée');
        closeModal();
        loadProxies();
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(btn, false);
    }
  });

  // Search
  searchInput.addEventListener('input', () => render(proxiesCache));

  // Auto-refresh
  let pollingInterval = null;
  function startPolling() {
    if (pollingInterval) return;
    pollingInterval = setInterval(() => {
      if (!document.hidden && currentMeetingId) {
        loadProxies();
      }
    }, 5000);
  }
  window.addEventListener('beforeunload', () => { if (pollingInterval) clearInterval(pollingInterval); });

  // Initialize
  if (currentMeetingId) {
    loadMeetingInfo();
    loadMembers();
    loadProxies();
    startPolling();
  }
})();
