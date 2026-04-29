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
  var sseConnected = false;
  var _sseFallbackToastEl = null;
  var _prevQuorumMet = null;
  var _operatorPresenceCount = 0;
  var _presenceHeartbeatTimer = null;

  // =========================================================================
  // SSE INDICATOR (OPC-02)
  // =========================================================================

  var SSE_LABELS = { live: '\u25cf En direct', reconnecting: '\u26a0 Reconnexion...', offline: '\u2715 Hors ligne' };

  function setSseIndicator(state) {
    var el = document.getElementById('opSseIndicator');
    var lb = document.getElementById('opSseLabel');
    if (el) el.setAttribute('data-sse-state', state);
    if (lb) lb.textContent = SSE_LABELS[state] || state;
    // Sync checklist SSE row + banner (CHECK-03)
    if (O.fn.updateChecklistSseRow) O.fn.updateChecklistSseRow(state);
  }

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
        if (O.currentMode === 'exec') O.fn.refreshExecView();
      }).catch(function(err) {
        setNotif('error', 'Erreur temps reel : ' + (err && err.message ? err.message : 'Connexion perdue'));
      });
      break;

    case 'attendance.updated':
      O.fn.loadQuorumStatus();
      if (O.currentMode === 'setup') O.fn.loadDashboard();
      if (O.currentMode === 'exec' && O.fn.refreshExecChecklist) O.fn.refreshExecChecklist();
      break;

    case 'quorum.updated':
      O.fn.loadQuorumStatus();
      if (O.currentMode === 'setup') O.fn.loadDashboard();
      var nowMet = data && (data.quorum_met === true || (data.data && data.data.quorum_met === true));
      if (_prevQuorumMet === false && nowMet === true) {
        setNotif('success', 'Quorum atteint !');
      }
      _prevQuorumMet = nowMet ? true : (nowMet === false ? false : _prevQuorumMet);
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
        var indicator = document.getElementById('opSseIndicator');
        if (indicator && indicator.parentElement) indicator.parentElement.appendChild(badge);
      }
      badge.textContent = count + ' opérateur(s) actif(s)';
      badge.style.display = '';
    } else if (badge) {
      badge.style.display = 'none';
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
