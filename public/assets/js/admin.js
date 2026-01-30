/**
 * admin.js — Administration page logic for AG-VOTE.
 *
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 * Handles: users CRUD, permissions matrix, state machine,
 *          system status, policies, demo reset.
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

  // ─── Users ───────────────────────────────────────────
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
    var name = document.getElementById('newName').value.trim();
    var email = document.getElementById('newEmail').value.trim();
    var role = document.getElementById('newRole').value;
    if (!name || !email) { setNotif('error', 'Nom et email requis'); return; }
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
      try {
        await api('/api/v1/admin_users.php', {action:'toggle', user_id:btn.dataset.id, is_active:active});
        loadUsers();
      } catch(err) { setNotif('error', err.message); }
      return;
    }

    // Rotate API key
    btn = e.target.closest('.btn-key-user');
    if (btn) {
      if (!confirm('Générer une nouvelle clé API ? L\'ancienne sera invalidée.')) return;
      try {
        var r = await api('/api/v1/admin_users.php', {action:'rotate_key', user_id:btn.dataset.id});
        if (r.body && r.body.ok) {
          setNotif('success', 'Nouvelle clé API : ' + r.body.data.api_key);
          loadUsers();
        }
      } catch(err) { setNotif('error', err.message); }
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

  // ─── Roles / Permissions ─────────────────────────────
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

  // ─── State Machine ───────────────────────────────────
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

  // ─── System Status ───────────────────────────────────
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

  async function loadQuorumPolicies() {
    try {
      var r = await api('/api/v1/quorum_policies.php');
      if (r.body && r.body.ok && r.body.data && Array.isArray(r.body.data.items)) {
        var items = r.body.data.items;
        var el = document.getElementById('quorumList');
        if (!items.length) { el.innerHTML = '<div class="text-center text-muted">Aucune politique</div>'; return; }
        el.innerHTML = items.map(function(p) {
          return '<div class="flex items-center justify-between py-2 border-b" style="border-color:var(--color-border-subtle)">' +
            '<div><div class="font-semibold text-sm">' + escapeHtml(p.name) + '</div>' +
            '<div class="text-xs text-muted">' + escapeHtml(p.description || p.mode || '') + ' — seuil ' + Math.round((p.threshold||0)*100) + '%</div></div></div>';
        }).join('');
      }
    } catch(e) {}
  }

  async function loadVotePolicies() {
    try {
      var r = await api('/api/v1/vote_policies.php');
      if (r.body && r.body.ok && r.body.data && Array.isArray(r.body.data.items)) {
        var items = r.body.data.items;
        var el = document.getElementById('voteList');
        if (!items.length) { el.innerHTML = '<div class="text-center text-muted">Aucune politique</div>'; return; }
        el.innerHTML = items.map(function(p) {
          return '<div class="flex items-center justify-between py-2 border-b" style="border-color:var(--color-border-subtle)">' +
            '<div><div class="font-semibold text-sm">' + escapeHtml(p.name) + '</div>' +
            '<div class="text-xs text-muted">' + escapeHtml(p.description || p.base || '') + ' — seuil ' + Math.round((p.threshold||0)*100) + '%</div></div></div>';
        }).join('');
      }
    } catch(e) {}
  }

  // Reset demo
  document.getElementById('btnResetDemo').addEventListener('click', async function() {
    if (!confirm('Cette action va supprimer TOUTES les données et réinitialiser la démo. Continuer ?')) return;
    try {
      var r = await api('/api/v1/admin_reset_demo.php', {});
      if (r.body && r.body.ok) { setNotif('success', 'Données de démo réinitialisées'); refreshAll(); }
      else { setNotif('error', r.body.error || 'Erreur'); }
    } catch(e) { setNotif('error', e.message); }
  });

  // ─── Refresh ─────────────────────────────────────────
  function refreshAll() {
    loadUsers();
    loadRoles();
    loadStates();
    loadSystemStatus();
    loadQuorumPolicies();
    loadVotePolicies();
  }

  document.getElementById('btnRefresh').addEventListener('click', refreshAll);

  // Guide drawer
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
