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

  let currentMeetingId = null;
  let currentMeetingStatus = null;
  let currentOpenMotion = null;
  let votersCache = [];
  let ballotsCache = {};

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
    meetingStatusBadge.textContent = '—';
    meetingStatusBadge.className = 'badge';
    meetingTitle.textContent = '—';
  }

  function showMeetingContent() {
    noMeetingAlert.style.display = 'none';
    quickLinks.style.display = 'flex';
    motionsSection.style.display = 'block';
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
      }

      // Load stats and motions
      await Promise.all([
        loadAttendanceStats(meetingId),
        loadQuorumStatus(meetingId),
        loadMotions(meetingId)
      ]);

    } catch (err) {
      setNotif('error', 'Erreur: ' + err.message);
    }
  }

  // Load attendance stats
  async function loadAttendanceStats(meetingId) {
    try {
      const { body } = await api(`/api/v1/attendances.php?meeting_id=${meetingId}`);

      if (body && body.ok && body.data) {
        const attendances = body.data.attendances || [];
        votersCache = attendances.filter(a => a.mode === 'present' || a.mode === 'remote');
        const present = votersCache.length;
        statPresent.textContent = present;
        document.getElementById('voteEligible').textContent = present;
      }
    } catch (err) {
      console.error('Attendance error:', err);
    }
  }

  // Load quorum status
  async function loadQuorumStatus(meetingId) {
    try {
      const { body } = await api(`/api/v1/quorum_status.php?meeting_id=${meetingId}`);

      if (body && body.ok && body.data) {
        const q = body.data;
        statQuorum.textContent = q.met ? '✓' : '✗';
        statQuorum.style.color = q.met ? 'var(--color-success)' : 'var(--color-danger)';
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
            <a href="/motions.htmx.html?meeting_id=${meetingId}" class="btn btn-primary btn-sm mt-4">
              ➕ Créer des résolutions
            </a>
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
              <span style="color:var(--color-success)">✓ ${m.votes_for || 0}</span>
              <span style="color:var(--color-danger)">✗ ${m.votes_against || 0}</span>
              <span>⚪ ${m.votes_abstain || 0}</span>
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
        setNotif('error', body?.error || 'Erreur');
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
        setNotif('error', body?.error || 'Erreur');
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
        setNotif('error', body?.error || 'Erreur');
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

  // Transitions (state machine)
  const TRANSITIONS = {
    draft: [{ to: 'scheduled', label: 'Planifier', icon: '📅' }],
    scheduled: [
      { to: 'frozen', label: 'Geler (verrouiller)', icon: '🧊' },
      { to: 'draft', label: 'Retour brouillon', icon: '↩️' }
    ],
    frozen: [
      { to: 'live', label: 'Ouvrir la séance', icon: '▶️' },
      { to: 'scheduled', label: 'Dégeler', icon: '↩️' }
    ],
    live: [{ to: 'closed', label: 'Clôturer la séance', icon: '⏹️' }],
    closed: [{ to: 'validated', label: 'Valider la séance', icon: '✅' }],
    validated: [{ to: 'archived', label: 'Archiver', icon: '📦' }],
    archived: []
  };

  async function doTransition(toStatus) {
    if (!currentMeetingId) return;
    if (!confirm(`Changer l'état vers "${toStatus}" ?`)) return;

    try {
      const { body } = await api('/api/v1/meeting_transition.php', {
        meeting_id: currentMeetingId,
        to_status: toStatus
      });

      if (body && body.ok) {
        setNotif('success', `Séance passée en "${toStatus}"`);
        loadMeetings();
        loadMeetingContext(currentMeetingId);
      } else {
        setNotif('error', body?.error || 'Erreur');
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // Register drawers
  if (window.ShellDrawer && window.ShellDrawer.register) {
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
        const quorumPolicies = qpRes.body?.items || [];

        // Load vote policies
        const vpRes = await api('/api/v1/vote_policies.php');
        const votePolicies = vpRes.body?.items || [];

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
            <button class="btn btn-primary btn-block" id="btnSaveSettings">💾 Enregistrer</button>
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
                ${t.icon} ${t.label}
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
  }

  // Event listeners
  meetingSelect.addEventListener('change', () => {
    loadMeetingContext(meetingSelect.value);
  });

  // Initial load
  loadMeetings();

  // Auto-refresh every 5s
  setInterval(() => {
    if (currentMeetingId && !document.hidden) {
      loadAttendanceStats(currentMeetingId);
      loadQuorumStatus(currentMeetingId);
      loadMotions(currentMeetingId);
    }
  }, 5000);

})();
