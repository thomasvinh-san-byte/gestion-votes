/**
 * members.js — Members management page logic for AG-VOTE.
 *
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 * Handles: members CRUD, groups management, CSV import,
 *          filtering/sorting/pagination, member detail dialog.
 */
(function() {
  'use strict';

  const searchInput = document.getElementById('searchInput');
  const membersList = document.getElementById('membersList');
  const membersCount = document.getElementById('membersCount');
  const resultsSubtitle = document.getElementById('resultsSubtitle');
  const filterChips = document.querySelectorAll('.filter-chip[data-filter]');
  const uploadZone = document.getElementById('uploadZone');
  const csvFile = document.getElementById('csvFile');
  const btnImport = document.getElementById('btnImport');
  const fileName = document.getElementById('fileName');
  const groupsList = document.getElementById('groupsList');
  const groupFilters = document.getElementById('groupFilters');
  const groupFiltersField = document.getElementById('groupFiltersField');
  const sortSelect = document.getElementById('sortSelect');
  const activeFiltersHint = document.getElementById('activeFiltersHint');
  const memberDetailDialog = document.getElementById('memberDetailDialog');
  const memberDetailBody = document.getElementById('memberDetailBody');

  let allMembers = [];
  let allGroups = [];
  let memberGroups = {}; // memberId -> [groupIds]
  let currentFilter = 'all';
  let currentGroupFilter = null; // null = all groups
  let currentPage = 1;
  let pageSize = 50; // server-side page size, capped at 50
  let _serverPagination = null; // pagination metadata from server

  // Onboarding elements
  var onboardingEl = document.getElementById('membersOnboarding');
  var onbStepMembers = document.getElementById('onbStepMembers');
  var onbStepWeights = document.getElementById('onbStepWeights');
  var onbStepGroups = document.getElementById('onbStepGroups');
  var onbStepMeeting = document.getElementById('onbStepMeeting');

  const paginPrev = document.getElementById('paginPrev');
  const paginNext = document.getElementById('paginNext');
  const paginInfo = document.getElementById('paginInfo');
  const paginSizeSelect = document.getElementById('paginSize');

  // Management tabs switching
  document.querySelectorAll('.mgmt-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.mgmt-tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
      document.querySelectorAll('.mgmt-tab-panel').forEach(p => { p.hidden = true; });
      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      const panelId = tab.getAttribute('aria-controls');
      if (panelId) document.getElementById(panelId).hidden = false;
    });
  });

  // Update active filters count hint
  function updateFiltersHint() {
    let n = 0;
    if (searchInput.value.trim()) n++;
    if (currentFilter !== 'all') n++;
    if (currentGroupFilter) n++;
    activeFiltersHint.textContent = n + (n > 1 ? ' filtres' : ' filtre');

    resultsSubtitle.textContent = n > 0
      ? 'Filtré selon vos critères'
      : 'Affichage de l\'ensemble des membres';
  }

  // Update stats
  function updateStats(members) {
    const total = members.length;
    const active = members.filter(m => m.is_active).length;
    const inactive = total - active;

    document.getElementById('kpiTotal').textContent = total;
    document.getElementById('kpiActive').textContent = active;
    document.getElementById('kpiInactive').textContent = inactive;

    // Email coverage (active members with email / active members)
    var kpiEmailCoverage = document.getElementById('kpiEmailCoverage');
    if (kpiEmailCoverage) {
      if (active > 0) {
        var withEmail = members.filter(function(m) { return m.is_active && m.email; }).length;
        var pct = Math.round((withEmail / active) * 100);
        kpiEmailCoverage.textContent = pct + '%';
      } else {
        kpiEmailCoverage.textContent = '\u2014';
      }
    }
  }

  // Update onboarding strip based on data state
  function updateOnboarding() {
    if (!onboardingEl) return;

    var hasMembers = allMembers.length > 0;
    var activeMembers = allMembers.filter(function(m) { return m.is_active; });
    var hasGroups = allGroups.length > 0;

    // Show onboarding strip when setup is incomplete
    var _allDone = hasMembers && hasGroups;
    onboardingEl.hidden = false; // Always show — it provides navigation context

    // Step 1: Members
    setStepState(onbStepMembers, hasMembers,
      hasMembers ? activeMembers.length + ' membre' + (activeMembers.length > 1 ? 's' : '') + ' actif' + (activeMembers.length > 1 ? 's' : '') : 'Ajouter des membres');

    // Step 2: Emails — done if members have email addresses
    var withEmail = activeMembers.filter(function(m) { return m.email; }).length;
    setStepState(onbStepWeights, hasMembers,
      hasMembers ? withEmail + '/' + activeMembers.length + ' avec email' : 'Vérifier les emails');

    // Step 3: Groups (optional)
    setStepState(onbStepGroups, hasGroups,
      hasGroups ? allGroups.length + ' groupe' + (allGroups.length > 1 ? 's' : '') : 'Organiser en groupes');

    // Step 4: Meeting link — always show as action if members ready
    if (onbStepMeeting) {
      if (hasMembers) {
        onbStepMeeting.classList.remove('pending');
        onbStepMeeting.classList.add('action');
        onbStepMeeting.innerHTML = '<span class="onboarding-step-num">→</span> <a href="/operator">Préparer une séance</a>';
      } else {
        onbStepMeeting.classList.remove('action');
        onbStepMeeting.classList.add('pending');
        onbStepMeeting.innerHTML = '<span class="onboarding-step-num">4</span> Préparer une séance';
      }
    }
  }

  function setStepState(el, done, label) {
    if (!el) return;
    el.classList.remove('done', 'pending');
    el.classList.add(done ? 'done' : 'pending');
    // Preserve the number span, update text
    var numSpan = el.querySelector('.onboarding-step-num');
    var optSpan = el.querySelector('.onboarding-optional');
    var numHtml = numSpan ? numSpan.outerHTML : '';
    var optHtml = optSpan ? ' ' + optSpan.outerHTML : '';
    if (done) {
      el.innerHTML = '<span class="onboarding-step-num">✓</span> ' + escapeHtml(label) + optHtml;
    } else {
      el.innerHTML = numHtml + ' ' + escapeHtml(label) + optHtml;
    }
  }

  // Onboarding step click: scroll to relevant section
  if (onboardingEl) {
    onboardingEl.addEventListener('click', function(e) {
      var step = e.target.closest('[data-scroll-to]');
      if (!step) return;
      var target = step.dataset.scrollTo;
      if (target === 'groups-panel') {
        // Switch to groups tab if not already active
        var groupsTab = document.querySelector('[data-mgmt-tab="groups"]');
        if (groupsTab && !groupsTab.classList.contains('active')) groupsTab.click();
        var panel = document.querySelector('[data-scroll-target="groups-panel"]');
        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else {
        var el = document.getElementById(target);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  // Filter members
  function filterMembers(members, filter, search, groupId) {
    search = search || '';
    groupId = groupId || null;
    let filtered = members;

    if (filter === 'active') {
      filtered = filtered.filter(m => m.is_active);
    } else if (filter === 'inactive') {
      filtered = filtered.filter(m => !m.is_active);
    }

    if (search) {
      const s = search.toLowerCase();
      filtered = filtered.filter(m =>
        (m.full_name || m.name || '').toLowerCase().includes(s) ||
        (m.email || '').toLowerCase().includes(s)
      );
    }

    if (groupId) {
      filtered = filtered.filter(m => {
        const groups = memberGroups[m.id] || [];
        return groups.includes(groupId);
      });
    }

    return filtered;
  }

  // Sort members
  function sortMembers(members) {
    const v = sortSelect.value;
    const norm = s => (s || '').toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
    const copy = [...members];

    if (v === 'nameAsc') copy.sort((a, b) => norm(a.full_name || a.name).localeCompare(norm(b.full_name || b.name)));
    if (v === 'nameDesc') copy.sort((a, b) => norm(b.full_name || b.name).localeCompare(norm(a.full_name || a.name)));

    return copy;
  }

  // ==========================================
  // GROUPS MANAGEMENT
  // ==========================================

  async function fetchGroups() {
    await Shared.withRetry({
      container: groupsList,
      errorMsg: 'Impossible de charger les groupes',
      action: async function () {
        var r = await api('/api/v1/member_groups.php');
        if (!r.body || !r.body.ok) throw new Error(r.body?.error || 'Erreur');
        allGroups = r.body.data?.groups || [];
        renderGroups();
        renderGroupFilters();
        updateOnboarding();
      }
    });
  }

  function renderGroups() {
    if (!allGroups.length) {
      groupsList.innerHTML = '<div class="groups-empty-hint">' +
        '<p class="text-muted text-sm">Aucun groupe créé.</p>' +
        '<p class="text-muted text-xs">Les groupes permettent de catégoriser vos membres par collège, département ou tout autre critère. ' +
        'Utile pour le filtrage et les statistiques par catégorie.</p>' +
      '</div>';
      return;
    }

    groupsList.innerHTML = allGroups.map(g => `
      <div class="group-card" data-group-id="${escapeHtml(g.id)}">
        <div class="group-info">
          <div class="group-color-dot" style="background-color: ${escapeHtml(g.color || '#6366f1')}"></div>
          <div class="group-details">
            <div class="group-name">${escapeHtml(g.name)}</div>
            <div class="group-count">${g.member_count || 0} membre${(g.member_count || 0) > 1 ? 's' : ''}</div>
          </div>
        </div>
        <div class="group-actions">
          <button class="member-action-btn" data-action="edit-group" data-id="${escapeHtml(g.id)}" title="Modifier" aria-label="Modifier"><svg class="icon"><use href="/assets/icons.svg#icon-edit"></use></svg></button>
          <button class="member-action-btn danger" data-action="delete-group" data-id="${escapeHtml(g.id)}" title="Supprimer" aria-label="Supprimer"><svg class="icon"><use href="/assets/icons.svg#icon-trash"></use></svg></button>
        </div>
      </div>
    `).join('');
  }

  function renderGroupFilters() {
    if (!allGroups.length) {
      groupFilters.innerHTML = '';
      groupFiltersField.hidden = true;
      return;
    }

    groupFiltersField.hidden = false;
    const allBtn = `<button class="filter-chip ${!currentGroupFilter ? 'active' : ''}" data-action="filter-group" data-group-id="">Tous les groupes</button>`;
    const groupBtns = allGroups.map(g => `
      <button class="filter-chip ${currentGroupFilter === g.id ? 'active' : ''}" data-action="filter-group" data-group-id="${escapeHtml(g.id)}">
        <span class="group-color-dot-sm" style="background-color: ${escapeHtml(g.color || '#6366f1')}"></span>
        ${escapeHtml(g.name)}
      </button>
    `).join('');

    groupFilters.innerHTML = allBtn + groupBtns;
  }

  window.filterByGroup = function(groupId) {
    currentGroupFilter = groupId;
    currentPage = 1;
    renderGroupFilters();
    renderMembers(allMembers);
    updateFiltersHint();
  };

  var _createGroupPending = false;
  document.getElementById('btnCreateGroup').addEventListener('click', async () => {
    if (_createGroupPending) return;
    const name = document.getElementById('groupName').value.trim();
    const color = document.getElementById('groupColor').value || '#6366f1';

    if (!name) {
      AgToast.show('error', 'Le nom du groupe est requis');
      return;
    }

    _createGroupPending = true;
    const btn = document.getElementById('btnCreateGroup');
    Shared.btnLoading(btn, true);

    try {
      const { body } = await api('/api/v1/member_groups.php', { name, color });

      if (body?.ok) {
        AgToast.show('success', 'Groupe créé');
        document.getElementById('groupName').value = '';
        await fetchGroups();
      } else {
        AgToast.show('error', body?.detail || body?.error || 'Erreur');
      }
    } catch (err) {
      AgToast.show('error', err.message);
    } finally {
      _createGroupPending = false;
      Shared.btnLoading(btn, false);
    }
  });

  window.editGroup = function(groupId) {
    const group = allGroups.find(g => g.id === groupId);
    if (!group) return;

    Shared.openModal({
      title: 'Modifier le groupe',
      body: `
        <div class="form-group mb-3">
          <label class="form-label">Nom du groupe *</label>
          <input class="form-input" type="text" id="editGroupName" value="${escapeHtml(group.name)}" placeholder="Nom du groupe">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-textarea" id="editGroupDesc" placeholder="Description optionnelle" rows="2">${escapeHtml(group.description || '')}</textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Couleur</label>
          <input type="color" id="editGroupColor" value="${escapeHtml(group.color || '#6366f1')}">
        </div>
      `,
      confirmText: 'Enregistrer',
      onConfirm: async function(modal) {
        const newName = modal.querySelector('#editGroupName').value.trim();
        const newDesc = modal.querySelector('#editGroupDesc').value.trim();
        const newColor = modal.querySelector('#editGroupColor').value;

        if (!newName) {
          AgToast.show('error', 'Le nom est requis');
          return false;
        }

        try {
          const { body } = await api('/api/v1/member_groups.php', {
            id: groupId,
            name: newName,
            description: newDesc || null,
            color: newColor
          }, 'PATCH');

          if (body?.ok) {
            AgToast.show('success', 'Groupe modifié');
            await fetchGroups();
            return true;
          } else {
            AgToast.show('error', body?.detail || body?.error || 'Erreur');
            return false;
          }
        } catch (err) {
          AgToast.show('error', err.message);
          return false;
        }
      }
    });
  };

  window.deleteGroup = async function(groupId) {
    const group = allGroups.find(g => g.id === groupId);
    if (!group) return;

    const ok = await AgConfirm.ask({
      title: 'Supprimer le groupe',
      message: 'Supprimer le groupe \u00ab\u00a0' + group.name + '\u00a0\u00bb ? Les membres ne seront pas supprim\u00e9s.',
      confirmLabel: 'Supprimer',
      variant: 'danger'
    });
    if (!ok) return;

    try {
      var r = await api('/api/v1/member_groups.php?id=' + encodeURIComponent(groupId), null, 'DELETE');
      if (r.body?.ok) {
        AgToast.show('success', 'Groupe supprimé');
        if (currentGroupFilter === groupId) {
          currentGroupFilter = null;
        }
        await fetchGroups();
        await fetchMembers();
      } else {
        AgToast.show('error', r.body?.detail || r.body?.error || 'Erreur');
      }
    } catch (err) {
      AgToast.show('error', err.message);
    }
  };

  function getMemberGroupBadges(memberId) {
    const groups = memberGroups[memberId] || [];
    if (!groups.length) return '';

    return groups.map(gid => {
      const g = allGroups.find(x => x.id === gid);
      if (!g) return '';
      return `<span class="group-badge" style="background-color: ${escapeHtml(g.color || '#6366f1')}20; color: ${escapeHtml(g.color || '#6366f1')}">
        <span class="group-badge-dot" style="background-color: ${escapeHtml(g.color || '#6366f1')}"></span>
        ${escapeHtml(g.name)}
      </span>`;
    }).join('');
  }

  async function fetchMemberGroups() {
    memberGroups = {};
    for (const m of allMembers) {
      if (m.groups) {
        memberGroups[m.id] = m.groups.map(g => g.id);
      }
    }
  }

  // ==========================================
  // MEMBER DETAIL DIALOG
  // ==========================================

  window.openMemberDetail = function(memberId) {
    const m = allMembers.find(x => x.id === memberId);
    if (!m) return;

    const name = m.full_name || m.name || '\u2014';
    const initials = name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
    const groupBadges = getMemberGroupBadges(m.id);

    memberDetailBody.innerHTML = `
      <div class="detail-header-row">
        <div class="detail-avatar ${m.is_active ? 'is-active' : ''}">${escapeHtml(initials)}</div>
        <div>
          <div class="detail-name">${escapeHtml(name)}</div>
          <div class="detail-status">${m.is_active
    ? '<span class="badge badge-success badge-sm">Actif</span>'
    : '<span class="badge badge-neutral badge-sm">Inactif</span>'}</div>
        </div>
      </div>
      <div class="detail-grid">
        <div class="detail-field">
          <div class="detail-label">Email</div>
          <div class="detail-value">${m.email
    ? '<a href="mailto:' + encodeURIComponent(m.email) + '">' + escapeHtml(m.email) + '</a>'
    : '<span class="text-muted">Non renseigné</span>'}</div>
        </div>
        <div class="detail-field">
          <div class="detail-label">Identifiant</div>
          <div class="detail-value detail-value-mono detail-value-id">${escapeHtml(m.id)}</div>
        </div>
        ${groupBadges ? `
        <div class="detail-field detail-field-full">
          <div class="detail-label">Groupes</div>
          <div class="detail-value"><div class="member-groups">${groupBadges}</div></div>
        </div>` : ''}
      </div>
      <div class="detail-actions">
        <button class="btn btn-primary btn-sm" data-action="edit-member" data-id="${escapeHtml(m.id)}">
          <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-edit"></use></svg>
          Modifier
        </button>
        <button class="btn btn-secondary btn-sm" data-action="toggle-active" data-id="${escapeHtml(m.id)}" data-active="${!m.is_active}">
          <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-${m.is_active ? 'pause' : 'play'}"></use></svg>
          ${m.is_active ? 'Désactiver' : 'Activer'}
        </button>
        ${m.email ? `<a class="btn btn-ghost btn-sm" href="mailto:${encodeURIComponent(m.email)}">
          <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-mail"></use></svg>
          Contacter
        </a>` : ''}
      </div>
    `;

    if (typeof memberDetailDialog.showModal === 'function') memberDetailDialog.showModal();
    else memberDetailDialog.setAttribute('open', 'open');
  };

  document.getElementById('closeMemberDetail').addEventListener('click', () => memberDetailDialog.close());
  memberDetailDialog.addEventListener('click', (e) => {
    const r = memberDetailDialog.getBoundingClientRect();
    const inside = r.top <= e.clientY && e.clientY <= r.bottom && r.left <= e.clientX && e.clientX <= r.right;
    if (!inside) memberDetailDialog.close();
  });

  // ==========================================
  // RENDER MEMBERS
  // ==========================================

  function updatePagination(totalFiltered) {
    // Use server pagination metadata when available
    const totalPages = _serverPagination
      ? Math.max(1, _serverPagination.total_pages)
      : Math.max(1, Math.ceil(totalFiltered / pageSize));
    const total = _serverPagination ? _serverPagination.total : totalFiltered;
    if (currentPage > totalPages) currentPage = totalPages;
    paginPrev.disabled = currentPage <= 1;
    paginNext.disabled = currentPage >= totalPages;
    paginInfo.textContent = `Page ${currentPage} / ${totalPages} (${total} membres total)`;
  }

  // Update filter chip count badges
  function updateFilterChipCounts(members) {
    var totalCount = members.length;
    var activeCount = members.filter(function(m) { return m.is_active; }).length;
    var inactiveCount = totalCount - activeCount;
    var counts = { all: totalCount, active: activeCount, inactive: inactiveCount };

    filterChips.forEach(function(chip) {
      var filter = chip.dataset.filter;
      var count = counts[filter];
      if (count === undefined) return;
      var countSpan = chip.querySelector('.count');
      if (!countSpan) {
        countSpan = document.createElement('span');
        countSpan.className = 'count';
        chip.appendChild(countSpan);
      }
      countSpan.textContent = count;
    });
  }

  function renderMembers(members) {
    const search = searchInput.value.trim();
    const filtered = sortMembers(filterMembers(members, currentFilter, search, currentGroupFilter));
    const n = filtered.length;
    // Show total from server when available (reflects full dataset, not just current page)
    const displayTotal = _serverPagination ? _serverPagination.total : n;
    membersCount.textContent = `${displayTotal} membre${displayTotal > 1 ? 's' : ''}`;
    updateFilterChipCounts(members);

    // Use server pagination total for pagination controls; allMembers is already the current page
    updatePagination(n);

    if (!n) {
      // Contextual empty state: different message when truly empty vs filtered to zero
      var isFiltered = currentFilter !== 'all' || searchInput.value.trim() || currentGroupFilter;
      var totalFromServer = _serverPagination ? _serverPagination.total : allMembers.length;
      if (totalFromServer === 0 && !isFiltered) {
        membersList.innerHTML = '<div class="empty-state-guided">' +
          '<svg class="icon icon-xl" aria-hidden="true"><use href="/assets/icons.svg#icon-users"></use></svg>' +
          '<h3>Aucun membre enregistré</h3>' +
          '<p>Commencez par ajouter vos participants. Deux options :</p>' +
          '<div class="empty-state-actions">' +
            '<button class="btn btn-primary" onclick="document.getElementById(\'mName\').focus();">' +
              '<svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-user-plus"></use></svg> Ajouter manuellement' +
            '</button>' +
            '<button class="btn btn-secondary" onclick="document.querySelector(\'[data-mgmt-tab=import]\').click();">' +
              '<svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-upload"></use></svg> Importer un CSV' +
            '</button>' +
          '</div>' +
        '</div>';
      } else if (isFiltered) {
        membersList.innerHTML = '<ag-empty-state icon="members" title="Aucun r\u00e9sultat" description="Essayez un autre terme de recherche."></ag-empty-state>';
      } else {
        membersList.innerHTML = '<ag-empty-state icon="members" title="Aucun membre" description="Importez une liste CSV ou ajoutez des membres un par un." action-label="Importer des membres" action-href="#importMembers"></ag-empty-state>';
      }
      return;
    }

    // allMembers already contains only the current page from the server (at most 50 items)
    const paged = filtered;

    membersList.innerHTML = paged.map(m => {
      const name = m.full_name || m.name || '\u2014';
      const initials = name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
      const cardClass = m.is_active ? 'is-active' : 'is-inactive';
      const statusBadge = m.is_active
        ? '<span class="badge badge-success badge-sm">Actif</span>'
        : '<span class="badge badge-neutral badge-sm">Inactif</span>';
      const groupBadges = getMemberGroupBadges(m.id);

      return `
        <article class="member-card ${cardClass}" data-member-id="${escapeHtml(m.id)}" data-action="open-detail" data-id="${escapeHtml(m.id)}" aria-label="${escapeHtml(name)}">
          <div class="member-avatar">${escapeHtml(initials)}</div>
          <div class="member-card-body">
            <div class="member-card-main">
              <h3 class="member-name">${escapeHtml(name)}</h3>
              ${m.email ? '<span class="member-email">' + escapeHtml(m.email) + '</span>' : ''}
            </div>
            <div class="member-card-meta">
              ${statusBadge}
              ${groupBadges}
            </div>
          </div>
          <div class="member-card-actions">
            <ag-tooltip text="Modifier ce membre" position="top">
              <button class="member-action-icon" data-action="edit-member" data-id="${escapeHtml(m.id)}" aria-label="Modifier ${escapeHtml(name)}">
                <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-edit"></use></svg>
              </button>
            </ag-tooltip>
            <ag-tooltip text="Supprimer ce membre" position="top">
              <button class="member-action-icon danger" data-action="delete-member" data-id="${escapeHtml(m.id)}" aria-label="Supprimer ${escapeHtml(name)}">
                <svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-trash"></use></svg>
              </button>
            </ag-tooltip>
          </div>
        </article>
      `;
    }).join('');
  }

  // ==========================================
  // DATA LOADING
  // ==========================================

  async function fetchMembers(page) {
    page = page || currentPage;
    // Cap pageSize at 50 to enforce server-side maximum
    if (pageSize > 50) pageSize = 50;
    membersList.innerHTML = '<div class="text-center p-6 text-muted members-loading">Chargement\u2026</div>';

    await Shared.withRetry({
      container: membersList,
      errorMsg: 'Impossible de charger les membres',
      action: async function () {
        const url = '/api/v1/members.php?include_groups=1&page=' + page + '&per_page=' + pageSize;
        const { body } = await api(url);
        if (!body || !body.ok) throw new Error(body?.error || 'Erreur serveur');
        allMembers = body.data?.items || [];
        _serverPagination = body.data?.pagination || null;
        currentPage = page;
        await fetchMemberGroups();
        updateStats(allMembers);
        renderMembers(allMembers);
        updateOnboarding();
      }
    });
  }

  // ==========================================
  // CREATE MEMBER
  // ==========================================

  // Live inline validation on create form
  var mNameInput = document.getElementById('mName');
  var mEmailInput = document.getElementById('mEmail');

  Shared.liveValidate(mNameInput, [
    { test: function(v) { return v.length > 0; }, msg: 'Le nom est requis' }
  ]);
  Shared.liveValidate(mEmailInput, [
    { test: function(v) { return !v || Utils.isValidEmail(v); }, msg: 'Format d\u2019email invalide' }
  ]);
  document.getElementById('btnCreate').addEventListener('click', async () => {
    var nameInput = document.getElementById('mName');
    var emailInput = document.getElementById('mEmail');

    var valid = Shared.validateAll([
      { input: nameInput, rules: [{ test: function(v) { return v.length > 0; }, msg: 'Le nom est requis' }] },
      { input: emailInput, rules: [{ test: function(v) { return !v || Utils.isValidEmail(v); }, msg: 'Format d\u2019email invalide' }] }
    ]);
    if (!valid) return;

    const full_name = nameInput.value.trim();
    const email = emailInput.value.trim();
    const is_active = document.getElementById('mActive').checked;

    const btn = document.getElementById('btnCreate');
    Shared.btnLoading(btn, true);

    try {
      const { body } = await api('/api/v1/members.php', {
        full_name,
        email: email || null,
        is_active
      });

      if (body?.ok) {
        AgToast.show('success', 'Membre ajouté');
        document.getElementById('mName').value = '';
        document.getElementById('mEmail').value = '';
        document.getElementById('mActive').checked = true;
        Shared.fieldClear(nameInput);
        Shared.fieldClear(emailInput);
        await fetchMembers();
      } else {
        AgToast.show('error', body?.detail || body?.error || 'Erreur');
      }
    } catch (err) {
      AgToast.show('error', err.message);
    } finally {
      Shared.btnLoading(btn, false);
    }
  });

  // ==========================================
  // MEMBER ACTIONS
  // ==========================================

  window.toggleActive = async function(memberId, newStatus) {
    const member = allMembers.find(m => m.id === memberId);
    if (!member) return;

    try {
      const { body } = await api('/api/v1/members.php', {
        member_id: memberId,
        full_name: member.full_name || member.name,
        email: member.email || '',
        is_active: newStatus
      }, 'PATCH');

      if (body?.ok) {
        member.is_active = newStatus;
        updateStats(allMembers);
        renderMembers(allMembers);
      } else {
        AgToast.show('error', body?.detail || body?.error || 'Erreur');
      }
    } catch (err) {
      AgToast.show('error', err.message);
    }
  };

  window.deleteMember = async function(memberId) {
    var member = allMembers.find(function(m) { return m.id === memberId; });
    var memberName = member ? (member.full_name || member.name || 'ce membre') : 'ce membre';

    const ok = await AgConfirm.ask({
      title: 'Supprimer ce membre ?',
      message: 'Supprimer d\u00e9finitivement ' + memberName + ' ? Cette action est irr\u00e9versible.',
      confirmLabel: 'Supprimer le membre',
      variant: 'danger'
    });
    if (!ok) return;

    try {
      var r = await api('/api/v1/members.php?member_id=' + encodeURIComponent(memberId), null, 'DELETE');
      if (r.body?.ok) {
        AgToast.show('success', 'Membre supprimé');
        await fetchMembers();
      } else {
        AgToast.show('error', r.body?.detail || r.body?.error || 'Erreur');
      }
    } catch (err) {
      AgToast.show('error', err.message);
    }
  };

  window.editMember = function(memberId) {
    const member = allMembers.find(m => m.id === memberId);
    if (!member) return;

    const name = member.full_name || member.name || '';
    const currentMemberGroups = memberGroups[memberId] || [];

    let groupsHtml = '';
    if (allGroups.length) {
      groupsHtml = `
        <div class="form-group mb-3">
          <label class="form-label">Groupes</label>
          <div class="groups-checkbox-list">
            ${allGroups.map(g => `
              <label class="group-checkbox-item">
                <input type="checkbox" name="memberGroup" value="${g.id}" ${currentMemberGroups.includes(g.id) ? 'checked' : ''}>
                <span class="group-color-dot" style="background-color: ${escapeHtml(g.color || '#6366f1')}"></span>
                <span>${escapeHtml(g.name)}</span>
              </label>
            `).join('')}
          </div>
        </div>
      `;
    }

    Shared.openModal({
      title: 'Modifier le membre',
      body: `
        <div class="form-group mb-3">
          <label class="form-label">Nom complet *</label>
          <input class="form-input" type="text" id="editMemberName" value="${escapeHtml(name)}" placeholder="Jean Dupont">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Email</label>
          <input class="form-input" type="email" id="editMemberEmail" value="${escapeHtml(member.email || '')}" placeholder="jean@exemple.com">
        </div>
        <div class="form-group mb-3">
          <label class="flex items-center gap-2">
            <input type="checkbox" id="editMemberActive" ${member.is_active ? 'checked' : ''}>
            <span>Membre actif</span>
          </label>
        </div>
        ${groupsHtml}
      `,
      confirmText: 'Enregistrer',
      onConfirm: async function(modal) {
        var editNameEl = modal.querySelector('#editMemberName');
        var editEmailEl = modal.querySelector('#editMemberEmail');

        var valid = Shared.validateAll([
          { input: editNameEl, rules: [{ test: function(v) { return v.length > 0; }, msg: 'Le nom est requis' }] },
          { input: editEmailEl, rules: [{ test: function(v) { return !v || Utils.isValidEmail(v); }, msg: 'Format d\u2019email invalide' }] }
        ]);
        if (!valid) return false;

        const newName = editNameEl.value.trim();
        const newEmail = editEmailEl.value.trim();
        const newActive = modal.querySelector('#editMemberActive').checked;

        const selectedGroups = Array.from(modal.querySelectorAll('input[name="memberGroup"]:checked')).map(cb => cb.value);

        try {
          const { body } = await api('/api/v1/members.php', {
            member_id: memberId,
            full_name: newName,
            email: newEmail || null,
            is_active: newActive
          }, 'PATCH');

          if (!body?.ok) {
            AgToast.show('error', body?.detail || body?.error || 'Erreur');
            return false;
          }

          const groupsChanged = JSON.stringify(selectedGroups.sort()) !== JSON.stringify(currentMemberGroups.sort());
          if (groupsChanged) {
            var grpRes = await api('/api/v1/member_group_assignments.php', {
              member_id: memberId,
              group_ids: selectedGroups
            }, 'PUT');
            if (!grpRes.body?.ok) {
              AgToast.show('warning', 'Membre modifié mais les groupes n\'ont pas été mis à jour.');
              await fetchGroups();
              await fetchMembers();
              return true;
            }
          }

          AgToast.show('success', 'Membre modifié');
          await fetchGroups();
          await fetchMembers();
          return true;
        } catch (err) {
          AgToast.show('error', err.message);
          return false;
        }
      }
    });
  };

  // ==========================================
  // EVENT LISTENERS
  // ==========================================

  // Filter chips (status)
  filterChips.forEach(chip => {
    chip.addEventListener('click', () => {
      filterChips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      currentFilter = chip.dataset.filter;
      currentPage = 1;
      // Re-fetch page 1 from server; client-side filter still applied on result set
      fetchMembers(1);
      updateFiltersHint();
    });
  });

  // Search (debounced to avoid re-render on every keystroke)
  // Re-fetches page 1 from server on search change; full-text search across all pages
  // requires a page reload to reach page 2+
  let _searchTimeout = null;
  searchInput.addEventListener('input', () => {
    clearTimeout(_searchTimeout);
    _searchTimeout = setTimeout(() => {
      currentPage = 1;
      fetchMembers(1);
      updateFiltersHint();
    }, 250);
  });

  // Sort
  sortSelect.addEventListener('change', () => {
    currentPage = 1;
    fetchMembers(1);
  });

  // Pagination — re-fetch from server on page navigation
  paginPrev.addEventListener('click', () => {
    if (currentPage > 1) { fetchMembers(currentPage - 1); }
  });
  paginNext.addEventListener('click', () => {
    if (!paginNext.disabled) { fetchMembers(currentPage + 1); }
  });
  paginSizeSelect.addEventListener('change', () => {
    // Cap at 50 to enforce server-side maximum
    pageSize = Math.min(parseInt(paginSizeSelect.value) || 50, 50);
    currentPage = 1;
    fetchMembers(1);
  });

  // CSV upload
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
    if (e.dataTransfer.files.length) {
      csvFile.files = e.dataTransfer.files;
      handleFileSelect();
    }
  });
  csvFile.addEventListener('change', handleFileSelect);

  function handleFileSelect() {
    if (csvFile.files.length) {
      fileName.textContent = csvFile.files[0].name;
      btnImport.disabled = false;
    }
  }

  // Import CSV
  btnImport.addEventListener('click', async () => {
    if (!csvFile.files.length) return;

    const formData = new FormData();
    formData.append('csv_file', csvFile.files[0]);

    Shared.btnLoading(btnImport, true);
    const importOut = document.getElementById('importOut');

    try {
      const { status, body } = await apiUpload('/api/v1/members_import_csv.php', formData);

      importOut.hidden = false;
      if (status === 0) {
        throw new Error(body?.message || 'Le téléversement a échoué (réseau ou délai)');
      }
      if (body?.ok) {
        const d = body.data || {};
        const imported = d.imported || 0;
        const skipped = d.skipped || 0;
        const errors = d.errors || [];

        let html = `<div class="import-summary import-summary-ok">
          <strong>${imported} membre${imported > 1 ? 's' : ''} importé${imported > 1 ? 's' : ''}</strong>`;
        if (skipped) html += ` — ${skipped} ligne${skipped > 1 ? 's' : ''} ignorée${skipped > 1 ? 's' : ''}`;
        html += '</div>';

        if (errors.length) {
          html += '<div class="import-errors"><div class="import-errors-title">Lignes en erreur :</div><ul>';
          errors.forEach(e => {
            html += `<li>Ligne ${e.line || '?'} : ${escapeHtml(e.error || e.message || 'Erreur')}</li>`;
          });
          html += '</ul></div>';
        }

        importOut.innerHTML = html;
        importOut.className = 'import-result';
        AgToast.show('success', `Import terminé : ${imported} membres`);
        await fetchMembers();
      } else {
        importOut.innerHTML = `<div class="import-summary import-summary-err">${escapeHtml(body?.error || body?.detail || 'Erreur import')}</div>`;
        importOut.className = 'import-result';
      }
    } catch (err) {
      importOut.hidden = false;
      importOut.innerHTML = `<div class="import-summary import-summary-err">${escapeHtml(err.message)}</div>`;
      importOut.className = 'import-result';
    } finally {
      Shared.btnLoading(btnImport, false);
      csvFile.value = '';
      fileName.textContent = 'Aucun fichier sélectionné';
      btnImport.disabled = true;
    }
  });

  // Generate seed members
  document.getElementById('btnSeed').addEventListener('click', async function () {
    const ok = await AgConfirm.ask({
      title: 'G\u00e9n\u00e9rer des membres fictifs',
      message: 'G\u00e9n\u00e9rer 10 membres fictifs pour tester l\u2019application ? Les membres g\u00e9n\u00e9r\u00e9s pourront \u00eatre supprim\u00e9s individuellement.',
      confirmLabel: 'G\u00e9n\u00e9rer',
      variant: 'info'
    });
    if (!ok) return;
    var btn = document.getElementById('btnSeed');
    Shared.btnLoading(btn, true);
    try {
      var r = await api('/api/v1/dev_seed_members.php', { count: 10 });
      if (r.body?.ok) {
        AgToast.show('success', (r.body.data?.created || 0) + ' membres générés');
        await fetchMembers();
      } else {
        AgToast.show('error', r.body?.detail || r.body?.error || 'Erreur');
      }
    } catch (err) {
      AgToast.show('error', err.message);
    } finally {
      Shared.btnLoading(btn, false);
    }
  });

  // Enter key shortcuts
  document.getElementById('mName').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') document.getElementById('btnCreate').click();
  });

  document.getElementById('groupName').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') document.getElementById('btnCreateGroup').click();
  });

  // ==========================================
  // EVENT DELEGATION (replaces inline onclick)
  // ==========================================

  document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-action]');
    if (!btn) return;
    var action = btn.dataset.action;
    var id = btn.dataset.id;

    switch (action) {
    case 'edit-group':
      window.editGroup(id);
      break;
    case 'delete-group':
      window.deleteGroup(id);
      break;
    case 'filter-group':
      window.filterByGroup(btn.dataset.groupId || null);
      break;
    case 'open-detail':
      // Ignore if the click was on an action button inside the card
      if (e.target.closest('[data-action="edit-member"]') || e.target.closest('[data-action="delete-member"]')) return;
      window.openMemberDetail(id);
      break;
    case 'edit-member':
      e.stopPropagation();
      window.editMember(id);
      if (memberDetailDialog.open) memberDetailDialog.close();
      break;
    case 'delete-member':
      e.stopPropagation();
      window.deleteMember(id);
      break;
    case 'toggle-active':
      window.toggleActive(id, btn.dataset.active === 'true');
      if (memberDetailDialog.open) memberDetailDialog.close();
      break;
    }
  });

  // ==========================================
  // INIT
  // ==========================================

  fetchGroups();
  fetchMembers();
})();
