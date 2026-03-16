/* GO-LIVE-STATUS: ready — Admin JS. innerHTML audité — OK. */
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

  // --- Onboarding Banner (localStorage dismiss) ---
  (function initOnboarding() {
    var banner = document.getElementById('obBanner');
    var closeBtn = document.getElementById('obClose');
    if (!banner) return;
    if (localStorage.getItem('ag_ob_dismissed') === '1') {
      banner.style.display = 'none';
    }
    if (closeBtn) {
      closeBtn.addEventListener('click', function() {
        banner.style.display = 'none';
        localStorage.setItem('ag_ob_dismissed', '1');
      });
    }
  })();

  // --- Dashboard KPIs & Upcoming Sessions ---
  (function initDashboard() {
    async function loadDashboardData() {
      try {
        var r = await api('/api/v1/meetings.php');
        if (r.body && r.body.ok && r.body.data) {
          var meetings = r.body.data.items || [];
          var _now = new Date();
          var upcoming = meetings.filter(function(m) { return m.status === 'scheduled' || m.status === 'frozen'; });
          var live = meetings.filter(function(m) { return m.status === 'live'; });
          var closed = meetings.filter(function(m) { return m.status === 'closed'; });
          var _validated = meetings.filter(function(m) { return m.status === 'validated'; });

          var kpiUp = document.getElementById('kpiUpcomingVal');
          var kpiLi = document.getElementById('kpiLiveVal');
          var kpiCo = document.getElementById('kpiConvocationsVal');
          var kpiPv = document.getElementById('kpiPVVal');
          if (kpiUp) kpiUp.textContent = upcoming.length;
          if (kpiLi) kpiLi.textContent = live.length;
          if (kpiCo) kpiCo.textContent = upcoming.length;
          if (kpiPv) kpiPv.textContent = closed.length;

          // Urgent action card
          var urgentCard = document.getElementById('urgentCard');
          if (urgentCard && closed.length > 0) {
            urgentCard.style.display = '';
          }

          // Upcoming sessions list
          var sessionsEl = document.getElementById('dashUpcomingSessions');
          if (sessionsEl) {
            if (upcoming.length === 0) {
              sessionsEl.innerHTML = '<div class="p-4 text-center text-muted text-sm">Aucune s\u00e9ance \u00e0 venir</div>';
            } else {
              sessionsEl.innerHTML = upcoming.slice(0, 5).map(function(m) {
                var d = m.scheduled_date ? new Date(m.scheduled_date).toLocaleDateString('fr-FR') : '';
                return '<a href="/meetings.htmx.html?id=' + encodeURIComponent(m.id) + '" class="irow"><div class="irow-body"><div class="irow-title">' + escapeHtml(m.title || 'S\u00e9ance #' + m.id) + '</div><div class="text-xs text-muted">' + d + '</div></div><span class="irow-arrow">\u203A</span></a>';
              }).join('');
            }
          }

          // Pending tasks list
          var tasksEl = document.getElementById('dashPendingTasks');
          if (tasksEl) {
            var tasks = [];
            if (closed.length > 0) tasks.push({ label: closed.length + ' s\u00e9ance(s) \u00e0 valider', href: '/postsession.htmx.html', icon: 'danger' });
            if (live.length > 0) tasks.push({ label: live.length + ' s\u00e9ance(s) en cours', href: '/operator.htmx.html', icon: 'success' });
            if (upcoming.length > 0) tasks.push({ label: upcoming.length + ' s\u00e9ance(s) \u00e0 pr\u00e9parer', href: '/meetings.htmx.html', icon: 'primary' });
            if (tasks.length === 0) {
              tasksEl.innerHTML = '<div class="p-4 text-center text-muted text-sm">Aucune t\u00e2che en attente</div>';
            } else {
              tasksEl.innerHTML = tasks.map(function(t) {
                return '<a href="' + t.href + '" class="irow"><div class="irow-body"><div class="irow-title">' + t.label + '</div></div><span class="irow-arrow">\u203A</span></a>';
              }).join('');
            }
          }
        }
      } catch(e) { /* dashboard section is optional, fail silently */ }
    }
    loadDashboardData();
  })();

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
  // USERS — Lightweight count summary only
  // Full user management moved to /users.htmx.html
  // ═══════════════════════════════════════════════════════
  async function loadUsers() {
    try {
      var r = await api('/api/v1/admin_users.php');
      if (r.body && r.body.ok && r.body.data) {
        var count = (r.body.data.items || []).length;
        var el = document.getElementById('adminUsersCount');
        if (el) el.textContent = count + ' utilisateur' + (count !== 1 ? 's' : '');
      }
    } catch (e) { /* silent - summary only */ }
  }

  // ═══════════════════════════════════════════════════════
  // MEETING ROLES
  // ═══════════════════════════════════════════════════════
  let _meetings = [];
  let _allUsers = []; // populated by loadMeetingSelects for bulk role assignment

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
        _meetings = r.body.data.items || [];
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
        _allUsers = users; // kept for bulk role assignment
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
      const url = '/api/v1/admin_meeting_roles.php' + (meetingId ? '?meeting_id=' + encodeURIComponent(meetingId) : '');
      const r = await api(url);
      if (r.body && r.body.ok && r.body.data) {
        const items = r.body.data.items || [];
        if (!items.length) {
          tbody.innerHTML = '<tr><td colspan="4">' + Shared.emptyState({ icon: 'meetings', title: 'Aucun rôle attribué', description: 'Sélectionnez une séance et assignez des rôles depuis le formulaire ci-dessus.' }) + '</td></tr>';
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
      onConfirm: async function(modal) {
        var role = document.getElementById('bulkRole').value;
        var checked = document.querySelectorAll('.bulk-user-cb:checked');
        if (!checked.length) { setNotif('error', 'S\u00e9lectionnez au moins un utilisateur'); return false; }

        // Disable confirm button and show progress
        var confirmBtn = modal.querySelector('.modal-confirm-btn');
        if (confirmBtn) Shared.btnLoading(confirmBtn, true);

        var success = 0;
        var errors = 0;
        var errorNames = [];
        var total = checked.length;

        for (var i = 0; i < total; i++) {
          var userId = checked[i].value;
          var userName = checked[i].parentElement.textContent.trim().split('(')[0].trim();
          try {
            var r = await api('/api/v1/admin_meeting_roles.php', {
              action: 'assign', meeting_id: meetingId, user_id: userId, role: role
            });
            if (r.body && r.body.ok) success++;
            else { errors++; errorNames.push(userName); }
          } catch(e) { errors++; errorNames.push(userName); }
        }

        if (confirmBtn) Shared.btnLoading(confirmBtn, false);

        if (success > 0) setNotif('success', success + ' r\u00f4le' + (success > 1 ? 's' : '') + ' attribu\u00e9' + (success > 1 ? 's' : ''));
        if (errors > 0) {
          var detail = errorNames.length <= 3 ? errorNames.join(', ') : errorNames.slice(0,3).join(', ') + '\u2026';
          setNotif('error', errors + ' \u00e9chec' + (errors > 1 ? 's' : '') + ' : ' + detail);
        }
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
    } catch(e) {
      setNotif('error', 'Erreur chargement politiques de vote');
      var c = document.getElementById('voteList');
      if (c) c.innerHTML = '<div class="text-center p-4 text-muted">Erreur de chargement</div>';
    }
  }

  function renderVoteList(items) {
    const el = document.getElementById('voteList');
    if (!items.length) {
      el.innerHTML = Shared.emptyState({ icon: 'votes', title: 'Aucune politique de vote', description: 'Créez une politique depuis le formulaire ci-dessus.' });
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
      onConfirm: async function(modal) {
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
        try {
          var r = await api('/api/v1/admin_vote_policies.php', payload);
          if (r.body && r.body.ok) { setNotif('success', isEdit ? 'Politique mise à jour' : 'Politique créée'); loadVotePolicies(); }
          else { setNotif('error', getApiError(r.body)); return false; }
        } catch(err) { setNotif('error', err.message); return false; }
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

    } catch (e) {
      setNotif('error', 'Erreur chargement rôles');
      ['permMatrix', 'systemRolesInfo', 'meetingRolesInfo'].forEach(function(id) {
        var c = document.getElementById(id);
        if (c) c.innerHTML = '<div class="text-center p-4 text-muted">Erreur de chargement</div>';
      });
    }
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

    } catch (e) {
      setNotif('error', 'Erreur chargement états');
      ['stateFlow', 'transitionsBody'].forEach(function(id) {
        var c = document.getElementById(id);
        if (c) c.innerHTML = '<div class="text-center p-4 text-muted">Erreur de chargement</div>';
      });
    }
  }

  // Load statistics by state + archived meetings list (single API call)
  async function loadStateStatsAndArchived() {
    var list = document.getElementById('archivedMeetingsList');
    try {
      var r = await api('/api/v1/meetings.php');
      if (r.body && r.body.ok && r.body.data) {
        var meetings = r.body.data.items || [];

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
            alertsContainer.innerHTML = Shared.emptyState({ icon: 'generic', title: 'Aucune alerte récente', description: 'Le système fonctionne normalement.' });
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
          var r = await api('/api/v1/admin_reset_demo.php', { confirm: 'RESET' });
          if (r.body && r.body.ok) {
            var n = r.body.data?.reset_count || r.body.reset_count || 0;
            setNotif('success', n + ' séance(s) réinitialisée(s)');
            refreshAll();
          } else { setNotif('error', getApiError(r.body)); }
        } catch(e) { setNotif('error', e.message); }
        finally { Shared.btnLoading(btn, false); }
      }
    });
  });

  // ═══════════════════════════════════════════════════════
  // P7-2: ADMIN AUDIT LOG — moved to /audit.htmx.html
  // DOM elements removed; function is a no-op to avoid errors
  // when called from refreshAll().
  // ═══════════════════════════════════════════════════════
  function loadAdminAuditLog() {
    // Audit log has been extracted to the dedicated /audit.htmx.html page.
    // This stub is kept so refreshAll() callers do not throw errors.
  }

  // ═══════════════════════════════════════════════════════
  // REFRESH ALL
  // ═══════════════════════════════════════════════════════
  function refreshAll() {
    loadUsers();
    loadMeetingSelects().then(loadMeetingRoles);
    // loadQuorumPolicies() extracted to settings.js (Phase 13-01)
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
