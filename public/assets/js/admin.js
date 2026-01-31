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

  var roleLabelsSystem = Shared.ROLE_LABELS_SYSTEM;
  var roleLabelsSeance = Shared.ROLE_LABELS_MEETING;
  var allRoleLabels = Shared.ROLE_LABELS_ALL;

  // ─── Tabs (with ARIA support) ────────────────────────
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
  var _users = [];

  async function loadUsers() {
    try {
      var filter = document.getElementById('filterRole').value;
      var url = '/api/v1/admin_users.php' + (filter ? '?role=' + filter : '');
      var r = await api(url);
      if (r.body && r.body.ok && r.body.data) {
        _users = r.body.data.items || [];
        renderUsersTable(_users);
      }
    } catch (e) { console.error('loadUsers', e); }
  }

  function renderUsersTable(users) {
    var tbody = document.getElementById('usersTableBody');
    if (!users.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4 text-muted">Aucun utilisateur</td></tr>';
      return;
    }
    tbody.innerHTML = users.map(function(u) {
      var meetingTags = (u.meeting_roles || []).map(function(mr) {
        return '<span class="meeting-role-tag"><span class="role-badge ' + escapeHtml(mr.role) + '">' +
          escapeHtml(allRoleLabels[mr.role] || mr.role) + '</span> ' +
          escapeHtml(mr.meeting_title || '') + '</span>';
      }).join(' ');

      return '<tr data-user-id="' + u.id + '">' +
        '<td><strong>' + escapeHtml(u.name || '') + '</strong></td>' +
        '<td>' + escapeHtml(u.email || '') + '</td>' +
        '<td><span class="role-badge ' + escapeHtml(u.role) + '">' + escapeHtml(roleLabelsSystem[u.role] || u.role) + '</span></td>' +
        '<td>' + (meetingTags || '<span class="text-muted">—</span>') + '</td>' +
        '<td>' + (u.is_active ? '<span class="text-success">Oui</span>' : '<span class="text-danger">Non</span>') + '</td>' +
        '<td>' + (u.has_api_key ? '<span class="text-success">Oui</span>' : '<span class="text-muted">Non</span>') + '</td>' +
        '<td class="flex gap-1 flex-wrap">' +
          '<button class="btn btn-ghost btn-xs btn-edit-user" data-id="' + u.id + '">Modifier</button>' +
          '<button class="btn btn-ghost btn-xs btn-toggle-user" data-id="' + u.id + '" data-active="' + (u.is_active ? '1' : '0') + '">' + (u.is_active ? 'Désactiver' : 'Activer') + '</button>' +
          '<button class="btn btn-ghost btn-xs btn-key-user" data-id="' + u.id + '">Clé API</button>' +
        '</td></tr>';
    }).join('');
  }

  document.getElementById('filterRole').addEventListener('change', loadUsers);

  // Create user
  document.getElementById('btnCreateUser').addEventListener('click', async function() {
    var btn = this;
    var name = document.getElementById('newName').value.trim();
    var email = document.getElementById('newEmail').value.trim();
    var role = document.getElementById('newRole').value;
    if (!name || !email) { setNotif('error', 'Nom et email requis'); return; }
    Shared.btnLoading(btn, true);
    try {
      var r = await api('/api/v1/admin_users.php', {action:'create', name:name, email:email, role:role});
      if (r.body && r.body.ok) {
        setNotif('success', 'Utilisateur créé. Clé API : ' + (r.body.data.api_key || ''));
        document.getElementById('newName').value = '';
        document.getElementById('newEmail').value = '';
        loadUsers();
      } else {
        setNotif('error', r.body.error || 'Erreur');
      }
    } catch (e) { setNotif('error', e.message); }
    finally { Shared.btnLoading(btn, false); }
  });

  // Delegated clicks on users table
  document.getElementById('usersTableBody').addEventListener('click', async function(e) {
    var btn;

    // Toggle active
    btn = e.target.closest('.btn-toggle-user');
    if (btn) {
      var active = btn.dataset.active === '1' ? 0 : 1;
      var label = active ? 'activer' : 'désactiver';
      if (!confirm('Voulez-vous ' + label + ' cet utilisateur ?')) return;
      Shared.btnLoading(btn, true);
      try {
        await api('/api/v1/admin_users.php', {action:'toggle', user_id:btn.dataset.id, is_active:active});
        loadUsers();
      } catch(err) { setNotif('error', err.message); }
      finally { Shared.btnLoading(btn, false); }
      return;
    }

    // Rotate API key
    btn = e.target.closest('.btn-key-user');
    if (btn) {
      if (!confirm('Générer une nouvelle clé API ? L\'ancienne sera invalidée.')) return;
      Shared.btnLoading(btn, true);
      try {
        var r = await api('/api/v1/admin_users.php', {action:'rotate_key', user_id:btn.dataset.id});
        if (r.body && r.body.ok) {
          setNotif('success', 'Nouvelle clé API : ' + r.body.data.api_key);
          loadUsers();
        }
      } catch(err) { setNotif('error', err.message); }
      finally { Shared.btnLoading(btn, false); }
      return;
    }

    // Edit user (modal dialog)
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
            '<label class="form-label">Email</label>' +
            '<input class="form-input" type="email" id="editEmail" value="' + escapeHtml(user.email || '') + '">' +
          '</div>' +
          '<div class="form-group">' +
            '<label class="form-label">Rôle système</label>' +
            '<select class="form-input" id="editRole">' + roleOptions + '</select>' +
          '</div>',
        confirmText: 'Enregistrer',
        onConfirm: function(modal) {
          var newName = modal.querySelector('#editName').value.trim();
          var newEmail = modal.querySelector('#editEmail').value.trim();
          var newRole = modal.querySelector('#editRole').value;
          if (!newName || !newEmail) { setNotif('error', 'Nom et email requis'); return false; }
          api('/api/v1/admin_users.php', {action:'update', user_id:user.id, name:newName, email:newEmail, role:newRole})
            .then(function(r) {
              if (r.body && r.body.ok) { setNotif('success', 'Utilisateur modifié'); loadUsers(); }
              else { setNotif('error', r.body.error || 'Erreur'); }
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
  var _meetings = [];

  async function loadMeetingSelects() {
    try {
      var r = await api('/api/v1/meetings.php');
      if (r.body && r.body.ok && r.body.data) {
        _meetings = r.body.data.items || r.body.data || [];
        var meetingSel = document.getElementById('mrMeeting');
        meetingSel.innerHTML = '<option value="">— Toutes les séances —</option>' +
          _meetings.map(function(m) {
            var statusMap = Shared.MEETING_STATUS_MAP || {};
            var st = (statusMap[m.status] || {}).text || m.status;
            return '<option value="' + m.id + '">' + escapeHtml(m.title) + ' (' + escapeHtml(st) + ')</option>';
          }).join('');
      }
    } catch(e) { console.error('loadMeetingSelects', e); }

    // Populate user select from full users list
    try {
      var r2 = await api('/api/v1/admin_users.php');
      if (r2.body && r2.body.ok && r2.body.data) {
        var users = r2.body.data.items || [];
        var userSel = document.getElementById('mrUser');
        userSel.innerHTML = '<option value="">— Sélectionner —</option>' +
          users.filter(function(u) { return u.is_active; }).map(function(u) {
            return '<option value="' + u.id + '">' + escapeHtml(u.name) + ' (' + escapeHtml(roleLabelsSystem[u.role] || u.role) + ')</option>';
          }).join('');
      }
    } catch(e) { console.error('loadUserSelect', e); }
  }

  async function loadMeetingRoles() {
    var meetingId = document.getElementById('mrMeeting').value;
    var tbody = document.getElementById('meetingRolesBody');
    tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-muted">Chargement...</td></tr>';

    try {
      var url = '/api/v1/admin_meeting_roles.php' + (meetingId ? '?meeting_id=' + meetingId : '');
      var r = await api(url);
      if (r.body && r.body.ok && r.body.data) {
        var items = r.body.data.items || [];
        if (!items.length) {
          tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-muted">Aucun rôle assigné</td></tr>';
          return;
        }
        tbody.innerHTML = items.map(function(row) {
          var meetingTitle = row.meeting_title || row.meeting_id || '';
          var userName = row.user_name || row.name || row.user_id || '';
          var role = row.role || '';
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

  // Assign role
  document.getElementById('btnAssignRole').addEventListener('click', async function() {
    var btn = this;
    var meetingId = document.getElementById('mrMeeting').value;
    var userId = document.getElementById('mrUser').value;
    var role = document.getElementById('mrRole').value;
    if (!meetingId || !userId) { setNotif('error', 'Séance et utilisateur requis'); return; }
    Shared.btnLoading(btn, true);
    try {
      var r = await api('/api/v1/admin_meeting_roles.php', {action:'assign', meeting_id:meetingId, user_id:userId, role:role});
      if (r.body && r.body.ok) {
        setNotif('success', 'Rôle assigné');
        loadMeetingRoles();
        loadUsers(); // refresh meeting roles column in users table
      } else {
        setNotif('error', r.body.error || 'Erreur');
      }
    } catch(e) { setNotif('error', e.message); }
    finally { Shared.btnLoading(btn, false); }
  });

  // Revoke role (delegated)
  document.getElementById('meetingRolesBody').addEventListener('click', async function(e) {
    var btn = e.target.closest('.btn-revoke-role');
    if (!btn) return;
    if (!confirm('Révoquer ce rôle de séance ?')) return;
    Shared.btnLoading(btn, true);
    try {
      var r = await api('/api/v1/admin_meeting_roles.php', {
        action: 'revoke',
        meeting_id: btn.dataset.meetingId,
        user_id: btn.dataset.userId,
        role: btn.dataset.role
      });
      if (r.body && r.body.ok) {
        setNotif('success', 'Rôle révoqué');
        loadMeetingRoles();
        loadUsers();
      } else {
        setNotif('error', r.body.error || 'Erreur');
      }
    } catch(e) { setNotif('error', e.message); }
    finally { Shared.btnLoading(btn, false); }
  });

  // ═══════════════════════════════════════════════════════
  // POLICIES — QUORUM
  // ═══════════════════════════════════════════════════════
  var _quorumPolicies = [];

  async function loadQuorumPolicies() {
    try {
      var r = await api('/api/v1/admin_quorum_policies.php');
      if (r.body && r.body.ok && r.body.data && Array.isArray(r.body.data.items)) {
        _quorumPolicies = r.body.data.items;
        renderQuorumList(_quorumPolicies);
      }
    } catch(e) { console.error('loadQuorumPolicies', e); }
  }

  function renderQuorumList(items) {
    var el = document.getElementById('quorumList');
    if (!items.length) {
      el.innerHTML = '<div class="text-center text-muted">Aucune politique de quorum</div>';
      return;
    }
    el.innerHTML = items.map(function(p) {
      return '<div class="flex items-center justify-between py-2 border-b" style="border-color:var(--color-border-subtle)">' +
        '<div>' +
          '<div class="font-semibold text-sm">' + escapeHtml(p.name) + '</div>' +
          '<div class="text-xs text-muted">' +
            escapeHtml(p.description || '') +
            (p.mode ? ' | mode: ' + escapeHtml(p.mode) : '') +
            ' | seuil: ' + Math.round((p.threshold||0)*100) + '%' +
            (p.include_proxies ? ' | procurations' : '') +
            (p.count_remote ? ' | distanciel' : '') +
          '</div>' +
        '</div>' +
        '<button class="btn btn-ghost btn-xs btn-edit-quorum" data-id="' + escapeHtml(p.id) + '">Modifier</button>' +
      '</div>';
    }).join('');
  }

  function openQuorumModal(policy) {
    var isEdit = !!policy;
    var p = policy || {};

    var modeOptions = ['single','evolving','double'].map(function(m) {
      var sel = m === (p.mode || 'single') ? ' selected' : '';
      return '<option value="' + m + '"' + sel + '>' + m + '</option>';
    }).join('');

    var denOptions = ['eligible_members','eligible_weight'].map(function(d) {
      var sel = d === (p.denominator || 'eligible_members') ? ' selected' : '';
      return '<option value="' + d + '"' + sel + '>' + d + '</option>';
    }).join('');

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
        '<div class="flex gap-4 mb-3">' +
          '<label class="flex items-center gap-2 text-sm"><input type="checkbox" id="qpProxies"' + (p.include_proxies ? ' checked' : '') + '> Inclure procurations</label>' +
          '<label class="flex items-center gap-2 text-sm"><input type="checkbox" id="qpRemote"' + (p.count_remote ? ' checked' : '') + '> Compter distanciel</label>' +
        '</div>',
      confirmText: isEdit ? 'Enregistrer' : 'Créer',
      onConfirm: function(modal) {
        var name = modal.querySelector('#qpName').value.trim();
        if (!name) { setNotif('error', 'Nom requis'); return false; }
        var payload = {
          name: name,
          description: modal.querySelector('#qpDesc').value.trim(),
          mode: modal.querySelector('#qpMode').value,
          denominator: modal.querySelector('#qpDen').value,
          threshold: modal.querySelector('#qpThreshold').value,
          include_proxies: modal.querySelector('#qpProxies').checked ? 1 : 0,
          count_remote: modal.querySelector('#qpRemote').checked ? 1 : 0
        };
        if (isEdit) payload.id = p.id;
        api('/api/v1/admin_quorum_policies.php', payload)
          .then(function(r) {
            if (r.body && r.body.ok) { setNotif('success', isEdit ? 'Politique modifiée' : 'Politique créée'); loadQuorumPolicies(); }
            else { setNotif('error', r.body.error || 'Erreur'); }
          })
          .catch(function(err) { setNotif('error', err.message); });
      }
    });
  }

  document.getElementById('btnAddQuorum').addEventListener('click', function() { openQuorumModal(null); });

  document.getElementById('quorumList').addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-edit-quorum');
    if (!btn) return;
    var policy = _quorumPolicies.find(function(p) { return p.id === btn.dataset.id; });
    if (policy) openQuorumModal(policy);
  });

  // ═══════════════════════════════════════════════════════
  // POLICIES — VOTE
  // ═══════════════════════════════════════════════════════
  var _votePolicies = [];

  async function loadVotePolicies() {
    try {
      var r = await api('/api/v1/admin_vote_policies.php');
      if (r.body && r.body.ok && r.body.data && Array.isArray(r.body.data.items)) {
        _votePolicies = r.body.data.items;
        renderVoteList(_votePolicies);
      }
    } catch(e) { console.error('loadVotePolicies', e); }
  }

  function renderVoteList(items) {
    var el = document.getElementById('voteList');
    if (!items.length) {
      el.innerHTML = '<div class="text-center text-muted">Aucune politique de vote</div>';
      return;
    }
    el.innerHTML = items.map(function(p) {
      return '<div class="flex items-center justify-between py-2 border-b" style="border-color:var(--color-border-subtle)">' +
        '<div>' +
          '<div class="font-semibold text-sm">' + escapeHtml(p.name) + '</div>' +
          '<div class="text-xs text-muted">' +
            escapeHtml(p.description || '') +
            (p.base ? ' | base: ' + escapeHtml(p.base) : '') +
            ' | seuil: ' + Math.round((p.threshold||0)*100) + '%' +
            (p.abstention_as_against ? ' | abstention=contre' : '') +
          '</div>' +
        '</div>' +
        '<button class="btn btn-ghost btn-xs btn-edit-vote" data-id="' + escapeHtml(p.id) + '">Modifier</button>' +
      '</div>';
    }).join('');
  }

  function openVoteModal(policy) {
    var isEdit = !!policy;
    var p = policy || {};

    var baseOptions = ['expressed','total_eligible'].map(function(b) {
      var sel = b === (p.base || 'expressed') ? ' selected' : '';
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
          ' Compter abstentions comme contre' +
        '</label>',
      confirmText: isEdit ? 'Enregistrer' : 'Créer',
      onConfirm: function(modal) {
        var name = modal.querySelector('#vpName').value.trim();
        if (!name) { setNotif('error', 'Nom requis'); return false; }
        var payload = {
          name: name,
          description: modal.querySelector('#vpDesc').value.trim(),
          base: modal.querySelector('#vpBase').value,
          threshold: modal.querySelector('#vpThreshold').value,
          abstention_as_against: modal.querySelector('#vpAbstention').checked ? 1 : 0
        };
        if (isEdit) payload.id = p.id;
        api('/api/v1/admin_vote_policies.php', payload)
          .then(function(r) {
            if (r.body && r.body.ok) { setNotif('success', isEdit ? 'Politique modifiée' : 'Politique créée'); loadVotePolicies(); }
            else { setNotif('error', r.body.error || 'Erreur'); }
          })
          .catch(function(err) { setNotif('error', err.message); });
      }
    });
  }

  document.getElementById('btnAddVote').addEventListener('click', function() { openVoteModal(null); });

  document.getElementById('voteList').addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-edit-vote');
    if (!btn) return;
    var policy = _votePolicies.find(function(p) { return p.id === btn.dataset.id; });
    if (policy) openVoteModal(policy);
  });

  // ═══════════════════════════════════════════════════════
  // PERMISSIONS MATRIX
  // ═══════════════════════════════════════════════════════
  async function loadRoles() {
    try {
      var r = await api('/api/v1/admin_roles.php');
      if (!r.body || !r.body.ok) return;
      var d = r.body.data;

      // System roles info
      var sysInfo = Object.entries(d.system_roles || {}).map(function(e) {
        var cnt = (d.users_by_system_role || []).find(function(x) { return x.role === e[0]; });
        return '<div class="flex items-center justify-between py-2 border-b" style="border-color:var(--color-border-subtle)">' +
          '<span class="role-badge ' + e[0] + '">' + escapeHtml(e[1]) + '</span>' +
          '<span class="text-sm text-muted">' + ((cnt && cnt.count) || 0) + ' utilisateur(s)</span></div>';
      }).join('');
      document.getElementById('systemRolesInfo').innerHTML = sysInfo;

      // Meeting roles info
      var mtgInfo = Object.entries(d.meeting_roles || {}).map(function(e) {
        var cnt = (d.meeting_role_counts || []).find(function(x) { return x.role === e[0]; });
        return '<div class="flex items-center justify-between py-2 border-b" style="border-color:var(--color-border-subtle)">' +
          '<span class="role-badge ' + e[0] + '">' + escapeHtml(e[1]) + '</span>' +
          '<span class="text-sm text-muted">' + ((cnt && cnt.users) || 0) + ' personne(s), ' + ((cnt && cnt.meetings) || 0) + ' séance(s)</span></div>';
      }).join('');
      document.getElementById('meetingRolesInfo').innerHTML = mtgInfo;

      // Permission matrix
      var perms = d.permissions_by_role || {};
      var allPermsSet = {};
      var roleOrder = ['admin','operator','auditor','viewer','president','assessor','voter'];
      roleOrder.forEach(function(role) {
        (perms[role] || []).forEach(function(p) { allPermsSet[p.permission] = true; });
      });
      var allPerms = Object.keys(allPermsSet).sort();

      // Group by resource
      var groups = {};
      allPerms.forEach(function(p) {
        var parts = p.split(':');
        var g = parts[0];
        if (!groups[g]) groups[g] = [];
        groups[g].push(p);
      });

      var permsByRole = {};
      roleOrder.forEach(function(role) {
        permsByRole[role] = {};
        (perms[role] || []).forEach(function(p) { permsByRole[role][p.permission] = true; });
      });

      var html = '<table class="perm-matrix"><thead><tr><th>Permission</th>';
      roleOrder.forEach(function(role) {
        var isSys = !!roleLabelsSystem[role];
        html += '<th><span class="role-badge ' + role + '">' + escapeHtml(allRoleLabels[role] || role) + '</span><br><span class="text-xs text-muted">' + (isSys ? 'S' : 'M') + '</span></th>';
      });
      html += '</tr></thead><tbody>';

      Object.keys(groups).sort().forEach(function(g) {
        html += '<tr><td colspan="' + (roleOrder.length + 1) + '" style="background:var(--color-bg-subtle);font-weight:700;text-transform:uppercase;font-size:10px;letter-spacing:0.1em;padding:6px 8px">' + escapeHtml(g) + '</td></tr>';
        groups[g].forEach(function(perm) {
          html += '<tr><td>' + escapeHtml(perm) + '</td>';
          roleOrder.forEach(function(role) {
            html += '<td>' + (permsByRole[role][perm] ? '<span class="perm-check">&#10003;</span>' : '<span class="perm-none">-</span>') + '</td>';
          });
          html += '</tr>';
        });
      });
      html += '</tbody></table>';
      document.getElementById('permMatrix').innerHTML = html;

    } catch (e) { console.error('loadRoles', e); }
  }

  // ═══════════════════════════════════════════════════════
  // STATE MACHINE
  // ═══════════════════════════════════════════════════════
  async function loadStates() {
    try {
      var r = await api('/api/v1/admin_roles.php');
      if (!r.body || !r.body.ok) return;
      var d = r.body.data;
      var statuses = d.statuses || {};
      var transitions = d.state_transitions || [];

      // Flow diagram
      var flow = ['draft','scheduled','frozen','live','closed','validated','archived'];
      document.getElementById('stateFlow').innerHTML = flow.map(function(s, i) {
        var label = statuses[s] || s;
        return (i > 0 ? '<span class="state-arrow">&rarr;</span>' : '') +
          '<span class="state-node">' + escapeHtml(label) + '</span>';
      }).join('');

      // Transitions table
      document.getElementById('transitionsBody').innerHTML = transitions.map(function(t) {
        return '<tr>' +
          '<td><span class="state-node state-node-sm">' + escapeHtml(statuses[t.from_status] || t.from_status) + '</span></td>' +
          '<td><span class="state-node state-node-sm">' + escapeHtml(statuses[t.to_status] || t.to_status) + '</span></td>' +
          '<td><span class="role-badge ' + escapeHtml(t.required_role) + '">' + escapeHtml(allRoleLabels[t.required_role] || t.required_role) + '</span></td>' +
          '<td class="text-sm">' + escapeHtml(t.description || '') + '</td></tr>';
      }).join('');

    } catch (e) { console.error('loadStates', e); }
  }

  // ═══════════════════════════════════════════════════════
  // SYSTEM STATUS
  // ═══════════════════════════════════════════════════════
  async function loadSystemStatus() {
    try {
      var r = await api('/api/v1/admin_system_status.php');
      if (r.body && r.body.ok && r.body.data) {
        var s = r.body.data.system || r.body.data;
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
  // RESET DEMO
  // ═══════════════════════════════════════════════════════
  document.getElementById('btnResetDemo').addEventListener('click', async function() {
    if (!confirm('Cette action va supprimer TOUTES les données et réinitialiser la démo. Continuer ?')) return;
    var btn = this;
    Shared.btnLoading(btn, true);
    try {
      var r = await api('/api/v1/admin_reset_demo.php', {});
      if (r.body && r.body.ok) { setNotif('success', 'Données de démo réinitialisées'); refreshAll(); }
      else { setNotif('error', r.body.error || 'Erreur'); }
    } catch(e) { setNotif('error', e.message); }
    finally { Shared.btnLoading(btn, false); }
  });

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
