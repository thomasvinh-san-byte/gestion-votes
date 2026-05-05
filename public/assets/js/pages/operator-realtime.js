/**
 * operator-realtime.js — Real-time updates (SSE + polling) for the operator console.
 * Requires: utils.js, shared.js, operator-tabs.js (OpS bridge)
 */
(function() {
  'use strict';

  var O = window.OpS;

  // =========================================================================
  // Real-time updates: SSE (primary) with polling fallback
  // =========================================================================

  var POLL_FAST = 5000;  // 5s when vote is active
  var POLL_SLOW = 15000; // 15s otherwise
  var pollTimer = null;
  var pollRunning = false;
  var newVoteDebounceTimer = null;
  var sseStream = null;
  // Track quorum.met across heartbeats so we can fire the "Quorum atteint !"
  // toast on the false → true transition (avoids re-firing on every tick).
  var _prevQuorumMet = null;
  var sseConnected = false;
  var _sseFallbackToastEl = null;
  var _operatorPresenceCount = 0;
  var _presenceHeartbeatTimer = null;

  // =========================================================================
  // <ag-health-bar> attribute helpers (Phase v2.3 / COCKPIT-01..05,07)
  // =========================================================================

  /**
   * Derive the ag-health-bar quorum-state from current/required ratio.
   * - missed: current < required
   * - at-risk: required <= current < required * 1.10  (within 10% buffer above threshold)
   * - met: current >= required * 1.10
   * Per COCKPIT-07.
   */
  function _computeQuorumState(current, required) {
    var c = Number(current); var r = Number(required);
    if (!isFinite(c) || !isFinite(r) || r <= 0) return 'met';
    if (c < r) return 'missed';
    if (c < r * 1.10) return 'at-risk';
    return 'met';
  }
  function _hb() { return document.getElementById('opHealthBar'); }
  function _setHb(attr, val) {
    var el = _hb();
    if (!el) return;
    var s = (val == null || val === '') ? '' : String(val);
    if (el.getAttribute(attr) !== s) el.setAttribute(attr, s);
    // F-2: mirror quorum-state onto #viewExec so the danger pulse can target the vote zone
    // (per ROADMAP SC #2 + COCKPIT-03 \u2014 pulse "entoure la zone vote", not the bar itself).
    if (attr === 'quorum-state') {
      var view = document.getElementById('viewExec');
      if (view && view.getAttribute('data-quorum-state') !== s) view.setAttribute('data-quorum-state', s);
    }
  }

  // =========================================================================
  // <viewExec> data-vote-state mirror (Phase v2.4 / COCKPIT-V24-01 / D-04)
  // =========================================================================
  /**
   * Compute current vote-state from O.currentOpenMotion and write to #viewExec.
   * Pattern h\u00e9rit\u00e9 de _setHb(quorum-state) v2.3 P1 \u2014 single mirror, no event wiring
   * required, safe to call multiple times (no-op if attribute unchanged).
   *
   * Values:
   *   idle    \u2014 aucun vote actif (no currentOpenMotion)
   *   open    \u2014 vote ouvert (currentOpenMotion && !closed_at)
   *   closed  \u2014 vote ferm\u00e9 non proclam\u00e9 (currentOpenMotion && closed_at)
   */
  function _computeVoteState() {
    var m = window.O && window.O.currentOpenMotion;
    if (!m) return 'idle';
    if (m.closed_at) return 'closed';
    return 'open';
  }
  function _setVoteState(forced) {
    var view = document.getElementById('viewExec');
    if (!view) return;
    var s = (forced != null) ? String(forced) : _computeVoteState();
    if (view.getAttribute('data-vote-state') !== s) view.setAttribute('data-vote-state', s);
  }
  // Expose for operator-exec.js refreshExecView() to call on every refresh tick
  window.O = window.O || {}; window.O.fn = window.O.fn || {};
  window.O.fn.syncVoteState = _setVoteState;

  // =========================================================================
  // SSE INDICATOR \u2014 drives <ag-health-bar sse-state="..."> (single surface)
  // Legacy DOM SSE indicator removed in Plan 01.3 (F-6) \u2014 the ambient pill on
  // the health bar is now the canonical SSE state surface.
  // =========================================================================

  function setSseIndicator(state) {
    _setHb('sse-state', state);
    // Sync checklist SSE row + banner (CHECK-03)
    if (O.fn.updateChecklistSseRow) O.fn.updateChecklistSseRow(state);
  }

  // =========================================================================
  // <ag-health-bar id="opHealthBar"> motion-change hook (Plan 01.3 / F-5)
  // Called from operator-motions.js whenever O.currentOpenMotion is set/cleared.
  // Pushes motion-number + motion-title attributes onto #opHealthBar.
  // COCKPIT-05 reactive-attribute contract.
  // =========================================================================

  window.O = window.O || {}; window.O.fn = window.O.fn || {};
  window.O.fn.notifyMotionChange = function() {
    var m = window.O.currentOpenMotion;
    if (m) {
      _setHb('motion-number', m.number || m.short_id || '');
      var t = m.title || '';
      _setHb('motion-title', t.length > 80 ? t.slice(0, 77) + '\u2026' : t);
    } else {
      _setHb('motion-number', '');
      _setHb('motion-title', 'Aucune r\u00e9solution active');
    }
  };

  /**
   * Try to connect SSE for the current meeting. If SSE is available
   * (EventStream loaded + Redis + meeting selected), events trigger
   * immediate data refresh instead of waiting for the next poll cycle.
   * Polling continues at a slower rate as a safety net.
   */
  function connectSSE() {
    if (!window.EventStream || !O.currentMeetingId) return;

    // Close previous connection
    if (sseStream) sseStream.close();

    sseStream = EventStream.connect(O.currentMeetingId, {
      onConnect: function() {
        sseConnected = true;
        setSseIndicator('live');
        // Dismiss fallback toast if it exists
        if (_sseFallbackToastEl) {
          _sseFallbackToastEl.dismiss();
          _sseFallbackToastEl = null;
        }
        console.info('[operator] SSE connected for meeting', O.currentMeetingId);
        // Start presence heartbeat to renew Redis TTL every 60s
        if (_presenceHeartbeatTimer) clearInterval(_presenceHeartbeatTimer);
        _presenceHeartbeatTimer = setInterval(function() {
          if (sseConnected && O.currentMeetingId) {
            fetch('/api/v1/events.php?meeting_id=' + encodeURIComponent(O.currentMeetingId) + '&heartbeat=1', {
              method: 'HEAD', credentials: 'same-origin'
            }).catch(function(){});
          }
        }, 60000);
      },
      onDisconnect: function() {
        sseConnected = false;
        setSseIndicator('reconnecting');
        clearInterval(_presenceHeartbeatTimer);
        // If still disconnected after 5s, show offline state
        setTimeout(function() {
          if (!sseConnected) setSseIndicator('offline');
        }, 5000);
      },
      onFallback: function() {
        sseConnected = false;
        setSseIndicator('offline');
        // Show persistent fallback notification (duration=0 = no auto-dismiss)
        if (typeof AgToast !== 'undefined' && AgToast.show) {
          _sseFallbackToastEl = AgToast.show('warning', 'Connexion temps reel interrompue — passage en mode poll', 0);
        } else {
          setNotif('warning', 'Connexion temps reel interrompue — passage en mode poll', 0);
        }
      },
      onEvent: function(type, data) {
        handleSSEEvent(type, data);
      },
    });
  }

  /**
   * Handle a single SSE event by refreshing the relevant data.
   */
  function handleSSEEvent(type, data) {
    if (!O.currentMeetingId) return;

    switch (type) {
    case 'vote.cast':
    case 'vote.updated':
      if (data.motion_id || (data.data && data.data.motion_id)) {
        var motionId = data.motion_id || data.data.motion_id;
        O.fn.loadBallots(motionId).then(function() {
          if (O.currentMode === 'exec') O.fn.refreshExecView();
        }).catch(function(err) {
          setNotif('error', 'Erreur temps reel : ' + (err && err.message ? err.message : 'Connexion perdue'));
        });
      }
      break;

    case 'motion.opened':
      O.fn.loadResolutions().then(function() {
        _setVoteState(); // v2.4 D-04 mirror
        if (O.currentOpenMotion) {
          var title = O.currentOpenMotion.title;
          setNotif('info', 'Vote ouvert: ' + title);
          O.announce('Vote ouvert : ' + title);
          if (O.currentMeetingStatus === 'live' && O.currentMode !== 'exec') {
            O.fn.loadBallots(O.currentOpenMotion.id).then(function() { O.fn.setMode('exec'); }).catch(function(err) {
              setNotif('error', 'Erreur temps reel : ' + (err && err.message ? err.message : 'Connexion perdue'));
            });
          } else if (O.currentMode === 'exec') {
            O.fn.loadBallots(O.currentOpenMotion.id).then(function() { O.fn.refreshExecView(); }).catch(function(err) {
              setNotif('error', 'Erreur temps reel : ' + (err && err.message ? err.message : 'Connexion perdue'));
            });
          }
        }
      }).catch(function(err) {
        setNotif('error', 'Erreur temps reel : ' + (err && err.message ? err.message : 'Connexion perdue'));
      });
      break;

    case 'motion.closed':
    case 'motion.updated':
      O.fn.loadResolutions().then(function() {
        _setVoteState(); // v2.4 D-04 mirror
        if (O.currentMode === 'exec') O.fn.refreshExecView();
      }).catch(function(err) {
        setNotif('error', 'Erreur temps reel : ' + (err && err.message ? err.message : 'Connexion perdue'));
      });
      break;

    case 'attendance.updated':
      O.fn.loadQuorumStatus();
      if (O.currentMode === 'setup') O.fn.loadDashboard();
      if (O.currentMode === 'exec' && O.fn.refreshExecChecklist) O.fn.refreshExecChecklist();
      // Drive votes-remaining attribute on the health bar (Plan 01.3 / COCKPIT-05).
      try {
        var motion = window.O && window.O.currentOpenMotion;
        if (motion) {
          var cast = motion.votes_cast != null ? motion.votes_cast : 0;
          var totalVoters = (data && data.data && data.data.total) || (data && data.total) || motion.total_voters || 0;
          if (totalVoters > 0) {
            var remaining = Math.max(0, totalVoters - cast);
            _setHb('votes-remaining', remaining + ' / ' + totalVoters);
          }
        }
      } catch (e) { /* swallow — health bar update is best-effort */ }
      break;

    case 'quorum.updated':
      O.fn.loadQuorumStatus();
      if (O.currentMode === 'setup') O.fn.loadDashboard();
      // Drive quorum-state + quorum-ratio attributes on the health bar (Plan 01.3 / COCKPIT-02,07).
      // Legacy success toast removed: persistent indicator replaces ephemeral notification.
      var p = (data && data.data) ? data.data : (data || {});
      var qCur = (p.quorum_current != null) ? p.quorum_current : (p.present_count != null ? p.present_count : null);
      var qReq = p.quorum_required != null ? p.quorum_required : null;
      if (qCur != null && qReq != null) {
        _setHb('quorum-state', _computeQuorumState(qCur, qReq));
        _setHb('quorum-ratio', qCur + ' / ' + qReq);
      }
      break;

    case 'operator.presence':
      var cnt = (data && data.count) ? parseInt(data.count, 10) : 0;
      if (!isNaN(cnt)) updateOperatorPresenceBadge(cnt);
      break;

    case 'speech.queue_updated':
      O.fn.loadSpeechQueue();
      break;

    case 'meeting.status_changed':
      O.fn.loadResolutions();
      O.fn.loadStatusChecklist();
      O.fn.loadDashboard();
      break;

    case 'meeting.heartbeat':
      applyHeartbeat(data);
      break;

    default:
      schedulePoll(200);
      break;
    }

    // Forward to notification toast system
    if (window.Notifications && window.Notifications.handleSseEvent) {
      window.Notifications.handleSseEvent(type, data);
    }
  }

  /**
   * Update/create the multi-operator presence badge.
   * Badge appears when >1 operator is connected to the same meeting.
   * @param {number} count - Number of active operators
   */
  function updateOperatorPresenceBadge(count) {
    _operatorPresenceCount = count;
    var badge = document.getElementById('opPresenceBadge');
    if (count > 1) {
      if (!badge) {
        badge = document.createElement('span');
        badge.id = 'opPresenceBadge';
        badge.style.cssText = 'font-size:0.75rem;margin-left:0.75rem;background:var(--color-warning-bg,#fef3c7);color:var(--color-warning,#92400e);border-radius:0.375rem;padding:0.125rem 0.5rem;';
        // Plan 01.3 / F-6 — legacy DOM SSE indicator removed; anchor to meeting bar right cluster.
        var anchor = document.querySelector('.op-meeting-bar-right') || document.getElementById('opHealthBar');
        if (anchor) anchor.appendChild(badge);
        else document.body.appendChild(badge);
      }
      badge.textContent = count + ' opérateur(s) actif(s)';
      badge.style.display = '';
    } else if (badge) {
      badge.style.display = 'none';
    }
  }

  // Heartbeat pulse: renders quorum + presence + status from a server snapshot
  // every 10s. Idempotent — safe to call repeatedly. Skips repaints when payload
  // is identical to last known state.
  var _lastHeartbeatHash = null;
  function applyHeartbeat(data) {
    if (!data || typeof data !== 'object') return;

    var hash = JSON.stringify({
      s: data.status, q: data.quorum, o: data.operator_count,
    });
    if (hash === _lastHeartbeatHash) return;
    _lastHeartbeatHash = hash;

    if (typeof data.operator_count === 'number') {
      updateOperatorPresenceBadge(data.operator_count);
    }

    if (data.quorum && data.quorum.applied) {
      var badge = document.getElementById('quorumStatusBadge');
      var detail = document.getElementById('quorumStatusDetail');
      var card = document.getElementById('quorumStatusCard');
      if (badge) {
        badge.textContent = (data.quorum.met === true) ? 'Atteint' : (data.quorum.met === false ? 'Non atteint' : '—');
        badge.classList.remove('badge-success', 'badge-warning', 'badge-danger');
        badge.classList.add(data.quorum.met === true ? 'badge-success' : 'badge-warning');
      }
      if (detail) {
        var pres = data.quorum.present_members || 0;
        var elig = data.quorum.eligible_members || 0;
        detail.textContent = pres + ' présent' + (pres > 1 ? 's' : '') + ' sur ' + elig;
      }
      if (card && card.hasAttribute('hidden')) card.removeAttribute('hidden');

      var nowMet = data.quorum.met === true;
      if (_prevQuorumMet === false && nowMet) {
        setNotif('success', 'Quorum atteint !');
      }
      _prevQuorumMet = nowMet;
    }

    if (data.server_time) {
      var ts = document.getElementById('opSseTimestamp');
      if (ts) ts.textContent = new Date(data.server_time).toLocaleTimeString('fr-FR');
    }
  }

  function schedulePoll(ms) {
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(autoPoll, ms);
  }

  async function autoPoll() {
    pollTimer = null;

    if (!O.currentMeetingId || document.hidden) {
      schedulePoll(POLL_SLOW);
      return;
    }

    if (pollRunning) return;
    pollRunning = true;

    try {
      var activeTab = document.querySelector('.tab-btn.active');
      var activeTabId = activeTab ? activeTab.dataset.tab : null;
      var onVoteTab = activeTabId === 'vote';

      O.fn.loadSpeechQueue();
      await O.fn.loadResolutions();

      if (O.currentMode === 'setup') {
        O.fn.loadStatusChecklist();
        O.fn.loadDashboard();
        O.fn.loadDevices();
      } else {
        O.fn.loadDevices();
      }

      var isVoteActive = !!O.currentOpenMotion;
      var currentMotionId = O.currentOpenMotion ? O.currentOpenMotion.id : null;

      // Detect if a new vote was opened (not by us) — debounced
      if (!sseConnected && isVoteActive && currentMotionId !== O.previousOpenMotionId) {
        if (newVoteDebounceTimer) clearTimeout(newVoteDebounceTimer);
        var motionTitle = O.currentOpenMotion.title;
        newVoteDebounceTimer = setTimeout(function() {
          setNotif('info', 'Vote ouvert: ' + motionTitle);
          O.announce('Vote ouvert : ' + motionTitle);
          if (O.currentMode === 'exec') {
            O.fn.loadBallots(O.currentOpenMotion.id).then(function() { O.fn.refreshExecView(); });
          } else if (O.currentMeetingStatus === 'live') {
            O.fn.loadBallots(O.currentOpenMotion.id).then(function() { O.fn.setMode('exec'); });
          } else {
            O.fn.switchTab('vote');
          }
        }, 500);
      }

      O.previousOpenMotionId = currentMotionId;

      if (isVoteActive && O.currentOpenMotion) {
        await O.fn.loadBallots(O.currentOpenMotion.id);
        if (O.currentMode === 'setup' && onVoteTab) {
          var noVote = document.getElementById('noActiveVote');
          var panel = document.getElementById('activeVotePanel');
          var title = document.getElementById('activeVoteTitle');
          if (noVote) Shared.hide(noVote);
          if (panel) Shared.show(panel, 'block');
          if (title) title.textContent = O.currentOpenMotion.title;
          O.fn.renderManualVoteList();
        }
      } else if (onVoteTab) {
        O.fn.loadVoteTab();
      }

      if (onVoteTab) O.fn.loadQuorumStatus();

      O.fn.renderConformityChecklist();
      O.fn.refreshAlerts();
      if (O.currentMode === 'exec') O.fn.refreshExecView();

      var baseInterval = isVoteActive ? POLL_FAST : POLL_SLOW;
      var interval = sseConnected ? baseInterval * 3 : baseInterval;
      schedulePoll(interval);
    } catch (err) {
      // autoPoll error — falling back to slow poll
      schedulePoll(POLL_SLOW);
    } finally {
      pollRunning = false;
    }
  }

  // Refresh immediately when tab becomes visible
  document.addEventListener('visibilitychange', function() {
    if (!document.hidden && O.currentMeetingId) {
      schedulePoll(100);
    }
  });

  // Cleanup on page unload (timers owned by this module only;
  // speechTimerInterval/execSpeechTimerInterval are cleaned by operator-tabs.js)
  window.addEventListener('beforeunload', function() {
    if (pollTimer) clearTimeout(pollTimer);
    if (newVoteDebounceTimer) clearTimeout(newVoteDebounceTimer);
    if (_sseDebounceTimer) clearTimeout(_sseDebounceTimer);
    if (sseStream) sseStream.close();
  });

  // Register on OpS
  O.fn.connectSSE   = connectSSE;
  O.fn.schedulePoll = schedulePoll;

  // SSE lifecycle driven by MeetingContext — per user decision:
  // debounced reconnect (300ms), close on meeting cleared
  var _sseDebounceTimer = null;

  window.addEventListener(MeetingContext.EVENT_NAME, function(e) {
    var newId = e.detail ? e.detail.newId : null;
    if (_sseDebounceTimer) clearTimeout(_sseDebounceTimer);

    if (!newId) {
      // Meeting cleared — disconnect SSE and stop polling
      if (sseStream) { sseStream.close(); sseStream = null; sseConnected = false; }
      if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
      setSseIndicator('offline');
      return;
    }

    _sseDebounceTimer = setTimeout(function() {
      _sseDebounceTimer = null;
      connectSSE();
    }, 300);
  });

  // Start polling (SSE connects via MeetingContext:change event)
  schedulePoll(POLL_SLOW);

})();
