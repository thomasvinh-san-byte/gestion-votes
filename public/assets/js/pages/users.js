/* GO-LIVE-STATUS: ready — Users JS. innerHTML audite — OK. */
/**
 * users.js — Users management page logic for AG-VOTE.
 *
 * Standalone users page extracted from admin.js.
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 * Handles: users list, CRUD (create, edit, delete, toggle, password), search, filter, pagination.
 */
(function() {
  'use strict';

  var roleLabelsSystem = Shared.ROLE_LABELS_SYSTEM;

  // ═══════════════════════════════════════════════════════
  // STATE
  // ═══════════════════════════════════════════════════════
  var _allUsers = [];
  var _users = [];
  var _currentPage = 1;
  var _pageSize = 10;
  var _editingUserId = null;
  var _currentRoleFilter = '';

  // ═══════════════════════════════════════════════════════
  // NOTIFICATION HELPER
  // Delegates to global setNotif() defined in utils.js which uses AgToast.
  // ═══════════════════════════════════════════════════════
  // (local setNotif removed — global setNotif from utils.js is used directly)

  function getApiError(body, fallback) {
    return (body && body.error) ? body.error : (fallback || 'Une erreur est survenue');
  }

  // ═══════════════════════════════════════════════════════
  // LOAD USERS
  // ═══════════════════════════════════════════════════════
  async function loadUsers() {
    var filter = _currentRoleFilter;
    var url = '/api/v1/admin_users.php' + (filter ? '?role=' + encodeURIComponent(filter) : '');
    try {
      var r = await api(url);
      if (r.body && r.body.ok && r.body.data) {
        _allUsers = r.body.data.items || [];
        _currentPage = 1;
        updateRoleCounts(_allUsers);
        filterAndRenderUsers();
      }
    } catch (e) {
      setNotif('error', 'Erreur chargement utilisateurs');
      var c = document.getElementById('usersTableBody');
      if (c) {
        c.setAttribute('aria-busy', 'false');
        c.innerHTML = '<div class="text-center p-4 text-muted">Erreur de chargement</div>';
      }
    }
  }

  // ═══════════════════════════════════════════════════════
  // ROLE COUNTS
  // ═══════════════════════════════════════════════════════
  function updateRoleCounts(users) {
    var counts = { admin: 0, operator: 0, auditor: 0, viewer: 0 };
    users.forEach(function(u) { if (counts[u.role] !== undefined) counts[u.role]++; });
    var adminEl = document.getElementById('roleCountAdmin');
    var operatorEl = document.getElementById('roleCountOperator');
    var auditorEl = document.getElementById('roleCountAuditor');
    var viewerEl = document.getElementById('roleCountViewer');
    if (adminEl) adminEl.textContent = counts.admin;
    if (operatorEl) operatorEl.textContent = counts.operator;
    if (auditorEl) auditorEl.textContent = counts.auditor;
    if (viewerEl) viewerEl.textContent = counts.viewer;
  }

  // ═══════════════════════════════════════════════════════
  // FILTER & RENDER
  // ═══════════════════════════════════════════════════════
  function filterAndRenderUsers() {
    var searchInput = document.getElementById('searchUser');
    var search = searchInput ? searchInput.value.trim() : '';

    if (search && window.Utils && Utils.fuzzyFilter) {
      _users = Utils.fuzzyFilter(_allUsers, search, ['name', 'email']);
    } else if (search) {
      var searchLower = search.toLowerCase();
      _users = _allUsers.filter(function(u) {
        return (u.name || '').toLowerCase().indexOf(searchLower) !== -1 ||
               (u.email || '').toLowerCase().indexOf(searchLower) !== -1;
      });
    } else {
      _users = _allUsers;
    }

    var countEl = document.getElementById('usersCount');
    if (countEl) {
      countEl.textContent = _users.length + ' utilisateur' + (_users.length !== 1 ? 's' : '');
    }

    // Pagination
    var pag = document.getElementById('usersPagination');
    if (pag) {
      pag.setAttribute('total', _users.length);
      pag.setAttribute('page', _currentPage);
    }

    var start = (_currentPage - 1) * _pageSize;
    var pageUsers = _users.slice(start, start + _pageSize);
    renderUsersTable(pageUsers);
  }

  function renderUsersTable(users) {
    var container = document.getElementById('usersTableBody');
    if (!container) return;
    container.setAttribute('aria-busy', 'false');

    if (!users.length) {
      container.innerHTML = '<ag-empty-state icon="members" title="Aucun utilisateur" description="Les utilisateurs autoris\u00e9s appara\u00eetront ici."></ag-empty-state>';
      return;
    }

    container.innerHTML = users.map(function(u) {
      var initials = (u.name || '?').split(' ').map(function(w) { return w[0]; }).join('').slice(0, 2).toUpperCase();
      var activeClass = u.is_active ? 'is-active' : 'is-inactive';
      var avatarRoleClass = 'avatar-' + (u.role === 'admin' ? 'admin' : u.role === 'operator' ? 'operator' : u.role === 'auditor' ? 'auditor' : 'viewer');
      var statusBadge = u.is_active
        ? '<span class="user-status-badge active">Actif</span>'
        : '<span class="user-status-badge">Inactif</span>';
      var roleBadgeClass = u.role === 'admin' ? 'admin' : u.role === 'operator' ? 'operator' : u.role === 'auditor' ? 'auditor' : 'viewer';
      var roleLabel = roleLabelsSystem[u.role] || u.role;
      var lastLogin = u.last_login
        ? new Date(u.last_login).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
        : '\u2014';

      return '<div class="user-row ' + activeClass + '" data-user-id="' + escapeHtml(u.id) + '">' +
        '<div class="user-avatar ' + avatarRoleClass + '" aria-hidden="true">' + escapeHtml(initials) + '</div>' +
        '<div class="user-row-body">' +
          '<div class="user-row-main">' +
            '<span class="user-row-name">' + escapeHtml(u.name || '') + '</span>' +
            '<span class="user-row-email">' + escapeHtml(u.email || '') + '</span>' +
          '</div>' +
          '<div class="user-row-meta">' +
            '<span class="role-badge ' + roleBadgeClass + '">' + escapeHtml(roleLabel) + '</span>' +
            statusBadge +
          '</div>' +
          '<span class="user-row-lastlogin" title="Derni\u00e8re connexion">' + lastLogin + '</span>' +
        '</div>' +
        '<div class="user-row-actions">' +
          '<ag-tooltip text="Modifier cet utilisateur" position="top">' +
            '<button class="btn btn-ghost btn-icon btn-sm btn-edit-user" data-id="' + escapeHtml(u.id) + '" type="button" aria-label="Modifier">' +
              '<svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-edit-2"></use></svg>' +
            '</button>' +
          '</ag-tooltip>' +
          '<ag-tooltip text="' + (u.is_active ? 'D\u00e9sactiver le compte' : 'Activer le compte') + '" position="top">' +
            '<button class="btn btn-ghost btn-icon btn-sm btn-toggle-user" data-id="' + escapeHtml(u.id) + '" data-active="' + (u.is_active ? '1' : '0') + '" type="button" aria-label="' + (u.is_active ? 'D\u00e9sactiver' : 'Activer') + '">' +
              '<svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-' + (u.is_active ? 'toggle-right' : 'toggle-left') + '"></use></svg>' +
            '</button>' +
          '</ag-tooltip>' +
          '<ag-tooltip text="Supprimer d\u00e9finitivement" position="top">' +
            '<button class="btn btn-ghost btn-icon btn-sm btn-danger-text btn-delete-user" data-id="' + escapeHtml(u.id) + '" data-name="' + escapeHtml(u.name || '') + '" type="button" aria-label="Supprimer">' +
              '<svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-trash-2"></use></svg>' +
            '</button>' +
          '</ag-tooltip>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  // ═══════════════════════════════════════════════════════
  // MODAL HELPERS
  // ═══════════════════════════════════════════════════════
  function openUserModal(user) {
    _editingUserId = user ? user.id : null;
    var modal = document.getElementById('userModal');
    if (!modal) return;

    // Set title
    if (modal.setAttribute) modal.setAttribute('title', user ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur');

    // Reset fields
    var idEl = document.getElementById('modalUserId');
    var nameEl = document.getElementById('modalUserName');
    var emailEl = document.getElementById('modalUserEmail');
    var pwEl = document.getElementById('modalUserPassword');
    var roleEl = document.getElementById('modalUserRole');
    var pwGroup = document.getElementById('modalPasswordGroup');
    var strengthEl = document.getElementById('passwordStrength');

    if (idEl) idEl.value = user ? user.id : '';
    if (nameEl) nameEl.value = user ? (user.name || '') : '';
    if (emailEl) emailEl.value = user ? (user.email || '') : '';
    if (pwEl) pwEl.value = '';
    if (roleEl) roleEl.value = user ? (user.role || 'viewer') : 'viewer';
    if (strengthEl) strengthEl.hidden = true;

    // Password field: required for create, optional for edit
    if (pwGroup) {
      var pwLabel = pwGroup.querySelector('label');
      if (pwLabel) pwLabel.textContent = user ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe *';
    }
    if (pwEl) pwEl.required = !user;

    // Clear validation state
    if (nameEl) Shared.fieldClear && Shared.fieldClear(nameEl);
    if (emailEl) Shared.fieldClear && Shared.fieldClear(emailEl);
    if (pwEl) Shared.fieldClear && Shared.fieldClear(pwEl);

    // Open the modal
    if (modal.open) {
      modal.open();
    } else {
      modal.removeAttribute('hidden');
    }
  }

  function closeUserModal() {
    var modal = document.getElementById('userModal');
    if (!modal) return;
    if (modal.close) {
      modal.close();
    } else {
      modal.setAttribute('hidden', '');
    }
    _editingUserId = null;
  }

  // ═══════════════════════════════════════════════════════
  // PASSWORD STRENGTH
  // ═══════════════════════════════════════════════════════
  function bindPasswordStrength(inputId, strengthId, fillId, textId) {
    var pwInput = document.getElementById(inputId);
    if (!pwInput) return;
    pwInput.addEventListener('input', function() {
      var pw = this.value;
      var strengthEl = document.getElementById(strengthId);
      var fillEl = document.getElementById(fillId);
      var textEl = document.getElementById(textId);
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

  // ═══════════════════════════════════════════════════════
  // SAVE USER (create or update)
  // ═══════════════════════════════════════════════════════
  async function saveUser() {
    var nameEl = document.getElementById('modalUserName');
    var emailEl = document.getElementById('modalUserEmail');
    var pwEl = document.getElementById('modalUserPassword');
    var roleEl = document.getElementById('modalUserRole');

    var rules = [
      { input: nameEl, rules: [{ test: function(v) { return v.length > 0; }, msg: 'Le nom est requis' }] },
      { input: emailEl, rules: [
        { test: function(v) { return v.length > 0; }, msg: 'L\u2019e-mail est requis' },
        { test: function(v) { return Utils.isValidEmail(v); }, msg: 'Format d\u2019e-mail invalide' }
      ]}
    ];

    // Password validation: required for new user, optional for edit
    if (!_editingUserId) {
      rules.push({ input: pwEl, rules: [{ test: function(v) { return v.length >= 8; }, msg: 'Minimum 8 caract\u00e8res' }] });
    } else if (pwEl && pwEl.value) {
      rules.push({ input: pwEl, rules: [{ test: function(v) { return v.length >= 8; }, msg: 'Minimum 8 caract\u00e8res' }] });
    }

    var valid = Shared.validateAll(rules);
    if (!valid) return;

    var saveBtn = document.getElementById('btnSaveUser');
    Shared.btnLoading(saveBtn, true);

    var name = nameEl ? nameEl.value.trim() : '';
    var email = emailEl ? emailEl.value.trim() : '';
    var role = roleEl ? roleEl.value : 'viewer';
    var password = pwEl ? pwEl.value : '';

    try {
      var payload;
      var r;
      if (_editingUserId) {
        // Update
        payload = { action: 'update', user_id: _editingUserId, name: name, email: email, role: role };
        if (password) payload.password = password;
        r = await api('/api/v1/admin_users.php', payload);
      } else {
        // Create
        payload = { action: 'create', name: name, email: email, role: role, password: password };
        r = await api('/api/v1/admin_users.php', payload);
      }

      if (r.body && r.body.ok) {
        setNotif('success', _editingUserId ? 'Utilisateur mis \u00e0 jour' : 'Utilisateur cr\u00e9\u00e9');
        closeUserModal();
        loadUsers();
      } else {
        setNotif('error', getApiError(r.body));
      }
    } catch (e) {
      setNotif('error', e.message || 'Une erreur est survenue');
    } finally {
      Shared.btnLoading(saveBtn, false);
    }
  }

  // ═══════════════════════════════════════════════════════
  // DELETE USER
  // ═══════════════════════════════════════════════════════
  async function deleteUser(userId, userName) {
    const ok = await AgConfirm.ask({
      title: 'Supprimer cet utilisateur ?',
      message: 'Supprimer d\u00e9finitivement ' + userName + ' ? Cette action est irr\u00e9versible.',
      confirmLabel: 'Supprimer l\'utilisateur',
      variant: 'danger'
    });
    if (!ok) return;
    try {
      var r = await api('/api/v1/admin_users.php', { action: 'delete', user_id: userId });
      if (r.body && r.body.ok) {
        setNotif('success', 'Utilisateur supprim\u00e9');
        loadUsers();
      } else {
        setNotif('error', getApiError(r.body, 'Erreur lors de la suppression'));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // ═══════════════════════════════════════════════════════
  // TOGGLE USER
  // ═══════════════════════════════════════════════════════
  async function toggleUser(userId, isActive) {
    var newActive = isActive ? 0 : 1;
    const ok = await AgConfirm.ask({
      title: (newActive ? 'Activer' : 'D\u00e9sactiver') + ' l\'utilisateur',
      message: 'Voulez-vous ' + (newActive ? 'activer' : 'd\u00e9sactiver') + ' cet utilisateur ?',
      confirmLabel: newActive ? 'Activer' : 'D\u00e9sactiver',
      variant: 'warning'
    });
    if (!ok) return;
    try {
      var r = await api('/api/v1/admin_users.php', { action: 'toggle', user_id: userId, is_active: newActive });
      if (r.body && r.body.ok) {
        loadUsers();
      } else {
        setNotif('error', getApiError(r.body, 'Erreur'));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // ═══════════════════════════════════════════════════════
  // EVENT BINDINGS
  // ═══════════════════════════════════════════════════════

  // Refresh button
  var btnRefresh = document.getElementById('btnRefresh');
  if (btnRefresh) {
    btnRefresh.addEventListener('click', loadUsers);
  }

  // Add user button
  var btnAddUser = document.getElementById('btnAddUser');
  if (btnAddUser) {
    btnAddUser.addEventListener('click', function() {
      openUserModal(null);
    });
  }

  // Search input
  var searchUserInput = document.getElementById('searchUser');
  if (searchUserInput) {
    searchUserInput.addEventListener('input', function() {
      _currentPage = 1;
      filterAndRenderUsers();
    });
  }

  // Role filter — pill tabs
  document.querySelectorAll('#roleFilter .filter-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var activeTab = document.querySelector('#roleFilter .filter-tab.active');
      if (activeTab) activeTab.classList.remove('active');
      btn.classList.add('active');
      _currentRoleFilter = btn.dataset.role || '';
      _currentPage = 1;
      loadUsers();
    });
  });

  // Modal save button
  var btnSaveUser = document.getElementById('btnSaveUser');
  if (btnSaveUser) {
    btnSaveUser.addEventListener('click', saveUser);
  }

  // Modal cancel button
  var btnCancelUser = document.getElementById('btnCancelUser');
  if (btnCancelUser) {
    btnCancelUser.addEventListener('click', closeUserModal);
  }

  // Password strength for modal
  bindPasswordStrength('modalUserPassword', 'passwordStrength', 'passwordStrengthFill', 'passwordStrengthText');

  // Delegated clicks on users table
  var usersTableBody = document.getElementById('usersTableBody');
  if (usersTableBody) {
    usersTableBody.addEventListener('click', function(e) {
      var btn;

      // Edit user
      btn = e.target.closest('.btn-edit-user');
      if (btn) {
        var userId = btn.dataset.id;
        var user = _users.find(function(u) { return String(u.id) === String(userId); });
        if (user) openUserModal(user);
        return;
      }

      // Toggle active/inactive
      btn = e.target.closest('.btn-toggle-user');
      if (btn) {
        var active = btn.dataset.active === '1';
        toggleUser(btn.dataset.id, active);
        return;
      }

      // Delete user
      btn = e.target.closest('.btn-delete-user');
      if (btn) {
        deleteUser(btn.dataset.id, btn.dataset.name || 'cet utilisateur');
        return;
      }
    });
  }

  // Pagination page-change event
  var paginationEl = document.getElementById('usersPagination');
  if (paginationEl) {
    paginationEl.addEventListener('page-change', function(e) {
      _currentPage = e.detail && e.detail.page ? e.detail.page : 1;
      filterAndRenderUsers();
    });
  }

  // ═══════════════════════════════════════════════════════
  // INIT
  // ═══════════════════════════════════════════════════════
  document.addEventListener('DOMContentLoaded', loadUsers);

})();
