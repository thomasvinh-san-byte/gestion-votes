/**
 * operator-exec.js — Execution mode + live view for the operator console.
 * Requires: utils.js, shared.js, operator-tabs.js (OpS bridge)
 */
(function() {
  'use strict';

  var O = window.OpS;

  // =========================================================================
  // EXECUTION VIEW — KPIs & Live Panels
  // =========================================================================

  function refreshExecKPIs() {
    // Quorum bar
    var qBar = document.getElementById('execQuorumBar');
    if (qBar) {
      var present = O.attendanceCache.filter(function(a) { return a.mode === 'present' || a.mode === 'remote'; }).length;
      var proxyActive = O.proxiesCache.filter(function(p) { return !p.revoked_at; }).length;
      var currentVoters = present + proxyActive;
      var totalMembers = O.membersCache.length;
      var policy = O.policiesCache.quorum.find(function(p) { return p.id === (O.currentMeeting ? O.currentMeeting.quorum_policy_id : null); });
      var threshold = policy && policy.threshold ? parseFloat(policy.threshold) : 0.5;
      var required = Math.ceil(totalMembers * threshold);
      qBar.setAttribute('current', currentVoters);
      qBar.setAttribute('required', required);
      qBar.setAttribute('total', totalMembers);
    }

    // Participation %
    var partEl = document.getElementById('execParticipation');
    if (partEl && O.currentOpenMotion) {
      var totalBallots = Object.keys(O.ballotsCache).length;
      var eligible = O.attendanceCache.filter(function(a) { return a.mode === 'present' || a.mode === 'remote'; }).length +
                     O.proxiesCache.filter(function(p) { return !p.revoked_at; }).length;
      var pct = eligible > 0 ? Math.round((totalBallots / eligible) * 100) : 0;
      partEl.textContent = pct + '%';
      partEl.style.color = pct >= 75 ? 'var(--color-success)' : pct >= 50 ? 'var(--color-warning)' : 'var(--color-text-muted)';
    } else if (partEl) {
      partEl.textContent = '—';
      partEl.style.color = '';
    }

    // Motions progress
    var doneEl = document.getElementById('execMotionsDone');
    var totalEl = document.getElementById('execMotionsTotal');
    if (doneEl && totalEl) {
      var closed = O.motionsCache.filter(function(m) { return m.closed_at; }).length;
      doneEl.textContent = closed;
      totalEl.textContent = O.motionsCache.length;
    }

    // Vote participation bar in exec
    var barFill = document.getElementById('execVoteParticipationBar');
    var barPct = document.getElementById('execVoteParticipationPct');
    if (barFill && barPct) {
      if (O.currentOpenMotion) {
        var tb = Object.keys(O.ballotsCache).length;
        var el = O.attendanceCache.filter(function(a) { return a.mode === 'present' || a.mode === 'remote'; }).length +
                 O.proxiesCache.filter(function(p) { return !p.revoked_at; }).length;
        var p = el > 0 ? Math.round((tb / el) * 100) : 0;
        barFill.style.width = p + '%';
        barPct.textContent = p + '%';
      } else {
        barFill.style.width = '0%';
        barPct.textContent = '—';
      }
    }
  }

  function refreshExecView() {
    refreshExecKPIs();
    refreshExecVote();
    refreshExecSpeech();
    refreshExecDevices();
    refreshExecManualVotes();
    O.fn.refreshAlerts();
    O.fn.updateExecCloseSession();
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

    if (O.currentOpenMotion) {
      if (noVotePanel) Shared.hide(noVotePanel);
      if (activeVotePanel) activeVotePanel.hidden = false;

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
      if (barFor) barFor.style.width = pctFor + '%';
      if (barAgainst) barAgainst.style.width = pctAgainst + '%';
      if (barAbstain) barAbstain.style.width = pctAbstain + '%';

      var pFor = document.getElementById('opPctFor');
      var pAgainst = document.getElementById('opPctAgainst');
      var pAbstain = document.getElementById('opPctAbstain');
      if (pFor) pFor.textContent = pctFor + '%';
      if (pAgainst) pAgainst.textContent = pctAgainst + '%';
      if (pAbstain) pAbstain.textContent = pctAbstain + '%';
    } else {
      if (noVotePanel) Shared.show(noVotePanel, 'block');
      if (activeVotePanel) activeVotePanel.hidden = true;
      if (liveBadge) Shared.hide(liveBadge);
      if (btnClose) { btnClose.disabled = true; Shared.hide(btnClose); }
      renderExecQuickOpenList();
    }
  }

  function renderExecQuickOpenList() {
    var list = document.getElementById('execQuickOpenList');
    if (!list) return;

    var isLive = O.currentMeetingStatus === 'live';
    var openableMotions = O.motionsCache.filter(function(m) { return !m.opened_at && !m.closed_at; });

    if (!isLive || openableMotions.length === 0) {
      list.innerHTML = isLive
        ? '<p class="text-muted text-sm">Aucune résolution en attente</p>'
        : '<p class="text-muted text-sm">La séance doit être en cours pour ouvrir un vote</p>';
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
        var name = escapeHtml(O.currentSpeakerCache.full_name || '—');
        var startTime = O.currentSpeakerCache.updated_at ? new Date(O.currentSpeakerCache.updated_at).getTime() : Date.now();
        speakerInfo.innerHTML =
          '<div class="exec-speaker-active">' +
            '<svg class="icon icon-text exec-speaker-mic" aria-hidden="true"><use href="/assets/icons.svg#icon-mic"></use></svg>' +
            '<strong>' + name + '</strong>' +
            '<span class="exec-speaker-timer" id="execSpeakerTimer">00:00</span>' +
          '</div>';
        function updateExecTimer() {
          var el = document.getElementById('execSpeakerTimer');
          if (!el) return;
          var elapsed = Math.floor((Date.now() - startTime) / 1000);
          var mm = String(Math.floor(elapsed / 60)).padStart(2, '0');
          var ss = String(elapsed % 60).padStart(2, '0');
          el.textContent = mm + ':' + ss;
        }
        updateExecTimer();
        O.execSpeechTimerInterval = setInterval(updateExecTimer, 1000);
      } else {
        speakerInfo.innerHTML = '<span class="text-sm text-muted">Aucun orateur</span>';
      }
    }

    if (actionsEl) {
      actionsEl.style.display = O.currentSpeakerCache ? '' : 'none';
    }

    if (queueList) {
      if (O.speechQueueCache.length === 0) {
        queueList.innerHTML = '<span class="text-muted text-sm">File vide</span>';
      } else {
        queueList.innerHTML = '<div class="text-sm text-muted mb-1">File (' + O.speechQueueCache.length + ') :</div>' +
          O.speechQueueCache.slice(0, 5).map(function(s, i) {
            return '<div class="text-sm">' + (i + 1) + '. ' + escapeHtml(s.full_name || '—') + '</div>';
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
    }).join('') + (remaining > 0 ? '<div class="text-xs text-muted text-center mt-2">+ ' + remaining + ' votants non affichés</div>' : '')
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

  // Register on OpS — overwrites the stubs from operator-tabs.js
  O.fn.refreshExecKPIs         = refreshExecKPIs;
  O.fn.refreshExecView         = refreshExecView;
  O.fn.refreshExecVote         = refreshExecVote;
  O.fn.renderExecQuickOpenList = renderExecQuickOpenList;
  O.fn.refreshExecSpeech       = refreshExecSpeech;
  O.fn.refreshExecDevices      = refreshExecDevices;
  O.fn.refreshExecManualVotes  = refreshExecManualVotes;

})();
