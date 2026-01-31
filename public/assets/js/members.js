/** members.js — Members management page for AG-VOTE. Must be loaded AFTER utils.js, shared.js and shell.js. */
(function() {
  'use strict';

  let membersCache = [];
  const membersList = document.getElementById('membersList');
  const searchInput = document.getElementById('searchInput');

  const getInitials = Shared.getInitials;

  // Parse members from response
  function parseMembers(resp) {
    if (!resp) return [];
    if (Array.isArray(resp)) return resp;
    if (Array.isArray(resp.members)) return resp.members;
    if (resp.data) {
      if (Array.isArray(resp.data.members)) return resp.data.members;
      if (Array.isArray(resp.data)) return resp.data;
    }
    return [];
  }

  // Render members
  function render(members) {
    const query = searchInput.value.toLowerCase().trim();

    const filtered = query
      ? members.filter(m => {
          const name = (m.full_name || m.name || '').toLowerCase();
          const email = (m.email || '').toLowerCase();
          return name.includes(query) || email.includes(query);
        })
      : members;

    document.getElementById('membersCount').textContent = `${filtered.length} membre${filtered.length > 1 ? 's' : ''}`;

    if (filtered.length === 0) {
      membersList.innerHTML = `
        <div class="empty-state p-6">
          <div class="empty-state-icon">👥</div>
          <div class="empty-state-title">Aucun membre</div>
          <div class="empty-state-description">
            ${query ? 'Aucun résultat pour cette recherche' : 'Ajoutez des membres ou importez un CSV'}
          </div>
        </div>
      `;
      return;
    }

    membersList.innerHTML = filtered.map(m => {
      const name = escapeHtml(m.full_name || m.name || '—');
      const email = escapeHtml(m.email || '—');
      const power = m.voting_power ?? m.power ?? 1;
      const isActive = m.is_active !== false && m.active !== false;
      const initials = getInitials(m.full_name || m.name);

      return `
        <div class="member-card">
          <div class="member-info">
            <div class="member-avatar">${initials}</div>
            <div class="member-details">
              <div class="member-name">${name}</div>
              <div class="member-email">${email}</div>
            </div>
          </div>
          <div class="member-actions">
            <span class="badge badge-primary">${power} voix</span>
            <span class="badge ${isActive ? 'badge-success' : 'badge-neutral'}">
              ${isActive ? 'Actif' : 'Inactif'}
            </span>
            <button class="btn btn-ghost btn-sm btn-edit" data-id="${m.id}">✏️</button>
          </div>
        </div>
      `;
    }).join('');
  }

  // Load members
  async function loadMembers() {
    membersList.innerHTML = `
      <div class="text-center p-6">
        <div class="spinner"></div>
        <div class="mt-4 text-muted">Chargement des membres...</div>
      </div>
    `;

    try {
      const { body } = await api('/api/v1/members.php');
      membersCache = parseMembers(body);
      render(membersCache);

      // Update KPIs
      const active = membersCache.filter(m => m.is_active !== false && m.active !== false);
      const totalPower = membersCache.reduce((sum, m) => sum + (m.voting_power ?? m.power ?? 1), 0);

      document.getElementById('kpiTotal').textContent = membersCache.length;
      document.getElementById('kpiActive').textContent = active.length;
      document.getElementById('kpiPower').textContent = totalPower;
    } catch (err) {
      membersList.innerHTML = `
        <div class="alert alert-danger">
          <span>❌</span>
          <span>Erreur de chargement: ${escapeHtml(err.message)}</span>
        </div>
      `;
    }
  }

  // Search filter (debounced)
  searchInput.addEventListener('input', Utils.debounce(() => render(membersCache), 250));

  // Create member
  document.getElementById('btnCreate').addEventListener('click', async () => {
    const full_name = document.getElementById('mName').value.trim();
    const email = document.getElementById('mEmail').value.trim();
    const voting_power = parseInt(document.getElementById('mPower').value) || 1;
    const is_active = document.getElementById('mActive').checked;

    if (!full_name) {
      setNotif('error', 'Le nom est requis');
      document.getElementById('mName').focus();
      return;
    }

    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setNotif('error', 'Format d\'email invalide');
      document.getElementById('mEmail').focus();
      return;
    }

    try {
      const { body } = await api('/api/v1/members.php', { full_name, email: email || null, voting_power, is_active });

      if (body && body.ok !== false) {
        setNotif('success', 'Membre ajouté');
        document.getElementById('mName').value = '';
        document.getElementById('mEmail').value = '';
        document.getElementById('mPower').value = '1';
        loadMembers();
      } else {
        setNotif('error', body?.error || 'Erreur création');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  });

  // Upload zone
  const uploadZone = document.getElementById('uploadZone');
  const csvFile = document.getElementById('csvFile');
  const btnImport = document.getElementById('btnImport');
  const fileName = document.getElementById('fileName');

  uploadZone.addEventListener('click', () => csvFile.click());

  uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('dragover');
  });

  uploadZone.addEventListener('dragleave', () => {
    uploadZone.classList.remove('dragover');
  });

  uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');

    const files = e.dataTransfer.files;
    if (files.length && files[0].name.endsWith('.csv')) {
      csvFile.files = files;
      updateFileName(files[0].name);
    }
  });

  csvFile.addEventListener('change', () => {
    if (csvFile.files.length) {
      updateFileName(csvFile.files[0].name);
    }
  });

  function updateFileName(name) {
    fileName.textContent = name;
    btnImport.disabled = false;
  }

  // Import CSV
  btnImport.addEventListener('click', async () => {
    const file = csvFile.files[0];
    if (!file) return;

    const out = document.getElementById('importOut');
    out.style.display = 'block';
    out.textContent = 'Import en cours...';

    const fd = new FormData();
    fd.append('file', file);

    try {
      const response = await fetch('/api/v1/members_import_csv.php', {
        method: 'POST',
        body: fd
      });
      const result = await response.json();

      out.textContent = JSON.stringify(result, null, 2);

      if (result && result.ok !== false) {
        setNotif('success', `✅ Import terminé: ${result.imported || 0} membres`);
        loadMembers();
      } else {
        setNotif('error', result?.error || 'Erreur import');
      }
    } catch (err) {
      out.textContent = err.message;
      setNotif('error', 'Erreur import: ' + err.message);
    }
  });

  // Seed members
  document.getElementById('btnSeed').addEventListener('click', async () => {
    try {
      const { body } = await api('/api/v1/dev_seed_members.php', { count: 30 });

      if (body && body.ok !== false) {
        setNotif('success', '🧪 Membres fictifs générés');
        loadMembers();
      } else {
        setNotif('warning', 'Seed non disponible');
      }
    } catch (err) {
      setNotif('warning', 'Endpoint seed non disponible');
    }
  });

  // Register stats drawer
  if (window.ShellDrawer && window.ShellDrawer.register) {
    window.ShellDrawer.register('stats', 'Statistiques membres', function(mid, body) {
      const total = membersCache.length;
      const active = membersCache.filter(function(m) { return m.is_active !== false && m.active !== false; }).length;
      const inactive = total - active;
      const totalPower = membersCache.reduce(function(s, m) { return s + (m.voting_power || m.power || 1); }, 0);
      const avgPower = total > 0 ? (totalPower / total).toFixed(1) : '0';
      const withEmail = membersCache.filter(function(m) { return m.email; }).length;

      body.innerHTML =
        '<div style="display:flex;flex-direction:column;gap:16px;padding:4px 0;">' +
          '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">' +
            '<div style="background:var(--color-bg-subtle,#f5f5f5);padding:12px;border-radius:8px;text-align:center;">' +
              '<div style="font-size:24px;font-weight:700;">' + total + '</div>' +
              '<div class="text-sm text-muted">Total</div>' +
            '</div>' +
            '<div style="background:var(--color-bg-subtle,#f5f5f5);padding:12px;border-radius:8px;text-align:center;">' +
              '<div style="font-size:24px;font-weight:700;color:var(--color-success,#22c55e);">' + active + '</div>' +
              '<div class="text-sm text-muted">Actifs</div>' +
            '</div>' +
            '<div style="background:var(--color-bg-subtle,#f5f5f5);padding:12px;border-radius:8px;text-align:center;">' +
              '<div style="font-size:24px;font-weight:700;">' + totalPower + '</div>' +
              '<div class="text-sm text-muted">Voix totales</div>' +
            '</div>' +
            '<div style="background:var(--color-bg-subtle,#f5f5f5);padding:12px;border-radius:8px;text-align:center;">' +
              '<div style="font-size:24px;font-weight:700;">' + avgPower + '</div>' +
              '<div class="text-sm text-muted">Moy. voix</div>' +
            '</div>' +
          '</div>' +
          '<div style="border-top:1px solid var(--color-border,#ddd);padding-top:12px;">' +
            '<div style="display:flex;justify-content:space-between;padding:4px 0;" class="text-sm">' +
              '<span class="text-muted">Inactifs</span><span>' + inactive + '</span></div>' +
            '<div style="display:flex;justify-content:space-between;padding:4px 0;" class="text-sm">' +
              '<span class="text-muted">Avec email</span><span>' + withEmail + '</span></div>' +
            '<div style="display:flex;justify-content:space-between;padding:4px 0;" class="text-sm">' +
              '<span class="text-muted">Sans email</span><span>' + (total - withEmail) + '</span></div>' +
          '</div>' +
        '</div>';
    });
  }

  // Initial load
  loadMembers();
})();
