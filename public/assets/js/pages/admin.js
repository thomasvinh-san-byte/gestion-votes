/* GO-LIVE-STATUS: ready — Admin JS. KPIs + user management only. Rebuilt v4.3. */
/**
 * admin.js — Administration page logic for AG-VOTE.
 *
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 * Handles: 4-card KPI row (members / sessions / votes / active),
 *          users CRUD (create, edit, toggle, delete, set password).
 */
(function() {
  'use strict';

  var roleLabelsSystem = Shared.ROLE_LABELS_SYSTEM;

  // ═══════════════════════════════════════════════════════
  // KPI ROW — 4 cards: Members / Sessions / Votes / Active
  // ═══════════════════════════════════════════════════════

  async function loadAdminKpis() {
    try {
      var results = await Promise.all([
        api('/api/v1/members'),
        api('/api/v1/meetings.php'),
        api('/api/v1/admin_users.php')
      ]);

      var membersRes = results[0];
      var meetingsRes = results[1];
      var usersRes = results[2];

      // Members count
      var membersEl = document.getElementById('adminKpiMembers');
      if (membersEl && membersRes.body && membersRes.body.ok) {
        var membersData = membersRes.body.data;
        var membersCount = 0;
        if (membersData && Array.isArray(membersData.items)) {
          membersCount = membersData.items.length;
        } else if (membersData && typeof membersData.total === 'number') {
          membersCount = membersData.total;
        }
        membersEl.textContent = membersCount;
      }

      // Sessions + votes count
      var sessionsEl = document.getElementById('adminKpiSessions');
      var votesEl = document.getElementById('adminKpiVotes');
      if (meetingsRes.body && meetingsRes.body.ok) {
        var meetings = (meetingsRes.body.data && meetingsRes.body.data.items) || [];
        if (sessionsEl) sessionsEl.textContent = meetings.length;
        // Sum motions_count across meetings, or fall back to closed+validated count
        var totalVotes = 0;
        meetings.forEach(function(m) {
          if (typeof m.motions_count === 'number') {
            totalVotes += m.motions_count;
          }
        });
        if (totalVotes === 0) {
          // Fallback: count meetings that have concluded votes
          totalVotes = meetings.filter(function(m) {
            return m.status === 'closed' || m.status === 'validated' || m.status === 'archived';
          }).length;
        }
        if (votesEl) votesEl.textContent = totalVotes;
      }

      // Active users count (last 7 days)
      var activeEl = document.getElementById('adminKpiActive');
      if (activeEl && usersRes.body && usersRes.body.ok) {
        var users = (usersRes.body.data && usersRes.body.data.items) || [];
        var sevenDaysAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;
        var activeCount = users.filter(function(u) {
          return u.last_login && new Date(u.last_login).getTime() > sevenDaysAgo;
        }).length;
        activeEl.textContent = activeCount;
      }

    } catch (e) {
      // KPI load failed — show visible error state on cards that are still showing '-'
      var kpiIds = ['adminKpiMembers', 'adminKpiSessions', 'adminKpiVotes', 'adminKpiActive'];
      kpiIds.forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el.textContent === '-') {
          el.textContent = '—';
          el.title = 'Erreur chargement indicateurs';
          el.style.color = 'var(--color-error, #DC2626)';
        }
      });
      setNotif('error', 'Erreur lors du chargement des indicateurs');
    }
  }

  // ═══════════════════════════════════════════════════════
  // USERS MANAGEMENT
  // ═══════════════════════════════════════════════════════
  var _users = [];
  var _allUsers = [];

  // --- Avatar helpers ---
  function getInitials(name) {
    var parts = (name || '').trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    return (name || '?').substring(0, 2).toUpperCase();
  }

  function getAvatarColor(name) {
    var colors = ['#1650E0','#059669','#D97706','#DC2626','#7C3AED','#0891B2','#BE185D','#4F46E5'];
    var hash = 0;
    var str = name || '';
    for (var i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
    return colors[Math.abs(hash) % colors.length];
  }

  // --- Pagination state ---
  var USERS_PER_PAGE = 10;
  var _usersCurrentPage = 1;

  async function loadUsers() {
    try {
      var r = await api('/api/v1/admin_users.php');
      if (r.body && r.body.ok && r.body.data) {
        _allUsers = r.body.data.items || [];
        _usersCurrentPage = 1;
        filterAndRenderUsers();
      }
    } catch (e) {
      setNotif('error', 'Erreur chargement utilisateurs');
      var c = document.getElementById('usersListContainer');
      if (c) c.innerHTML = '<div class="p-6 text-center text-muted">Erreur de chargement</div>';
    }
  }

  function filterAndRenderUsers() {
    var searchInput = document.getElementById('searchUser');
    var filterRoleEl = document.getElementById('filterRole');
    var search = searchInput ? searchInput.value.trim() : '';
    var roleFilter = filterRoleEl ? filterRoleEl.value : '';

    // Apply search filter
    if (search && window.Utils && Utils.fuzzyFilter) {
      _users = Utils.fuzzyFilter(_allUsers, search, ['name', 'email']);
    } else if (search) {
      var searchLower = search.toLowerCase();
      _users = _allUsers.filter(function(u) {
        return (u.name || '').toLowerCase().includes(searchLower) ||
               (u.email || '').toLowerCase().includes(searchLower);
      });
    } else {
      _users = _allUsers;
    }

    // Apply role filter
    if (roleFilter) {
      _users = _users.filter(function(u) { return u.role === roleFilter; });
    }

    var countEl = document.getElementById('usersCount');
    if (countEl) {
      countEl.textContent = _users.length + ' utilisateur' + (_users.length !== 1 ? 's' : '');
    }

    _usersCurrentPage = 1;
    renderUsersTable(_users, _usersCurrentPage);
  }

  function renderUsersTable(users, page) {
    var container = document.getElementById('usersListContainer');
    if (!container) return;
    if (!users.length) {
      container.innerHTML = '<div class="p-6">' + Shared.emptyState({ icon: 'members', title: 'Aucun utilisateur trouvé', description: 'Créez un compte depuis le formulaire ci-dessus.' }) + '</div>';
      updateUsersPagination(0, 0, 0);
      return;
    }

    var currentPage = page || 1;
    var totalPages = Math.ceil(users.length / USERS_PER_PAGE);
    currentPage = Math.min(Math.max(currentPage, 1), totalPages);
    _usersCurrentPage = currentPage;

    var start = (currentPage - 1) * USERS_PER_PAGE;
    var end = Math.min(start + USERS_PER_PAGE, users.length);
    var pageUsers = users.slice(start, end);

    var roleTagVariant = { admin: 'accent', operator: 'success', auditor: 'purple', viewer: '' };

    container.innerHTML = pageUsers.map(function(u) {
      var initials = getInitials(u.name || '');
      var avatarColor = getAvatarColor(u.name || '');
      var roleLabel = escapeHtml(roleLabelsSystem[u.role] || u.role);
      var variant = roleTagVariant[u.role] || '';
      var roleTagClass = variant ? 'tag tag-' + variant : 'tag';
      var activeClass = u.is_active !== false ? ' is-active' : ' is-inactive';
      var statusLabel = u.is_active !== false ? 'Actif' : 'Inactif';
      var statusBadgeClass = u.is_active !== false ? 'user-status-badge active' : 'user-status-badge';
      var isActiveVal = u.is_active !== false ? '1' : '0';
      var toggleLabel = u.is_active !== false ? 'D\u00e9sactiver' : 'Activer';
      return '<div class="user-row' + activeClass + '" data-user-id="' + escapeHtml(u.id) + '">' +
        '<div class="user-avatar" style="background:' + avatarColor + ';border-color:' + avatarColor + ';color:#fff">' + escapeHtml(initials) + '</div>' +
        '<div class="user-row-body">' +
          '<div class="user-row-main">' +
            '<span class="user-row-name">' + escapeHtml(u.name || '') + '</span>' +
            '<span class="user-row-email">' + escapeHtml(u.email || '') + '</span>' +
          '</div>' +
          '<div class="user-row-meta">' +
            '<span class="' + roleTagClass + '">' + roleLabel + '</span>' +
            '<span class="' + statusBadgeClass + '">' + statusLabel + '</span>' +
          '</div>' +
        '</div>' +
        '<div class="user-row-actions">' +
          '<ag-tooltip text="Modifier"><button class="btn btn-ghost btn-icon btn-xs btn-edit-user" data-id="' + escapeHtml(u.id) + '" type="button" aria-label="Modifier l\u2019utilisateur ' + escapeHtml(u.name || u.email || '') + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></ag-tooltip>' +
          '<ag-tooltip text="Mot de passe"><button class="btn btn-ghost btn-icon btn-xs btn-password-user" data-id="' + escapeHtml(u.id) + '" data-name="' + escapeHtml(u.name || '') + '" type="button" aria-label="R\u00e9initialiser le mot de passe de ' + escapeHtml(u.name || u.email || '') + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></button></ag-tooltip>' +
          '<ag-tooltip text="' + toggleLabel + '"><button class="btn btn-ghost btn-icon btn-xs btn-toggle-user" data-id="' + escapeHtml(u.id) + '" data-active="' + isActiveVal + '" type="button" aria-label="' + toggleLabel + ' ' + escapeHtml(u.name || u.email || '') + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg></button></ag-tooltip>' +
          '<ag-tooltip text="Supprimer"><button class="btn btn-ghost btn-icon btn-xs btn-danger-text btn-delete-user" data-id="' + escapeHtml(u.id) + '" data-name="' + escapeHtml(u.name || '') + '" type="button" aria-label="Supprimer l\u2019utilisateur ' + escapeHtml(u.name || u.email || '') + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button></ag-tooltip>' +
        '</div>' +
      '</div>';
    }).join('');

    updateUsersPagination(currentPage, totalPages, users.length, start, end);
  }

  function updateUsersPagination(currentPage, totalPages, total, start, end) {
    var infoEl = document.getElementById('usersPaginationInfo');
    var pagesEl = document.getElementById('usersPaginationPages');
    var prevBtn = document.getElementById('usersPrevPage');
    var nextBtn = document.getElementById('usersNextPage');

    if (!infoEl || !pagesEl || !prevBtn || !nextBtn) return;

    if (!total) {
      infoEl.textContent = '\u2014 utilisateurs';
      pagesEl.innerHTML = '';
      prevBtn.disabled = true;
      nextBtn.disabled = true;
      return;
    }

    infoEl.textContent = (start + 1) + '\u2013' + end + ' sur ' + total + ' utilisateur' + (total !== 1 ? 's' : '');

    var pages = '';
    for (var i = 1; i <= totalPages; i++) {
      var activeClass = i === currentPage ? ' active' : '';
      pages += '<button class="pagination-page' + activeClass + '" data-page="' + i + '">' + i + '</button>';
    }
    pagesEl.innerHTML = pages;

    prevBtn.disabled = currentPage <= 1;
    nextBtn.disabled = currentPage >= totalPages;
  }

  // ═══════════════════════════════════════════════════════
  // EVENT LISTENERS — all null-guarded
  // ═══════════════════════════════════════════════════════

  // Search input
  var searchUserEl = document.getElementById('searchUser');
  if (searchUserEl) {
    searchUserEl.addEventListener('input', filterAndRenderUsers);
  }

  // Role filter
  var filterRoleEl = document.getElementById('filterRole');
  if (filterRoleEl) {
    filterRoleEl.addEventListener('change', filterAndRenderUsers);
  }

  // Pagination prev/next
  var usersPrevBtn = document.getElementById('usersPrevPage');
  if (usersPrevBtn) {
    usersPrevBtn.addEventListener('click', function() {
      if (_usersCurrentPage > 1) {
        renderUsersTable(_users, _usersCurrentPage - 1);
      }
    });
  }

  var usersNextBtn = document.getElementById('usersNextPage');
  if (usersNextBtn) {
    usersNextBtn.addEventListener('click', function() {
      var totalPages = Math.ceil(_users.length / USERS_PER_PAGE);
      if (_usersCurrentPage < totalPages) {
        renderUsersTable(_users, _usersCurrentPage + 1);
      }
    });
  }

  var usersPagesEl = document.getElementById('usersPaginationPages');
  if (usersPagesEl) {
    usersPagesEl.addEventListener('click', function(e) {
      var btn = e.target.closest('.pagination-page');
      if (btn) {
        renderUsersTable(_users, parseInt(btn.dataset.page, 10));
      }
    });
  }

  // Password strength indicator
  var newPasswordEl = document.getElementById('newPassword');
  if (newPasswordEl) {
    newPasswordEl.addEventListener('input', function() {
      var pw = this.value;
      var strengthEl = document.getElementById('passwordStrength');
      var fillEl = document.getElementById('passwordStrengthFill');
      var textEl = document.getElementById('passwordStrengthText');
      if (!strengthEl || !fillEl || !textEl) return;

      if (!pw) { strengthEl.hidden = true; return; }
      strengthEl.hidden = false;

      var score = 0;
      if (pw.length >= 8) score++;
      if (pw.length >= 12) score++;
      if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
      if (/\d/.test(pw)) score++;
      if (/[^A-Za-z0-9]/.test(pw)) score++;

      var levels = [
        { cls: 'weak', text: 'Faible', color: 'var(--color-danger)' },
        { cls: 'fair', text: 'Moyen', color: 'var(--color-warning)' },
        { cls: 'good', text: 'Bon', color: 'var(--color-primary)' },
        { cls: 'strong', text: 'Fort', color: 'var(--color-success)' }
      ];
      var level = score <= 1 ? 0 : score <= 2 ? 1 : score <= 3 ? 2 : 3;
      fillEl.className = 'password-strength-fill ' + levels[level].cls;
      textEl.textContent = levels[level].text;
      textEl.style.color = levels[level].color;
    });
  }

  // Inline validation on create user form
  var newNameEl = document.getElementById('newName');
  if (newNameEl) {
    Shared.liveValidate(newNameEl, [
      { test: function(v) { return v.length > 0; }, msg: 'Le nom est requis' }
    ]);
  }
  var newEmailEl = document.getElementById('newEmail');
  if (newEmailEl) {
    Shared.liveValidate(newEmailEl, [
      { test: function(v) { return v.length > 0; }, msg: 'L\u2019e-mail est requis' },
      { test: function(v) { return Utils.isValidEmail(v); }, msg: 'Format d\u2019e-mail invalide' }
    ]);
  }
  var newPwEl = document.getElementById('newPassword');
  if (newPwEl) {
    Shared.liveValidate(newPwEl, [
      { test: function(v) { return v.length >= 8; }, msg: 'Minimum 8 caract\u00e8res' }
    ]);
  }

  // Create user button — opens form modal or inline form submit
  var btnCreateUserEl = document.getElementById('btnCreateUser');
  if (btnCreateUserEl) {
    btnCreateUserEl.addEventListener('click', async function() {
      var btn = this;

      // Check if inline form fields are filled — if not, open modal-style inline form
      var nameEl = document.getElementById('newName');
      var emailEl = document.getElementById('newEmail');
      var pwEl = document.getElementById('newPassword');
      var roleEl = document.getElementById('newRole');

      // If fields empty / not visible, open modal form
      if (!nameEl || !nameEl.value.trim()) {
        // Toggle create form visibility (the form is in the DOM, just scroll to it)
        var createForm = document.querySelector('.admin-create-form');
        if (createForm) {
          createForm.hidden = !createForm.hidden;
          if (!createForm.hidden && nameEl) nameEl.focus();
        }
        return;
      }

      var valid = Shared.validateAll([
        { input: nameEl, rules: [{ test: function(v) { return v.length > 0; }, msg: 'Le nom est requis' }] },
        { input: emailEl, rules: [
          { test: function(v) { return v.length > 0; }, msg: 'L\u2019e-mail est requis' },
          { test: function(v) { return Utils.isValidEmail(v); }, msg: 'Format d\u2019e-mail invalide' }
        ]},
        { input: pwEl, rules: [{ test: function(v) { return v.length >= 8; }, msg: 'Minimum 8 caract\u00e8res' }] }
      ]);
      if (!valid) return;

      var name = nameEl.value.trim();
      var email = emailEl.value.trim();
      var role = roleEl ? roleEl.value : 'viewer';
      var password = pwEl.value;

      Shared.btnLoading(btn, true);
      try {
        var r = await api('/api/v1/admin_users.php', { action: 'create', name: name, email: email, role: role, password: password });
        if (r.body && r.body.ok) {
          setNotif('success', 'Utilisateur cr\u00e9\u00e9');
          nameEl.value = '';
          emailEl.value = '';
          pwEl.value = '';
          Shared.fieldClear(nameEl);
          Shared.fieldClear(emailEl);
          Shared.fieldClear(pwEl);
          var strengthEl = document.getElementById('passwordStrength');
          if (strengthEl) strengthEl.hidden = true;
          loadUsers();
        } else {
          setNotif('error', getApiError(r.body));
        }
      } catch (e) { setNotif('error', e.message); }
      finally { Shared.btnLoading(btn, false); }
    });
  }

  // Delegated clicks on users list
  var usersListContainerEl = document.getElementById('usersListContainer');
  if (usersListContainerEl) {
    usersListContainerEl.addEventListener('click', async function(e) {
      var btn;

      // Toggle active
      btn = e.target.closest('.btn-toggle-user');
      if (btn) {
        var active = btn.dataset.active === '1' ? 0 : 1;
        var label = active ? 'activer' : 'd\u00e9sactiver';
        var toggleBtn = btn;
        var ok = await AgConfirm.ask({
          title: (active ? 'Activer' : 'D\u00e9sactiver') + ' l\'utilisateur',
          message: 'Voulez-vous ' + label + ' cet utilisateur ?',
          confirmLabel: active ? 'Activer' : 'D\u00e9sactiver',
          variant: 'warning'
        });
        if (!ok) return;
        Shared.btnLoading(toggleBtn, true);
        try {
          var r = await api('/api/v1/admin_users.php', { action: 'toggle', user_id: toggleBtn.dataset.id, is_active: active });
          if (r.body && r.body.ok) {
            loadUsers();
          } else {
            setNotif('error', getApiError(r.body));
          }
        } catch (err) { setNotif('error', err.message); }
        finally { Shared.btnLoading(toggleBtn, false); }
        return;
      }

      // Set password
      btn = e.target.closest('.btn-password-user');
      if (btn) {
        var userId = btn.dataset.id;
        var userName = btn.dataset.name || '';
        Shared.openModal({
          title: 'D\u00e9finir le mot de passe \u2014 ' + escapeHtml(userName),
          body:
            '<div class="form-group mb-4">' +
              '<label class="form-label">Nouveau mot de passe</label>' +
              '<input class="form-input" type="password" id="setPassword" placeholder="Min. 8 caract\u00e8res" autocomplete="new-password">' +
            '</div>' +
            '<div class="form-group mb-4">' +
              '<label class="form-label">Confirmer le nouveau mot de passe</label>' +
              '<input class="form-input" type="password" id="confirmPassword" placeholder="Confirmer le mot de passe" autocomplete="new-password">' +
            '</div>' +
            '<div class="form-group">' +
              '<label class="form-label">Votre mot de passe (confirmation)</label>' +
              '<input class="form-input" type="password" id="confirmAdminPw" placeholder="Votre mot de passe administrateur" autocomplete="current-password">' +
            '</div>',
          confirmText: 'Enregistrer',
          onConfirm: async function(modal) {
            var pw = modal.querySelector('#setPassword').value;
            var confirm = modal.querySelector('#confirmPassword').value;
            var adminPw = modal.querySelector('#confirmAdminPw').value;
            if (!pw || pw.length < 8) { Shared.fieldError(modal.querySelector('#setPassword'), 'Minimum 8 caract\u00e8res'); return false; }
            if (pw !== confirm) { Shared.fieldError(modal.querySelector('#confirmPassword'), 'Les mots de passe ne correspondent pas'); return false; }
            if (!adminPw) { Shared.fieldError(modal.querySelector('#confirmAdminPw'), 'Mot de passe requis'); return false; }
            try {
              var r = await api('/api/v1/admin_users.php', { action: 'set_password', user_id: userId, password: pw, confirm_password: adminPw });
              if (r.body && r.body.ok) {
                setNotif('success', 'Mot de passe d\u00e9fini');
                loadUsers();
              } else if (r.body && r.body.error === 'confirmation_failed') {
                Shared.fieldError(modal.querySelector('#confirmAdminPw'), 'Mot de passe incorrect');
                return false;
              } else {
                setNotif('error', getApiError(r.body));
                return false;
              }
            } catch (err) { setNotif('error', err.message); return false; }
          }
        });
        return;
      }

      // Delete user
      btn = e.target.closest('.btn-delete-user');
      if (btn) {
        var delUserName = btn.dataset.name || 'cet utilisateur';
        var delBtn = btn;
        Shared.openModal({
          title: 'Supprimer l\'utilisateur',
          body: '<div class="alert alert-danger mb-3"><strong>Action irr\u00e9versible</strong></div>' +
            '<p>Supprimer d\u00e9finitivement <strong>' + escapeHtml(delUserName) + '</strong> ?</p>' +
            '<div class="form-group mt-4">' +
              '<label class="form-label">Votre mot de passe (confirmation)</label>' +
              '<input class="form-input" type="password" id="confirmDeletePw" placeholder="Votre mot de passe administrateur" autocomplete="current-password">' +
            '</div>',
          confirmText: 'Supprimer',
          confirmClass: 'btn btn-danger',
          onConfirm: async function(modal) {
            var pw = modal.querySelector('#confirmDeletePw').value;
            if (!pw) { Shared.fieldError(modal.querySelector('#confirmDeletePw'), 'Mot de passe requis'); return false; }
            Shared.btnLoading(delBtn, true);
            try {
              var r = await api('/api/v1/admin_users.php', { action: 'delete', user_id: delBtn.dataset.id, confirm_password: pw });
              if (r.body && r.body.ok) {
                setNotif('success', 'Utilisateur supprim\u00e9');
                loadUsers();
              } else if (r.body && r.body.error === 'confirmation_failed') {
                Shared.fieldError(modal.querySelector('#confirmDeletePw'), 'Mot de passe incorrect');
                return false;
              } else {
                setNotif('error', getApiError(r.body, 'Erreur lors de la suppression'));
              }
            } catch (err) { setNotif('error', err.message); }
            finally { Shared.btnLoading(delBtn, false); }
          }
        });
        return;
      }

      // Edit user
      btn = e.target.closest('.btn-edit-user');
      if (btn) {
        var user = _users.find(function(u) { return u.id === btn.dataset.id; });
        if (!user) return;

        var roleOptions = Object.keys(roleLabelsSystem).map(function(k) {
          var sel = k === user.role ? ' selected' : '';
          return '<option value="' + k + '"' + sel + '>' + escapeHtml(roleLabelsSystem[k]) + '</option>';
        }).join('');

        Shared.openModal({
          title: 'Modifier l\'utilisateur',
          body:
            '<div class="form-group mb-4">' +
              '<label class="form-label">Nom</label>' +
              '<input class="form-input" type="text" id="editName" value="' + escapeHtml(user.name || '') + '">' +
            '</div>' +
            '<div class="form-group mb-4">' +
              '<label class="form-label">E-mail</label>' +
              '<input class="form-input" type="email" id="editEmail" value="' + escapeHtml(user.email || '') + '">' +
            '</div>' +
            '<div class="form-group">' +
              '<label class="form-label">R\u00f4le syst\u00e8me</label>' +
              '<select class="form-input" id="editRole">' + roleOptions + '</select>' +
            '</div>',
          confirmText: 'Enregistrer',
          onConfirm: async function(modal) {
            var editNameEl = modal.querySelector('#editName');
            var editEmailEl = modal.querySelector('#editEmail');
            var valid = Shared.validateAll([
              { input: editNameEl, rules: [{ test: function(v) { return v.length > 0; }, msg: 'Le nom est requis' }] },
              { input: editEmailEl, rules: [
                { test: function(v) { return v.length > 0; }, msg: 'L\u2019e-mail est requis' },
                { test: function(v) { return Utils.isValidEmail(v); }, msg: 'Format d\u2019e-mail invalide' }
              ]}
            ]);
            if (!valid) return false;
            var newName = editNameEl.value.trim();
            var newEmail = editEmailEl.value.trim();
            var newRole = modal.querySelector('#editRole').value;
            try {
              var r = await api('/api/v1/admin_users.php', { action: 'update', user_id: user.id, name: newName, email: newEmail, role: newRole });
              if (r.body && r.body.ok) {
                setNotif('success', 'Utilisateur mis \u00e0 jour');
                loadUsers();
              } else {
                setNotif('error', getApiError(r.body));
                return false;
              }
            } catch (err) { setNotif('error', err.message); return false; }
          }
        });
        return;
      }
    });
  }

  // ═══════════════════════════════════════════════════════
  // REFRESH BUTTON
  // ═══════════════════════════════════════════════════════
  var btnRefreshEl = document.getElementById('btnRefresh');
  if (btnRefreshEl) {
    btnRefreshEl.addEventListener('click', function() {
      loadAdminKpis();
      loadUsers();
    });
  }

  // ═══════════════════════════════════════════════════════
  // INITIAL LOAD
  // ═══════════════════════════════════════════════════════
  loadAdminKpis();
  loadUsers();

})();
