/** proxies.js — Proxy management page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
  'use strict';

  let currentMeetingId = null;
  let proxiesCache = [];
  let membersCache = [];
  const proxiesList = document.getElementById('proxiesList');
  const searchInput = document.getElementById('searchInput');

  // Get meeting_id from URL
  function getMeetingIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('meeting_id');
  }

  // Check meeting ID
  currentMeetingId = getMeetingIdFromUrl();
  if (!currentMeetingId) {
    setNotif('error', 'Aucune séance sélectionnée');
    setTimeout(() => window.location.href = '/meetings.htmx.html', 2000);
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

  // Load meeting info
  async function loadMeetingInfo() {
    try {
      const { body } = await api(`/api/v1/meetings.php?id=${currentMeetingId}`);
      if (body && body.ok && body.data) {
        document.getElementById('meetingTitle').textContent = body.data.title;
        document.getElementById('meetingName').textContent = body.data.title;
        document.getElementById('meetingContext').style.display = 'flex';

        if (body.data.max_proxies_per_member) {
          document.getElementById('maxProxies').textContent = body.data.max_proxies_per_member;
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
      membersCache = body?.data || body?.members || [];

      const giverSelect = document.getElementById('giverSelect');
      const receiverSelect = document.getElementById('receiverSelect');

      const options = membersCache.map(m =>
        `<option value="${m.id}">${escapeHtml(m.full_name || m.name)}</option>`
      ).join('');

      giverSelect.innerHTML = '<option value="">— Sélectionner un membre —</option>' + options;
      receiverSelect.innerHTML = '<option value="">— Sélectionner un membre —</option>' + options;
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

    document.getElementById('proxiesCount').textContent = `${filtered.length} procuration${filtered.length > 1 ? 's' : ''} active${filtered.length > 1 ? 's' : ''}`;

    if (filtered.length === 0) {
      proxiesList.innerHTML = `
        <div class="empty-state p-6">
          <div class="empty-state-icon">📝</div>
          <div class="empty-state-title">Aucune procuration</div>
          <div class="empty-state-description">
            ${query ? 'Aucun résultat pour cette recherche' : 'Créez une procuration pour déléguer un vote'}
          </div>
        </div>
      `;
      return;
    }

    proxiesList.innerHTML = filtered.map(p => {
      const giverName = escapeHtml(p.giver_name || '—');
      const receiverName = escapeHtml(p.receiver_name || '—');
      const giverInitials = getInitials(p.giver_name);
      const receiverInitials = getInitials(p.receiver_name);
      const power = p.voting_power ?? 1;

      return `
        <div class="proxy-card">
          <div class="proxy-member">
            <div class="proxy-avatar giver">${giverInitials}</div>
            <div>
              <div class="proxy-name">${giverName}</div>
              <div class="proxy-meta">Mandant · ${power} voix</div>
            </div>
          </div>

          <div class="proxy-arrow">→</div>

          <div class="proxy-member">
            <div class="proxy-avatar receiver">${receiverInitials}</div>
            <div>
              <div class="proxy-name">${receiverName}</div>
              <div class="proxy-meta">Mandataire</div>
            </div>
          </div>

          <button class="btn btn-ghost btn-sm btn-delete" data-proxy-id="${p.id}">
            🗑️
          </button>
        </div>
      `;
    }).join('');

    // Bind delete buttons
    proxiesList.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', () => deleteProxy(btn.dataset.proxyId));
    });
  }

  // Update KPIs
  function updateKPIs(proxies) {
    const total = proxies.length;
    const givers = new Set(proxies.map(p => p.giver_id)).size;
    const receivers = new Set(proxies.map(p => p.receiver_id)).size;
    const voices = proxies.reduce((sum, p) => sum + (p.voting_power || 1), 0);

    document.getElementById('kpiTotal').textContent = total;
    document.getElementById('kpiGivers').textContent = givers;
    document.getElementById('kpiReceivers').textContent = receivers;
    document.getElementById('kpiVoicesDelegated').textContent = voices;
  }

  // Load proxies
  async function loadProxies() {
    proxiesList.innerHTML = `
      <div class="text-center p-6">
        <div class="spinner"></div>
        <div class="mt-4 text-muted">Chargement des procurations...</div>
      </div>
    `;

    try {
      const { body } = await api(`/api/v1/proxies.php?meeting_id=${currentMeetingId}`);
      proxiesCache = body?.data || body?.proxies || [];
      render(proxiesCache);
      updateKPIs(proxiesCache);
    } catch (err) {
      proxiesList.innerHTML = `
        <div class="alert alert-danger">
          <span>❌</span>
          <span>Erreur de chargement: ${escapeHtml(err.message)}</span>
        </div>
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
        setNotif('success', '✅ Procuration supprimée');
        loadProxies();
      } else {
        setNotif('error', body?.error || 'Erreur suppression');
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
      warning.style.display = 'flex';
      document.getElementById('proxyWarningText').textContent =
        'Un membre ne peut pas se donner procuration à lui-même';
      return false;
    }

    // Check if giver already gave proxy
    const existing = proxiesCache.find(p => String(p.giver_id) === String(giverId));
    if (existing) {
      warning.style.display = 'flex';
      document.getElementById('proxyWarningText').textContent =
        'Ce membre a déjà donné procuration';
      return false;
    }

    // Check max proxies
    const receiverProxies = proxiesCache.filter(p => String(p.receiver_id) === String(receiverId)).length;
    const maxProxies = parseInt(document.getElementById('maxProxies').textContent) || 3;
    if (receiverProxies >= maxProxies) {
      warning.style.display = 'flex';
      document.getElementById('proxyWarningText').textContent =
        `Ce mandataire a déjà ${receiverProxies} procurations (max: ${maxProxies})`;
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

    try {
      const { body } = await api('/api/v1/proxies.php', {
        meeting_id: currentMeetingId,
        giver_id: giverId,
        receiver_id: receiverId
      });

      if (body && body.ok !== false) {
        setNotif('success', '✅ Procuration créée');
        closeModal();
        loadProxies();
      } else {
        setNotif('error', body?.error || 'Erreur création');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Search
  searchInput.addEventListener('input', () => render(proxiesCache));

  // Initialize
  if (currentMeetingId) {
    loadMeetingInfo();
    loadMembers();
    loadProxies();
  }
})();
