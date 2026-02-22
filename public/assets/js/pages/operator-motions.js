/**
 * operator-motions.js — Motions / Votes / Results / Transitions sub-module
 * for the operator console.
 * Requires: utils.js, shared.js, operator-tabs.js (OpS bridge)
 */
(function() {
  'use strict';

  const O = window.OpS;

  const VALID_VOTE_TYPES = ['for', 'against', 'abstain'];

  // =========================================================================
  // TAB: RÉSOLUTIONS - Motions
  // =========================================================================

  async function loadResolutions() {
    try {
      const { body } = await api(`/api/v1/motions_for_meeting.php?meeting_id=${O.currentMeetingId}`);
      O.motionsCache = body?.data?.items || [];
      O.currentOpenMotion = O.motionsCache.find(m => m.opened_at && !m.closed_at) || null;
      renderResolutions();
      document.getElementById('tabCountResolutions').textContent = O.motionsCache.length;
    } catch (err) {
      setNotif('error', 'Erreur chargement résolutions');
    }
  }

  // Initialize previousOpenMotionId when loading meeting to avoid false "vote opened" notifications
  function initializePreviousMotionState() {
    O.previousOpenMotionId = O.currentOpenMotion?.id || null;
  }

  function renderResolutions() {
    const list = document.getElementById('resolutionsList');
    const searchTerm = (document.getElementById('resolutionSearch')?.value || '').toLowerCase();
    let filtered = O.motionsCache;
    if (searchTerm) {
      filtered = O.motionsCache.filter(m => (m.title || '').toLowerCase().includes(searchTerm));
    }

    const canEdit = !['validated', 'archived'].includes(O.currentMeetingStatus);
    const isLive = O.currentMeetingStatus === 'live';
    const totalCount = O.motionsCache.length;

    // Build header hint
    const headerHint = filtered.length > 0 ? `
      <div class="resolutions-list-header">
        <div class="hint">
          ${icon('mouse-pointer', 'icon-sm')}
          <span>Cliquez sur une résolution pour voir les détails</span>
        </div>
        <span>${filtered.length} résolution${filtered.length > 1 ? 's' : ''}</span>
      </div>
    ` : '';

    list.innerHTML = headerHint + (filtered.map((m, i) => {
      const isOpen = !!(m.opened_at && !m.closed_at);
      const isClosed = !!m.closed_at;
      const dec = m.decision || m.result || '';
      const closedLabels = { adopted: 'Adoptée', rejected: 'Rejetée', no_quorum: 'Sans quorum', no_votes: 'Sans vote', no_policy: 'Terminé' };
      const statusClass = isOpen ? 'open' : (isClosed ? (dec === 'adopted' ? 'closed adopted' : 'closed') : 'pending');
      const statusText = isOpen ? 'Vote en cours' : (isClosed ? (closedLabels[dec] || 'Terminé') : 'En attente');

      // Vote actions
      let voteActions = '';
      if (isLive && !isOpen && !isClosed) {
        voteActions = `<button class="btn btn-sm btn-primary btn-open-vote" data-motion-id="${m.id}">${icon('play', 'icon-sm icon-text')}Ouvrir</button>`;
      } else if (isLive && isOpen) {
        voteActions = `<button class="btn btn-sm btn-warning btn-close-vote" data-motion-id="${m.id}">${icon('square', 'icon-sm icon-text')}Terminer</button>`;
      }

      // Edit actions (only for pending resolutions)
      let editActions = '';
      if (canEdit && !isOpen && !isClosed) {
        editActions = `
          <button class="btn btn-sm btn-ghost btn-edit-motion" data-motion-id="${m.id}" title="Modifier">${icon('edit', 'icon-sm')}</button>
          <button class="btn btn-sm btn-ghost btn-delete-motion" data-motion-id="${m.id}" title="Supprimer">${icon('trash', 'icon-sm')}</button>
        `;
      }

      // Reorder buttons (only when not searching and can edit)
      let reorderBtns = '';
      if (canEdit && !searchTerm && !isOpen && !isClosed) {
        const globalIdx = O.motionsCache.findIndex(x => x.id === m.id);
        const canMoveUp = globalIdx > 0;
        const canMoveDown = globalIdx < totalCount - 1;
        reorderBtns = `
          <button class="btn btn-xs btn-ghost btn-move-up" data-motion-id="${m.id}" ${canMoveUp ? '' : 'disabled'} title="Monter">▲</button>
          <button class="btn btn-xs btn-ghost btn-move-down" data-motion-id="${m.id}" ${canMoveDown ? '' : 'disabled'} title="Descendre">▼</button>
        `;
      }

      const results = isClosed ? `
        <div style="display:flex;gap:1rem;font-size:0.85rem;margin-top:0.5rem;">
          <span style="color:var(--color-success)">${icon('check', 'icon-xs')} ${Shared.formatWeight(m.votes_for || 0)}</span>
          <span style="color:var(--color-danger)">${icon('x', 'icon-xs')} ${Shared.formatWeight(m.votes_against || 0)}</span>
          <span style="color:var(--color-text-muted)">${icon('minus', 'icon-xs')} ${Shared.formatWeight(m.votes_abstain || 0)}</span>
        </div>
      ` : '';

      return `
        <div class="resolution-section" data-motion-id="${m.id}">
          <div class="resolution-header">
            <div class="resolution-reorder">${reorderBtns}</div>
            <span class="resolution-chevron"><svg class="icon" aria-hidden="true"><use href="/assets/icons.svg#icon-chevron-right"></use></svg></span>
            <span style="font-weight:700;margin-right:0.5rem;">${i + 1}.</span>
            <span class="resolution-title">${escapeHtml(m.title)}</span>
            <span class="resolution-status ${statusClass}">${statusText}</span>
            <div class="resolution-header-actions" style="margin-left:auto;display:flex;gap:0.5rem;align-items:center;">
              ${voteActions}
              ${editActions}
            </div>
          </div>
          <div class="resolution-body">
            <div class="resolution-content">
              ${m.description ? escapeHtml(m.description) : '<em class="text-muted">Aucune description</em>'}
            </div>
            ${results}
          </div>
        </div>
      `;
    }).join('') || '<div class="text-center p-4 text-muted">Aucune résolution</div>');

    // Bind collapsible (only on chevron/title, not buttons)
    list.querySelectorAll('.resolution-header').forEach(header => {
      header.addEventListener('click', (e) => {
        if (e.target.closest('button')) return; // Don't toggle on button clicks
        header.closest('.resolution-section').classList.toggle('expanded');
      });
    });

    // Bind vote actions
    list.querySelectorAll('.btn-open-vote').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        openVote(btn.dataset.motionId);
      });
    });

    list.querySelectorAll('.btn-close-vote').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        closeVote(btn.dataset.motionId);
      });
    });

    // Bind edit button
    list.querySelectorAll('.btn-edit-motion').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        showEditResolutionModal(btn.dataset.motionId);
      });
    });

    // Bind delete button
    list.querySelectorAll('.btn-delete-motion').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const motionId = btn.dataset.motionId;
        const motion = O.motionsCache.find(m => String(m.id) === String(motionId));
        const title = motion ? escapeHtml(motion.title || '—') : 'cette résolution';
        const ok = await new Promise(resolve => {
          const modal = O.createModal({
            id: 'deleteMotionModal',
            title: 'Supprimer la résolution',
            onDismiss: () => resolve(false),
            content: `
              <h3 id="deleteMotionModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">${icon('trash-2', 'icon-sm icon-text')} Supprimer ?</h3>
              <p style="margin:0 0 1.5rem;">Résolution : <strong>${title}</strong></p>
              <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                <button class="btn btn-secondary" data-action="cancel">Annuler</button>
                <button class="btn btn-danger" data-action="confirm">${icon('trash-2', 'icon-sm icon-text')} Supprimer</button>
              </div>
            `
          });
          modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { O.closeModal(modal); resolve(false); });
          modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { O.closeModal(modal); resolve(true); });
        });
        if (!ok) return;
        try {
          await api('/api/v1/motion_delete.php', { motion_id: motionId, meeting_id: O.currentMeetingId });
          setNotif('success', 'Résolution supprimée');
          await loadResolutions();
          await O.fn.loadStatusChecklist();
        } catch (err) {
          setNotif('error', err.message);
        }
      });
    });

    // Bind reorder buttons
    list.querySelectorAll('.btn-move-up').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        moveResolution(btn.dataset.motionId, -1);
      });
    });

    list.querySelectorAll('.btn-move-down').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        moveResolution(btn.dataset.motionId, 1);
      });
    });
  }

  // Edit resolution modal
  function showEditResolutionModal(motionId) {
    const motion = O.motionsCache.find(m => m.id === motionId);
    if (!motion) return;

    const modal = document.createElement('div');
    modal.className = 'modal-backdrop';
    modal.style.cssText = 'position:fixed;inset:0;background:var(--color-backdrop);z-index:var(--z-modal-backdrop, 400);display:flex;align-items:center;justify-content:center;opacity:1;visibility:visible;';

    modal.innerHTML = `
      <div style="background:var(--color-surface);border-radius:12px;padding:1.5rem;max-width:600px;width:90%;max-height:80vh;overflow:auto;">
        <h3 style="margin:0 0 1rem;">Modifier la résolution</h3>
        <div class="form-group mb-3">
          <label class="form-label">Titre</label>
          <input type="text" class="form-input" id="editResolutionTitle" value="${escapeHtml(motion.title || '')}" maxlength="200">
        </div>
        <div class="form-group mb-3">
          <label class="form-label">Description / Texte complet</label>
          <textarea class="form-input" id="editResolutionDesc" rows="6">${escapeHtml(motion.description || '')}</textarea>
        </div>
        <div class="form-group mb-3">
          <label class="form-label">
            <input type="checkbox" id="editResolutionSecret" ${motion.secret ? 'checked' : ''}>
            Vote secret
          </label>
        </div>
        <div class="flex gap-2 justify-end">
          <button class="btn btn-secondary" id="btnCancelEdit">Annuler</button>
          <button class="btn btn-primary" id="btnSaveEdit">Enregistrer</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
    document.getElementById('btnCancelEdit').addEventListener('click', () => modal.remove());

    document.getElementById('btnSaveEdit').addEventListener('click', async () => {
      const title = document.getElementById('editResolutionTitle').value.trim();
      const description = document.getElementById('editResolutionDesc').value.trim();
      const secret = document.getElementById('editResolutionSecret').checked;

      if (!title) {
        setNotif('error', 'Titre requis');
        return;
      }

      const btn = document.getElementById('btnSaveEdit');
      Shared.btnLoading(btn, true);
      try {
        const { body } = await api('/api/v1/motions.php', {
          motion_id: motionId,
          agenda_id: motion.agenda_id,
          title,
          description,
          secret
        });

        if (body?.ok === true) {
          setNotif('success', 'Résolution mise à jour');
          modal.remove();
          await loadResolutions();
        } else {
          setNotif('error', getApiError(body, 'Erreur lors de la mise à jour'));
        }
      } catch (err) {
        setNotif('error', err.message);
      } finally {
        Shared.btnLoading(btn, false);
      }
    });

    // Focus on title
    document.getElementById('editResolutionTitle').focus();
  }

  // Move resolution up or down
  async function moveResolution(motionId, direction) {
    const idx = O.motionsCache.findIndex(m => m.id === motionId);
    if (idx < 0) return;

    const newIdx = idx + direction;
    if (newIdx < 0 || newIdx >= O.motionsCache.length) return;

    // Swap in local cache for immediate feedback
    const ids = O.motionsCache.map(m => m.id);
    [ids[idx], ids[newIdx]] = [ids[newIdx], ids[idx]];

    // Optimistic update
    [O.motionsCache[idx], O.motionsCache[newIdx]] = [O.motionsCache[newIdx], O.motionsCache[idx]];
    renderResolutions();

    // Save to server
    try {
      const { body } = await api('/api/v1/motion_reorder.php', {
        meeting_id: O.currentMeetingId,
        motion_ids: ids
      });

      if (body?.ok !== true) {
        // Revert on error
        loadResolutions();
        setNotif('error', getApiError(body, 'Erreur lors du réordonnancement'));
      }
    } catch (err) {
      loadResolutions();
      setNotif('error', err.message);
    }
  }

  async function createResolution() {
    const title = document.getElementById('newResolutionTitle').value.trim();
    const desc = document.getElementById('newResolutionDesc').value.trim();
    if (!title) {
      setNotif('error', 'Titre requis');
      return;
    }

    try {
      const { body } = await api('/api/v1/motion_create_simple.php', {
        meeting_id: O.currentMeetingId,
        title: title,
        description: desc || ''
      });

      if (body?.ok === true) {
        setNotif('success', 'Résolution créée');
        Shared.hide(document.getElementById('addResolutionForm'));
        document.getElementById('newResolutionTitle').value = '';
        document.getElementById('newResolutionDesc').value = '';
        await loadResolutions();
        await O.fn.loadStatusChecklist();
        O.fn.checkLaunchReady();
      } else {
        setNotif('error', getApiError(body, 'Erreur lors de la création'));
      }
    } catch (err) {
      setNotif('error', err.message);
    }
  }

  // =========================================================================
  // TAB: VOTE EN DIRECT
  // =========================================================================

  async function loadVoteTab() {
    if (!O.currentOpenMotion) {
      Shared.show(document.getElementById('noActiveVote'), 'block');
      Shared.hide(document.getElementById('activeVotePanel'));
      renderQuickOpenList();
      return;
    }

    Shared.hide(document.getElementById('noActiveVote'));
    Shared.show(document.getElementById('activeVotePanel'), 'block');
    document.getElementById('activeVoteTitle').textContent = O.currentOpenMotion.title;

    await loadBallots(O.currentOpenMotion.id);
    renderManualVoteList();
  }

  // Render quick open buttons in the Vote tab when no vote is active
  function renderQuickOpenList() {
    const list = document.getElementById('quickOpenMotionList');
    if (!list) return;

    const isLive = O.currentMeetingStatus === 'live';
    const openableMotions = O.motionsCache.filter(m => !m.opened_at && !m.closed_at);

    if (!isLive || openableMotions.length === 0) {
      list.innerHTML = isLive
        ? '<p class="text-muted text-sm">Aucune résolution en attente</p>'
        : '<p class="text-muted text-sm">La séance doit être en mode "live" pour ouvrir un vote</p>';
      return;
    }

    list.innerHTML = openableMotions.slice(0, 5).map((m, i) => `
      <button class="btn btn-primary btn-quick-open" data-motion-id="${m.id}">
        ${icon('play', 'icon-sm icon-text')}${i + 1}. ${escapeHtml(m.title.length > 30 ? m.title.substring(0, 30) + '...' : m.title)}
      </button>
    `).join('');

    if (openableMotions.length > 5) {
      list.innerHTML += `<span class="text-muted text-sm">+ ${openableMotions.length - 5} autres</span>`;
    }

    // Bind quick open buttons
    list.querySelectorAll('.btn-quick-open').forEach(btn => {
      btn.addEventListener('click', () => openVote(btn.dataset.motionId));
    });
  }

  async function loadBallots(motionId) {
    try {
      const { body } = await api(`/api/v1/ballots.php?motion_id=${motionId}`);
      const ballots = body?.data?.items || [];
      O.ballotsCache = {};
      O.ballotSourceCache = {};
      let forCount = 0, againstCount = 0, abstainCount = 0;
      ballots.forEach(b => {
        O.ballotsCache[b.member_id] = b.value;
        O.ballotSourceCache[b.member_id] = b.source || 'tablet';
        if (b.value === 'for') forCount++;
        else if (b.value === 'against') againstCount++;
        else if (b.value === 'abstain') abstainCount++;
      });
      O.setText('liveVoteFor', forCount);
      O.setText('liveVoteAgainst', againstCount);
      O.setText('liveVoteAbstain', abstainCount);
    } catch (err) {
      setNotif('error', 'Erreur chargement bulletins');
    }
  }

  function renderManualVoteList() {
    // P3-5: Filtrage par recherche
    const searchInput = document.getElementById('manualVoteSearch');
    const searchTerm = (searchInput ? searchInput.value : '').toLowerCase();
    let voters = O.attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');
    if (searchTerm) {
      voters = voters.filter(v => (v.full_name || '').toLowerCase().includes(searchTerm));
    }
    const list = document.getElementById('manualVoteList');

    // Allow vote correction - buttons are never disabled, but show current vote
    list.innerHTML = voters.map(v => {
      const vote = O.ballotsCache[v.member_id];
      const hasVoted = !!vote;
      const isManual = O.ballotSourceCache[v.member_id] === 'manual';
      const cancelBtn = (hasVoted && isManual)
        ? `<button class="mode-btn btn-cancel-ballot" data-member-id="${v.member_id}" title="Annuler ce vote manuel" style="color:var(--color-danger);margin-left:0.25rem;">${icon('trash-2', 'icon-sm')}</button>`
        : '';
      return `
        <div class="attendance-card ${hasVoted ? 'present' : ''}" data-member-id="${v.member_id}">
          <span class="attendance-name">${escapeHtml(v.full_name || '—')}</span>
          <div class="attendance-mode-btns">
            <button class="mode-btn for ${vote === 'for' ? 'active' : ''}" data-vote="for" title="Pour">${icon('check', 'icon-sm')}</button>
            <button class="mode-btn against ${vote === 'against' ? 'active' : ''}" data-vote="against" title="Contre">${icon('x', 'icon-sm')}</button>
            <button class="mode-btn abstain ${vote === 'abstain' ? 'active' : ''}" data-vote="abstain" title="Abstention">${icon('minus', 'icon-sm')}</button>
            ${cancelBtn}
          </div>
        </div>
      `;
    }).join('') || '<div class="text-center p-4 text-muted">Aucun votant</div>';

    // Bind all buttons (allow vote correction)
    list.querySelectorAll('.mode-btn').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        const card = e.target.closest('.attendance-card');
        const memberId = card.dataset.memberId;
        const newVote = btn.dataset.vote;
        const currentVote = O.ballotsCache[memberId];

        // Skip if clicking same vote
        if (currentVote === newVote) return;

        // Confirm if correcting existing vote via modal
        const _vl = { for: 'Pour', against: 'Contre', abstain: 'Abstention' };
        if (currentVote) {
          const ok = await new Promise(resolve => {
            const memberName = card.querySelector('.attendance-name')?.textContent || '—';
            const modal = O.createModal({
              id: 'correctVoteModal',
              title: 'Modifier le vote',
              onDismiss: () => resolve(false),
              content: `
                <h3 id="correctVoteModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">Modifier le vote ?</h3>
                <p style="margin:0 0 0.5rem;">Membre : <strong>${escapeHtml(memberName)}</strong></p>
                <p style="margin:0 0 1.5rem;">De <strong>${_vl[currentVote] || currentVote}</strong> vers <strong>${_vl[newVote] || newVote}</strong></p>
                <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                  <button class="btn btn-secondary" data-action="cancel">Annuler</button>
                  <button class="btn btn-primary" data-action="confirm">Modifier</button>
                </div>
              `
            });
            modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { O.closeModal(modal); resolve(false); });
            modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { O.closeModal(modal); resolve(true); });
          });
          if (!ok) return;
        }

        await castManualVote(memberId, newVote);
      });
    });

    // P3-4: Cancel manual vote buttons
    list.querySelectorAll('.btn-cancel-ballot').forEach(btn => {
      btn.addEventListener('click', async () => {
        const memberId = btn.dataset.memberId;
        const card = btn.closest('.attendance-card');
        const memberName = card?.querySelector('.attendance-name')?.textContent || '—';
        const voteLabels = { for: 'Pour', against: 'Contre', abstain: 'Abstention' };
        const currentVote = O.ballotsCache[memberId];

        const confirmed = await new Promise(resolve => {
          const modal = O.createModal({
            id: 'cancelBallotModal',
            title: 'Annuler le vote manuel',
            onDismiss: () => resolve(null),
            content: `
              <h3 id="cancelBallotModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">Annuler ce vote ?</h3>
              <p>Membre : <strong>${escapeHtml(memberName)}</strong></p>
              <p>Vote actuel : <strong>${voteLabels[currentVote] || currentVote}</strong></p>
              <div class="form-group" style="margin-top:1rem;">
                <label class="form-label">Justification <span class="text-danger">*</span></label>
                <input class="form-input" type="text" id="cancelBallotReason" placeholder="Raison de l'annulation" required>
              </div>
              <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1rem;">
                <button class="btn btn-secondary" data-action="cancel">Annuler</button>
                <button class="btn btn-danger" data-action="confirm" id="btnConfirmCancel" disabled>Supprimer le vote</button>
              </div>
            `
          });
          const reasonInput = modal.querySelector('#cancelBallotReason');
          const confirmBtn = modal.querySelector('#btnConfirmCancel');
          reasonInput.addEventListener('input', () => { confirmBtn.disabled = reasonInput.value.trim().length < 3; });
          modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { O.closeModal(modal); resolve(null); });
          confirmBtn.addEventListener('click', () => { O.closeModal(modal); resolve(reasonInput.value.trim()); });
          setTimeout(() => reasonInput.focus(), 60);
        });

        if (!confirmed) return;

        btn.disabled = true;
        try {
          const { body } = await api('/api/v1/ballots_cancel.php', {
            motion_id: O.currentOpenMotion.id,
            member_id: memberId,
            reason: confirmed
          });
          if (body?.ok) {
            delete O.ballotsCache[memberId];
            delete O.ballotSourceCache[memberId];
            await loadBallots(O.currentOpenMotion.id);
            renderManualVoteList();
            setNotif('success', 'Vote annulé');
          } else {
            setNotif('error', body?.error_label || body?.error || 'Erreur lors de l\'annulation');
          }
        } catch (err) {
          setNotif('error', err.message);
        }
        btn.disabled = false;
      });
    });
  }

  async function castManualVote(memberId, vote) {
    if (!O.currentOpenMotion) return;
    if (!Utils.isValidUUID(memberId)) { setNotif('error', 'ID membre invalide'); return; }

    // Validate vote type
    if (!VALID_VOTE_TYPES.includes(vote)) {
      setNotif('error', `Type de vote invalide: ${vote}`);
      return;
    }

    // P3-3: Lire la justification depuis le champ éditable
    const justifInput = document.getElementById('manualVoteJustification');
    const justification = (justifInput ? justifInput.value.trim() : '') || 'Vote opérateur manuel';

    try {
      const { body } = await api('/api/v1/manual_vote.php', {
        meeting_id: O.currentMeetingId,
        motion_id: O.currentOpenMotion.id,
        member_id: memberId,
        vote: vote,
        justification: justification
      });

      if (body?.ok === true) {
        O.ballotsCache[memberId] = vote;
        await loadBallots(O.currentOpenMotion.id);
        renderManualVoteList();
        setNotif('success', 'Vote enregistré');
      } else {
        setNotif('error', getApiError(body, 'Erreur lors du vote'));
        // P3-6: Refresh auto pour resynchroniser l'état après erreur
        if (O.currentOpenMotion) await loadBallots(O.currentOpenMotion.id);
        renderManualVoteList();
      }
    } catch (err) {
      setNotif('error', err.message);
      // P3-6: Refresh auto pour resynchroniser après erreur réseau
      if (O.currentOpenMotion) await loadBallots(O.currentOpenMotion.id);
      renderManualVoteList();
    }
  }

  /**
   * Apply unanimity vote - set all present/remote voters to the same vote
   * @param {'for'|'against'|'abstain'} voteType - The vote type to apply to all voters
   */
  async function applyUnanimity(voteType) {
    if (!O.currentOpenMotion) {
      setNotif('error', 'Aucun vote en cours');
      return;
    }

    const voteLabels = { for: 'Pour', against: 'Contre', abstain: 'Abstention' };
    const voteColors = { for: 'var(--color-success)', against: 'var(--color-danger)', abstain: 'var(--color-text-muted)' };
    const voters = O.attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote');

    if (voters.length === 0) {
      setNotif('error', 'Aucun votant présent');
      return;
    }

    // P3-2: Modale de confirmation au lieu de confirm()
    const alreadyVoted = voters.filter(v => O.ballotsCache[v.member_id]).length;
    const motionTitle = O.currentOpenMotion ? escapeHtml(O.currentOpenMotion.title || '—') : '—';

    const confirmed = await new Promise(resolve => {
      const modal = O.createModal({
        id: 'unanimityConfirmModal',
        title: 'Confirmer le vote unanime',
        onDismiss: () => resolve(false),
        content: `
          <h3 id="unanimityConfirmModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">${icon('alert-triangle', 'icon-sm icon-text')} Vote unanime</h3>
          <p style="margin:0 0 0.5rem;">Résolution : <strong>${motionTitle}</strong></p>
          <p style="margin:0 0 0.5rem;">Vote : <strong style="color:${voteColors[voteType]}">${voteLabels[voteType]}</strong> pour <strong>${voters.length}</strong> votant(s)</p>
          ${alreadyVoted > 0 ? `<p style="margin:0 0 0.5rem;color:var(--color-warning);font-size:0.875rem;">${icon('alert-triangle', 'icon-sm icon-text')} ${alreadyVoted} votant(s) ont déjà voté — leur vote existant sera conservé.</p>` : ''}
          <p style="margin:0 0 1.5rem;color:var(--color-text-muted);font-size:0.875rem;">Cette action enregistrera un vote manuel pour chaque votant présent ou à distance.</p>
          <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-primary" data-action="confirm">Confirmer (${voters.length} votes)</button>
          </div>
        `
      });
      modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { O.closeModal(modal); resolve(false); });
      modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { O.closeModal(modal); resolve(true); });
    });
    if (!confirmed) return;

    let successCount = 0;
    let errorCount = 0;

    // Show loading state with progress counter
    const btns = ['btnUnanimityFor', 'btnUnanimityAgainst', 'btnUnanimityAbstain']
      .map(id => document.getElementById(id))
      .filter(Boolean);
    btns.forEach(btn => {
      btn.disabled = true;
      btn.dataset.origHtml = btn.innerHTML;
      btn.innerHTML = '<span class="spinner spinner-sm"></span> 0/' + voters.length + ' votes…';
    });

    function updateProgress() {
      var done = successCount + errorCount;
      btns.forEach(btn => {
        btn.innerHTML = '<span class="spinner spinner-sm"></span> ' + done + '/' + voters.length + ' votes…';
      });
    }

    try {
      // Process votes in parallel batches for speed
      const batchSize = 5;
      for (let i = 0; i < voters.length; i += batchSize) {
        const batch = voters.slice(i, i + batchSize);
        const results = await Promise.allSettled(
          batch.map(voter =>
            api('/api/v1/manual_vote.php', {
              meeting_id: O.currentMeetingId,
              motion_id: O.currentOpenMotion.id,
              member_id: voter.member_id,
              vote: voteType,
              justification: `Unanimité opérateur: ${voteLabels[voteType]}`
            })
          )
        );

        results.forEach((result, idx) => {
          if (result.status === 'fulfilled' && result.value.body?.ok) {
            successCount++;
            O.ballotsCache[batch[idx].member_id] = voteType;
          } else {
            errorCount++;
          }
        });
        updateProgress();
      }

      // Refresh display
      await loadBallots(O.currentOpenMotion.id);
      renderManualVoteList();

      if (errorCount === 0) {
        setNotif('success', `Unanimité "${voteLabels[voteType]}" appliquée (${successCount} votes)`);
      } else {
        setNotif('warning', `${successCount} votes enregistrés, ${errorCount} erreur(s)`);
      }
    } catch (err) {
      setNotif('error', 'Erreur: ' + err.message);
    } finally {
      btns.forEach(btn => {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.origHtml || btn.innerHTML;
      });
    }
  }

  async function openVote(motionId) {
    if (!Utils.isValidUUID(motionId)) { setNotif('error', 'ID résolution invalide'); return; }
    // P3-1: Confirmation modale avant ouverture
    const motion = O.motionsCache.find(m => String(m.id) === String(motionId));
    const motionTitle = motion ? escapeHtml(motion.title || '—') : 'cette résolution';

    const confirmed = await new Promise(resolve => {
      const modal = O.createModal({
        id: 'openVoteConfirmModal',
        title: 'Confirmer l\'ouverture du vote',
        onDismiss: () => resolve(false),
        content: `
          <h3 id="openVoteConfirmModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">${icon('alert-triangle', 'icon-sm icon-text')} Ouvrir le vote ?</h3>
          <p style="margin:0 0 0.5rem;">Résolution : <strong>${motionTitle}</strong></p>
          <p style="margin:0 0 1.5rem;color:var(--color-text-muted);font-size:0.875rem;">Le vote sera immédiatement accessible à tous les votants. Un seul vote peut être ouvert à la fois.</p>
          <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-primary" data-action="confirm">${icon('play', 'icon-sm icon-text')} Ouvrir le vote</button>
          </div>
        `
      });
      modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { O.closeModal(modal); resolve(false); });
      modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { O.closeModal(modal); resolve(true); });
    });
    if (!confirmed) return;

    // Disable ALL open-vote buttons and quick-open buttons to prevent double-click
    const openBtns = document.querySelectorAll('.btn-open-vote, .btn-quick-open');
    openBtns.forEach(btn => {
      btn.disabled = true;
      btn.dataset.origHtml = btn.dataset.origHtml || btn.innerHTML;
      btn.innerHTML = '<span class="spinner spinner-sm"></span> Ouverture…';
    });

    try {
      const openResult = await api('/api/v1/motions_open.php', { meeting_id: O.currentMeetingId, motion_id: motionId });

      if (!openResult.body?.ok) {
        const errorMsg = getApiError(openResult.body, 'Erreur ouverture vote');
        setNotif('error', errorMsg);
        await loadResolutions();
        return;
      }

      setNotif('success', 'Vote ouvert');
      O.announce('Vote ouvert.');

      await loadResolutions();

      if (O.currentOpenMotion) await loadBallots(O.currentOpenMotion.id);

      if (O.currentMode === 'exec') {
        O.fn.refreshExecView();
      } else if (O.currentMeetingStatus === 'live') {
        O.fn.setMode('exec');
      } else {
        O.fn.switchTab('vote');
        await loadVoteTab();
      }
    } catch (err) {
      setNotif('error', err.message);
      await loadResolutions();
    } finally {
      // Restore any surviving buttons (DOM may have been re-rendered by loadResolutions)
      openBtns.forEach(btn => {
        if (btn.isConnected && btn.dataset.origHtml) {
          btn.disabled = false;
          btn.innerHTML = btn.dataset.origHtml;
        }
      });
    }
  }

  async function closeVote(motionId) {
    if (!Utils.isValidUUID(motionId)) { setNotif('error', 'ID résolution invalide'); return; }
    // P2-4 / P3: Modale de confirmation avec récapitulatif au lieu de confirm()
    const motion = O.motionsCache.find(m => String(m.id) === String(motionId));
    const motionTitle = motion ? escapeHtml(motion.title || '—') : 'ce vote';
    const vFor = parseInt(document.getElementById('liveVoteFor')?.textContent || '0', 10);
    const vAgainst = parseInt(document.getElementById('liveVoteAgainst')?.textContent || '0', 10);
    const vAbstain = parseInt(document.getElementById('liveVoteAbstain')?.textContent || '0', 10);
    const vTotal = vFor + vAgainst + vAbstain;

    const confirmed = await new Promise(resolve => {
      const modal = O.createModal({
        id: 'closeVoteConfirmModal',
        title: 'Terminer le scrutin',
        onDismiss: () => resolve(false),
        content: `
          <h3 id="closeVoteConfirmModal-title" style="margin:0 0 0.75rem;font-size:1.125rem;">Terminer le scrutin ?</h3>
          <p style="margin:0 0 0.75rem;">Résolution : <strong>${motionTitle}</strong></p>
          <div style="display:flex;gap:1rem;margin:0 0 1rem;font-size:0.9375rem;">
            <span style="color:var(--color-success);">${icon('check', 'icon-sm icon-text')} ${vFor}</span>
            <span style="color:var(--color-danger);">${icon('x', 'icon-sm icon-text')} ${vAgainst}</span>
            <span style="color:var(--color-text-muted);">&#9675; ${vAbstain}</span>
            <span class="text-muted">&mdash; ${vTotal} vote(s)</span>
          </div>
          <p style="margin:0 0 1.5rem;color:var(--color-warning);font-size:0.875rem;">${icon('alert-triangle', 'icon-sm icon-text')} Les résultats seront figés définitivement. Plus aucun vote ne sera accepté.</p>
          <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-warning" data-action="confirm">${icon('check-circle', 'icon-sm icon-text')} Terminer le scrutin</button>
          </div>
        `
      });
      modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { O.closeModal(modal); resolve(false); });
      modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { O.closeModal(modal); resolve(true); });
    });
    if (!confirmed) return;

    // Disable all close-vote buttons during the operation and show spinner
    const closeBtns = document.querySelectorAll('.btn-close-vote, #btnCloseVote, #execBtnCloseVote');
    const closeOrigHtml = new Map();
    closeBtns.forEach(b => {
      b.disabled = true;
      closeOrigHtml.set(b, b.innerHTML);
      b.innerHTML = '<span class="spinner spinner-sm"></span> Clôture…';
    });

    try {
      const closeResult = await api('/api/v1/motions_close.php', { meeting_id: O.currentMeetingId, motion_id: motionId });
      const closeData = closeResult.body?.data || {};
      const eligibleCount = closeData.eligible_count || 0;
      const votesCast = closeData.votes_cast || 0;
      if (eligibleCount > 0 && votesCast < eligibleCount) {
        const missing = eligibleCount - votesCast;
        setNotif('warning', `Vote clôturé — ${missing} votant${missing > 1 ? 's' : ''} n'${missing > 1 ? 'ont' : 'a'} pas voté (${votesCast}/${eligibleCount})`);
      } else {
        setNotif('success', 'Vote clôturé');
      }
      O.currentOpenMotion = null;
      O.ballotsCache = {};
      await loadResolutions();
      await loadVoteTab();
      if (O.currentMode === 'exec') O.fn.refreshExecView();
      O.announce('Vote clôturé.');

      // P2-5: Proclamation explicite des résultats (uses VoteEngine decision)
      const closedMotion = O.motionsCache.find(m => String(m.id) === String(motionId));
      if (closedMotion) {
        const rFor = parseFloat(closedMotion.votes_for) || 0;
        const rAgainst = parseFloat(closedMotion.votes_against) || 0;
        const rAbstain = parseFloat(closedMotion.votes_abstain) || 0;
        const rNsp = parseFloat(closedMotion.votes_nsp) || 0;
        const cFor = Shared.formatWeight(rFor);
        const cAgainst = Shared.formatWeight(rAgainst);
        const cAbstain = Shared.formatWeight(rAbstain);
        const cNsp = Shared.formatWeight(rNsp);
        const cTotal = Shared.formatWeight(rFor + rAgainst + rAbstain + rNsp);

        // Use authoritative decision from VoteEngine (via close response or motion record)
        const decision = closeData.results?.decision || closedMotion.decision || closedMotion.result || '';
        const reason = closeData.results?.reason || closedMotion.decision_reason || '';
        const decisionLabels = { adopted: 'ADOPTÉE', rejected: 'REJETÉE', no_quorum: 'QUORUM NON ATTEINT', no_votes: 'AUCUN VOTE', no_policy: 'SANS POLITIQUE' };
        const resultText = decisionLabels[decision] || decision.toUpperCase() || '—';
        const isAdopted = decision === 'adopted';
        const resultColor = isAdopted ? 'var(--color-success)' : 'var(--color-danger)';
        const resultIcon = isAdopted ? 'check-circle' : 'x-circle';

        const proclamModal = O.createModal({
          id: 'proclamationModal',
          title: 'Résultat du vote',
          maxWidth: '520px',
          content: `
            <div style="text-align:center;padding:1rem 0;">
              <i data-lucide="${resultIcon}" style="width:64px;height:64px;color:${resultColor};margin-bottom:1rem;"></i>
              <h2 id="proclamationModal-title" style="font-size:1.5rem;margin:0 0 0.5rem;">${escapeHtml(closedMotion.title)}</h2>
              <p style="font-size:2rem;font-weight:700;color:${resultColor};margin:0.5rem 0;" aria-live="assertive">
                ${resultText}
              </p>
              ${reason ? `<p style="color:var(--color-text-secondary);font-size:0.95rem;margin:0 0 1rem;">${escapeHtml(reason)}</p>` : ''}
              <div style="display:flex;justify-content:center;gap:2rem;margin:1.5rem 0;font-size:1.1rem;">
                <span><strong style="color:var(--color-success)">${cFor}</strong> Pour</span>
                <span><strong style="color:var(--color-danger)">${cAgainst}</strong> Contre</span>
                <span><strong style="color:var(--color-text-muted)">${cAbstain}</strong> Abstention</span>
                ${rNsp > 0 ? `<span><strong style="color:var(--color-text-muted)">${cNsp}</strong> NSP</span>` : ''}
              </div>
              <p style="color:var(--color-text-muted);font-size:0.9rem;">${cTotal} vote${cTotal !== '1' ? 's' : ''} exprimé${cTotal !== '1' ? 's' : ''}</p>
              <button class="btn btn-primary" data-action="close-proclamation" style="margin-top:1rem;">Fermer</button>
            </div>
          `
        });

        if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [proclamModal] });
        proclamModal.querySelector('[data-action="close-proclamation"]').addEventListener('click', () => O.closeModal(proclamModal));
      }
    } catch (err) {
      setNotif('error', err.message);
      await loadResolutions();
    } finally {
      // Restore any surviving buttons (DOM may have been re-rendered)
      closeBtns.forEach(b => {
        if (b.isConnected) {
          b.disabled = false;
          const orig = closeOrigHtml.get(b);
          if (orig) b.innerHTML = orig;
        }
      });
    }
  }

  // =========================================================================
  // TAB: RÉSULTATS
  // =========================================================================

  async function loadResults() {
    const closed = O.motionsCache.filter(m => m.closed_at);
    const adopted = closed.filter(m => (m.decision || m.result) === 'adopted').length;
    const rejected = closed.filter(m => (m.decision || m.result) === 'rejected').length;

    O.setText('resultAdopted', adopted);
    O.setText('resultRejected', rejected);
    O.setText('resultTotal', O.motionsCache.length);

    const decisionLabels = { adopted: 'Adoptée', rejected: 'Rejetée', no_quorum: 'Sans quorum', no_votes: 'Aucun vote', no_policy: 'Sans politique' };
    const decisionColors = { adopted: 'var(--color-success)', rejected: 'var(--color-danger)' };

    const list = document.getElementById('resultsDetailList');
    list.innerHTML = O.motionsCache.map((m, i) => {
      const isClosed = !!m.closed_at;
      const vFor = parseFloat(m.votes_for) || 0;
      const vAgainst = parseFloat(m.votes_against) || 0;
      const vAbstain = parseFloat(m.votes_abstain) || 0;
      const total = vFor + vAgainst + vAbstain;
      const pct = total > 0 ? Math.round((vFor / total) * 100) : 0;
      const dec = m.decision || m.result || '';
      const status = !isClosed ? 'En attente' : (decisionLabels[dec] || dec || 'Rejetée');
      const statusColor = !isClosed ? 'var(--color-text-muted)' : (decisionColors[dec] || 'var(--color-danger)');

      return `
        <div class="settings-section" style="margin-bottom:1rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong>${i + 1}. ${escapeHtml(m.title)}</strong>
            <span style="color:${statusColor};font-weight:600;">${status}</span>
          </div>
          ${isClosed ? `
            <div style="display:flex;gap:2rem;margin-top:1rem;font-size:1.1rem;">
              <span style="color:var(--color-success)">${icon('check', 'icon-sm')} ${Shared.formatWeight(vFor)}</span>
              <span style="color:var(--color-danger)">${icon('x', 'icon-sm')} ${Shared.formatWeight(vAgainst)}</span>
              <span style="color:var(--color-text-muted)">${icon('minus', 'icon-sm')} ${Shared.formatWeight(vAbstain)}</span>
              <span style="margin-left:auto;">${pct}% pour</span>
            </div>
          ` : ''}
        </div>
      `;
    }).join('') || '<div class="text-center p-4 text-muted">Aucune résolution</div>';

    // Export links (preview=1 generates a draft if meeting not validated)
    document.getElementById('exportPV').href = `/api/v1/meeting_generate_report_pdf.php?meeting_id=${O.currentMeetingId}&preview=1`;
    document.getElementById('exportAttendance').href = `/api/v1/export_attendance_csv.php?meeting_id=${O.currentMeetingId}`;
    document.getElementById('exportVotes').href = `/api/v1/export_votes_csv.php?meeting_id=${O.currentMeetingId}`;

    // Update close session section
    updateCloseSessionStatus();
  }

  /**
   * Compute closure readiness state.
   * Returns { total, closedCount, openCount, pendingCount, allDone, hasOpenVote,
   *           adopted, rejected, presentCount, totalMembers, durationStr }
   */
  function getCloseSessionState() {
    const total = O.motionsCache.length;
    const closedMotions = O.motionsCache.filter(m => m.closed_at);
    const closedCount = closedMotions.length;
    const openCount = O.motionsCache.filter(m => m.opened_at && !m.closed_at).length;
    const pendingCount = total - closedCount - openCount;
    const allDone = total > 0 && closedCount === total;
    const hasOpenVote = openCount > 0;
    const adopted = closedMotions.filter(m => (m.decision || m.result) === 'adopted').length;
    const rejected = closedMotions.filter(m => (m.decision || m.result) === 'rejected').length;
    const presentCount = O.attendanceCache.filter(a => a.mode === 'present' || a.mode === 'remote').length;
    const totalMembers = O.attendanceCache.length;

    // Session duration
    const startedAt = O.currentMeeting?.opened_at || O.currentMeeting?.started_at;
    let durationStr = '';
    if (startedAt) {
      const elapsed = Math.floor((Date.now() - new Date(startedAt).getTime()) / 1000);
      const hours = Math.floor(elapsed / 3600);
      const minutes = Math.floor((elapsed % 3600) / 60);
      durationStr = hours > 0 ? `${hours}h${String(minutes).padStart(2, '0')}` : `${minutes} min`;
    }

    return { total, closedCount, openCount, pendingCount, allDone, hasOpenVote,
             adopted, rejected, presentCount, totalMembers, durationStr };
  }

  function updateCloseSessionStatus() {
    const section = document.getElementById('closeSessionSection');
    const checksDiv = document.getElementById('closeSessionChecks');
    const statusDiv = document.getElementById('closeSessionStatus');
    const btnClose = document.getElementById('btnCloseSession');
    if (!section || !statusDiv || !btnClose) return;

    // Only show for live sessions
    if (O.currentMeetingStatus !== 'live') {
      Shared.hide(section);
      return;
    }
    Shared.show(section, 'block');

    const s = getCloseSessionState();
    let canClose = true;

    // Security checks display
    if (checksDiv) {
      let checksHtml = '';
      if (s.hasOpenVote) {
        checksHtml += `<div class="close-check close-check-warn">${icon('alert-triangle', 'icon-sm')} Un vote est en cours — clôturez-le d'abord</div>`;
        canClose = false;
      } else {
        checksHtml += `<div class="close-check close-check-ok">${icon('check-circle', 'icon-sm')} Aucun vote en cours</div>`;
      }

      if (s.pendingCount > 0) {
        checksHtml += `<div class="close-check close-check-warn">${icon('alert-triangle', 'icon-sm')} ${s.pendingCount} résolution(s) non votée(s)</div>`;
      } else if (s.total > 0) {
        checksHtml += `<div class="close-check close-check-ok">${icon('check-circle', 'icon-sm')} ${s.closedCount}/${s.total} résolution(s) votée(s)</div>`;
      }

      if (s.presentCount > 0) {
        checksHtml += `<div class="close-check close-check-ok">${icon('users', 'icon-sm')} ${s.presentCount} participant(s) / ${s.totalMembers} inscrit(s)</div>`;
      } else {
        checksHtml += `<div class="close-check close-check-warn">${icon('alert-triangle', 'icon-sm')} Aucun participant présent</div>`;
      }

      checksDiv.innerHTML = checksHtml;
    }

    // Status summary
    let statusHtml = '';
    if (s.allDone) {
      statusHtml = `<div class="alert alert-success mb-2">${icon('check-circle', 'icon-sm icon-text')} Tous les votes sont terminés. Prêt à clôturer.</div>`;
    } else if (s.closedCount > 0) {
      statusHtml = `<div class="alert alert-info mb-2">${icon('info', 'icon-sm icon-text')} ${s.closedCount}/${s.total} résolution(s) traitée(s).</div>`;
    }

    statusDiv.innerHTML = statusHtml;
    btnClose.disabled = !canClose;
  }

  /**
   * Update the exec-mode close session banner.
   * Shows when meeting is live and no vote is currently open.
   */
  function updateExecCloseSession() {
    const banner = document.getElementById('execCloseBanner');
    if (!banner) return;

    if (O.currentMeetingStatus !== 'live') {
      Shared.hide(banner);
      return;
    }

    const s = getCloseSessionState();

    // Hide while a vote is actively open
    if (s.hasOpenVote) {
      Shared.hide(banner);
      return;
    }

    Shared.show(banner, 'flex');

    const titleEl = document.getElementById('execCloseTitle');
    const summaryEl = document.getElementById('execCloseSummary');

    if (s.allDone) {
      if (titleEl) titleEl.textContent = 'Tous les votes sont terminés';
      if (summaryEl) summaryEl.textContent = `${s.closedCount} résolution(s) votée(s) — prêt à clôturer.`;
      banner.className = 'exec-close-banner exec-close-banner-ready';
    } else if (s.closedCount > 0) {
      if (titleEl) titleEl.textContent = 'Clôture possible';
      if (summaryEl) summaryEl.textContent = `${s.closedCount}/${s.total} résolution(s) votée(s), ${s.pendingCount} en attente.`;
      banner.className = 'exec-close-banner exec-close-banner-warn';
    } else {
      if (titleEl) titleEl.textContent = 'Aucun vote effectué';
      if (summaryEl) summaryEl.textContent = `${s.total} résolution(s) en attente.`;
      banner.className = 'exec-close-banner exec-close-banner-warn';
    }
  }

  async function closeSession() {
    // Hard block: cannot close with an open vote
    const openVotes = O.motionsCache.filter(m => m.opened_at && !m.closed_at);
    if (openVotes.length > 0) {
      setNotif('error', 'Impossible de clôturer : un vote est encore ouvert.');
      return;
    }

    const s = getCloseSessionState();
    const meetingTitle = O.currentMeeting ? escapeHtml(O.currentMeeting.title || '') : '';

    // Build security checks HTML
    let checksHtml = '';
    checksHtml += `<div class="close-check close-check-ok">${icon('check-circle', 'icon-sm')} Aucun vote en cours</div>`;

    if (s.pendingCount > 0) {
      checksHtml += `<div class="close-check close-check-warn">${icon('alert-triangle', 'icon-sm')} ${s.pendingCount} résolution(s) non votée(s)</div>`;
    } else if (s.total > 0) {
      checksHtml += `<div class="close-check close-check-ok">${icon('check-circle', 'icon-sm')} ${s.closedCount}/${s.total} résolution(s) votée(s)</div>`;
    }

    if (s.presentCount > 0) {
      checksHtml += `<div class="close-check close-check-ok">${icon('users', 'icon-sm')} ${s.presentCount} participant(s) sur ${s.totalMembers}</div>`;
    } else {
      checksHtml += `<div class="close-check close-check-warn">${icon('alert-triangle', 'icon-sm')} Aucun participant présent</div>`;
    }

    // Build results summary
    let summaryParts = [];
    if (s.adopted > 0) summaryParts.push(`<span style="color:var(--color-success)">${s.adopted} adoptée(s)</span>`);
    if (s.rejected > 0) summaryParts.push(`<span style="color:var(--color-danger)">${s.rejected} rejetée(s)</span>`);
    const summaryLine = summaryParts.length > 0 ? ` — ${summaryParts.join(', ')}` : '';

    const confirmed = await new Promise(resolve => {
      const modal = O.createModal({
        id: 'closeSessionConfirmModal',
        title: 'Clôturer la séance',
        maxWidth: '540px',
        onDismiss: () => resolve(false),
        content: `
          <h3 id="closeSessionConfirmModal-title" style="margin:0 0 1rem;font-size:1.125rem;display:flex;align-items:center;gap:0.5rem;">
            ${icon('square', 'icon-sm')} Clôturer la séance
          </h3>
          ${meetingTitle ? `<p style="margin:0 0 1rem;font-weight:600;font-size:1rem;">${meetingTitle}</p>` : ''}

          <div style="background:var(--color-bg-subtle);border:1px solid var(--color-border);border-radius:8px;padding:1rem;margin-bottom:1rem;">
            <div style="display:flex;flex-direction:column;gap:0.5rem;font-size:0.9rem;">
              ${checksHtml}
            </div>
          </div>

          <div style="background:var(--color-bg-subtle);border:1px solid var(--color-border);border-radius:8px;padding:1rem;margin-bottom:1rem;font-size:0.875rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.375rem;">
              ${icon('bar-chart', 'icon-sm')} <strong>Bilan :</strong> ${s.closedCount}/${s.total} résolution(s) traitée(s)${summaryLine}
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.375rem;">
              ${icon('users', 'icon-sm')} ${s.presentCount} présent(s) / ${s.totalMembers} inscrit(s)
            </div>
            ${s.durationStr ? `<div style="display:flex;align-items:center;gap:0.5rem;">${icon('clock', 'icon-sm')} Durée de la séance : ${s.durationStr}</div>` : ''}
          </div>

          <div style="display:flex;gap:0.75rem;padding:0.875rem 1rem;background:var(--color-danger-subtle);border:1px solid var(--color-danger);border-radius:8px;margin-bottom:1.5rem;font-size:0.85rem;color:var(--color-danger-text, var(--color-text));">
            ${icon('alert-triangle', 'icon-sm')}
            <div>
              <strong>Action irréversible</strong>
              <p style="margin:0.25rem 0 0;opacity:0.85;">La séance passera en statut « clôturée ». Les votes ne pourront plus être modifiés. Un procès-verbal sera disponible.</p>
            </div>
          </div>

          <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
            <button class="btn btn-secondary" data-action="cancel">Annuler</button>
            <button class="btn btn-danger" data-action="confirm">${icon('square', 'icon-sm icon-text')} Clôturer définitivement</button>
          </div>
        `
      });
      modal.querySelector('[data-action="cancel"]').addEventListener('click', () => { O.closeModal(modal); resolve(false); });
      modal.querySelector('[data-action="confirm"]').addEventListener('click', () => { O.closeModal(modal); resolve(true); });
    });
    if (!confirmed) return;

    // Show loading on close session buttons during API call
    const sessionCloseBtns = document.querySelectorAll('#btnCloseSession, #execBtnCloseSession');
    sessionCloseBtns.forEach(b => Shared.btnLoading(b, true));

    try {
      const { body } = await api('/api/v1/meeting_transition.php', {
        meeting_id: O.currentMeetingId,
        to_status: 'closed'
      });
      if (body?.ok) {
        setNotif('success', 'Séance clôturée avec succès');
        await O.fn.loadMeetingContext(O.currentMeetingId);
        O.fn.loadMeetings();
        O.fn.setMode('setup', { tab: 'resultats' });
        O.announce('Séance clôturée.');
      } else {
        setNotif('error', getApiError(body, 'Erreur lors de la clôture'));
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      sessionCloseBtns.forEach(b => { if (b.isConnected) Shared.btnLoading(b, false); });
    }
  }

  // =========================================================================
  // TRANSITIONS
  // =========================================================================

  async function doTransition(toStatus) {
    // Redirect session closure to the enhanced security flow
    if (toStatus === 'closed') {
      return closeSession();
    }

    const statusLabels = { draft: 'brouillon', scheduled: 'planifiée', frozen: 'gelée', live: 'en cours', paused: 'en pause', closed: 'clôturée', validated: 'validée', archived: 'archivée' };
    const statusLabel = statusLabels[toStatus] || toStatus;
    const confirmed = await O.confirmModal({
      title: 'Confirmer le changement d\'état',
      body: `<p>La séance passera en statut <strong>« ${statusLabel} »</strong>.</p>`
    });
    if (!confirmed) return;

    // Show loading on the transition button that triggered this action
    const transBtn = document.querySelector(`[data-transition="${toStatus}"]`);
    if (transBtn) Shared.btnLoading(transBtn, true);

    try {
      const { body } = await api('/api/v1/meeting_transition.php', {
        meeting_id: O.currentMeetingId,
        to_status: toStatus
      });
      if (body?.ok) {
        if (body.warnings?.length) {
          body.warnings.forEach(w => setNotif('warning', w.msg));
        }
        setNotif('success', `Séance passée en "${statusLabel}"`);
        await O.fn.loadMeetingContext(O.currentMeetingId);
        O.fn.loadMeetings();

        // Auto-switch mode based on new status
        if (toStatus === 'live') {
          O.fn.setMode('exec');
          O.announce('Séance en cours — mode exécution activé.');
        } else if (toStatus === 'paused') {
          O.fn.setMode('setup');
          O.announce('Séance en pause.');
        } else if (['closed', 'validated', 'archived'].includes(toStatus)) {
          O.fn.setMode('setup', { tab: 'resultats' });
          O.announce(`Séance ${statusLabel}.`);
        }
      } else {
        setNotif('error', getApiError(body));
      }
    } catch (err) {
      setNotif('error', err.message);
    } finally {
      if (transBtn && transBtn.isConnected) Shared.btnLoading(transBtn, false);
    }
  }

  // =========================================================================
  // Register on OpS — overwrites the stubs from operator-tabs.js
  // =========================================================================
  O.fn.loadResolutions            = loadResolutions;
  O.fn.initializePreviousMotionState = initializePreviousMotionState;
  O.fn.renderResolutions          = renderResolutions;
  O.fn.showEditResolutionModal    = showEditResolutionModal;
  O.fn.moveResolution             = moveResolution;
  O.fn.createResolution           = createResolution;
  O.fn.loadVoteTab                = loadVoteTab;
  O.fn.renderQuickOpenList        = renderQuickOpenList;
  O.fn.loadBallots                = loadBallots;
  O.fn.renderManualVoteList       = renderManualVoteList;
  O.fn.castManualVote             = castManualVote;
  O.fn.applyUnanimity             = applyUnanimity;
  O.fn.openVote                   = openVote;
  O.fn.closeVote                  = closeVote;
  O.fn.loadResults                = loadResults;
  O.fn.getCloseSessionState       = getCloseSessionState;
  O.fn.updateCloseSessionStatus   = updateCloseSessionStatus;
  O.fn.updateExecCloseSession     = updateExecCloseSession;
  O.fn.closeSession               = closeSession;
  O.fn.doTransition               = doTransition;

})();
