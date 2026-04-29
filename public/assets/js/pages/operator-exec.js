/* global anime, Node */
/**
 * operator-exec.js — Execution mode + live view for the operator console.
 * Requires: utils.js, shared.js, operator-tabs.js (OpS bridge)
 *
 * Provides:
 *  - Quorum warning modal (showQuorumWarning / handleQuorumAction)
 *  - Action bar: Proclamer (handleProclaim), Vote toggle
 *  - Keyboard shortcuts: P (proclaim), F (vote toggle)
 *  - Agenda sidebar rendering (renderAgendaList)
 *  - Execution header timer (updateExecHeaderTimer)
 *  - KPI strip updates (opKpiPresent, opKpiQuorum, opKpiVoted, opKpiResolution)
 *  - Progress track click navigation (bindProgressSegmentClicks)
 *  - Resolution tags (updateResolutionTags)
 *  - Transition card for proclamation auto-advance
 */
(function() {
  'use strict';

  var O = window.OpS;

  // =========================================================================
  // INTERNAL STATE
  // =========================================================================

  var _execTimerInterval = null;
  var _prevVoteTotal = 0;
  var _deltaFadeTimer = null;

  // =========================================================================
  // KPI ANIMATION HELPERS (VIS-05)
  // Uses Anime.js (loaded via CDN with defer) for count-up animation.
  // Graceful fallback: if anime not yet loaded, sets value directly.
  // =========================================================================

  /**
   * Animate an integer KPI value using count-up (for elements with child spans).
   * Updates el.firstChild (text node) so child spans are preserved.
   * @param {string} elementId  - DOM id of the KPI element
   * @param {number} newValue   - Target integer value
   */
  function animateKpiValue(elementId, newValue) {
    var el = document.getElementById(elementId);
    if (!el || typeof anime === 'undefined') {
      if (el && el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) {
        el.firstChild.nodeValue = newValue;
      }
      return;
    }
    var currentValue = parseInt(el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE
      ? el.firstChild.nodeValue : el.textContent) || 0;
    var targetValue = parseInt(newValue) || 0;
    if (currentValue === targetValue) return;

    var obj = { val: currentValue };
    anime({
      targets: obj,
      val: targetValue,
      duration: 600,
      easing: 'easeOutQuad',
      round: 1,
      update: function() {
        if (el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) {
          el.firstChild.nodeValue = obj.val;
        } else {
          el.textContent = obj.val;
        }
      }
    });
  }

  /**
   * Animate a percentage KPI value using count-up (for pure textContent elements).
   * @param {string} elementId  - DOM id of the KPI element
   * @param {number} newPct     - Target percentage integer (no % sign)
   */
  function animateKpiPct(elementId, newPct) {
    var el = document.getElementById(elementId);
    if (!el || typeof anime === 'undefined') {
      if (el) el.textContent = newPct + '%';
      return;
    }
    var currentValue = parseInt(el.textContent) || 0;
    var targetValue = parseInt(newPct) || 0;
    if (currentValue === targetValue) return;

    var obj = { val: currentValue };
    anime({
      targets: obj,
      val: targetValue,
      duration: 600,
      easing: 'easeOutQuad',
      round: 1,
      update: function() {
        el.textContent = obj.val + '%';
      }
    });
  }

  // =========================================================================
  // QUORUM WARNING MODAL (OPR-09)
  // =========================================================================

  /**
   * Show the quorum warning blocking modal with stats.
   * @param {{ present: number, inscrits: number, requis: number }} stats
   */
  function showQuorumWarning(stats) {
    var overlay = document.getElementById('opQuorumOverlay');
    if (!overlay) return;

    // Populate stat cards
    var statsEl = document.getElementById('opQuorumStats');
    if (statsEl) {
      statsEl.innerHTML =
        '<div class="op-quorum-stat"><span class="op-quorum-stat-value">' + stats.present + '</span><span class="op-quorum-stat-label">Presents</span></div>' +
        '<div class="op-quorum-stat"><span class="op-quorum-stat-value">' + stats.inscrits + '</span><span class="op-quorum-stat-label">Inscrits</span></div>' +
        '<div class="op-quorum-stat"><span class="op-quorum-stat-value">' + stats.requis + '</span><span class="op-quorum-stat-label">Requis</span></div>';
    }

    // Reset continuer button and risk note
    var riskNote = document.getElementById('opQuorumRiskNote');
    var continuerBtn = document.getElementById('opQuorumContinuer');
    if (riskNote) riskNote.hidden = true;
    if (continuerBtn) {
      continuerBtn.textContent = 'Continuer sous reserve';
      continuerBtn.className = 'btn btn-ghost';
    }

    // Show overlay
    overlay.hidden = false;

    // Bind action buttons (use fresh listeners via clone)
    var reporterBtn = document.getElementById('opQuorumReporter');
    var suspendreBtn = document.getElementById('opQuorumSuspendre');

    if (reporterBtn) {
      reporterBtn.onclick = function() {
        overlay.hidden = true;
        handleQuorumAction('reporter');
      };
    }
    if (suspendreBtn) {
      suspendreBtn.onclick = function() {
        overlay.hidden = true;
        handleQuorumAction('suspendre');
      };
    }
    if (continuerBtn) {
      continuerBtn.onclick = function() {
        // First click: show risk warning. Second click: confirm.
        if (riskNote && riskNote.hidden) {
          riskNote.hidden = false;
          continuerBtn.textContent = 'Confirmer : continuer';
          continuerBtn.className = 'btn btn-danger';
          return;
        }
        overlay.hidden = true;
        handleQuorumAction('continuer');
      };
    }
  }

  /**
   * Handle a quorum action after user selection.
   * @param {'reporter'|'suspendre'|'continuer'} action
   */
  function handleQuorumAction(action) {
    if (action === 'reporter') {
      O.announce('Seance reportee pour 2e convocation');
    } else if (action === 'suspendre') {
      O.announce('Seance suspendue pour 30 minutes');
    } else if (action === 'continuer') {
      O.announce('Seance continue sous reserve de quorum');
    }
  }

  // =========================================================================
  // PROCLAMATION + TRANSITION CARD (OPR-10)
  // =========================================================================

  /**
   * Proclaim the current open motion immediately (no confirmation dialog).
   * Shows a brief transition card and auto-advances to next resolution.
   */
  function handleProclaim() {
    if (!O.currentOpenMotion) return;
    var currentMotionTitle = O.currentOpenMotion.title || '';

    // Close the vote first if still open
    if (O.currentOpenMotion && !O.currentOpenMotion.closed_at) {
      O.fn.closeVote(O.currentOpenMotion.id);
    }

    // Show transition card
    showTransitionCard(currentMotionTitle);

    // Auto-advance after brief delay
    setTimeout(function() {
      hideTransitionCard();
      advanceToNextResolution();
    }, 800);
  }

  /**
   * Show the transition card with a proclaimed message.
   * @param {string} title - The resolution title
   */
  function showTransitionCard(title) {
    var card = document.getElementById('opTransitionCard');
    var text = document.getElementById('opTransitionText');
    if (card) card.hidden = false;
    if (text) text.textContent = 'Resolution proclamee : ' + title;
  }

  /**
   * Hide the transition card.
   */
  function hideTransitionCard() {
    var card = document.getElementById('opTransitionCard');
    if (card) card.hidden = true;
  }

  /**
   * Advance to the next unvoted resolution in the agenda.
   */
  function advanceToNextResolution() {
    var nextMotion = O.motionsCache.find(function(m) { return !m.opened_at && !m.closed_at; });
    if (nextMotion) {
      selectMotion(nextMotion.id);
    } else {
      // All resolutions voted -- update view
      refreshExecView();
    }
  }

  /**
   * Select a motion by ID: update current state, refresh views, reset to Resultat sub-tab.
   * @param {string} motionId
   */
  function selectMotion(motionId) {
    var motion = O.motionsCache.find(function(m) { return m.id === motionId; });
    if (!motion) return;

    // Update resolution title and tags
    var titleEl = document.getElementById('opResTitle');
    if (titleEl) titleEl.textContent = motion.title || '';
    updateResolutionTags(motion);

    // Update live dot visibility
    var liveDot = document.getElementById('opResLiveDot');
    if (liveDot) liveDot.hidden = !motion.opened_at || !!motion.closed_at;

    // Reset to Resultat sub-tab
    resetToResultatTab();

    // Refresh agenda sidebar highlighting
    renderAgendaList();

    // Refresh view
    refreshExecView();
  }

  /**
   * Reset sub-tabs to the Resultat tab.
   */
  function resetToResultatTab() {
    var tabs = document.querySelectorAll('.op-tab');
    var panels = document.querySelectorAll('.op-tab-panel');
    tabs.forEach(function(t) { t.classList.remove('active'); });
    panels.forEach(function(p) { p.classList.remove('active'); });
    var resultatTab = document.querySelector('[data-op-tab="resultat"]');
    var resultatPanel = document.getElementById('opPanelResultat');
    if (resultatTab) resultatTab.classList.add('active');
    if (resultatPanel) resultatPanel.classList.add('active');
  }

  // =========================================================================
  // KEYBOARD SHORTCUTS
  // =========================================================================

  document.addEventListener('keydown', function(e) {
    // Skip if in input/textarea/select or meta/ctrl/alt key held
    var tag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    if (e.metaKey || e.ctrlKey || e.altKey) return;

    // Only active in execution mode
    if (O.currentMode !== 'exec') return;

    if (e.key === 'p' || e.key === 'P') {
      e.preventDefault();
      var proclaimBtn = document.getElementById('opBtnProclaim');
      if (proclaimBtn && !proclaimBtn.disabled) handleProclaim();
    }
    if (e.key === 'f' || e.key === 'F') {
      e.preventDefault();
      var toggleBtn = document.getElementById('opBtnToggleVote');
      if (toggleBtn && !toggleBtn.disabled) toggleBtn.click();
    }
  });

  // =========================================================================
  // AGENDA SIDEBAR RENDERING
  // =========================================================================

  /**
   * Render the agenda list in the right sidebar with status circles.
   */
  function renderAgendaList() {
    var list = document.getElementById('opAgendaList');
    if (!list || !O.motionsCache) return;

    list.innerHTML = O.motionsCache.map(function(m, i) {
      var status = m.closed_at ? 'voted' : (O.currentOpenMotion && O.currentOpenMotion.id === m.id ? 'current' : 'pending');
      return '<div class="op-agenda-item ' + status + '" data-motion-id="' + escapeHtml(m.id) + '" role="button" tabindex="0">' +
        '<span class="op-agenda-num">' + (i + 1) + '</span>' +
        '<span class="op-agenda-title">' + escapeHtml(m.title || '') + '</span>' +
        '<span class="op-agenda-status-dot"></span>' +
      '</div>';
    }).join('');

    // Click handler: navigate to resolution, reset to Resultat tab
    list.querySelectorAll('.op-agenda-item[data-motion-id]').forEach(function(item) {
      item.addEventListener('click', function() {
        var motionId = item.dataset.motionId;
        selectMotion(motionId);
      });
      // Keyboard support (Enter/Space)
      item.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          var motionId = item.dataset.motionId;
          selectMotion(motionId);
        }
      });
    });
  }

  // =========================================================================
  // SUB-TAB SWITCHING
  // =========================================================================

  // Wire sub-tab click handlers (delegation)
  document.addEventListener('click', function(e) {
    var tab = e.target.closest('.op-tab');
    if (!tab) return;
    var tabName = tab.dataset.opTab;
    if (!tabName) return;

    // Deactivate all sub-tabs and panels
    var tabs = document.querySelectorAll('.op-tab');
    var panels = document.querySelectorAll('.op-tab-panel');
    tabs.forEach(function(t) { t.classList.remove('active'); });
    panels.forEach(function(p) { p.classList.remove('active'); });

    // Activate selected
    tab.classList.add('active');
    var panelId = 'opPanel' + tabName.charAt(0).toUpperCase() + tabName.slice(1);
    var panel = document.getElementById(panelId);
    if (panel) panel.classList.add('active');
  });

  // =========================================================================
  // EXECUTION HEADER TIMER (Task 2)
  // =========================================================================

  /**
   * Update the execution header timer display (HH:MM:SS).
   */
  function updateExecHeaderTimer() {
    var el = document.getElementById('opExecTimer');
    if (!el || !O.currentMeeting || !O.currentMeeting.opened_at) return;
    var start = new Date(O.currentMeeting.opened_at).getTime();
    var elapsed = Math.floor((Date.now() - start) / 1000);
    var hh = String(Math.floor(elapsed / 3600)).padStart(2, '0');
    var mm = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
    var ss = String(elapsed % 60).padStart(2, '0');
    el.textContent = hh + ':' + mm + ':' + ss;
  }

  /**
   * Start the execution header timer interval.
   */
  function startExecTimer() {
    stopExecTimer();
    updateExecHeaderTimer();
    _execTimerInterval = setInterval(updateExecHeaderTimer, 1000);
  }

  /**
   * Stop the execution header timer interval.
   */
  function stopExecTimer() {
    if (_execTimerInterval) {
      clearInterval(_execTimerInterval);
      _execTimerInterval = null;
    }
  }

  // =========================================================================
  // KPI STRIP UPDATES (Task 2)
  // =========================================================================

  /**
   * Compute common attendance/quorum figures used by multiple functions.
   * @returns {{ present: number, proxyActive: number, currentVoters: number, totalMembers: number, required: number, threshold: number }}
   */
  function computeQuorumStats() {
    var present = O.attendanceCache.filter(function(a) { return a.mode === 'present' || a.mode === 'remote'; }).length;
    var proxyActive = O.proxiesCache.filter(function(p) { return !p.revoked_at; }).length;
    var currentVoters = present + proxyActive;
    var totalMembers = O.membersCache.length;
    var policy = O.policiesCache && O.policiesCache.quorum ? O.policiesCache.quorum.find(function(p) { return p.id === (O.currentMeeting ? O.currentMeeting.quorum_policy_id : null); }) : null;
    var threshold = policy && policy.threshold ? parseFloat(policy.threshold) : 0.5;
    var required = Math.ceil(totalMembers * threshold);
    return { present: present, proxyActive: proxyActive, currentVoters: currentVoters, totalMembers: totalMembers, required: required, threshold: threshold };
  }

  function refreshExecKPIs() {
    var stats = computeQuorumStats();

    // ---------- New KPI strip (Plan 01 IDs) ----------

    // PRESENTS: x/y — animate leading number, update total span statically
    var kpiPresent = document.getElementById('opKpiPresent');
    if (kpiPresent) {
      var totalSpanP = kpiPresent.querySelector('.op-kpi-total');
      if (!totalSpanP) {
        // First render: set full HTML including child span
        kpiPresent.innerHTML = stats.present + '<span class="op-kpi-total">/' + stats.totalMembers + '</span>';
      } else {
        // Subsequent renders: animate leading number, update total span
        totalSpanP.textContent = '/' + stats.totalMembers;
        animateKpiValue('opKpiPresent', stats.present);
      }
    }

    // QUORUM: percentage + check icon — animate percentage
    var kpiQuorum = document.getElementById('opKpiQuorum');
    var kpiQuorumCheck = document.getElementById('opKpiQuorumCheck');
    if (kpiQuorum) {
      var qPct = stats.totalMembers > 0 ? Math.round((stats.currentVoters / stats.totalMembers) * 100) : 0;
      animateKpiPct('opKpiQuorum', qPct);
    }
    if (kpiQuorumCheck) {
      kpiQuorumCheck.hidden = stats.currentVoters < stats.required;
    }

    // ONT VOTE: voted/eligible for current motion — animate leading number
    var kpiVoted = document.getElementById('opKpiVoted');
    if (kpiVoted) {
      if (O.currentOpenMotion) {
        var totalBallots = Object.keys(O.ballotsCache).length;
        var eligible = stats.present + stats.proxyActive;
        var totalSpanV = kpiVoted.querySelector('.op-kpi-total');
        if (!totalSpanV) {
          kpiVoted.innerHTML = totalBallots + '<span class="op-kpi-total">/' + eligible + '</span>';
        } else {
          totalSpanV.textContent = '/' + eligible;
          animateKpiValue('opKpiVoted', totalBallots);
        }

        // Delta badge: show +N when vote count increases
        var delta = totalBallots - _prevVoteTotal;
        if (delta > 0 && _prevVoteTotal > 0) {
          var badge = document.getElementById('opVoteDeltaBadge');
          if (badge) {
            badge.textContent = '+' + delta + ' \u25b2';
            badge.hidden = false;
            if (_deltaFadeTimer) clearTimeout(_deltaFadeTimer);
            _deltaFadeTimer = setTimeout(function() { badge.hidden = true; }, 3000);
          }
        }
        _prevVoteTotal = totalBallots;
      } else {
        kpiVoted.innerHTML = '0<span class="op-kpi-total">/0</span>';
        _prevVoteTotal = 0;
      }
    }

    // RESOLUTION: closed/total — animate leading number
    var kpiResolution = document.getElementById('opKpiResolution');
    if (kpiResolution) {
      var closed = O.motionsCache.filter(function(m) { return m.closed_at; }).length;
      var totalSpanR = kpiResolution.querySelector('.op-kpi-total');
      if (!totalSpanR) {
        kpiResolution.innerHTML = closed + '<span class="op-kpi-total">/' + O.motionsCache.length + '</span>';
      } else {
        totalSpanR.textContent = '/' + O.motionsCache.length;
        animateKpiValue('opKpiResolution', closed);
      }
    }

    // ---------- Legacy participation (backward compat) ----------
    var partEl = document.getElementById('execParticipation');
    if (partEl && O.currentOpenMotion) {
      var tb = Object.keys(O.ballotsCache).length;
      var el = stats.present + stats.proxyActive;
      var pct = el > 0 ? Math.round((tb / el) * 100) : 0;
      partEl.textContent = pct + '%';
      partEl.classList.remove('text-success', 'text-warning', 'text-muted');
      partEl.classList.add(pct >= 75 ? 'text-success' : pct >= 50 ? 'text-warning' : 'text-muted');
    } else if (partEl) {
      partEl.textContent = '\u2014';
      partEl.classList.remove('text-success', 'text-warning', 'text-muted');
    }

    // ---------- Legacy motions progress (backward compat) ----------
    var doneEl = document.getElementById('execMotionsDone');
    var totalEl = document.getElementById('execMotionsTotal');
    if (doneEl && totalEl) {
      var closedLeg = O.motionsCache.filter(function(m) { return m.closed_at; }).length;
      doneEl.textContent = closedLeg;
      totalEl.textContent = O.motionsCache.length;
    }

    // Vote participation bar in exec
    var barFill = document.getElementById('execVoteParticipationBar');
    var barPct = document.getElementById('execVoteParticipationPct');
    if (barFill && barPct) {
      if (O.currentOpenMotion) {
        var tb2 = Object.keys(O.ballotsCache).length;
        var el2 = stats.present + stats.proxyActive;
        var p2 = el2 > 0 ? Math.round((tb2 / el2) * 100) : 0;
        barFill.style.setProperty('--bar-pct', p2 + '%');
        barPct.textContent = p2 + '%';
      } else {
        barFill.style.setProperty('--bar-pct', '0%');
        barPct.textContent = '\u2014';
      }
    }

    // ---------- Quorum warning trigger ----------
    if (O.currentMeetingStatus === 'live' && O.currentOpenMotion && stats.currentVoters < stats.required && !O.quorumWarningShown) {
      O.quorumWarningShown = true;
      showQuorumWarning({ present: stats.currentVoters, inscrits: stats.totalMembers, requis: stats.required });
    }

    // ---------- Action bar visibility ----------
    var actionBar = document.getElementById('opActionBar');
    if (actionBar) {
      actionBar.hidden = O.currentMeetingStatus !== 'live';
    }
    var proclaimBtn = document.getElementById('opBtnProclaim');
    if (proclaimBtn) {
      proclaimBtn.disabled = !O.currentOpenMotion || !O.currentOpenMotion.closed_at;
    }
    var toggleVoteBtn = document.getElementById('opBtnToggleVote');
    if (toggleVoteBtn) {
      if (O.currentOpenMotion && !O.currentOpenMotion.closed_at) {
        toggleVoteBtn.disabled = false;
        toggleVoteBtn.innerHTML = '<span class="op-kbd-hint">F</span>' +
          '<svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-square"></use></svg> Fermer le vote';
      } else {
        // Enable if there are openable motions
        var hasOpenable = O.motionsCache.some(function(m) { return !m.opened_at && !m.closed_at; });
        toggleVoteBtn.disabled = !hasOpenable;
        toggleVoteBtn.innerHTML = '<span class="op-kbd-hint">F</span>' +
          '<svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-play"></use></svg> Ouvrir le vote';
      }
    }
  }

  // =========================================================================
  // PROGRESS TRACK CLICK HANDLER (Task 2)
  // =========================================================================

  /**
   * Bind click handler for progress track segments.
   * Voted and active segments navigate to the corresponding resolution.
   */
  function bindProgressSegmentClicks() {
    var progress = document.getElementById('opResolutionProgress');
    if (!progress) return;
    progress.addEventListener('click', function(e) {
      var segment = e.target.closest('.op-progress-segment');
      if (!segment) return;
      if (segment.classList.contains('voted') || segment.classList.contains('active')) {
        var motionId = segment.dataset.motionId;
        if (motionId) selectMotion(motionId);
      }
    });
  }

  // =========================================================================
  // RESOLUTION TAGS (Task 2)
  // =========================================================================

  /**
   * Update the resolution tags display for the selected motion.
   * @param {object} motion - The motion object
   */
  function updateResolutionTags(motion) {
    var container = document.getElementById('opResTags');
    if (!container) return;
    var tags = [];
    if (motion.majority_type) tags.push('<span class="tag tag-accent">' + escapeHtml(motion.majority_type) + '</span>');
    if (motion.is_key) tags.push('<span class="tag tag-purple">cle</span>');
    if (motion.is_secret) tags.push('<span class="tag tag-warning">secret</span>');
    container.innerHTML = tags.join('');
  }

  // =========================================================================
  // EXECUTION HEADER TITLE (Task 2)
  // =========================================================================

  /**
   * Update the execution header with current meeting title.
   */
  function updateExecHeader() {
    var titleEl = document.getElementById('opExecTitle');
    if (titleEl && O.currentMeeting) {
      titleEl.textContent = O.currentMeeting.title || '--';
    }
  }

  // =========================================================================
  // EXEC VIEW REFRESH (orchestrator)
  // =========================================================================

  function refreshExecView() {
    refreshExecKPIs();
    refreshExecVote();
    refreshExecSpeech();
    refreshExecDevices();
    refreshExecManualVotes();
    renderAgendaList();
    updateExecHeader();
    O.fn.refreshAlerts();
    O.fn.updateExecCloseSession();
    refreshExecChecklist();
  }

  function refreshExecVote() {
    var titleEl = document.getElementById('execVoteTitle');
    var forEl = document.getElementById('execVoteFor');
    var againstEl = document.getElementById('execVoteAgainst');
    var abstainEl = document.getElementById('execVoteAbstain');
    var liveBadge = document.getElementById('execLiveBadge');
    var btnClose = document.getElementById('execBtnCloseVote');
    var noVotePanel = document.getElementById('execNoVote');
    var activeVotePanel = document.getElementById('execActiveVote');
    var postVoteGuidance = document.getElementById('opPostVoteGuidance');
    var endOfAgenda = document.getElementById('opEndOfAgenda');

    if (O.currentOpenMotion) {
      if (noVotePanel) Shared.hide(noVotePanel);
      if (activeVotePanel) activeVotePanel.hidden = false;
      // Hide guidance panels when a vote is open
      if (postVoteGuidance) postVoteGuidance.hidden = true;
      if (endOfAgenda) endOfAgenda.hidden = true;

      if (titleEl) titleEl.textContent = O.currentOpenMotion.title;
      if (liveBadge) Shared.show(liveBadge);
      if (btnClose) { btnClose.disabled = false; Shared.show(btnClose); }

      var fc = 0, ac = 0, ab = 0;
      Object.values(O.ballotsCache).forEach(function(v) {
        if (v === 'for') fc++;
        else if (v === 'against') ac++;
        else if (v === 'abstain') ab++;
      });

      if (forEl) forEl.textContent = fc;
      if (againstEl) againstEl.textContent = ac;
      if (abstainEl) abstainEl.textContent = ab;

      var total = fc + ac + ab;
      var pctFor = total > 0 ? Math.round((fc / total) * 100) : 0;
      var pctAgainst = total > 0 ? Math.round((ac / total) * 100) : 0;
      var pctAbstain = total > 0 ? Math.round((ab / total) * 100) : 0;

      var barFor = document.getElementById('opBarFor');
      var barAgainst = document.getElementById('opBarAgainst');
      var barAbstain = document.getElementById('opBarAbstain');
      if (barFor) barFor.style.setProperty('--bar-pct', pctFor + '%');
      if (barAgainst) barAgainst.style.setProperty('--bar-pct', pctAgainst + '%');
      if (barAbstain) barAbstain.style.setProperty('--bar-pct', pctAbstain + '%');

      var pFor = document.getElementById('opPctFor');
      var pAgainst = document.getElementById('opPctAgainst');
      var pAbstain = document.getElementById('opPctAbstain');
      if (pFor) pFor.textContent = pctFor + '%';
      if (pAgainst) pAgainst.textContent = pctAgainst + '%';
      if (pAbstain) pAbstain.textContent = pctAbstain + '%';

      // Hide breakdown during open vote (locked decision: only show total count)
      var hideBreakdown = O.currentOpenMotion && !O.currentOpenMotion.closed_at;
      [forEl, againstEl, abstainEl].forEach(function(el) { if (el && el.parentElement) el.parentElement.hidden = !!hideBreakdown; });
      [barFor, barAgainst, barAbstain].forEach(function(el) { if (el && el.parentElement) el.parentElement.hidden = !!hideBreakdown; });

      // Resolution title in the card header
      var resTitle = document.getElementById('opResTitle');
      if (resTitle) resTitle.textContent = O.currentOpenMotion.title;

      // Live dot
      var liveDot = document.getElementById('opResLiveDot');
      if (liveDot) liveDot.hidden = false;

      // Resolution tags
      updateResolutionTags(O.currentOpenMotion);
    } else {
      if (activeVotePanel) activeVotePanel.hidden = true;
      if (liveBadge) Shared.hide(liveBadge);
      if (btnClose) { btnClose.disabled = true; Shared.hide(btnClose); }

      // Hide live dot when no vote
      var liveDotOff = document.getElementById('opResLiveDot');
      if (liveDotOff) liveDotOff.hidden = true;

      // Determine which guidance panel to show
      var isLive = O.currentMeetingStatus === 'live';
      var openableMotions = O.motionsCache.filter(function(m) { return !m.opened_at && !m.closed_at; });
      var hasClosedMotions = O.motionsCache.some(function(m) { return m.closed_at; });
      var allMotionsClosed = O.motionsCache.length > 0 && openableMotions.length === 0 && !O.currentOpenMotion;

      if (isLive && allMotionsClosed) {
        // All motions are closed — show end-of-agenda guidance
        if (noVotePanel) Shared.hide(noVotePanel);
        if (postVoteGuidance) postVoteGuidance.hidden = true;
        if (endOfAgenda) endOfAgenda.hidden = false;
      } else if (isLive && hasClosedMotions && openableMotions.length > 0) {
        // A vote just closed and there are more motions — show post-vote guidance
        if (noVotePanel) Shared.hide(noVotePanel);
        if (endOfAgenda) endOfAgenda.hidden = true;
        if (postVoteGuidance) postVoteGuidance.hidden = false;
      } else {
        // Default: show no-vote panel with quick open list
        if (postVoteGuidance) postVoteGuidance.hidden = true;
        if (endOfAgenda) endOfAgenda.hidden = true;
        if (noVotePanel) Shared.show(noVotePanel, 'block');
        renderExecQuickOpenList();
      }
    }
  }

  function renderExecQuickOpenList() {
    var list = document.getElementById('execQuickOpenList');
    if (!list) return;

    var isLive = O.currentMeetingStatus === 'live';
    var openableMotions = O.motionsCache.filter(function(m) { return !m.opened_at && !m.closed_at; });

    if (!isLive || openableMotions.length === 0) {
      list.innerHTML = isLive
        ? '<p class="text-muted text-sm">Aucune resolution en attente</p>'
        : '<p class="text-muted text-sm">La seance doit etre en cours pour ouvrir un vote</p>';
      return;
    }

    list.innerHTML = openableMotions.slice(0, 5).map(function(m, i) {
      return '<button class="btn btn-primary btn-quick-open" data-motion-id="' + escapeHtml(m.id) + '">' +
        icon('play', 'icon-sm icon-text') + (i + 1) + '. ' + escapeHtml(m.title.length > 30 ? m.title.substring(0, 30) + '...' : m.title) +
      '</button>';
    }).join('');

    if (openableMotions.length > 5) {
      list.innerHTML += '<span class="text-muted text-sm">+ ' + (openableMotions.length - 5) + ' autres</span>';
    }

    list.querySelectorAll('.btn-quick-open').forEach(function(btn) {
      btn.addEventListener('click', function() { O.fn.openVote(btn.dataset.motionId); });
    });
  }

  function refreshExecSpeech() {
    var speakerInfo = document.getElementById('execSpeakerInfo');
    var actionsEl = document.getElementById('execSpeechActions');
    var queueList = document.getElementById('execSpeechQueue');

    if (O.execSpeechTimerInterval) {
      clearInterval(O.execSpeechTimerInterval);
      O.execSpeechTimerInterval = null;
    }

    if (speakerInfo) {
      if (O.currentSpeakerCache) {
        var name = escapeHtml(O.currentSpeakerCache.full_name || '\u2014');
        var startTime = O.currentSpeakerCache.updated_at ? new Date(O.currentSpeakerCache.updated_at).getTime() : Date.now();
        speakerInfo.innerHTML =
          '<div class="exec-speaker-active">' +
            '<svg class="icon icon-text exec-speaker-mic" aria-hidden="true"><use href="/assets/icons.svg#icon-mic"></use></svg>' +
            '<strong>' + name + '</strong>' +
            '<span class="exec-speaker-timer" id="execSpeakerTimer">00:00</span>' +
          '</div>';
        function updateSpeechTimer() {
          var el = document.getElementById('execSpeakerTimer');
          if (!el) return;
          var elapsed = Math.floor((Date.now() - startTime) / 1000);
          var mm = String(Math.floor(elapsed / 60)).padStart(2, '0');
          var ss = String(elapsed % 60).padStart(2, '0');
          el.textContent = mm + ':' + ss;
        }
        updateSpeechTimer();
        O.execSpeechTimerInterval = setInterval(updateSpeechTimer, 1000);
      } else {
        speakerInfo.innerHTML = '<span class="text-sm text-muted">Aucun orateur</span>';
      }
    }

    if (actionsEl) {
      actionsEl.hidden = !O.currentSpeakerCache;
    }

    if (queueList) {
      if (O.speechQueueCache.length === 0) {
        queueList.innerHTML = '<span class="text-muted text-sm">File vide</span>';
      } else {
        queueList.innerHTML = '<div class="text-sm text-muted mb-1">File (' + O.speechQueueCache.length + ') :</div>' +
          O.speechQueueCache.slice(0, 5).map(function(s, i) {
            return '<div class="text-sm">' + (i + 1) + '. ' + escapeHtml(s.full_name || '\u2014') + '</div>';
          }).join('');
        if (O.speechQueueCache.length > 5) {
          queueList.innerHTML += '<div class="text-sm text-muted">+ ' + (O.speechQueueCache.length - 5) + ' autres</div>';
        }
      }
    }
  }

  function refreshExecDevices() {
    var devOnlineEl = document.getElementById('devOnline');
    var devStaleEl = document.getElementById('devStale');
    var execOnline = document.getElementById('execDevOnline');
    var execStale = document.getElementById('execDevStale');

    if (execOnline && devOnlineEl) execOnline.textContent = devOnlineEl.textContent;
    if (execStale && devStaleEl) execStale.textContent = devStaleEl.textContent;
  }

  // =========================================================================
  // CHECKLIST PANEL UPDATES (CHECK-01..05)
  // =========================================================================

  /**
   * Update a single checklist row's state and value.
   * @param {string} rowName - 'sse'|'quorum'|'votes'|'online'
   * @param {string} state   - 'ok'|'alert'|'neutral'
   * @param {string} value   - Display value (e.g. '42/60 (70%)')
   */
  function setChecklistRow(rowName, state, value) {
    var suffix = rowName.charAt(0).toUpperCase() + rowName.slice(1);
    var row = document.getElementById('opChecklistRow' + suffix);
    var valEl = document.getElementById('opChecklist' + suffix + 'Value');
    if (!row || !valEl) return;
    valEl.textContent = value;
    // Toggle state classes — only add --alert if not already present (avoid restarting animation)
    var isAlert = state === 'alert';
    var wasAlert = row.classList.contains('op-checklist-row--alert');
    if (isAlert && !wasAlert) {
      row.classList.add('op-checklist-row--alert');
      row.classList.remove('op-checklist-row--ok');
    } else if (!isAlert) {
      row.classList.remove('op-checklist-row--alert');
      row.classList.toggle('op-checklist-row--ok', state === 'ok');
    }
  }

  /**
   * Update checklist SSE row and banner from SSE connection state.
   * @param {string} state - 'live'|'reconnecting'|'offline'
   */
  function updateChecklistSseRow(state) {
    var labels = { live: 'Connecte', reconnecting: 'Reconnexion…', offline: 'Deconnecte' };
    var rowState = state === 'offline' ? 'alert' : (state === 'live' ? 'ok' : 'neutral');
    setChecklistRow('sse', rowState, labels[state] || state);
    // SSE banner: show only when offline
    var banner = document.getElementById('opChecklistSseBanner');
    if (banner) banner.hidden = (state !== 'offline');
  }

  /**
   * Refresh all checklist indicators from current cached data.
   * Called from refreshExecView() and on SSE events.
   */
  function refreshExecChecklist() {
    // Quorum row (CHECK-01)
    var stats = computeQuorumStats();
    var quorumMet = stats.currentVoters >= stats.required;
    var pct = stats.totalMembers > 0 ? Math.round(stats.currentVoters / stats.totalMembers * 100) : 0;
    setChecklistRow('quorum', quorumMet ? 'ok' : 'alert',
      stats.currentVoters + '/' + stats.required + ' (' + pct + '%)');
    // Update quorum row aria-label for accessibility
    var quorumValEl = document.getElementById('opChecklistQuorumValue');
    if (quorumValEl) {
      quorumValEl.setAttribute('aria-label',
        'Quorum : ' + stats.currentVoters + ' presents sur ' + stats.required + ' requis');
    }

    // Votes row (CHECK-02)
    var totalBallots = O.ballotsCache ? Object.keys(O.ballotsCache).length : 0;
    var eligible = stats.currentVoters;
    setChecklistRow('votes', 'neutral', totalBallots + '/' + eligible);

    // Online voters row (CHECK-04)
    var onlineEl = document.getElementById('execDevOnline');
    var onlineCount = onlineEl ? onlineEl.textContent.trim() : '0';
    setChecklistRow('online', 'neutral', onlineCount + ' en ligne');
  }

  function refreshExecManualVotes() {
    var list = document.getElementById('execManualVoteList');
    if (!list) return;

    if (!O.currentOpenMotion) {
      list.innerHTML = '<span class="text-muted text-sm">Aucun vote actif</span>';
      return;
    }

    var searchInput = document.getElementById('execManualSearch');
    var searchTerm = (searchInput ? searchInput.value : '').toLowerCase();
    var voters = O.attendanceCache.filter(function(a) { return a.mode === 'present' || a.mode === 'remote'; });

    if (searchTerm) {
      voters = voters.filter(function(v) { return (v.full_name || '').toLowerCase().includes(searchTerm); });
    }

    var shown = voters.slice(0, 20);
    var remaining = voters.length - shown.length;
    list.innerHTML = shown.map(function(v) {
      var vote = O.ballotsCache[v.member_id];
      return '<div class="exec-manual-vote-row" data-member-id="' + escapeHtml(v.member_id) + '">'
        + '<span class="text-sm">' + escapeHtml(v.full_name || '\u2014') + '</span>'
        + '<div class="flex gap-1">'
        + '<button class="btn btn-xs ' + (vote === 'for' ? 'btn-success' : 'btn-ghost') + '" data-vote="for" aria-label="Pour \u2014 ' + escapeHtml(v.full_name || '') + '">Pour</button>'
        + '<button class="btn btn-xs ' + (vote === 'against' ? 'btn-danger' : 'btn-ghost') + '" data-vote="against" aria-label="Contre \u2014 ' + escapeHtml(v.full_name || '') + '">Contre</button>'
        + '<button class="btn btn-xs ' + (vote === 'abstain' ? 'btn-warning' : 'btn-ghost') + '" data-vote="abstain" aria-label="Abstention \u2014 ' + escapeHtml(v.full_name || '') + '">Abst.</button>'
        + '</div></div>';
    }).join('') + (remaining > 0 ? '<div class="text-xs text-muted text-center mt-2">+ ' + remaining + ' votants non affiches</div>' : '')
    || '<span class="text-muted text-sm">Aucun votant</span>';

    // Bind vote buttons
    list.querySelectorAll('[data-vote]').forEach(function(btn) {
      btn.addEventListener('click', async function() {
        var row = btn.closest('[data-member-id]');
        var memberId = row.dataset.memberId;
        var voteType = btn.dataset.vote;
        if (O.ballotsCache[memberId] === voteType) return;
        await O.fn.castManualVote(memberId, voteType);
        refreshExecManualVotes();
      });
    });
  }

  // =========================================================================
  // ACTION BAR BUTTON HANDLERS
  // =========================================================================

  // Proclamer button
  var proclaimBtn = document.getElementById('opBtnProclaim');
  if (proclaimBtn) {
    proclaimBtn.addEventListener('click', function() {
      if (!proclaimBtn.disabled) handleProclaim();
    });
  }

  // Vote toggle button
  var toggleVoteBtn = document.getElementById('opBtnToggleVote');
  if (toggleVoteBtn) {
    toggleVoteBtn.addEventListener('click', function() {
      if (toggleVoteBtn.disabled) return;
      if (O.currentOpenMotion && !O.currentOpenMotion.closed_at) {
        O.fn.closeVote(O.currentOpenMotion.id);
      } else {
        // Open next available vote
        var nextMotion = O.motionsCache.find(function(m) { return !m.opened_at && !m.closed_at; });
        if (nextMotion) O.fn.openVote(nextMotion.id);
      }
    });
  }

  // =========================================================================
  // GUIDANCE PANEL BUTTON HANDLERS (OPC-04 / OPC-05)
  // =========================================================================

  // Post-vote: "Vote suivant" — focus/open next openable motion
  document.addEventListener('click', function(e) {
    if (e.target.id === 'opBtnNextVote') {
      var nextMotion = O.motionsCache.find(function(m) { return !m.opened_at && !m.closed_at; });
      if (nextMotion) {
        // Scroll the agenda item into view and open the vote
        var agendaItem = document.querySelector('[data-motion-id="' + nextMotion.id + '"]');
        if (agendaItem) agendaItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        O.fn.openVote(nextMotion.id);
      }
    }
    if (e.target.id === 'opBtnCloseSession' || e.target.id === 'opBtnEndSession') {
      // Trigger the existing close session button
      var existingCloseBtn = document.getElementById('execBtnCloseSession');
      if (existingCloseBtn) {
        existingCloseBtn.click();
      }
    }
  });

  // Initialize progress track click handler
  bindProgressSegmentClicks();

  // =========================================================================
  // REGISTER ON OpS — overwrites the stubs from operator-tabs.js
  // =========================================================================

  O.fn.refreshExecKPIs         = refreshExecKPIs;
  O.fn.refreshExecView         = refreshExecView;
  O.fn.refreshExecVote         = refreshExecVote;
  O.fn.renderExecQuickOpenList = renderExecQuickOpenList;
  O.fn.refreshExecSpeech       = refreshExecSpeech;
  O.fn.refreshExecDevices      = refreshExecDevices;
  O.fn.refreshExecManualVotes  = refreshExecManualVotes;
  O.fn.showQuorumWarning       = showQuorumWarning;
  O.fn.handleProclaim          = handleProclaim;
  O.fn.renderAgendaList        = renderAgendaList;
  O.fn.selectMotion            = selectMotion;
  O.fn.updateExecHeaderTimer   = updateExecHeaderTimer;
  O.fn.startExecTimer          = startExecTimer;
  O.fn.stopExecTimer           = stopExecTimer;
  O.fn.updateResolutionTags    = updateResolutionTags;
  O.fn.bindProgressSegmentClicks = bindProgressSegmentClicks;
  O.fn.refreshExecChecklist    = refreshExecChecklist;
  O.fn.updateChecklistSseRow   = updateChecklistSseRow;
  O.fn.setChecklistRow         = setChecklistRow;

})();
