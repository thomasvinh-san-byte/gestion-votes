/**
 * admin.js — Administration page logic for AG-VOTE.
 *
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 * Handles: users CRUD, meeting roles assign/revoke,
 *          policies CRUD, permissions matrix, state machine,
 *          system status, demo reset.
 */
(function() {
  'use strict';

  const roleLabelsSystem = Shared.ROLE_LABELS_SYSTEM;
  const roleLabelsSeance = Shared.ROLE_LABELS_MEETING;
  const allRoleLabels = Shared.ROLE_LABELS_ALL;

  // --- Tabs (with ARIA support) ---
  document.querySelectorAll('.admin-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.admin-tab').forEach(function(t) {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
      });
      document.querySelectorAll('.admin-panel').forEach(function(p) { p.classList.remove('active'); });
      tab.classList.add('active');
      tab.setAttribute('aria-selected', 'true');
      document.getElementById('panel-' + tab.dataset.tab).classList.add('active');
    });
  });

  // ═══════════════════════════════════════════════════════
  // USERS
  // ═══════════════════════════════════════════════════════
  let _users = [];
  let _allUsers = [];

  async function loadUsers() {
    try {
      const filter = document.getElementById('filterRole').value;
      const url = '/api/v1/admin_users.php' + (filter ? '?role=' + filter : '');
      const r = await api(url);
      if (r.body && r.body.ok && r.body.data) {
        _allUsers = r.body.data.items || [];
        filterAndRenderUsers();
      }
    } catch (e) { setNotif('error', 'Erreur chargement utilisateurs'); }
  }

  function filterAndRenderUsers() {
    const searchInput = document.getElementById('searchUser');
    const search = searchInput ? searchInput.value.trim() : '';

    if (search && window.Utils && Utils.fuzzyFilter) {
      // Use fuzzy search for better matching
      _users = Utils.fuzzyFilter(_allUsers, search, ['name', 'email']);
    } else if (search) {
      // Fallback to simple includes
      const searchLower = search.toLowerCase();
      _users = _allUsers.filter(function(u) {
        return (u.name || '').toLowerCase().includes(searchLower) ||
               (u.email || '').toLowerCase().includes(searchLower);
      });
    } else {
      _users = _allUsers;
    }

    const countEl = document.getElementById('usersCount');
    if (countEl) {
      countEl.textContent = _users.length + ' utilisateur' + (_users.length !== 1 ? 's' : '');
    }

    renderUsersTable(_users);
  }

  // Search input handler
  var searchUserInput = document.getElementById('searchUser');
  if (searchUserInput) {
    searchUserInput.addEventListener('input', filterAndRenderUsers);
  }

  function renderUsersTable(users) {
    var container = document.getElementById('usersTableBody');
    if (!users.length) {
      container.innerHTML = '<div class="text-center p-4 text-muted">Aucun utilisateur trouvé</div>';
      return;
    }
    container.innerHTML = users.map(function(u) {
      var meetingTags = (u.meeting_roles || []).map(function(mr) {
        return '<span class="meeting-role-tag"><span class="role-badge ' + escapeHtml(mr.role) + '">' +
          escapeHtml(allRoleLabels[mr.role] || mr.role) + '</span> ' +
          escapeHtml(mr.meeting_title || '') + '</span>';
      }).join(' ');

      var initials = (u.name || '?').split(' ').map(function(w) { return w[0]; }).join('').slice(0, 2).toUpperCase();
      var activeClass = u.is_active ? 'is-active' : 'is-inactive';
      var statusBadge = u.is_active
        ? '<span class="user-status-badge active">Actif</span>'
        : '<span class="user-status-badge">Inactif</span>';
      var pwBadge = u.has_password
        ? '<span class="user-pw-badge has-pw">MdP</span>'
        : '<span class="user-pw-badge">Sans MdP</span>';

      return '<div class="user-row ' + activeClass + '" data-user-id="' + u.id + '">' +
        '<div class="user-avatar">' + initials + '</div>' +
        '<div class="user-row-body">' +
          '<div class="user-row-main">' +
            '<span class="user-row-name">' + escapeHtml(u.name || '') + '</span>' +
            '<span class="user-row-email">' + escapeHtml(u.email || '') + '</span>' +
          '</div>' +
          '<div class="user-row-meta">' +
            '<span class="role-badge ' + escapeHtml(u.role) + '">' + escapeHtml(roleLabelsSystem[u.role] || u.role) + '</span>' +
            (meetingTags ? ' ' + meetingTags : '') +
            statusBadge + pwBadge +
          '</div>' +
        '</div>' +
        '<div class="user-row-actions">' +
          '<button class="btn btn-ghost btn-xs btn-edit-user" data-id="' + u.id + '">Modifier</button>' +
          '<button class="btn btn-ghost btn-xs btn-toggle-user" data-id="' + u.id + '" data-active="' + (u.is_active ? '1' : '0') + '">' + (u.is_active ? 'Désactiver' : 'Activer') + '</button>' +
          '<button class="btn btn-ghost btn-xs btn-password-user" data-id="' + u.id + '" data-name="' + escapeHtml(u.name || '') + '">Mot de passe</button>' +
          '<button class="btn btn-ghost btn-xs btn-danger-text btn-delete-user" data-id="' + u.id + '" data-name="' + escapeHtml(u.name || '') + '">Supprimer</button>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  document.getElementById('filterRole').addEventListener('change', loadUsers);

  // P7-3: Password strength indicator
  var newPasswordInput = document.getElementById('newPassword');
  if (newPasswordInput) {
    newPasswordInput.addEventListener('input', function() {
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

  // Create user
  document.getElementById('btnCreateUser').addEventListener('click', async function() {
    const btn = this;
    const name = document.getElementById('newName').value.trim();
    const email = document.getElementById('newEmail').value.trim();
    const role = document.getElementById('newRole').value;
    const password = document.getElementById('newPassword').value;
    if (!name || !email) { setNotif('error', 'Nom et e-mail requis'); return; }
    if (!Utils.isValidEmail(email)) { setNotif('error', 'Adresse e-mail invalide'); return; }
    if (!password || password.length < 8) { setNotif('error', 'Mot de passe requis (min. 8 caractères)'); return; }
    Shared.btnLoading(btn, true);
    try {
      const r = await api('/api/v1/admin_users.php', {action:'create', name:name, email:email, role:role, password:password});
      if (r.body && r.body.ok) {
        setNotif('success', 'Utilisateur créé');
        document.getElementById('newName').value = '';
        document.getElementById('newEmail').value = '';
        document.getElementById('newPassword').value = '';
        var strengthEl = document.getElementById('passwordStrength');
        if (strengthEl) strengthEl.hidden = true;
        loadUsers();
      } else {
        setNotif('error', getApiError(r.body));
      }
    } catch (e) { setNotif('error', e.message); }
    finally { Shared.btnLoading(btn, false); }
  });

  // Delegated clicks on users table
  document.getElementById('usersTableBody').addEventListener('click', async function(e) {
    let btn;

    // Toggle active
    btn = e.target.closest('.btn-toggle-user');
    if (btn) {
      const active = btn.dataset.active === '1' ? 0 : 1;
      const label = active ? 'activer' : 'désactiver';
      const toggleBtn = btn;
      Shared.openModal({
        title: (active ? 'Activer' : 'Désactiver') + ' l\'utilisateur',
        body: '<p>Voulez-vous ' + label + ' cet utilisateur ?</p>',
        confirmText: active ? 'Activer' : 'Désactiver',
        onConfirm: async function() {
          Shared.btnLoading(toggleBtn, true);
          try {
            await api('/api/v1/admin_users.php', {action:'toggle', user_id:toggleBtn.dataset.id, is_active:active});
            loadUsers();
          } catch(err) { setNotif('error', err.message); }
          finally { Shared.btnLoading(toggleBtn, false); }
        }
      });
      return;
    }

    // Set password
    btn = e.target.closest('.btn-password-user');
    if (btn) {
      const userId = btn.dataset.id;
      const userName = btn.dataset.name || '';
      Shared.openModal({
        title: 'Définir le mot de passe — ' + userName,
        body:
          '<div class="form-group mb-4">' +
            '<label class="form-label">Nouveau mot de passe</label>' +
            '<input class="form-input" type="password" id="setPassword" placeholder="Min. 8 caractères" autocomplete="new-password">' +
          '</div>' +
          '<div class="form-group">' +
            '<label class="form-label">Confirmer le mot de passe</label>' +
            '<input class="form-input" type="password" id="confirmPassword" placeholder="Confirmer le mot de passe" autocomplete="new-password">' +
          '</div>',
        confirmText: 'Enregistrer',
        onConfirm: function(modal) {
          const pw = modal.querySelector('#setPassword').value;
          const confirm = modal.querySelector('#confirmPassword').value;
          if (!pw || pw.length < 8) { setNotif('error', 'Le mot de passe doit contenir au moins 8 caractères'); return false; }
          if (pw !== confirm) { setNotif('error', 'Les mots de passe ne correspondent pas'); return false; }
          api('/api/v1/admin_users.php', {action:'set_password', user_id:userId, password:pw})
            .then(function(r) {
              if (r.body && r.body.ok) { setNotif('success', 'Mot de passe défini'); loadUsers(); }
              else { setNotif('error', getApiError(r.body)); }
            })
            .catch(function(err) { setNotif('error', err.message); });
        }
      });
      return;
    }

    // Delete user
    btn = e.target.closest('.btn-delete-user');
    if (btn) {
      const userName = btn.dataset.name || 'cet utilisateur';
      const delBtn = btn;
      Shared.openModal({
        title: 'Supprimer l\'utilisateur',
        body: '<div class="alert alert-danger mb-3"><strong>Action irréversible</strong></div>' +
          '<p>Supprimer définitivement <strong>' + escapeHtml(userName) + '</strong> ?</p>',
        confirmText: 'Supprimer',
        confirmClass: 'btn btn-danger',
        onConfirm: async function() {
          Shared.btnLoading(delBtn, true);
          try {
            var r = await api('/api/v1/admin_users.php', {action:'delete', user_id:delBtn.dataset.id});
            if (r.body && r.body.ok) {
              setNotif('success', 'Utilisateur supprimé');
              loadUsers();
            } else {
              setNotif('error', getApiError(r.body, 'Erreur lors de la suppression'));
            }
          } catch(err) { setNotif('error', err.message); }
          finally { Shared.btnLoading(delBtn, false); }
        }
      });
      return;
    }

    // Edit user (modal dialog)
    btn = e.target.closest('.btn-edit-user');
    if (btn) {
      const user = _users.find(function(u) { return u.id === btn.dataset.id; });
      if (!user) return;

      const roleOptions = Object.keys(roleLabelsSystem).map(function(k) {
        const sel = k === user.role ? ' selected' : '';
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
            '<label class="form-label">Rôle système</label>' +
            '<select class="form-input" id="editRole">' + roleOptions + '</select>' +
          '</div>',
        confirmText: 'Enregistrer',
        onConfirm: function(modal) {
          const newName = modal.querySelector('#editName').value.trim();
          const newEmail = modal.querySelector('#editEmail').value.trim();
          const newRole = modal.querySelector('#editRole').value;
          if (!newName || !newEmail) { setNotif('error', 'Nom et e-mail requis'); return false; }
          if (!Utils.isValidEmail(newEmail)) { setNotif('error', 'Adresse e-mail invalide'); return false; }
          api('/api/v1/admin_users.php', {action:'update', user_id:user.id, name:newName, email:newEmail, role:newRole})
            .then(function(r) {
              if (r.body && r.body.ok) { setNotif('success', 'Utilisateur mis à jour'); loadUsers(); }
              else { setNotif('error', getApiError(r.body)); }
            })
            .catch(function(err) { setNotif('error', err.message); });
        }
      });
      return;
    }
  });

  // ═══════════════════════════════════════════════════════
  // MEETING ROLES
  // ═══════════════════════════════════════════════════════
  let _meetings = [];

  /**
   * Check if element is an ag-searchable-select component.
   * @param {Element} el - DOM element to check
   * @returns {boolean} True if element is a searchable select
   */
  function isSearchableSelect(el) {
    return el && el.tagName && el.tagName.toLowerCase() === 'ag-searchable-select';
  }

  async function loadMeetingSelects() {
    try {
      const r = await api('/api/v1/meetings.php?active_only=1');
      if (r.body && r.body.ok && r.body.data) {
        _meetings = r.body.data.meetings || r.body.data.items || [];
        const meetingSel = document.getElementById('mrMeeting');
        const statusMap = Shared.MEETING_STATUS_MAP || {};

        if (isSearchableSelect(meetingSel)) {
          // Use ag-searchable-select API
          const options = _meetings.map(function(m) {
            const st = (statusMap[m.status] || {}).text || m.status;
            return {
              value: m.id,
              label: m.title || 'Séance',
              sublabel: st
            };
          });
          meetingSel.setOptions(options);
        } else {
          // Fallback to native select
          meetingSel.innerHTML = '<option value="">— Sélectionner —</option>' +
            _meetings.map(function(m) {
              const st = (statusMap[m.status] || {}).text || m.status;
              return '<option value="' + m.id + '">' + escapeHtml(m.title) + ' (' + escapeHtml(st) + ')</option>';
            }).join('');
        }
      }
    } catch(e) { setNotif('error', 'Erreur chargement séances'); }

    // Populate user select from full users list
    try {
      const r2 = await api('/api/v1/admin_users.php');
      if (r2.body && r2.body.ok && r2.body.data) {
        const users = r2.body.data.items || [];
        const userSel = document.getElementById('mrUser');

        if (isSearchableSelect(userSel)) {
          // Use ag-searchable-select API
          const options = users.filter(function(u) { return u.is_active; }).map(function(u) {
            return {
              value: u.id,
              label: u.name || 'Utilisateur',
              sublabel: u.email + ' — ' + (roleLabelsSystem[u.role] || u.role)
            };
          });
          userSel.setOptions(options);
        } else {
          // Fallback to native select
          userSel.innerHTML = '<option value="">— Sélectionner —</option>' +
            users.filter(function(u) { return u.is_active; }).map(function(u) {
              return '<option value="' + u.id + '">' + escapeHtml(u.name) + ' (' + escapeHtml(roleLabelsSystem[u.role] || u.role) + ')</option>';
            }).join('');
        }
      }
    } catch(e) { setNotif('error', 'Erreur chargement utilisateurs'); }
  }

  async function loadMeetingRoles() {
    const meetingId = document.getElementById('mrMeeting').value;
    const tbody = document.getElementById('meetingRolesBody');
    tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-muted">Chargement...</td></tr>';

    try {
      const url = '/api/v1/admin_meeting_roles.php' + (meetingId ? '?meeting_id=' + meetingId : '');
      const r = await api(url);
      if (r.body && r.body.ok && r.body.data) {
        const items = r.body.data.items || [];
        if (!items.length) {
          tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-muted">Aucun rôle attribué</td></tr>';
          return;
        }
        tbody.innerHTML = items.map(function(row) {
          const meetingTitle = row.meeting_title || row.meeting_id || '';
          const userName = row.user_name || row.name || row.user_id || '';
          const role = row.role || '';
          return '<tr>' +
            '<td>' + escapeHtml(meetingTitle) + '</td>' +
            '<td><strong>' + escapeHtml(userName) + '</strong></td>' +
            '<td><span class="role-badge ' + escapeHtml(role) + '">' + escapeHtml(roleLabelsSeance[role] || role) + '</span></td>' +
            '<td><button class="btn btn-ghost btn-xs btn-danger-text btn-revoke-role" ' +
              'data-meeting-id="' + escapeHtml(row.meeting_id || '') + '" ' +
              'data-user-id="' + escapeHtml(row.user_id || '') + '" ' +
              'data-role="' + escapeHtml(role) + '">Révoquer</button></td></tr>';
        }).join('');
      }
    } catch(e) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-muted">Erreur de chargement</td></tr>';
    }
  }

  // Filter roles when meeting selection changes
  document.getElementById('mrMeeting').addEventListener('change', loadMeetingRoles);

  // P7-security: Hide president option from non-admin operators
  function filterPresidentOption() {
    var auth = window.Auth || {};
    if (auth.role === 'admin') return; // admins see all
    var mrRole = document.getElementById('mrRole');
    if (mrRole) {
      var presOpt = mrRole.querySelector('option[value="president"]');
      if (presOpt) presOpt.remove();
    }
  }
  if (window.Auth && window.Auth.ready) {
    window.Auth.ready.then(filterPresidentOption);
  } else {
    filterPresidentOption();
  }

  // Assign role
  document.getElementById('btnAssignRole').addEventListener('click', async function() {
    const btn = this;
    const meetingId = document.getElementById('mrMeeting').value;
    const userId = document.getElementById('mrUser').value;
    const role = document.getElementById('mrRole').value;
    if (!meetingId || !userId) { setNotif('error', 'Séance et utilisateur requis'); return; }
    Shared.btnLoading(btn, true);
    try {
      const r = await api('/api/v1/admin_meeting_roles.php', {action:'assign', meeting_id:meetingId, user_id:userId, role:role});
      if (r.body && r.body.ok) {
        setNotif('success', 'Rôle attribué');
        loadMeetingRoles();
        loadUsers(); // refresh meeting roles column in users table
      } else {
        setNotif('error', getApiError(r.body));
      }
    } catch(e) { setNotif('error', e.message); }
    finally { Shared.btnLoading(btn, false); }
  });

  // Revoke role (delegated)
  document.getElementById('meetingRolesBody').addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-revoke-role');
    if (!btn) return;
    const revokeBtn = btn;
    const roleName = allRoleLabels[btn.dataset.role] || btn.dataset.role;
    Shared.openModal({
      title: 'Révoquer le rôle',
      body: '<p>Révoquer le rôle <strong>' + escapeHtml(roleName) + '</strong> de cet utilisateur pour cette séance ?</p>',
      confirmText: 'Révoquer',
      confirmClass: 'btn btn-danger',
      onConfirm: async function() {
        Shared.btnLoading(revokeBtn, true);
        try {
          var r = await api('/api/v1/admin_meeting_roles.php', {
            action: 'revoke',
            meeting_id: revokeBtn.dataset.meetingId,
            user_id: revokeBtn.dataset.userId,
            role: revokeBtn.dataset.role
          });
          if (r.body && r.body.ok) {
            setNotif('success', 'Rôle révoqué');
            loadMeetingRoles();
            loadUsers();
          } else {
            setNotif('error', getApiError(r.body));
          }
        } catch(err) { setNotif('error', err.message); }
        finally { Shared.btnLoading(revokeBtn, false); }
      }
    });
  });

  // P7-4: Bulk role assignment
  document.getElementById('btnBulkAssign').addEventListener('click', function() {
    const meetingId = document.getElementById('mrMeeting').value;
    if (!meetingId) { setNotif('error', 'Sélectionnez d\'abord une séance'); return; }

    const activeUsers = _allUsers.filter(function(u) { return u.is_active; });
    if (!activeUsers.length) { setNotif('error', 'Aucun utilisateur actif'); return; }

    const checkboxes = activeUsers.map(function(u) {
      return '<label style="display:flex;align-items:center;gap:0.5rem;padding:0.35rem 0;cursor:pointer;">' +
        '<input type="checkbox" value="' + escapeHtml(u.id) + '" class="bulk-user-cb"> ' +
        escapeHtml(u.name) + ' <span style="color:var(--color-text-muted);font-size:0.85rem;">(' + escapeHtml(u.email) + ')</span>' +
        '</label>';
    }).join('');

    var isAdmin = window.Auth && window.Auth.role === 'admin';
    var bulkRoleOptions =
      '<option value="voter">Électeur</option>' +
      '<option value="assessor">Assesseur</option>' +
      (isAdmin ? '<option value="president">Président</option>' : '');

    Shared.openModal({
      title: 'Assignation en masse',
      body:
        '<div class="form-group">' +
          '<label class="form-label">Rôle à attribuer</label>' +
          '<select class="form-input" id="bulkRole">' + bulkRoleOptions + '</select>' +
        '</div>' +
        '<div class="form-group">' +
          '<label class="form-label">Utilisateurs</label>' +
          '<div style="display:flex;gap:0.5rem;margin-bottom:0.5rem;">' +
            '<button type="button" class="btn btn-ghost btn-xs" id="bulkSelectAll">Tout sélectionner</button>' +
            '<button type="button" class="btn btn-ghost btn-xs" id="bulkSelectNone">Tout désélectionner</button>' +
          '</div>' +
          '<div style="max-height:300px;overflow:auto;border:1px solid var(--color-border);border-radius:8px;padding:0.5rem;" id="bulkUserList">' +
            checkboxes +
          '</div>' +
        '</div>',
      confirmText: 'Assigner',
      onConfirm: async function() {
        var role = document.getElementById('bulkRole').value;
        var checked = document.querySelectorAll('.bulk-user-cb:checked');
        if (!checked.length) { setNotif('error', 'Sélectionnez au moins un utilisateur'); return; }

        var success = 0;
        var errors = 0;
        for (var i = 0; i < checked.length; i++) {
          try {
            var r = await api('/api/v1/admin_meeting_roles.php', {
              action: 'assign', meeting_id: meetingId, user_id: checked[i].value, role: role
            });
            if (r.body && r.body.ok) success++;
            else errors++;
          } catch(e) { errors++; }
        }

        if (success > 0) setNotif('success', success + ' rôle' + (success > 1 ? 's' : '') + ' attribué' + (success > 1 ? 's' : ''));
        if (errors > 0) setNotif('error', errors + ' erreur' + (errors > 1 ? 's' : ''));
        loadMeetingRoles();
        loadUsers();
      }
    });

    // Wire up select all / none after modal is in DOM
    setTimeout(function() {
      var allBtn = document.getElementById('bulkSelectAll');
      var noneBtn = document.getElementById('bulkSelectNone');
      if (allBtn) allBtn.addEventListener('click', function() {
        document.querySelectorAll('.bulk-user-cb').forEach(function(cb) { cb.checked = true; });
      });
      if (noneBtn) noneBtn.addEventListener('click', function() {
        document.querySelectorAll('.bulk-user-cb').forEach(function(cb) { cb.checked = false; });
      });
    }, 60);
  });

  // ═══════════════════════════════════════════════════════
  // POLICIES — QUORUM
  // ═══════════════════════════════════════════════════════
  let _quorumPolicies = [];

  async function loadQuorumPolicies() {
    try {
      const r = await api('/api/v1/admin_quorum_policies.php');
      if (r.body && r.body.ok && r.body.data && Array.isArray(r.body.data.items)) {
        _quorumPolicies = r.body.data.items;
        renderQuorumList(_quorumPolicies);
      }
    } catch(e) { setNotif('error', 'Erreur chargement politiques de quorum'); }
  }

  function renderQuorumList(items) {
    const el = document.getElementById('quorumList');
    if (!items.length) {
      el.innerHTML = '<div class="text-center text-muted">Aucune politique de quorum</div>';
      return;
    }
    el.innerHTML = items.map(function(p) {
      return '<div class="policy-card">' +
        '<div class="policy-info">' +
          '<div class="policy-name">' + escapeHtml(p.name) + '</div>' +
          '<div class="policy-details">' +
            escapeHtml(p.description || '') +
            (p.mode ? ' | mode : ' + escapeHtml(p.mode) : '') +
            ' | seuil : ' + Math.round((p.threshold||0)*100) + '%' +
            (p.include_proxies ? ' | procurations' : '') +
            (p.count_remote ? ' | distants' : '') +
          '</div>' +
        '</div>' +
        '<div class="policy-actions">' +
          '<button class="btn btn-ghost btn-xs btn-edit-quorum" data-id="' + escapeHtml(p.id) + '">Modifier</button>' +
          '<button class="btn btn-ghost btn-xs btn-danger-text btn-delete-quorum" data-id="' + escapeHtml(p.id) + '" data-name="' + escapeHtml(p.name) + '">Supprimer</button>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  function openQuorumModal(policy) {
    const isEdit = !!policy;
    const p = policy || {};

    const modeOptions = ['single','evolving','double'].map(function(m) {
      const sel = m === (p.mode || 'single') ? ' selected' : '';
      const labels = { single: 'Simple', evolving: 'Évolutif', double: 'Double convocation' };
      return '<option value="' + m + '"' + sel + '>' + (labels[m] || m) + '</option>';
    }).join('');

    const denOptions = ['eligible_members','eligible_weight'].map(function(d) {
      const sel = d === (p.denominator || 'eligible_members') ? ' selected' : '';
      const labels = { eligible_members: 'Membres éligibles', eligible_weight: 'Poids éligible' };
      return '<option value="' + d + '"' + sel + '>' + (labels[d] || d) + '</option>';
    }).join('');

    const den2Options = ['eligible_members','eligible_weight'].map(function(d) {
      const sel = d === (p.denominator2 || 'eligible_members') ? ' selected' : '';
      const labels = { eligible_members: 'Membres éligibles', eligible_weight: 'Poids éligible' };
      return '<option value="' + d + '"' + sel + '>' + (labels[d] || d) + '</option>';
    }).join('');

    var showCall2 = (p.mode === 'double' || p.mode === 'evolving') ? '' : ' hidden';

    Shared.openModal({
      title: isEdit ? 'Modifier la politique de quorum' : 'Nouvelle politique de quorum',
      body:
        '<div class="form-group mb-3">' +
          '<label class="form-label">Nom</label>' +
          '<input class="form-input" type="text" id="qpName" value="' + escapeHtml(p.name || '') + '">' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Description</label>' +
          '<input class="form-input" type="text" id="qpDesc" value="' + escapeHtml(p.description || '') + '">' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Mode</label>' +
          '<select class="form-input" id="qpMode">' + modeOptions + '</select>' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Dénominateur</label>' +
          '<select class="form-input" id="qpDen">' + denOptions + '</select>' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Seuil (0 à 1)</label>' +
          '<input class="form-input" type="number" id="qpThreshold" min="0" max="1" step="0.01" value="' + (p.threshold != null ? p.threshold : '0.5') + '">' +
        '</div>' +
        '<div id="qpCall2Section"' + showCall2 + '>' +
          '<hr style="border-color:var(--color-border);margin:0.75rem 0;">' +
          '<div class="text-sm font-semibold mb-2" style="color:var(--color-text-secondary)">2e convocation / 2e tour</div>' +
          '<div class="form-group mb-3">' +
            '<label class="form-label">Seuil de convocation 2 (0 à 1)</label>' +
            '<input class="form-input" type="number" id="qpThresholdCall2" min="0" max="1" step="0.01" value="' + (p.threshold_call2 != null ? p.threshold_call2 : '') + '" placeholder="Optionnel">' +
          '</div>' +
          '<div class="form-group mb-3">' +
            '<label class="form-label">Dénominateur 2e tour</label>' +
            '<select class="form-input" id="qpDen2">' + den2Options + '</select>' +
          '</div>' +
          '<div class="form-group mb-3">' +
            '<label class="form-label">Seuil 2e tour (0 à 1)</label>' +
            '<input class="form-input" type="number" id="qpThreshold2" min="0" max="1" step="0.01" value="' + (p.threshold2 != null ? p.threshold2 : '') + '" placeholder="Optionnel">' +
          '</div>' +
        '</div>' +
        '<div class="flex gap-4 mb-3">' +
          '<label class="flex items-center gap-2 text-sm"><input type="checkbox" id="qpProxies"' + (p.include_proxies ? ' checked' : '') + '> Inclure les procurations</label>' +
          '<label class="flex items-center gap-2 text-sm"><input type="checkbox" id="qpRemote"' + (p.count_remote ? ' checked' : '') + '> Compter les distants</label>' +
        '</div>',
      confirmText: isEdit ? 'Enregistrer' : 'Créer',
      onConfirm: function(modal) {
        const name = modal.querySelector('#qpName').value.trim();
        if (!name) { setNotif('error', 'Nom requis'); return false; }
        var thresholdVal = parseFloat(modal.querySelector('#qpThreshold').value);
        if (isNaN(thresholdVal) || thresholdVal < 0 || thresholdVal > 1) {
          setNotif('error', 'Le seuil doit être compris entre 0 et 1'); return false;
        }
        var mode = modal.querySelector('#qpMode').value;
        const payload = {
          name: name,
          description: modal.querySelector('#qpDesc').value.trim(),
          mode: mode,
          denominator: modal.querySelector('#qpDen').value,
          threshold: thresholdVal,
          include_proxies: modal.querySelector('#qpProxies').checked ? 1 : 0,
          count_remote: modal.querySelector('#qpRemote').checked ? 1 : 0
        };
        // Include call-2 parameters for double/evolving modes
        if (mode === 'double' || mode === 'evolving') {
          var tc2 = modal.querySelector('#qpThresholdCall2').value;
          var t2 = modal.querySelector('#qpThreshold2').value;
          if (tc2 !== '') payload.threshold_call2 = parseFloat(tc2);
          payload.denominator2 = modal.querySelector('#qpDen2').value;
          if (t2 !== '') payload.threshold2 = parseFloat(t2);
        }
        if (isEdit) payload.id = p.id;
        api('/api/v1/admin_quorum_policies.php', payload)
          .then(function(r) {
            if (r.body && r.body.ok) { setNotif('success', isEdit ? 'Politique mise à jour' : 'Politique créée'); loadQuorumPolicies(); }
            else { setNotif('error', getApiError(r.body)); }
          })
          .catch(function(err) { setNotif('error', err.message); });
      }
    });

    // Toggle call-2 section visibility based on mode selection
    setTimeout(function() {
      var modeSelect = document.getElementById('qpMode');
      var call2Section = document.getElementById('qpCall2Section');
      if (modeSelect && call2Section) {
        modeSelect.addEventListener('change', function() {
          call2Section.hidden = (this.value === 'single');
        });
      }
    }, 60);
  }

  document.getElementById('btnAddQuorum').addEventListener('click', function() { openQuorumModal(null); });

  document.getElementById('quorumList').addEventListener('click', async function(e) {
    // Edit
    var btn = e.target.closest('.btn-edit-quorum');
    if (btn) {
      const policy = _quorumPolicies.find(function(p) { return p.id === btn.dataset.id; });
      if (policy) openQuorumModal(policy);
      return;
    }
    // Delete
    btn = e.target.closest('.btn-delete-quorum');
    if (btn) {
      const name = btn.dataset.name || 'cette politique';
      const delBtn = btn;
      Shared.openModal({
        title: 'Supprimer la politique de quorum',
        body: '<div class="alert alert-danger mb-3"><strong>Action irréversible</strong></div>' +
          '<p>Supprimer la politique « <strong>' + escapeHtml(name) + '</strong> » ?</p>',
        confirmText: 'Supprimer',
        confirmClass: 'btn btn-danger',
        onConfirm: async function() {
          Shared.btnLoading(delBtn, true);
          try {
            var r = await api('/api/v1/admin_quorum_policies.php', {action:'delete', id:delBtn.dataset.id});
            if (r.body && r.body.ok) {
              setNotif('success', 'Politique supprimée');
              loadQuorumPolicies();
            } else {
              setNotif('error', getApiError(r.body, 'Erreur lors de la suppression'));
            }
          } catch(err) { setNotif('error', err.message); }
          finally { Shared.btnLoading(delBtn, false); }
        }
      });
    }
  });

  // ═══════════════════════════════════════════════════════
  // POLICIES — VOTE
  // ═══════════════════════════════════════════════════════
  let _votePolicies = [];

  async function loadVotePolicies() {
    try {
      const r = await api('/api/v1/admin_vote_policies.php');
      if (r.body && r.body.ok && r.body.data && Array.isArray(r.body.data.items)) {
        _votePolicies = r.body.data.items;
        renderVoteList(_votePolicies);
      }
    } catch(e) { setNotif('error', 'Erreur chargement politiques de vote'); }
  }

  function renderVoteList(items) {
    const el = document.getElementById('voteList');
    if (!items.length) {
      el.innerHTML = '<div class="text-center text-muted">Aucune politique de vote</div>';
      return;
    }
    el.innerHTML = items.map(function(p) {
      return '<div class="policy-card">' +
        '<div class="policy-info">' +
          '<div class="policy-name">' + escapeHtml(p.name) + '</div>' +
          '<div class="policy-details">' +
            escapeHtml(p.description || '') +
            (p.base ? ' | base : ' + escapeHtml(p.base) : '') +
            ' | seuil : ' + Math.round((p.threshold||0)*100) + '%' +
            (p.abstention_as_against ? ' | abstention=contre' : '') +
          '</div>' +
        '</div>' +
        '<div class="policy-actions">' +
          '<button class="btn btn-ghost btn-xs btn-edit-vote" data-id="' + escapeHtml(p.id) + '">Modifier</button>' +
          '<button class="btn btn-ghost btn-xs btn-danger-text btn-delete-vote" data-id="' + escapeHtml(p.id) + '" data-name="' + escapeHtml(p.name) + '">Supprimer</button>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  function openVoteModal(policy) {
    const isEdit = !!policy;
    const p = policy || {};

    const baseOptions = ['expressed','total_eligible'].map(function(b) {
      const sel = b === (p.base || 'expressed') ? ' selected' : '';
      return '<option value="' + b + '"' + sel + '>' + b + '</option>';
    }).join('');

    Shared.openModal({
      title: isEdit ? 'Modifier la politique de vote' : 'Nouvelle politique de vote',
      body:
        '<div class="form-group mb-3">' +
          '<label class="form-label">Nom</label>' +
          '<input class="form-input" type="text" id="vpName" value="' + escapeHtml(p.name || '') + '">' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Description</label>' +
          '<input class="form-input" type="text" id="vpDesc" value="' + escapeHtml(p.description || '') + '">' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Base de calcul</label>' +
          '<select class="form-input" id="vpBase">' + baseOptions + '</select>' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Seuil (0 à 1)</label>' +
          '<input class="form-input" type="number" id="vpThreshold" min="0" max="1" step="0.01" value="' + (p.threshold != null ? p.threshold : '0.5') + '">' +
        '</div>' +
        '<label class="flex items-center gap-2 text-sm">' +
          '<input type="checkbox" id="vpAbstention"' + (p.abstention_as_against ? ' checked' : '') + '>' +
          ' Compter les abstentions comme contre' +
        '</label>',
      confirmText: isEdit ? 'Enregistrer' : 'Créer',
      onConfirm: function(modal) {
        const name = modal.querySelector('#vpName').value.trim();
        if (!name) { setNotif('error', 'Nom requis'); return false; }
        var thresholdVal = parseFloat(modal.querySelector('#vpThreshold').value);
        if (isNaN(thresholdVal) || thresholdVal < 0 || thresholdVal > 1) {
          setNotif('error', 'Le seuil doit être compris entre 0 et 1'); return false;
        }
        const payload = {
          name: name,
          description: modal.querySelector('#vpDesc').value.trim(),
          base: modal.querySelector('#vpBase').value,
          threshold: thresholdVal,
          abstention_as_against: modal.querySelector('#vpAbstention').checked ? 1 : 0
        };
        if (isEdit) payload.id = p.id;
        api('/api/v1/admin_vote_policies.php', payload)
          .then(function(r) {
            if (r.body && r.body.ok) { setNotif('success', isEdit ? 'Politique mise à jour' : 'Politique créée'); loadVotePolicies(); }
            else { setNotif('error', getApiError(r.body)); }
          })
          .catch(function(err) { setNotif('error', err.message); });
      }
    });
  }

  document.getElementById('btnAddVote').addEventListener('click', function() { openVoteModal(null); });

  document.getElementById('voteList').addEventListener('click', async function(e) {
    // Edit
    var btn = e.target.closest('.btn-edit-vote');
    if (btn) {
      const policy = _votePolicies.find(function(p) { return p.id === btn.dataset.id; });
      if (policy) openVoteModal(policy);
      return;
    }
    // Delete
    btn = e.target.closest('.btn-delete-vote');
    if (btn) {
      const name = btn.dataset.name || 'cette politique';
      const delBtn = btn;
      Shared.openModal({
        title: 'Supprimer la politique de vote',
        body: '<div class="alert alert-danger mb-3"><strong>Action irréversible</strong></div>' +
          '<p>Supprimer la politique « <strong>' + escapeHtml(name) + '</strong> » ?</p>',
        confirmText: 'Supprimer',
        confirmClass: 'btn btn-danger',
        onConfirm: async function() {
          Shared.btnLoading(delBtn, true);
          try {
            var r = await api('/api/v1/admin_vote_policies.php', {action:'delete', id:delBtn.dataset.id});
            if (r.body && r.body.ok) {
              setNotif('success', 'Politique supprimée');
              loadVotePolicies();
            } else {
              setNotif('error', getApiError(r.body, 'Erreur lors de la suppression'));
            }
          } catch(err) { setNotif('error', err.message); }
          finally { Shared.btnLoading(delBtn, false); }
        }
      });
    }
  });

  // ═══════════════════════════════════════════════════════
  // PERMISSIONS MATRIX
  // ═══════════════════════════════════════════════════════
  async function loadRoles() {
    try {
      const r = await api('/api/v1/admin_roles.php');
      if (!r.body || !r.body.ok) return;
      const d = r.body.data;

      // System roles info
      const sysInfo = Object.entries(d.system_roles || {}).map(function(e) {
        const cnt = (d.users_by_system_role || []).find(function(x) { return x.role === e[0]; });
        return '<div class="flex items-center justify-between py-2 border-b" style="border-color:var(--color-border-subtle)">' +
          '<span class="role-badge ' + e[0] + '">' + escapeHtml(e[1]) + '</span>' +
          '<span class="text-sm text-muted">' + ((cnt && cnt.count) || 0) + ' utilisateur(s)</span></div>';
      }).join('');
      document.getElementById('systemRolesInfo').innerHTML = sysInfo;

      // Meeting roles info
      const mtgInfo = Object.entries(d.meeting_roles || {}).map(function(e) {
        const cnt = (d.meeting_role_counts || []).find(function(x) { return x.role === e[0]; });
        return '<div class="flex items-center justify-between py-2 border-b" style="border-color:var(--color-border-subtle)">' +
          '<span class="role-badge ' + e[0] + '">' + escapeHtml(e[1]) + '</span>' +
          '<span class="text-sm text-muted">' + ((cnt && cnt.users) || 0) + ' personne(s), ' + ((cnt && cnt.meetings) || 0) + ' séance(s)</span></div>';
      }).join('');
      document.getElementById('meetingRolesInfo').innerHTML = mtgInfo;

      // Permission matrix
      const perms = d.permissions_by_role || {};
      const allPermsSet = {};
      const roleOrder = ['admin','operator','auditor','viewer','president','assessor','voter'];
      roleOrder.forEach(function(role) {
        (perms[role] || []).forEach(function(p) { allPermsSet[p.permission] = true; });
      });
      const allPerms = Object.keys(allPermsSet).sort();

      // Group by resource
      const groups = {};
      allPerms.forEach(function(p) {
        const parts = p.split(':');
        const g = parts[0];
        if (!groups[g]) groups[g] = [];
        groups[g].push(p);
      });

      const permsByRole = {};
      roleOrder.forEach(function(role) {
        permsByRole[role] = {};
        (perms[role] || []).forEach(function(p) { permsByRole[role][p.permission] = true; });
      });

      let html = '<table class="perm-matrix"><thead><tr><th>Droit</th>';
      roleOrder.forEach(function(role) {
        const isSys = !!roleLabelsSystem[role];
        html += '<th><span class="role-badge ' + role + '">' + escapeHtml(allRoleLabels[role] || role) + '</span><br><span class="text-xs text-muted">' + (isSys ? 'S' : 'M') + '</span></th>';
      });
      html += '</tr></thead><tbody>';

      Object.keys(groups).sort().forEach(function(g) {
        html += '<tr><td colspan="' + (roleOrder.length + 1) + '" style="background:var(--color-bg-subtle);font-weight:700;text-transform:uppercase;font-size:10px;letter-spacing:0.1em;padding:6px 8px">' + escapeHtml(g) + '</td></tr>';
        groups[g].forEach(function(perm) {
          html += '<tr><td>' + escapeHtml(perm) + '</td>';
          roleOrder.forEach(function(role) {
            html += '<td>' + (permsByRole[role][perm] ? '<span class="perm-check"><svg class="icon icon-sm"><use href="/assets/icons.svg#icon-check"></use></svg></span>' : '<span class="perm-none">-</span>') + '</td>';
          });
          html += '</tr>';
        });
      });
      html += '</tbody></table>';
      document.getElementById('permMatrix').innerHTML = html;

      // Permission matrix search filtering
      const permSearchEl = document.getElementById('permSearch');
      if (permSearchEl) {
        permSearchEl.addEventListener('input', function() {
          const query = this.value.toLowerCase().trim();
          const rows = document.querySelectorAll('#permMatrix table tbody tr');
          rows.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            // Always show group header rows (they have colspan)
            const isGroupHeader = row.querySelector('td[colspan]');
            if (isGroupHeader) {
              row.style.display = '';
              return;
            }
            row.style.display = (!query || text.includes(query)) ? '' : 'none';
          });
        });
      }

    } catch (e) { setNotif('error', 'Erreur chargement rôles'); }
  }

  // ═══════════════════════════════════════════════════════
  // STATE MACHINE
  // ═══════════════════════════════════════════════════════
  async function loadStates() {
    try {
      const r = await api('/api/v1/admin_roles.php');
      if (!r.body || !r.body.ok) return;
      const d = r.body.data;
      const statuses = d.statuses || {};
      const transitions = d.state_transitions || [];

      // State icons
      const stateIcons = {
        'draft': icon('file-text', 'icon-sm'),
        'scheduled': icon('calendar', 'icon-sm'),
        'frozen': icon('lock', 'icon-sm'),
        'live': icon('circle', 'icon-sm'),
        'closed': icon('check-circle', 'icon-sm'),
        'validated': icon('clipboard-list', 'icon-sm'),
        'archived': icon('archive', 'icon-sm')
      };

      // Flow diagram with visual styling
      const flow = ['draft','scheduled','frozen','live','closed','validated','archived'];
      document.getElementById('stateFlow').innerHTML = flow.map(function(s, i) {
        const label = statuses[s] || s;
        const icon = stateIcons[s] || '';
        return (i > 0 ? '<span class="state-arrow-visual">→</span>' : '') +
          '<span class="state-node-visual ' + escapeHtml(s) + '">' + icon + ' ' + escapeHtml(label) + '</span>';
      }).join('');

      // Transitions table with visual states
      document.getElementById('transitionsBody').innerHTML = transitions.map(function(t) {
        const fromIcon = stateIcons[t.from_status] || '';
        const toIcon = stateIcons[t.to_status] || '';
        return '<tr>' +
          '<td><span class="state-node-visual ' + escapeHtml(t.from_status) + '" style="padding:0.5rem 0.75rem;font-size:0.8rem">' + fromIcon + ' ' + escapeHtml(statuses[t.from_status] || t.from_status) + '</span></td>' +
          '<td><span class="state-node-visual ' + escapeHtml(t.to_status) + '" style="padding:0.5rem 0.75rem;font-size:0.8rem">' + toIcon + ' ' + escapeHtml(statuses[t.to_status] || t.to_status) + '</span></td>' +
          '<td><span class="role-badge ' + escapeHtml(t.required_role) + '">' + escapeHtml(allRoleLabels[t.required_role] || t.required_role) + '</span></td>' +
          '<td class="text-sm">' + escapeHtml(t.description || '') + '</td></tr>';
      }).join('');

      // Load state stats + archived meetings (single API call)
      loadStateStatsAndArchived();

    } catch (e) { setNotif('error', 'Erreur chargement états'); }
  }

  // Load statistics by state + archived meetings list (single API call)
  async function loadStateStatsAndArchived() {
    var list = document.getElementById('archivedMeetingsList');
    try {
      var r = await api('/api/v1/meetings.php');
      if (r.body && r.body.ok && r.body.data) {
        var meetings = r.body.data.meetings || r.body.data.items || r.body.data || [];

        // --- State stats ---
        var counts = {
          draft: 0, scheduled: 0, frozen: 0, live: 0, closed: 0, validated: 0, archived: 0
        };
        meetings.forEach(function(m) {
          if (counts.hasOwnProperty(m.status)) counts[m.status]++;
        });

        var statsEl = document.getElementById('stateStats');
        statsEl.innerHTML = '<div class="flex flex-wrap gap-3">' +
          Object.entries(counts).map(function(e) {
            var status = e[0];
            var count = e[1];
            return '<div class="flex items-center gap-2">' +
              '<span class="state-node-visual ' + status + '" style="padding:0.25rem 0.5rem;font-size:0.75rem">' + count + '</span>' +
            '</div>';
          }).join('') +
        '</div>';

        // --- Archived meetings ---
        var archived = meetings.filter(function(m) { return m.status === 'archived'; });
        if (!archived.length) {
          list.innerHTML = '<div class="text-center p-4 text-muted">Aucune séance archivée</div>';
          return;
        }
        list.innerHTML = archived.map(function(m) {
          var date = m.archived_at ? new Date(m.archived_at).toLocaleDateString('fr-FR') : '—';
          return '<div class="system-stat" style="padding:0.75rem 1rem;">' +
            '<div style="flex:1;">' +
              '<strong>' + escapeHtml(m.title || m.slug || '') + '</strong>' +
              '<div class="text-xs text-muted">Archivée le ' + date + '</div>' +
            '</div>' +
            '<button class="btn btn-ghost btn-xs btn-unarchive" data-meeting-id="' + escapeHtml(m.id) + '" data-title="' + escapeHtml(m.title || '') + '">Dé-archiver</button>' +
          '</div>';
        }).join('');

        list.querySelectorAll('.btn-unarchive').forEach(function(btn) {
          btn.addEventListener('click', function() {
            var meetingId = btn.dataset.meetingId;
            var title = btn.dataset.title;
            Shared.openModal({
              title: 'Dé-archiver la séance',
              body: '<p>Restaurer <strong>' + escapeHtml(title) + '</strong> vers l\'état « Validée » ?</p>' +
                    '<p class="text-sm text-muted">Cela permet de corriger ou re-valider la séance avant de l\'archiver à nouveau.</p>',
              confirmText: 'Dé-archiver',
              onConfirm: async function() {
                try {
                  var r = await api('/api/v1/meeting_transition.php', {
                    meeting_id: meetingId,
                    to_status: 'validated'
                  });
                  if (r.body && r.body.ok) {
                    setNotif('success', 'Séance dé-archivée');
                    loadStateStatsAndArchived();
                  } else {
                    setNotif('error', getApiError(r.body));
                  }
                } catch(e) { setNotif('error', e.message); }
              }
            });
          });
        });
      }
    } catch(e) {
      list.innerHTML = '<div class="text-center p-4 text-muted">Erreur de chargement</div>';
    }
  }

  // ═══════════════════════════════════════════════════════
  // SYSTEM STATUS
  // ═══════════════════════════════════════════════════════
  async function loadSystemStatus() {
    try {
      const r = await api('/api/v1/admin_system_status.php');
      if (r.body && r.body.ok && r.body.data) {
        const s = r.body.data.system || r.body.data;
        document.getElementById('statDbStatus').textContent = 'Connectée';
        document.getElementById('statDbStatus').className = 'system-stat-value text-success';
        document.getElementById('statDbLatency').textContent = s.db_latency_ms != null ? s.db_latency_ms + ' ms' : '—';
        document.getElementById('statDbConnections').textContent = s.db_active_connections || '—';
        document.getElementById('statActiveMeetings').textContent = s.active_meetings || '—';
        document.getElementById('statTotalMeetings').textContent = s.count_meetings || '—';
        document.getElementById('statTotalMembers').textContent = s.total_members || '—';
        document.getElementById('statPhpVersion').textContent = s.php_version || '—';
        document.getElementById('statMemory').textContent = s.memory_usage || '—';
        document.getElementById('systemStatus').className = 'badge badge-success badge-dot';
        document.getElementById('systemStatus').textContent = 'En ligne';

        // --- Health KPI strip updates ---
        // Color-code latency with accessible labels
        var latencyText = s.db_latency_ms != null ? s.db_latency_ms + ' ms' : '—';
        var latencyVal = parseFloat(latencyText);
        var latencyDot = document.getElementById('healthLatencyDot');
        var latencyDisplay = document.getElementById('healthLatencyValue');
        if (latencyDot && latencyDisplay) {
          latencyDisplay.textContent = latencyText;
          if (latencyVal < 50) {
            latencyDot.className = 'admin-health-icon success';
            latencyDot.setAttribute('aria-label', 'Bonne latence');
          } else if (latencyVal < 200) {
            latencyDot.className = 'admin-health-icon warning';
            latencyDot.setAttribute('aria-label', 'Latence moyenne');
          } else {
            latencyDot.className = 'admin-health-icon danger';
            latencyDot.setAttribute('aria-label', 'Latence élevée');
          }
        }

        // Color-code memory with accessible labels
        // memory_usage is in MB (e.g. "12.3 MB"), use MB-based thresholds
        var memoryText = s.memory_usage || '—';
        var memoryDot = document.getElementById('healthMemoryDot');
        var memoryDisplay = document.getElementById('healthMemoryValue');
        if (memoryDot && memoryDisplay) {
          memoryDisplay.textContent = memoryText;
          var memMB = parseFloat(memoryText) || 0;
          if (memMB < 64) {
            memoryDot.className = 'admin-health-icon success';
            memoryDot.setAttribute('aria-label', 'Mémoire normale');
          } else if (memMB < 128) {
            memoryDot.className = 'admin-health-icon warning';
            memoryDot.setAttribute('aria-label', 'Mémoire élevée');
          } else {
            memoryDot.className = 'admin-health-icon danger';
            memoryDot.setAttribute('aria-label', 'Mémoire critique');
          }
        }

        // Active meetings count
        var meetingsDisplay = document.getElementById('healthMeetingsValue');
        if (meetingsDisplay) {
          meetingsDisplay.textContent = s.active_meetings || '0';
        }

        // System alerts
        var alertsContainer = document.getElementById('systemAlerts');
        var alerts = r.body.data.alerts || [];
        if (alertsContainer) {
          if (!alerts.length) {
            alertsContainer.innerHTML = '<div class="text-center p-3 text-muted text-sm">Aucune alerte récente</div>';
          } else {
            alertsContainer.innerHTML = alerts.map(function(a) {
              var sevClass = a.severity === 'critical' ? 'danger' : (a.severity === 'warn' ? 'warning' : 'info');
              var ts = a.created_at ? new Date(a.created_at).toLocaleString('fr-FR') : '';
              return '<div class="system-stat" style="padding:0.6rem 1rem;">' +
                '<span class="admin-health-icon ' + sevClass + '" style="flex-shrink:0">●</span>' +
                '<div style="flex:1;">' +
                  '<span class="text-sm font-medium">' + escapeHtml(a.message || a.code) + '</span>' +
                  (ts ? '<div class="text-xs text-muted">' + ts + '</div>' : '') +
                '</div>' +
              '</div>';
            }).join('');
          }
        }
      } else {
        document.getElementById('statDbStatus').textContent = 'Erreur';
        document.getElementById('statDbStatus').className = 'system-stat-value text-danger';
        document.getElementById('systemStatus').className = 'badge badge-danger';
        document.getElementById('systemStatus').textContent = 'Erreur';
      }
    } catch (e) {
      document.getElementById('statDbStatus').textContent = 'Hors ligne';
      document.getElementById('statDbStatus').className = 'system-stat-value text-danger';
      document.getElementById('systemStatus').className = 'badge badge-danger';
      document.getElementById('systemStatus').textContent = 'Erreur';
    }
  }

  // ═══════════════════════════════════════════════════════
  // RESET DEMO — P7-1: Strong confirmation modal
  // ═══════════════════════════════════════════════════════
  document.getElementById('btnResetDemo').addEventListener('click', function() {
    const btn = this;
    Shared.openModal({
      title: 'Réinitialisation complète des données',
      body:
        '<div class="alert alert-danger mb-4">' +
          '<strong>ATTENTION : Cette action est IRRÉVERSIBLE.</strong><br>' +
          'Toutes les séances, résolutions, votes et présences seront <strong>définitivement supprimés</strong>.<br>' +
          'Seuls les utilisateurs et la configuration seront conservés.' +
        '</div>' +
        '<div class="form-group">' +
          '<label class="form-label">Tapez <strong>REINITIALISER</strong> pour confirmer</label>' +
          '<input class="form-input" type="text" id="resetConfirmText" placeholder="REINITIALISER" autocomplete="off" spellcheck="false">' +
        '</div>',
      confirmText: 'Réinitialiser',
      confirmClass: 'btn btn-danger',
      onConfirm: async function(modal) {
        var text = modal.querySelector('#resetConfirmText').value.trim();
        if (text !== 'REINITIALISER') {
          setNotif('error', 'Tapez exactement REINITIALISER pour confirmer');
          return false;
        }
        Shared.btnLoading(btn, true);
        try {
          var r = await api('/api/v1/admin_reset_demo.php', {});
          if (r.body && r.body.ok) { setNotif('success', 'Données de démo réinitialisées'); refreshAll(); }
          else { setNotif('error', getApiError(r.body)); }
        } catch(e) { setNotif('error', e.message); }
        finally { Shared.btnLoading(btn, false); }
      }
    });
  });

  // ═══════════════════════════════════════════════════════
  // P7-2: ADMIN AUDIT LOG
  // ═══════════════════════════════════════════════════════
  let _auditOffset = 0;
  const _auditLimit = 50;

  async function loadAdminAuditLog() {
    var list = document.getElementById('adminAuditList');
    var actionFilter = document.getElementById('adminAuditAction').value;
    var searchQuery = document.getElementById('adminAuditSearch').value.trim();

    try {
      var url = '/api/v1/admin_audit_log.php?limit=' + _auditLimit + '&offset=' + _auditOffset;
      if (actionFilter) url += '&action=' + encodeURIComponent(actionFilter);
      if (searchQuery) url += '&q=' + encodeURIComponent(searchQuery);

      var r = await api(url);
      if (r.body && r.body.ok && r.body.data) {
        var d = r.body.data;

        // Populate action filter dropdown (first load)
        var actionSelect = document.getElementById('adminAuditAction');
        if (actionSelect.options.length <= 1 && d.action_types) {
          d.action_types.forEach(function(t) {
            var opt = document.createElement('option');
            opt.value = t.value;
            opt.textContent = t.label;
            actionSelect.appendChild(opt);
          });
        }

        if (!d.events || d.events.length === 0) {
          list.innerHTML = '<div class="text-center p-4 text-muted">Aucun événement admin</div>';
        } else {
          list.innerHTML = d.events.map(function(e) {
            var ts = new Date(e.timestamp).toLocaleString('fr-FR');
            var detail = e.detail ? ' — <span class="text-muted">' + escapeHtml(e.detail) + '</span>' : '';
            var ip = e.ip_address ? '<span class="text-xs text-muted">' + escapeHtml(e.ip_address) + '</span>' : '';
            return '<div class="system-stat" style="padding:0.6rem 1rem;">' +
              '<div style="flex:1;">' +
                '<span class="text-sm font-medium">' + escapeHtml(e.action_label) + '</span>' + detail +
                '<div class="text-xs text-muted" style="margin-top:2px;">' + ts + ' · ' + escapeHtml(e.actor_role || '') + ' ' + ip + '</div>' +
              '</div>' +
            '</div>';
          }).join('');
        }

        // Pagination
        var pagination = document.getElementById('adminAuditPagination');
        if (d.total > 0) {
          pagination.style.display = 'flex';
          document.getElementById('adminAuditCount').textContent = (d.offset + 1) + '-' + Math.min(d.offset + d.events.length, d.total) + ' sur ' + d.total;
          document.getElementById('adminAuditPrev').disabled = d.offset === 0;
          document.getElementById('adminAuditNext').disabled = (d.offset + _auditLimit) >= d.total;
        } else {
          pagination.style.display = 'none';
        }
      }
    } catch(e) {
      list.innerHTML = '<div class="text-center p-4 text-muted">Erreur de chargement</div>';
    }
  }

  document.getElementById('adminAuditAction').addEventListener('change', function() { _auditOffset = 0; loadAdminAuditLog(); });
  document.getElementById('adminAuditSearch').addEventListener('input', Utils.debounce(function() { _auditOffset = 0; loadAdminAuditLog(); }, 300));
  document.getElementById('adminAuditPrev').addEventListener('click', function() { _auditOffset = Math.max(0, _auditOffset - _auditLimit); loadAdminAuditLog(); });
  document.getElementById('adminAuditNext').addEventListener('click', function() { _auditOffset += _auditLimit; loadAdminAuditLog(); });

  // ═══════════════════════════════════════════════════════
  // REFRESH ALL
  // ═══════════════════════════════════════════════════════
  function refreshAll() {
    loadUsers();
    loadMeetingSelects().then(loadMeetingRoles);
    loadQuorumPolicies();
    loadVotePolicies();
    loadRoles();
    loadStates();
    loadSystemStatus();
    loadAdminAuditLog();
  }

  document.getElementById('btnRefresh').addEventListener('click', refreshAll);

  // ═══════════════════════════════════════════════════════
  // GUIDE DRAWER
  // ═══════════════════════════════════════════════════════
  if (window.ShellDrawer && window.ShellDrawer.register) {
    window.ShellDrawer.register('guide', 'Guide', function(mid, body) {
      body.innerHTML =
        '<div style="display:flex;flex-direction:column;gap:16px;padding:4px 0;">' +
          '<div><div style="font-weight:600;margin-bottom:8px">Modèle de rôles</div>' +
            '<div class="text-sm"><strong>Rôles système</strong> (permanents) :<br>' +
            'Admin, Opérateur, Auditeur, Observateur<br><br>' +
            '<strong>Rôles de séance</strong> (par séance) :<br>' +
            'Président, Assesseur, Électeur<br><br>' +
            'Un opérateur peut être président d\'une séance et assesseur d\'une autre.</div></div>' +
          '<div><div style="font-weight:600;margin-bottom:8px">Machine à états</div>' +
            '<div class="text-sm">Brouillon &rarr; Planifiée &rarr; Verrouillée &rarr; En cours &rarr; Clôturée &rarr; Validée &rarr; Archivée<br><br>' +
            'Le président verrouille, ouvre, clôture et valide.<br>L\'admin archive et peut dégeler.</div></div>' +
          '<div><div style="font-weight:600;margin-bottom:8px">Setup DB</div>' +
            '<pre class="text-xs" style="background:var(--color-bg-subtle,#f5f5f5);padding:8px;border-radius:6px;overflow-x:auto">sudo bash database/setup.sh</pre></div>' +
        '</div>';
    });
  }

  // Initial load
  refreshAll();
})();
