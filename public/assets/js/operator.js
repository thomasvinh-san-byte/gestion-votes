/**
 * operator.js — Simplified operator console for AG-VOTE.
 * Must be loaded AFTER utils.js, shared.js, shell.js and meeting-context.js.
 */
(function() {
  'use strict';

  const meetingSelect = document.getElementById('meetingSelect');
  const meetingStatusBadge = document.getElementById('meetingStatusBadge');
  const statPresent = document.getElementById('statPresent');
  const statQuorum = document.getElementById('statQuorum');
  const quickLinks = document.getElementById('quickLinks');
  const noMeetingAlert = document.getElementById('noMeetingAlert');
  const motionsSection = document.getElementById('motionsSection');
  const motionsList = document.getElementById('motionsList');
  const motionsCount = document.getElementById('motionsCount');
  const meetingTitle = document.getElementById('meetingTitle');
  const votePanel = document.getElementById('votePanel');
  const voterList = document.getElementById('voterList');
  const openMotionTitle = document.getElementById('openMotionTitle');
  const btnCloseVote = document.getElementById('btnCloseVote');
  const statusAlert = document.getElementById('statusAlert');
  const statusChecklist = document.getElementById('statusChecklist');
  const statusActions = document.getElementById('statusActions');

  // Attendance inline elements
  const attendanceSection = document.getElementById('attendanceSection');
  const attendanceHeader = document.getElementById('attendanceHeader');
  const attendanceList = document.getElementById('attendanceList');
  const attSearchInput = document.getElementById('attSearchInput');
  const btnAttAllPresent = document.getElementById('btnAttAllPresent');

  // Exports section elements
  const exportsSection = document.getElementById('exportsSection');
  const validatedInfo = document.getElementById('validatedInfo');
  const btnExportPV = document.getElementById('btnExportPV');
  const btnExportAttendance = document.getElementById('btnExportAttendance');
  const btnExportVotes = document.getElementById('btnExportVotes');

  let currentMeetingId = null;
  let currentMeetingStatus = null;
  let currentWizardChecks = {};
  let currentOpenMotion = null;
  let votersCache = [];
  let ballotsCache = {};

  // Attendance inline state
  let attendanceCache = [];
  let attSearchTerm = '';

  // Transitions (state machine) - moved up for early reference
  const TRANSITIONS = {
    draft: [{ to: 'scheduled', label: 'Planifier', iconName: 'calendar' }],
    scheduled: [
      { to: 'frozen', label: 'Geler (verrouiller)', iconName: 'lock' },
      { to: 'draft', label: 'Retour brouillon', iconName: 'arrow-left' }
    ],
    frozen: [
      { to: 'live', label: 'Ouvrir la séance', iconName: 'play' },
      { to: 'scheduled', label: 'Dégeler', iconName: 'unlock' }
    ],
    live: [{ to: 'closed', label: 'Clôturer la séance', iconName: 'square' }],
    closed: [{ to: 'validated', label: 'Valider la séance', iconName: 'check-circle' }],
    validated: [{ to: 'archived', label: 'Archiver', iconName: 'archive' }],
    archived: []
  };

  // Get meeting_id from URL
  function getMeetingIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('meeting_id');
  }

  // Update all meeting links with current meeting_id
  function updateMeetingLinks(meetingId) {
    document.querySelectorAll('[data-meeting-link]').forEach(link => {
      const baseUrl = link.getAttribute('href').split('?')[0];
      if (meetingId) {
        link.href = `${baseUrl}?meeting_id=${meetingId}`;
      } else {
        link.href = baseUrl;
      }
    });
  }

  // Load meetings list
  async function loadMeetings() {
    try {
      const { body } = await api('/api/v1/meetings_index.php');

      if (body && body.ok && body.data && Array.isArray(body.data.meetings)) {
        meetingSelect.innerHTML = '<option value="">— Sélectionner une séance —</option>';

        body.data.meetings.forEach(m => {
          const opt = document.createElement('option');
          opt.value = m.id;
          opt.textContent = `${m.title} (${m.status || 'draft'})`;
          meetingSelect.appendChild(opt);
        });

        // Pre-select if meeting_id in URL
        const urlMeetingId = getMeetingIdFromUrl();
        if (urlMeetingId) {
          meetingSelect.value = urlMeetingId;
          loadMeetingContext(urlMeetingId);
        } else {
          showNoMeeting();
        }
      }
    } catch (err) {
      setNotif('error', 'Erreur chargement séances: ' + err.message);
    }
  }

  // Show/hide sections based on meeting selection
  function showNoMeeting() {
    noMeetingAlert.style.display = 'block';
    quickLinks.style.display = 'none';
    motionsSection.style.display = 'none';
    votePanel.style.display = 'none';
    if (statusAlert) statusAlert.style.display = 'none';
    if (attendanceSection) attendanceSection.style.display = 'none';
    meetingStatusBadge.textContent = '—';
    meetingStatusBadge.className = 'badge';
    meetingTitle.textContent = '—';
  }

  function showMeetingContent() {
    noMeetingAlert.style.display = 'none';
    quickLinks.style.display = 'flex';
    motionsSection.style.display = 'block';
    if (attendanceSection) attendanceSection.style.display = 'block';
  }

  // Update status alert based on meeting state and wizard checks
  function updateStatusAlert() {
    if (!statusAlert || !currentMeetingId) {
      if (statusAlert) statusAlert.style.display = 'none';
      return;
    }

    // If meeting is live, hide the alert
    if (currentMeetingStatus === 'live') {
      statusAlert.style.display = 'none';
      return;
    }

    // Build checklist
    const checks = currentWizardChecks;
    const mid = currentMeetingId;
    const items = [];

    // Check 1: Members
    if (checks.hasMembers) {
      items.push({ done: true, text: 'Membres ajoutés' });
    } else {
      items.push({ done: false, text: 'Ajouter des membres', link: '/members.htmx.html' });
    }

    // Check 2: Attendance
    if (checks.hasAttendance) {
      items.push({ done: true, text: 'Présences pointées' });
    } else {
      items.push({ done: false, text: 'Pointer les présences (onglet Présences)', link: null });
    }

    // Check 3: Motions
    if (checks.hasMotions) {
      items.push({ done: true, text: 'Résolutions créées' });
    } else {
      items.push({ done: false, text: 'Créer des résolutions (onglet Résolutions)', link: null });
    }

    // Check 4: President assigned (optional for demo)
    if (checks.hasPresident) {
      items.push({ done: true, text: 'Président assigné' });
    } else {
      items.push({ done: true, text: 'Président: optionnel (bouton Rôles)', link: null, optional: true });
    }

    // Check 5: Policies (optional for demo - defaults apply)
    if (checks.policiesAssigned) {
      items.push({ done: true, text: 'Politiques configurées' });
    } else {
      items.push({ done: true, text: 'Politiques: défauts appliqués', link: null, optional: true });
    }

    // Render checklist
    statusChecklist.innerHTML = items.map(item => {
      const iconHtml = item.done ? icon('check', 'icon-sm icon-success') : icon('circle', 'icon-sm icon-muted');
      const cls = item.done ? (item.optional ? 'done optional' : 'done') : 'pending';
      let content = item.text;
      if (!item.done && item.link) {
        content = `<a href="${item.link}">${item.text}</a>`;
      }
      const style = item.optional ? 'opacity:0.7;font-style:italic;' : '';
      return `<div class="check-item ${cls}" style="${style}"><span>${iconHtml}</span> ${content}</div>`;
    }).join('');

    // Update title based on status
    const titles = {
      draft: { title: 'Séance en brouillon', desc: 'Planifiez la séance pour continuer.' },
      scheduled: { title: 'Séance planifiée', desc: 'Gelez la séance quand les présences sont finalisées.' },
      frozen: { title: 'Séance gelée', desc: 'Tout est prêt. Ouvrez la séance pour démarrer les votes.' },
      closed: { title: 'Séance clôturée', desc: 'La séance est terminée. Validez pour archiver.' },
      validated: { title: 'Séance validée', desc: 'La séance est verrouillée.' },
      archived: { title: 'Séance archivée', desc: 'Consultation uniquement.' }
    };
    const info = titles[currentMeetingStatus] || titles.draft;
    document.getElementById('statusAlertTitle').textContent = info.title;
    document.getElementById('statusAlertDesc').textContent = info.desc;

    // Render action buttons
    const transitions = TRANSITIONS[currentMeetingStatus] || [];
    if (transitions.length > 0) {
      statusActions.innerHTML = transitions.map(t => {
        const btnClass = t.to === 'live' ? 'btn-primary' : 'btn-secondary';
        const iconHtml = t.iconName ? icon(t.iconName, 'icon-sm icon-text') : '';
        return `<button class="btn ${btnClass}" data-transition="${t.to}">${iconHtml}${t.label}</button>`;
      }).join('');

      statusActions.querySelectorAll('[data-transition]').forEach(btn => {
        btn.addEventListener('click', () => doTransition(btn.dataset.transition));
      });
    } else {
      statusActions.innerHTML = '';
    }

    // Show or hide based on status
    if (currentMeetingStatus === 'live') {
      statusAlert.style.display = 'none';
    } else {
      statusAlert.style.display = 'block';
    }
  }

  // Load meeting context
  async function loadMeetingContext(meetingId) {
    if (!meetingId) {
      showNoMeeting();
      return;
    }

    currentMeetingId = meetingId;
    updateMeetingLinks(meetingId);
    showMeetingContent();

    // Notify wizard
    if (window.Wizard && window.Wizard.selectMeeting) {
      window.Wizard.selectMeeting(meetingId);
    }

    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('meeting_id', meetingId);
    window.history.replaceState({}, '', url);

    try {
      // Load meeting details
      const { body } = await api(`/api/v1/meetings.php?id=${meetingId}`);

      if (body && body.ok && body.data) {
        const m = body.data;
        meetingTitle.textContent = m.title;
        currentMeetingStatus = m.status;

        // Update status badge
        const statusInfo = Shared.MEETING_STATUS_MAP[m.status] || Shared.MEETING_STATUS_MAP['draft'];
        meetingStatusBadge.className = `badge ${statusInfo.badge}`;
        meetingStatusBadge.textContent = statusInfo.text;

        // Update exports section visibility
        updateExportsSection(m);
      }

      // Load stats, motions, wizard status and invitations
      await Promise.all([
        loadAttendanceStats(meetingId),
        loadQuorumStatus(meetingId),
        loadMotions(meetingId),
        loadWizardStatus(meetingId),
        loadInvitationStats(meetingId)
      ]);

      // Update status alert after all data is loaded
      updateStatusAlert();

    } catch (err) {
      setNotif('error', 'Erreur: ' + err.message);
    }
  }

  // Load wizard status for checklist
  async function loadWizardStatus(meetingId) {
    try {
      const { body } = await api(`/api/v1/wizard_status.php?meeting_id=${meetingId}`);
      if (body && body.ok && body.data) {
        const d = body.data;
        currentWizardChecks = {
          hasMembers: (d.members_count || 0) > 0,
          hasMotions: (d.motions_total || 0) > 0,
          hasAttendance: (d.present_count || 0) > 0,
          hasPresident: !!d.has_president,
          policiesAssigned: !!d.policies_assigned,
          allMotionsClosed: d.motions_total > 0 && d.motions_closed === d.motions_total
        };
      }
    } catch (err) {
      console.error('Wizard status error:', err);
    }
  }

  // Load attendance stats
  async function loadAttendanceStats(meetingId) {
    try {
      const { body } = await api(`/api/v1/attendances.php?meeting_id=${meetingId}`);

      if (body && body.ok && body.data) {
        const attendances = body.data.attendances || [];
        attendanceCache = attendances;
        votersCache = attendances.filter(a => a.mode === 'present' || a.mode === 'remote');
        const present = votersCache.length;
        statPresent.textContent = present;
        document.getElementById('voteEligible').textContent = present;

        // Update inline attendance
        renderAttendanceInline();
      }
    } catch (err) {
      console.error('Attendance error:', err);
    }
  }

  // Render attendance inline section
  function renderAttendanceInline() {
    if (!attendanceSection) return;

    // Update stats
    const present = attendanceCache.filter(a => a.mode === 'present').length;
    const remote = attendanceCache.filter(a => a.mode === 'remote').length;
    const excused = attendanceCache.filter(a => a.mode === 'excused').length;
    const absent = attendanceCache.filter(a => !a.mode || a.mode === 'absent').length;

    document.getElementById('attPresent').textContent = present;
    document.getElementById('attRemote').textContent = remote;
    document.getElementById('attExcused').textContent = excused;
    document.getElementById('attAbsent').textContent = absent;

    // Filter and sort
    let filtered = attendanceCache;
    if (attSearchTerm) {
      const term = attSearchTerm.toLowerCase();
      filtered = attendanceCache.filter(a => (a.full_name || '').toLowerCase().includes(term));
    }

    // Sort: present/remote first, then by name
    filtered = [...filtered].sort((a, b) => {
      const orderA = a.mode === 'present' ? 0 : a.mode === 'remote' ? 1 : a.mode === 'excused' ? 2 : 3;
      const orderB = b.mode === 'present' ? 0 : b.mode === 'remote' ? 1 : b.mode === 'excused' ? 2 : 3;
      if (orderA !== orderB) return orderA - orderB;
      return (a.full_name || '').localeCompare(b.full_name || '');
    });

    const isLocked = ['validated', 'archived'].includes(currentMeetingStatus);

    if (filtered.length === 0) {
      attendanceList.innerHTML = `<div class="att-empty">${attSearchTerm ? 'Aucun résultat' : 'Aucun membre'}</div>`;
      return;
    }

    attendanceList.innerHTML = filtered.map(m => {
      const mode = m.mode || 'absent';
      const disabled = isLocked ? 'disabled' : '';

      return `
        <div class="att-row" data-member-id="${m.member_id}">
          <span class="att-name">${escapeHtml(m.full_name || '—')}</span>
          <div class="att-btns">
            <button class="att-btn present ${mode === 'present' ? 'active' : ''}" data-mode="present" ${disabled}>P</button>
            <button class="att-btn remote ${mode === 'remote' ? 'active' : ''}" data-mode="remote" ${disabled}>D</button>
            <button class="att-btn excused ${mode === 'excused' ? 'active' : ''}" data-mode="excused" ${disabled}>E</button>
            <button class="att-btn absent ${mode === 'absent' ? 'active' : ''}" data-mode="absent" ${disabled}>A</button>
          </div>
        </div>
      `;
    }).join('');

    // Bind click handlers
    if (!isLocked) {
      attendanceList.querySelectorAll('.att-btn:not([disabled])').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const row = e.target.closest('.att-row');
          const memberId = row.dataset.memberId;
          const mode = btn.dataset.mode;
          updateAttendanceInline(memberId, mode);
        });
      });
    }
  }

  // Update single attendance inline
  async function updateAttendanceInline(memberId, mode) {
    try {
      const { body } = await api('/api/v1/attendances_upsert.php', {
        meeting_id: currentMeetingId,
        member_id: memberId,
        mode: mode
      });

      if (body && body.ok !== false) {
        // Update local cache
        const member = attendanceCache.find(m => String(m.member_id) === String(memberId));
        if (member) member.mode = mode;
        renderAttendanceInline();

        // Update voters cache for vote panel
        votersCache = attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');
        statPresent.textContent = votersCache.length;
        document.getElementById('voteEligible').textContent = votersCache.length;

        // Update wizard checks
        currentWizardChecks.hasAttendance = votersCache.length > 0;
        updateStatusAlert();
      } else {
        setNotif('error', getApiError(body));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // Bulk mark all as present
  async function markAllPresentInline() {
    if (!currentMeetingId) return;
    if (['validated', 'archived'].includes(currentMeetingStatus)) {
      setNotif('error', 'Séance verrouillée');
      return;
    }
    if (!confirm('Marquer tous les membres comme présents ?')) return;

    Shared.btnLoading(btnAttAllPresent, true);
    try {
      const { body } = await api('/api/v1/attendances_bulk.php', {
        meeting_id: currentMeetingId,
        mode: 'present'
      });

      if (body && body.ok) {
        attendanceCache.forEach(m => m.mode = 'present');
        renderAttendanceInline();

        votersCache = [...attendanceCache];
        statPresent.textContent = votersCache.length;
        document.getElementById('voteEligible').textContent = votersCache.length;

        currentWizardChecks.hasAttendance = true;
        updateStatusAlert();

        setNotif('success', 'Tous marqués présents');
      } else {
        setNotif('error', getApiError(body));
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      Shared.btnLoading(btnAttAllPresent, false);
    }
  }

  // Update exports section visibility
  function updateExportsSection(meeting) {
    if (!exportsSection) return;

    const showExports = ['validated', 'archived'].includes(currentMeetingStatus);
    exportsSection.style.display = showExports ? 'block' : 'none';

    if (showExports && meeting) {
      const validatedAt = meeting.validated_at;
      if (validatedAt) {
        const date = new Date(validatedAt);
        validatedInfo.textContent = `Séance validée le ${date.toLocaleDateString('fr-FR')} à ${date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}`;
      } else {
        validatedInfo.textContent = currentMeetingStatus === 'archived' ? 'Séance archivée' : 'Séance validée';
      }
    }
  }

  // Load quorum status
  async function loadQuorumStatus(meetingId) {
    try {
      const { body } = await api(`/api/v1/quorum_status.php?meeting_id=${meetingId}`);

      if (body && body.ok && body.data) {
        const q = body.data;
        statQuorum.innerHTML = q.met ? icon('check', 'icon-sm icon-success') : icon('x', 'icon-sm icon-danger');
      }
    } catch (err) {
      console.error('Quorum error:', err);
    }
  }

  // Load motions
  async function loadMotions(meetingId) {
    try {
      const { body } = await api(`/api/v1/motions_for_meeting.php?meeting_id=${meetingId}`);
      const motions = body?.data?.motions || [];

      motionsCount.textContent = `${motions.length} résolution${motions.length > 1 ? 's' : ''}`;

      // Find open motion
      currentOpenMotion = motions.find(m => m.opened_at && !m.closed_at) || null;

      if (currentOpenMotion) {
        showVotePanel(currentOpenMotion);
      } else {
        votePanel.style.display = 'none';
      }

      if (motions.length === 0) {
        motionsList.innerHTML = `
          <div class="empty-motions">
            <p>Aucune résolution</p>
            <button class="btn btn-primary btn-sm mt-4" data-tab-switch="resolutions">
              ${icon('plus', 'icon-sm icon-text')}Créer des résolutions
            </button>
          </div>
        `;
        return;
      }

      motionsList.innerHTML = motions.map((m, i) => {
        const isOpen = !!(m.opened_at && !m.closed_at);
        const isClosed = !!m.closed_at;
        const statusClass = isOpen ? 'is-open' : (isClosed ? 'is-closed' : '');
        const statusText = isOpen ? 'Vote en cours' : (isClosed ? 'Terminé' : 'En attente');

        let actionBtn = '';
        if (!isOpen && !isClosed) {
          actionBtn = `<button class="btn btn-primary btn-sm btn-open" data-motion-id="${m.id}">Ouvrir</button>`;
        } else if (isOpen) {
          actionBtn = `<button class="btn btn-secondary btn-sm btn-close" data-motion-id="${m.id}">Clôturer</button>`;
        }

        let results = '';
        if (isClosed) {
          results = `
            <div class="results-inline">
              <span style="color:var(--color-success)">${icon('check', 'icon-xs')} ${m.votes_for || 0}</span>
              <span style="color:var(--color-danger)">${icon('x', 'icon-xs')} ${m.votes_against || 0}</span>
              <span>${icon('minus', 'icon-xs')} ${m.votes_abstain || 0}</span>
            </div>
          `;
        }

        return `
          <div class="motion-row ${statusClass}">
            <div class="motion-number">${i + 1}</div>
            <div class="motion-info">
              <div class="motion-title">${escapeHtml(m.title)}</div>
              <div class="motion-meta">${statusText}</div>
            </div>
            ${results}
            <div class="motion-actions">${actionBtn}</div>
          </div>
        `;
      }).join('');

      // Bind buttons
      motionsList.querySelectorAll('.btn-open').forEach(btn => {
        btn.addEventListener('click', () => openVote(btn.dataset.motionId));
      });

      motionsList.querySelectorAll('.btn-close').forEach(btn => {
        btn.addEventListener('click', () => closeVoteFromList(btn.dataset.motionId));
      });

    } catch (err) {
      console.error('Motions error:', err);
      motionsList.innerHTML = '<div class="text-center p-4 text-muted">Erreur de chargement</div>';
    }
  }

  // Show vote panel
  function showVotePanel(motion) {
    votePanel.style.display = 'block';
    openMotionTitle.textContent = motion.title;
    ballotsCache = {};
    renderVoterList();
    loadBallots(motion.id);
  }

  // Load existing ballots for the motion
  async function loadBallots(motionId) {
    try {
      const { body } = await api(`/api/v1/ballots.php?motion_id=${motionId}`);
      const ballots = body?.data?.ballots || body?.ballots || [];

      ballotsCache = {};
      let forCount = 0, againstCount = 0, abstainCount = 0;

      ballots.forEach(b => {
        ballotsCache[b.member_id] = b.value;
        if (b.value === 'for') forCount++;
        else if (b.value === 'against') againstCount++;
        else if (b.value === 'abstain') abstainCount++;
      });

      document.getElementById('voteFor').textContent = forCount;
      document.getElementById('voteAgainst').textContent = againstCount;
      document.getElementById('voteAbstain').textContent = abstainCount;
      document.getElementById('voteTotal').textContent = ballots.length;

      renderVoterList();
    } catch (err) {
      console.error('Ballots error:', err);
    }
  }

  // Render voter list
  function renderVoterList() {
    if (votersCache.length === 0) {
      voterList.innerHTML = '<div class="text-center p-2 text-muted">Aucun membre présent</div>';
      return;
    }

    voterList.innerHTML = votersCache.map(v => {
      const vote = ballotsCache[v.member_id];
      const hasVoted = !!vote;
      const name = escapeHtml(v.full_name || '—');

      return `
        <div class="voter-row ${hasVoted ? 'has-voted' : ''}">
          <span class="voter-name">${name}</span>
          <div class="vote-btns">
            <button class="vote-btn for ${vote === 'for' ? 'active' : ''}"
                    data-member="${v.member_id}" data-vote="for" ${hasVoted ? 'disabled' : ''}>Pour</button>
            <button class="vote-btn against ${vote === 'against' ? 'active' : ''}"
                    data-member="${v.member_id}" data-vote="against" ${hasVoted ? 'disabled' : ''}>Contre</button>
            <button class="vote-btn abstain ${vote === 'abstain' ? 'active' : ''}"
                    data-member="${v.member_id}" data-vote="abstain" ${hasVoted ? 'disabled' : ''}>Abst.</button>
          </div>
        </div>
      `;
    }).join('');

    // Bind vote buttons
    voterList.querySelectorAll('.vote-btn:not([disabled])').forEach(btn => {
      btn.addEventListener('click', () => castManualVote(btn.dataset.member, btn.dataset.vote));
    });
  }

  // Cast manual vote
  async function castManualVote(memberId, vote) {
    if (!currentOpenMotion) return;

    const justification = 'Vote opérateur manuel';

    try {
      const { body } = await api('/api/v1/manual_vote.php', {
        meeting_id: currentMeetingId,
        motion_id: currentOpenMotion.id,
        member_id: memberId,
        vote: vote,
        justification: justification
      });

      if (body && body.ok) {
        ballotsCache[memberId] = vote;
        renderVoterList();
        loadBallots(currentOpenMotion.id);
        setNotif('success', 'Vote enregistré');
      } else {
        setNotif('error', getApiError(body));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // Open vote
  async function openVote(motionId) {
    const btn = motionsList.querySelector(`.btn-open[data-motion-id="${motionId}"]`);
    Shared.btnLoading(btn, true);
    try {
      const { body } = await api('/api/v1/motions_open.php', {
        meeting_id: currentMeetingId,
        motion_id: motionId
      });

      if (body && body.ok) {
        setNotif('success', 'Vote ouvert');
        loadMotions(currentMeetingId);
      } else {
        setNotif('error', getApiError(body));
        Shared.btnLoading(btn, false);
      }
    } catch (err) {
      setNotif('error', err.message);
      Shared.btnLoading(btn, false);
    }
  }

  // Close vote from list
  async function closeVoteFromList(motionId) {
    if (!confirm('Clôturer ce vote ?')) return;
    const btn = motionsList.querySelector(`.btn-close[data-motion-id="${motionId}"]`);
    Shared.btnLoading(btn, true);
    try {
      const { body } = await api('/api/v1/motions_close.php', {
        meeting_id: currentMeetingId,
        motion_id: motionId
      });

      if (body && body.ok) {
        setNotif('success', 'Vote clôturé');
        loadMotions(currentMeetingId);
      } else {
        setNotif('error', getApiError(body));
        Shared.btnLoading(btn, false);
      }
    } catch (err) {
      setNotif('error', err.message);
      Shared.btnLoading(btn, false);
    }
  }

  // Close vote from panel button
  btnCloseVote.addEventListener('click', () => {
    if (currentOpenMotion) {
      closeVoteFromList(currentOpenMotion.id);
    }
  });

  // Labels français pour les statuts
  const statusLabels = {
    draft: 'Brouillon',
    scheduled: 'Programmée',
    frozen: 'Figée',
    live: 'En cours',
    closed: 'Clôturée',
    validated: 'Validée',
    archived: 'Archivée'
  };

  async function doTransition(toStatus) {
    if (!currentMeetingId) return;

    const statusLabel = statusLabels[toStatus] || toStatus;

    try {
      // 1. Vérifier les prérequis avant transition
      const checkRes = await api(`/api/v1/meeting_workflow_check.php?meeting_id=${currentMeetingId}&to_status=${toStatus}`);

      if (!checkRes.body || !checkRes.body.ok) {
        setNotif('error', getApiError(checkRes.body, 'Erreur de vérification'));
        return;
      }

      const { can_proceed, issues, warnings } = checkRes.body.data;

      // 2. Si des issues bloquantes existent, afficher et bloquer
      if (issues && issues.length > 0) {
        const issuesList = issues.map(i => `• ${i}`).join('\n');
        alert(`Impossible de passer en "${statusLabel}"\n\nProblèmes à résoudre :\n${issuesList}`);
        setNotif('error', `${issues.length} problème(s) bloquant(s)`);
        return;
      }

      // 3. Si des warnings existent, demander confirmation explicite
      let confirmMessage = `Passer la séance en "${statusLabel}" ?`;
      if (warnings && warnings.length > 0) {
        const warningsList = warnings.map(w => `• ${w}`).join('\n');
        confirmMessage = `Passer la séance en "${statusLabel}" ?\n\nAvertissements :\n${warningsList}\n\nVoulez-vous continuer malgré ces avertissements ?`;
      }

      if (!confirm(confirmMessage)) return;

      // 4. Effectuer la transition
      const { body } = await api('/api/v1/meeting_transition.php', {
        meeting_id: currentMeetingId,
        to_status: toStatus
      });

      if (body && body.ok) {
        setNotif('success', `Séance passée en "${statusLabel}"`);
        loadMeetings();
        loadMeetingContext(currentMeetingId);
      } else {
        // Traduire les erreurs courantes
        const errorMessages = {
          'workflow_issues': 'Prérequis non remplis pour cette transition',
          'invalid_transition': 'Transition non autorisée depuis l\'état actuel',
          'meeting_not_found': 'Séance introuvable',
          'meeting_already_validated': 'Séance déjà validée (modification interdite)',
        };
        const errorMsg = errorMessages[body?.error] || body?.error || 'Erreur lors de la transition';
        setNotif('error', errorMsg);
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // Register drawers
  if (window.ShellDrawer && window.ShellDrawer.register) {
    // Roles drawer - assign president/assessors
    window.ShellDrawer.register('roles', 'Rôles de séance', async function(meetingId, body, esc) {
      if (!currentMeetingId) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>';
        return;
      }
      body.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement...</div>';

      try {
        // Load users
        const usersRes = await api('/api/v1/admin_users.php');
        const users = usersRes.body?.data?.items || [];

        // Load current meeting roles
        const rolesRes = await api(`/api/v1/admin_meeting_roles.php?meeting_id=${currentMeetingId}`);
        const currentRoles = rolesRes.body?.data?.items || [];

        // Find current president
        const currentPresident = currentRoles.find(r => r.role === 'president');
        const currentAssessors = currentRoles.filter(r => r.role === 'assessor');

        body.innerHTML = `
          <div style="padding:8px 0;display:flex;flex-direction:column;gap:16px;">
            <div class="form-group">
              <label class="form-label">${icon('briefcase', 'icon-sm icon-text')} Président de séance</label>
              <select class="form-input" id="rolesPresident">
                <option value="">— Aucun —</option>
                ${users.map(u => `
                  <option value="${u.id}" ${currentPresident?.user_id === u.id ? 'selected' : ''}>
                    ${esc(u.name || u.email || u.id)}
                  </option>
                `).join('')}
              </select>
              <p class="text-sm text-muted mt-1">Le président peut ouvrir/clôturer la séance et valider les résultats.</p>
            </div>

            <div class="form-group">
              <label class="form-label">${icon('award', 'icon-sm icon-text')}Assesseurs / Scrutateurs</label>
              <div id="assessorsList" style="display:flex;flex-direction:column;gap:8px;">
                ${currentAssessors.length === 0 ? '<div class="text-sm text-muted">Aucun assesseur</div>' : ''}
                ${currentAssessors.map(a => `
                  <div class="flex items-center gap-2" data-assessor-id="${a.user_id}">
                    <span class="flex-1">${esc(a.user_name || a.user_id)}</span>
                    <button class="btn btn-ghost btn-sm btn-remove-assessor" data-user-id="${a.user_id}">${icon('x', 'icon-sm')}</button>
                  </div>
                `).join('')}
              </div>
              <div class="flex gap-2 mt-2">
                <select class="form-input flex-1" id="newAssessor">
                  <option value="">Ajouter un assesseur...</option>
                  ${users.filter(u => !currentAssessors.find(a => a.user_id === u.id)).map(u => `
                    <option value="${u.id}">${esc(u.name || u.email || u.id)}</option>
                  `).join('')}
                </select>
                <button class="btn btn-secondary btn-sm" id="btnAddAssessor">+</button>
              </div>
            </div>

            <button class="btn btn-primary btn-block" id="btnSaveRoles">${icon('save', 'icon-sm icon-text')} Enregistrer le président</button>
          </div>
        `;

        // Save president
        body.querySelector('#btnSaveRoles').addEventListener('click', async () => {
          const presidentId = body.querySelector('#rolesPresident').value;
          if (!presidentId) {
            setNotif('warning', 'Sélectionnez un président');
            return;
          }

          try {
            const { body: res } = await api('/api/v1/admin_meeting_roles.php', {
              action: 'assign',
              meeting_id: currentMeetingId,
              user_id: presidentId,
              role: 'president'
            });

            if (res?.ok || res?.assigned) {
              setNotif('success', 'Président assigné');
              loadWizardStatus(currentMeetingId);
              updateStatusAlert();
            } else {
              setNotif('error', getApiError(res));
            }
          } catch (err) {
            setNotif('error', err.message);
          }
        });

        // Add assessor
        body.querySelector('#btnAddAssessor').addEventListener('click', async () => {
          const userId = body.querySelector('#newAssessor').value;
          if (!userId) return;

          try {
            const { body: res } = await api('/api/v1/admin_meeting_roles.php', {
              action: 'assign',
              meeting_id: currentMeetingId,
              user_id: userId,
              role: 'assessor'
            });

            if (res?.ok || res?.assigned) {
              setNotif('success', 'Assesseur ajouté');
              // Refresh drawer
              document.querySelector('[data-drawer="roles"]')?.click();
            } else {
              setNotif('error', getApiError(res));
            }
          } catch (err) {
            setNotif('error', err.message);
          }
        });

        // Remove assessor
        body.querySelectorAll('.btn-remove-assessor').forEach(btn => {
          btn.addEventListener('click', async () => {
            const userId = btn.dataset.userId;

            try {
              const { body: res } = await api('/api/v1/admin_meeting_roles.php', {
                action: 'revoke',
                meeting_id: currentMeetingId,
                user_id: userId,
                role: 'assessor'
              });

              if (res?.ok || res?.revoked) {
                setNotif('success', 'Assesseur retiré');
                btn.closest('[data-assessor-id]')?.remove();
              } else {
                setNotif('error', getApiError(res));
              }
            } catch (err) {
              setNotif('error', err.message);
            }
          });
        });

      } catch (e) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>';
        console.error('Roles drawer error:', e);
      }
    });

    // Settings drawer
    window.ShellDrawer.register('settings', 'Réglages de la séance', async function(meetingId, body, esc) {
      if (!currentMeetingId) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>';
        return;
      }
      body.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement...</div>';

      try {
        // Load quorum policies
        const qpRes = await api('/api/v1/quorum_policies.php');
        const quorumPolicies = qpRes.body?.data?.items || [];

        // Load vote policies
        const vpRes = await api('/api/v1/vote_policies.php');
        const votePolicies = vpRes.body?.data?.items || [];

        // Load current settings
        const qsRes = await api(`/api/v1/meeting_quorum_settings.php?meeting_id=${currentMeetingId}`);
        const currentQuorumPolicy = qsRes.body?.data?.quorum_policy_id || '';
        const currentConvocation = qsRes.body?.data?.convocation_no || 1;

        const vsRes = await api(`/api/v1/meeting_vote_settings.php?meeting_id=${currentMeetingId}`);
        const currentVotePolicy = vsRes.body?.data?.vote_policy_id || '';

        body.innerHTML = `
          <div style="padding:8px 0;display:flex;flex-direction:column;gap:16px;">
            <div class="form-group">
              <label class="form-label">Politique de quorum</label>
              <select class="form-input" id="settingsQuorumPolicy">
                <option value="">— Aucune —</option>
                ${quorumPolicies.map(p => `
                  <option value="${p.id}" ${p.id === currentQuorumPolicy ? 'selected' : ''}>
                    ${esc(p.label || p.name || p.id)}
                  </option>
                `).join('')}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Numéro de convocation</label>
              <select class="form-input" id="settingsConvocation">
                <option value="1" ${currentConvocation === 1 ? 'selected' : ''}>1ère convocation</option>
                <option value="2" ${currentConvocation === 2 ? 'selected' : ''}>2ème convocation</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Politique de vote (majorité)</label>
              <select class="form-input" id="settingsVotePolicy">
                <option value="">— Aucune —</option>
                ${votePolicies.map(p => `
                  <option value="${p.id}" ${p.id === currentVotePolicy ? 'selected' : ''}>
                    ${esc(p.label || p.name || p.id)}
                  </option>
                `).join('')}
              </select>
            </div>
            <button class="btn btn-primary btn-block" id="btnSaveSettings">${icon('save', 'icon-sm icon-text')} Enregistrer</button>
          </div>
        `;

        body.querySelector('#btnSaveSettings').addEventListener('click', async () => {
          const qpId = body.querySelector('#settingsQuorumPolicy').value;
          const conv = parseInt(body.querySelector('#settingsConvocation').value, 10);
          const vpId = body.querySelector('#settingsVotePolicy').value;

          try {
            await api('/api/v1/meeting_quorum_settings.php', {
              meeting_id: currentMeetingId,
              quorum_policy_id: qpId,
              convocation_no: conv
            });

            await api('/api/v1/meeting_vote_settings.php', {
              meeting_id: currentMeetingId,
              vote_policy_id: vpId
            });

            setNotif('success', 'Réglages enregistrés');
            document.querySelector('[data-drawer-close]')?.click();
          } catch (err) {
            setNotif('error', err.message);
          }
        });
      } catch (e) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>';
      }
    });

    // Quorum drawer
    window.ShellDrawer.register('quorum', 'Statut du quorum', async function(meetingId, body, esc) {
      if (!meetingId && currentMeetingId) meetingId = currentMeetingId;
      if (!meetingId) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>';
        return;
      }
      body.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement...</div>';
      try {
        const res = await api('/api/v1/quorum_status.php?meeting_id=' + meetingId);
        const b = res.body;
        if (b && b.ok && b.data) {
          const q = b.data;
          const ratio = Math.min(100, Math.round((q.ratio || 0) * 100));
          const threshold = Math.round((q.threshold || 0.5) * 100);
          const barColor = q.met ? 'var(--color-success)' : 'var(--color-warning)';
          body.innerHTML = `
            <div style="padding:8px 0;display:flex;flex-direction:column;gap:16px;">
              <div style="text-align:center;padding:16px;">
                <span class="badge ${q.met ? 'badge-success' : 'badge-warning'}" style="font-size:14px;padding:8px 16px;">
                  ${q.met ? 'Quorum atteint' : 'Quorum non atteint'}
                </span>
              </div>
              <div style="background:var(--color-bg-subtle);border-radius:8px;height:20px;position:relative;overflow:hidden;">
                <div style="background:${barColor};height:100%;width:${ratio}%;border-radius:8px;"></div>
                <div style="position:absolute;top:0;bottom:0;left:${threshold}%;width:2px;background:#333;"></div>
              </div>
              <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
                <span>${ratio}% atteint</span>
                <span>Seuil: ${threshold}%</span>
              </div>
              <div style="font-size:0.85rem;color:var(--color-text-muted);">
                ${q.present || 0} présents sur ${q.total_eligible || '—'} éligibles
              </div>
            </div>
          `;
        } else {
          body.innerHTML = '<div style="padding:16px;" class="text-muted">Indisponible.</div>';
        }
      } catch(e) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur.</div>';
      }
    });

    // Transitions drawer
    window.ShellDrawer.register('transitions', 'État de la séance', async function(meetingId, body, esc) {
      if (!currentMeetingId) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>';
        return;
      }

      const transitions = TRANSITIONS[currentMeetingStatus] || [];
      if (transitions.length === 0) {
        body.innerHTML = `
          <div style="padding:16px;">
            <div class="text-muted mb-4">État actuel: <strong>${currentMeetingStatus}</strong></div>
            <div class="text-sm text-muted">Aucune transition disponible.</div>
          </div>
        `;
        return;
      }

      body.innerHTML = `
        <div style="padding:8px 0;">
          <div class="text-muted mb-4">État actuel: <strong>${currentMeetingStatus}</strong></div>
          <div style="display:flex;flex-direction:column;gap:8px;">
            ${transitions.map(t => `
              <button class="btn btn-block" data-transition="${t.to}">
                ${t.iconName ? icon(t.iconName, 'icon-sm icon-text') : ''}${t.label}
              </button>
            `).join('')}
          </div>
        </div>
      `;

      body.querySelectorAll('[data-transition]').forEach(btn => {
        btn.addEventListener('click', () => {
          doTransition(btn.dataset.transition);
          // Close drawer
          document.querySelector('[data-drawer-close]')?.click();
        });
      });
    });

    // Motions drawer - create/manage resolutions
    window.ShellDrawer.register('motions', 'Résolutions', async function(meetingId, body, esc) {
      if (!currentMeetingId) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>';
        return;
      }
      body.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement...</div>';

      try {
        const { body: res } = await api(`/api/v1/motions_for_meeting.php?meeting_id=${currentMeetingId}`);
        const motions = res?.data?.motions || [];

        const canEdit = !['validated', 'archived'].includes(currentMeetingStatus);

        body.innerHTML = `
          <div style="padding:8px 0;display:flex;flex-direction:column;gap:16px;">
            <div class="flex items-center justify-between">
              <span class="text-sm text-muted">${motions.length} résolution(s)</span>
              ${canEdit ? '<button class="btn btn-sm btn-primary" id="btnAddMotion">+ Ajouter</button>' : ''}
            </div>

            <div id="addMotionForm" style="display:none;background:var(--color-bg-subtle);padding:12px;border-radius:6px;">
              <div class="form-group mb-2">
                <input type="text" class="form-input" id="newMotionTitle" placeholder="Titre de la résolution">
              </div>
              <div class="form-group mb-2">
                <textarea class="form-input" id="newMotionDesc" rows="2" placeholder="Description (optionnel)"></textarea>
              </div>
              <div class="flex gap-2">
                <button class="btn btn-sm btn-primary" id="btnConfirmMotion">Créer</button>
                <button class="btn btn-sm btn-ghost" id="btnCancelMotion">Annuler</button>
              </div>
            </div>

            <div id="motionsDrawerList" style="display:flex;flex-direction:column;gap:8px;">
              ${motions.map((m, i) => {
                const isOpen = !!(m.opened_at && !m.closed_at);
                const isClosed = !!m.closed_at;
                const statusIcon = isOpen ? icon('circle', 'icon-xs icon-warning') : (isClosed ? icon('check', 'icon-xs icon-success') : icon('circle', 'icon-xs icon-muted'));
                const statusText = isOpen ? 'En cours' : (isClosed ? 'Terminé' : 'En attente');

                return `
                  <div class="flex items-center gap-2 p-2 border border-border rounded" style="background:var(--color-surface);">
                    <span style="font-weight:bold;">${i + 1}</span>
                    <div class="flex-1" style="min-width:0;">
                      <div style="font-size:0.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(m.title)}</div>
                      <div style="font-size:0.75rem;color:var(--color-text-muted);">${statusIcon} ${statusText}</div>
                    </div>
                    ${canEdit && !isOpen && !isClosed ? `<button class="btn btn-xs btn-ghost btn-delete-motion" data-motion-id="${m.id}" title="Supprimer">${icon('trash', 'icon-sm')}</button>` : ''}
                  </div>
                `;
              }).join('')}
              ${motions.length === 0 ? '<div class="text-center p-4 text-muted">Aucune résolution</div>' : ''}
            </div>

            <button class="btn btn-block btn-secondary" data-tab-switch="resolutions">${icon('clipboard-list', 'icon-sm icon-text')}Voir toutes les résolutions</button>
          </div>
        `;

        if (canEdit) {
          const addForm = body.querySelector('#addMotionForm');

          // Add motion toggle
          body.querySelector('#btnAddMotion')?.addEventListener('click', () => {
            addForm.style.display = addForm.style.display === 'none' ? 'block' : 'none';
          });

          body.querySelector('#btnCancelMotion')?.addEventListener('click', () => {
            addForm.style.display = 'none';
          });

          // Create motion
          body.querySelector('#btnConfirmMotion')?.addEventListener('click', async () => {
            const title = body.querySelector('#newMotionTitle').value.trim();
            const desc = body.querySelector('#newMotionDesc').value.trim();

            if (!title) {
              setNotif('error', 'Titre requis');
              return;
            }

            try {
              const { body: createRes } = await api('/api/v1/motions.php', {
                meeting_id: currentMeetingId,
                title: title,
                description: desc || null
              });

              if (createRes?.ok !== false && (createRes?.data?.id || createRes?.id)) {
                setNotif('success', 'Résolution créée');
                loadMotions(currentMeetingId);
                loadWizardStatus(currentMeetingId);
                updateStatusAlert();
                // Refresh drawer
                document.querySelector('[data-drawer="motions"]')?.click();
              } else {
                setNotif('error', getApiError(createRes));
              }
            } catch (err) {
              setNotif('error', err.message);
            }
          });

          // Delete motion
          body.querySelectorAll('.btn-delete-motion').forEach(btn => {
            btn.addEventListener('click', async () => {
              if (!confirm('Supprimer cette résolution ?')) return;

              try {
                const { body: delRes } = await api('/api/v1/motions_delete.php', {
                  motion_id: btn.dataset.motionId,
                  meeting_id: currentMeetingId
                });

                if (delRes?.ok) {
                  setNotif('success', 'Résolution supprimée');
                  loadMotions(currentMeetingId);
                  loadWizardStatus(currentMeetingId);
                  updateStatusAlert();
                  // Refresh drawer
                  document.querySelector('[data-drawer="motions"]')?.click();
                } else {
                  setNotif('error', getApiError(delRes));
                }
              } catch (err) {
                setNotif('error', err.message);
              }
            });
          });
        }

      } catch (e) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>';
        console.error('Motions drawer error:', e);
      }
    });

    // Members drawer - view/add members
    window.ShellDrawer.register('members', 'Gestion des membres', async function(meetingId, body, esc) {
      body.innerHTML = '<div style="padding:16px;" class="text-muted">Chargement...</div>';

      try {
        const { body: res } = await api('/api/v1/members.php');
        const members = res?.data?.members || res?.members || [];

        body.innerHTML = `
          <div style="padding:8px 0;display:flex;flex-direction:column;gap:16px;">
            <div class="flex items-center justify-between">
              <span class="text-sm text-muted">${members.length} membre(s)</span>
              <div class="flex gap-2">
                <button class="btn btn-sm btn-secondary" id="btnImportCsv">${icon('download', 'icon-sm icon-text')} Import CSV</button>
                <button class="btn btn-sm btn-primary" id="btnAddMember">+ Ajouter</button>
              </div>
            </div>

            <div id="addMemberForm" style="display:none;background:var(--color-bg-subtle);padding:12px;border-radius:6px;">
              <div class="form-group mb-2">
                <input type="text" class="form-input" id="newMemberName" placeholder="Nom complet">
              </div>
              <div class="form-group mb-2">
                <input type="email" class="form-input" id="newMemberEmail" placeholder="Email (optionnel)">
              </div>
              <div class="flex gap-2">
                <button class="btn btn-sm btn-primary" id="btnConfirmAdd">Ajouter</button>
                <button class="btn btn-sm btn-ghost" id="btnCancelAdd">Annuler</button>
              </div>
            </div>

            <div id="importCsvForm" style="display:none;background:var(--color-bg-subtle);padding:12px;border-radius:6px;">
              <p class="text-sm text-muted mb-2">Format CSV: name,email,voting_power (en-têtes requis)</p>
              <input type="file" accept=".csv" id="csvFileInput" class="form-input mb-2">
              <div class="flex gap-2">
                <button class="btn btn-sm btn-primary" id="btnUploadCsv">${icon('upload', 'icon-sm icon-text')} Importer</button>
                <button class="btn btn-sm btn-ghost" id="btnCancelImport">Annuler</button>
              </div>
              <div id="importResult" class="mt-2" style="display:none;"></div>
            </div>

            <input type="text" class="form-input" id="memberSearchInput" placeholder="Rechercher...">

            <div id="membersList" style="max-height:300px;overflow-y:auto;display:flex;flex-direction:column;gap:4px;">
              ${members.map(m => `
                <div class="flex items-center gap-2 p-2 bg-surface border border-border rounded" style="font-size:0.85rem;">
                  <span class="flex-1">${esc(m.full_name || m.name || '—')}</span>
                  <span class="text-muted">${m.voting_power || 1}</span>
                </div>
              `).join('')}
              ${members.length === 0 ? '<div class="text-center p-4 text-muted">Aucun membre</div>' : ''}
            </div>

            <a href="/members.htmx.html" class="btn btn-block btn-secondary">${icon('users', 'icon-sm icon-text')}Vue complète</a>
          </div>
        `;

        const addForm = body.querySelector('#addMemberForm');
        const importForm = body.querySelector('#importCsvForm');
        const membersList = body.querySelector('#membersList');

        // Add member toggle
        body.querySelector('#btnAddMember').addEventListener('click', () => {
          addForm.style.display = addForm.style.display === 'none' ? 'block' : 'none';
          importForm.style.display = 'none';
        });

        body.querySelector('#btnCancelAdd').addEventListener('click', () => {
          addForm.style.display = 'none';
        });

        // Add member submit
        body.querySelector('#btnConfirmAdd').addEventListener('click', async () => {
          const name = body.querySelector('#newMemberName').value.trim();
          const email = body.querySelector('#newMemberEmail').value.trim();

          if (!name) {
            setNotif('error', 'Nom requis');
            return;
          }

          try {
            const { body: addRes } = await api('/api/v1/members.php', {
              full_name: name,
              email: email || null
            });

            if (addRes?.ok !== false && (addRes?.data?.id || addRes?.id)) {
              setNotif('success', 'Membre ajouté');
              // Refresh drawer
              document.querySelector('[data-drawer="members"]')?.click();
            } else {
              setNotif('error', getApiError(addRes));
            }
          } catch (err) {
            setNotif('error', err.message);
          }
        });

        // Import CSV toggle
        body.querySelector('#btnImportCsv').addEventListener('click', () => {
          importForm.style.display = importForm.style.display === 'none' ? 'block' : 'none';
          addForm.style.display = 'none';
        });

        body.querySelector('#btnCancelImport').addEventListener('click', () => {
          importForm.style.display = 'none';
        });

        // CSV upload
        body.querySelector('#btnUploadCsv').addEventListener('click', async () => {
          const fileInput = body.querySelector('#csvFileInput');
          const resultDiv = body.querySelector('#importResult');

          if (!fileInput.files || !fileInput.files[0]) {
            setNotif('error', 'Sélectionnez un fichier CSV');
            return;
          }

          const formData = new FormData();
          formData.append('file', fileInput.files[0]);

          try {
            const response = await fetch('/api/v1/members_import_csv.php', {
              method: 'POST',
              body: formData
            });
            const result = await response.json();

            resultDiv.style.display = 'block';
            if (result.ok) {
              resultDiv.className = 'alert alert-success';
              resultDiv.innerHTML = `${icon('check', 'icon-sm icon-text')}${result.imported} importé(s), ${result.skipped} ignoré(s)`;
              setNotif('success', `Import: ${result.imported} membres`);
              // Refresh wizard status
              loadWizardStatus(currentMeetingId);
              updateStatusAlert();
            } else {
              resultDiv.className = 'alert alert-danger';
              resultDiv.textContent = result.error || 'Erreur import';
            }
          } catch (err) {
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-danger';
            resultDiv.textContent = err.message;
          }
        });

        // Search filter
        body.querySelector('#memberSearchInput').addEventListener('input', (e) => {
          const term = e.target.value.toLowerCase();
          membersList.querySelectorAll('.flex.items-center').forEach(row => {
            const name = row.textContent.toLowerCase();
            row.style.display = name.includes(term) ? 'flex' : 'none';
          });
        });

      } catch (e) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Erreur de chargement.</div>';
        console.error('Members drawer error:', e);
      }
    });

    // Incident drawer - declare incidents
    window.ShellDrawer.register('incident', 'Déclarer un incident', async function(meetingId, body, esc) {
      if (!currentMeetingId) {
        body.innerHTML = '<div style="padding:16px;" class="text-muted">Sélectionnez une séance.</div>';
        return;
      }

      const INCIDENT_TYPES = [
        { value: 'network', label: 'Problème réseau', iconName: 'activity' },
        { value: 'hardware', label: 'Problème matériel', iconName: 'settings' },
        { value: 'procedural', label: 'Problème procédural', iconName: 'clipboard-list' },
        { value: 'voter', label: 'Problème votant', iconName: 'user' },
        { value: 'power', label: 'Coupure électrique', iconName: 'zap' },
        { value: 'other', label: 'Autre', iconName: 'info' }
      ];

      body.innerHTML = `
        <div style="padding:8px 0;display:flex;flex-direction:column;gap:16px;">
          <p class="text-sm text-muted">
            Déclarez tout incident survenu pendant la séance. Ces informations seront enregistrées dans le journal d'audit.
          </p>

          <div class="form-group">
            <label class="form-label">Type d'incident</label>
            <div id="incidentTypes" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
              ${INCIDENT_TYPES.map(t => `
                <button class="btn btn-secondary incident-type-btn" data-kind="${t.value}">
                  ${t.iconName ? icon(t.iconName, 'icon-sm icon-text') : ''}${t.label}
                </button>
              `).join('')}
            </div>
            <input type="hidden" id="incidentKind" value="">
          </div>

          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-input" id="incidentDetail" rows="3" placeholder="Décrivez l'incident..."></textarea>
          </div>

          <div id="incidentMsg" style="display:none;"></div>

          <button class="btn btn-warning btn-block" id="btnDeclareIncident" disabled>
            ${icon('alert-triangle', 'icon-sm icon-text')}Déclarer l'incident
          </button>

          <div class="text-sm text-muted" style="border-top:1px solid var(--color-border);padding-top:12px;">
            <strong>Incidents déclarés cette séance:</strong>
            <div id="incidentHistory" class="mt-2">—</div>
          </div>
        </div>
      `;

      const kindInput = body.querySelector('#incidentKind');
      const detailInput = body.querySelector('#incidentDetail');
      const btnDeclare = body.querySelector('#btnDeclareIncident');
      const msgBox = body.querySelector('#incidentMsg');

      // Type selection
      body.querySelectorAll('.incident-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          body.querySelectorAll('.incident-type-btn').forEach(b => b.classList.remove('btn-primary'));
          btn.classList.add('btn-primary');
          btn.classList.remove('btn-secondary');
          kindInput.value = btn.dataset.kind;
          validateIncidentForm();
        });
      });

      // Validate form
      function validateIncidentForm() {
        const hasKind = kindInput.value.length > 0;
        const hasDetail = detailInput.value.trim().length >= 5;
        btnDeclare.disabled = !(hasKind && hasDetail);
      }

      detailInput.addEventListener('input', validateIncidentForm);

      // Submit
      btnDeclare.addEventListener('click', async () => {
        if (btnDeclare.disabled) return;

        Shared.btnLoading(btnDeclare, true);
        try {
          const { body: res } = await api('/api/v1/vote_incident.php', {
            kind: kindInput.value,
            detail: detailInput.value.trim(),
            meeting_id: currentMeetingId
          });

          if (res?.ok || res?.saved) {
            msgBox.style.display = 'block';
            msgBox.className = 'alert alert-success';
            msgBox.textContent = 'Incident enregistré dans le journal d\'audit';
            setNotif('success', 'Incident déclaré');

            // Reset form
            kindInput.value = '';
            detailInput.value = '';
            body.querySelectorAll('.incident-type-btn').forEach(b => {
              b.classList.remove('btn-primary');
              b.classList.add('btn-secondary');
            });
            validateIncidentForm();
          } else {
            msgBox.style.display = 'block';
            msgBox.className = 'alert alert-danger';
            msgBox.textContent = getApiError(res);
          }
        } catch (err) {
          msgBox.style.display = 'block';
          msgBox.className = 'alert alert-danger';
          msgBox.textContent = err.message;
        } finally {
          Shared.btnLoading(btnDeclare, false);
        }
      });
    });
  }

  // Event listeners
  meetingSelect.addEventListener('change', () => {
    loadMeetingContext(meetingSelect.value);
  });

  // Attendance inline event listeners
  if (attendanceHeader) {
    attendanceHeader.addEventListener('click', () => {
      attendanceSection.classList.toggle('expanded');
    });
  }

  if (attSearchInput) {
    attSearchInput.addEventListener('input', (e) => {
      attSearchTerm = e.target.value.trim();
      renderAttendanceInline();
    });
    // Prevent toggle when clicking in search
    attSearchInput.addEventListener('click', (e) => e.stopPropagation());
  }

  if (btnAttAllPresent) {
    btnAttAllPresent.addEventListener('click', (e) => {
      e.stopPropagation();
      markAllPresentInline();
    });
  }

  // btnAttFullView removed - attendance is now in the Présences tab

  // Export buttons event listeners
  if (btnExportPV) {
    btnExportPV.addEventListener('click', async () => {
      if (!currentMeetingId) return;
      window.open(`/api/v1/meeting_generate_report_pdf.php?meeting_id=${currentMeetingId}&preview=1`, '_blank');
    });
  }

  if (btnExportAttendance) {
    btnExportAttendance.addEventListener('click', async () => {
      if (!currentMeetingId) return;
      window.open(`/api/v1/export_attendance_csv.php?meeting_id=${currentMeetingId}`, '_blank');
    });
  }

  if (btnExportVotes) {
    btnExportVotes.addEventListener('click', async () => {
      if (!currentMeetingId) return;
      window.open(`/api/v1/export_votes_csv.php?meeting_id=${currentMeetingId}`, '_blank');
    });
  }

  // ============================================================================
  // INVITATIONS MANAGEMENT
  // ============================================================================

  const invitationsCard = document.getElementById('invitationsCard');
  const invTotal = document.getElementById('invTotal');
  const invSent = document.getElementById('invSent');
  const invOpened = document.getElementById('invOpened');
  const invBounced = document.getElementById('invBounced');
  const invEngagement = document.getElementById('invEngagement');
  const invOpenRate = document.getElementById('invOpenRate');
  const invitationsOptions = document.getElementById('invitationsOptions');
  const invTemplateSelect = document.getElementById('invTemplateSelect');
  const scheduleGroup = document.getElementById('scheduleGroup');
  const invScheduleAt = document.getElementById('invScheduleAt');

  let invitationStats = {};
  let isScheduleMode = false;

  // Load invitation stats for current meeting
  async function loadInvitationStats(meetingId) {
    if (!invitationsCard) return;

    try {
      const { body } = await api(`/api/v1/invitations_stats.php?meeting_id=${meetingId}`);

      if (body && body.ok && body.data) {
        invitationStats = body.data;

        const inv = body.data.invitations || {};
        invTotal.textContent = inv.total || 0;
        invSent.textContent = (inv.sent || 0) + (inv.opened || 0) + (inv.accepted || 0);
        invOpened.textContent = (inv.opened || 0) + (inv.accepted || 0);
        invBounced.textContent = inv.bounced || 0;

        // Show engagement rate if there are sent emails
        const totalSent = (inv.sent || 0) + (inv.opened || 0) + (inv.accepted || 0) + (inv.bounced || 0);
        if (totalSent > 0) {
          invEngagement.style.display = 'block';
          invOpenRate.textContent = body.data.engagement?.open_rate + '%' || '0%';
        } else {
          invEngagement.style.display = 'none';
        }
      }
    } catch (err) {
      console.error('Invitation stats error:', err);
    }
  }

  // Load email templates for dropdown
  async function loadEmailTemplates() {
    if (!invTemplateSelect) return;

    try {
      const { body } = await api('/api/v1/email_templates.php?type=invitation');

      if (body && body.ok && body.data) {
        const templates = body.data.templates || [];

        invTemplateSelect.innerHTML = '<option value="">Template par defaut</option>';
        templates.forEach(t => {
          const opt = document.createElement('option');
          opt.value = t.id;
          opt.textContent = t.name + (t.is_default ? ' (defaut)' : '');
          invTemplateSelect.appendChild(opt);
        });
      }
    } catch (err) {
      console.error('Templates load error:', err);
    }
  }

  // Show/hide invitations options panel
  function showInvitationsOptions(scheduleMode = false) {
    if (!invitationsOptions) return;

    isScheduleMode = scheduleMode;
    invitationsOptions.style.display = 'block';
    scheduleGroup.style.display = scheduleMode ? 'block' : 'none';

    if (scheduleMode && invScheduleAt) {
      // Set default schedule to tomorrow at 9:00
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      tomorrow.setHours(9, 0, 0, 0);
      invScheduleAt.value = tomorrow.toISOString().slice(0, 16);
    }

    loadEmailTemplates();
  }

  function hideInvitationsOptions() {
    if (invitationsOptions) {
      invitationsOptions.style.display = 'none';
    }
  }

  // Send invitations
  async function sendInvitations() {
    if (!currentMeetingId) return;

    const templateId = invTemplateSelect?.value || null;
    const recipientsRadio = document.querySelector('input[name="invRecipients"]:checked');
    const onlyUnsent = recipientsRadio?.value !== 'all';
    const scheduledAt = isScheduleMode ? invScheduleAt?.value : null;

    // Confirm before sending
    const action = isScheduleMode ? 'programmer' : 'envoyer';
    const target = onlyUnsent ? 'aux membres non encore invites' : 'a tous les membres';
    if (!confirm(`Confirmer ${action} les invitations ${target} ?`)) return;

    const btnConfirm = document.getElementById('btnConfirmSend');
    if (btnConfirm) Shared.btnLoading(btnConfirm, true);

    try {
      const endpoint = isScheduleMode ? '/api/v1/invitations_schedule.php' : '/api/v1/invitations_send_bulk.php';
      const payload = {
        meeting_id: currentMeetingId,
        only_unsent: onlyUnsent
      };

      if (templateId) {
        payload.template_id = templateId;
      }

      if (isScheduleMode && scheduledAt) {
        payload.scheduled_at = scheduledAt;
      }

      const { body } = await api(endpoint, payload);

      if (body && (body.ok || body.data)) {
        const count = body.data?.sent || body.data?.scheduled || body.sent || 0;
        const msg = isScheduleMode
          ? `${count} invitation(s) programmee(s)`
          : `${count} invitation(s) envoyee(s)`;
        setNotif('success', msg);
        hideInvitationsOptions();
        loadInvitationStats(currentMeetingId);
      } else {
        setNotif('error', getApiError(body));
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      if (btnConfirm) Shared.btnLoading(btnConfirm, false);
    }
  }

  // Invitation button event listeners
  const btnSendInvitations = document.getElementById('btnSendInvitations');
  const btnScheduleInvitations = document.getElementById('btnScheduleInvitations');
  const btnConfirmSend = document.getElementById('btnConfirmSend');
  const btnCancelSend = document.getElementById('btnCancelSend');

  if (btnSendInvitations) {
    btnSendInvitations.addEventListener('click', () => {
      showInvitationsOptions(false);
    });
  }

  if (btnScheduleInvitations) {
    btnScheduleInvitations.addEventListener('click', () => {
      showInvitationsOptions(true);
    });
  }

  if (btnConfirmSend) {
    btnConfirmSend.addEventListener('click', sendInvitations);
  }

  if (btnCancelSend) {
    btnCancelSend.addEventListener('click', hideInvitationsOptions);
  }

  // Initial load
  loadMeetings();

  // Auto-refresh every 5s
  setInterval(async () => {
    if (currentMeetingId && !document.hidden) {
      await Promise.all([
        loadAttendanceStats(currentMeetingId),
        loadQuorumStatus(currentMeetingId),
        loadMotions(currentMeetingId),
        loadWizardStatus(currentMeetingId),
        loadInvitationStats(currentMeetingId)
      ]);
      updateStatusAlert();
    }
  }, 5000);

  // Also load invitation stats when meeting changes
  const originalLoadMeetingContext = loadMeetingContext;
  window.addEventListener('load', () => {
    // Override to add invitation stats loading
    if (currentMeetingId) {
      loadInvitationStats(currentMeetingId);
    }
  });

})();
