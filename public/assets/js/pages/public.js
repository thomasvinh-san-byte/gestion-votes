/**
 * public.js — Public projection screen logic for AG-VOTE.
 *
 * Standalone page (no shell.js dependency). Uses raw fetch with
 * API key injection. Handles: live polling, bar chart animation,
 * meeting picker, theme toggle, fullscreen, heartbeat.
 */
function escapeHtml(s) {
  if (s == null) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

window.APP_API_KEY = window.APP_API_KEY
  || new URLSearchParams(location.search).get("api_key")
  || (function(){ try { return sessionStorage.getItem("api_key"); } catch(e){ return null; } })()
  || "";
// Persist to sessionStorage (not localStorage) so it survives page reloads but not new sessions
if (window.APP_API_KEY) { try { sessionStorage.setItem("api_key", window.APP_API_KEY); } catch(e){} }

var MEETING_ID = new URLSearchParams(location.search).get('meeting_id') || null;
var currentMotionId = null;

function updateUrl() {
  var url = new URL(location.href);
  if (MEETING_ID) { url.searchParams.set('meeting_id', MEETING_ID); }
  else { url.searchParams.delete('meeting_id'); }
  history.replaceState(null, '', url);
}

function showMeetingPicker(meetings) {
  var list = document.getElementById('meeting_picker_list');
  list.innerHTML = '';
  if (!meetings || meetings.length === 0) {
    var msg = document.createElement('p');
    msg.className = 'meeting-picker-empty';
    msg.textContent = 'Aucune séance en cours. La page se rafraîchit automatiquement.';
    list.appendChild(msg);
  } else {
    meetings.forEach(function(m) {
      var btn = document.createElement('button');
      btn.className = 'meeting-picker-card';
      var time = m.started_at ? new Date(m.started_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';
      btn.innerHTML = '<span class="meeting-picker-card-title">' + escapeHtml(m.title || 'Sans titre') + '</span>'
        + (time ? '<span class="meeting-picker-card-time">Démarrée à ' + escapeHtml(time) + '</span>' : '');
      btn.addEventListener('click', function() { selectMeeting(m.id); });
      list.appendChild(btn);
    });
  }
  document.getElementById('meeting_picker').hidden = false;
}

function hideMeetingPicker() {
  document.getElementById('meeting_picker').hidden = true;
}

function selectMeeting(id) {
  MEETING_ID = id;
  updateUrl();
  hideMeetingPicker();
  document.getElementById('btnChangeMeeting').hidden = false;
  refresh();
}

function changeMeeting() {
  MEETING_ID = null;
  currentMotionId = null;
  updateUrl();
  document.getElementById('btnChangeMeeting').hidden = true;
  refresh();
}

// Inject API key — only for same-origin requests to avoid leaking key cross-origin
(function() {
  var originalFetch = window.fetch.bind(window);
  window.fetch = function(input, init) {
    var url = (typeof input === "string") ? input : (input?.url || "");
    if (!url.includes("/api/")) return originalFetch(input, init);
    // Only inject API key for relative URLs or same-origin absolute URLs
    try {
      var parsed = new URL(url, window.location.origin);
      if (parsed.origin !== window.location.origin) return originalFetch(input, init);
    } catch (e) { /* relative URL — safe */ }
    var headers = new Headers(init?.headers || {});
    if (!headers.has("X-Api-Key")) headers.set("X-Api-Key", window.APP_API_KEY);
    return originalFetch(input, { ...init, headers: headers });
  };
})();

// Clock
function startClock() {
  var el = document.getElementById('clock');
  var update = function() {
    el.textContent = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  };
  update();
  setInterval(update, 1000);
}

// Announce to screen reader
function announce(msg) {
  var el = document.getElementById('sr_alert');
  if (el) { el.textContent = msg; setTimeout(function() { el.textContent = ''; }, 1000); }
}

// Show/hide elements
function show(id, visible) {
  var el = document.getElementById(id);
  if (el) el.classList.toggle('visible', visible);
}

// Format weight: 1.0000 → "1", 1.5 → "1.5"
function fmtW(v) { var n = parseFloat(v); return isNaN(n) ? '0' : (Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '')); }

// Animate bars
function animateBars(forPct, againstPct, abstainPct, forCount, againstCount, abstainCount, forWeight, againstWeight, abstainWeight) {
  var maxHeight = 140; // max bar height in px (matches CSS .bar-wrapper height - margin)

  // Update percentages and counts
  document.getElementById('pct_for').textContent = forPct.toFixed(0) + '%';
  document.getElementById('pct_against').textContent = againstPct.toFixed(0) + '%';
  document.getElementById('pct_abstain').textContent = abstainPct.toFixed(0) + '%';

  document.getElementById('count_for').textContent = fmtW(forWeight);
  document.getElementById('count_against').textContent = fmtW(againstWeight);
  document.getElementById('count_abstain').textContent = fmtW(abstainWeight);

  // Animate bar heights
  requestAnimationFrame(function() {
    document.getElementById('bar_for_fill').style.height = (forPct / 100 * maxHeight) + 'px';
    document.getElementById('bar_against_fill').style.height = (againstPct / 100 * maxHeight) + 'px';
    document.getElementById('bar_abstain_fill').style.height = (abstainPct / 100 * maxHeight) + 'px';

    // Trigger label animation
    setTimeout(function() {
      document.getElementById('bar_for').classList.add('animate');
      document.getElementById('bar_against').classList.add('animate');
      document.getElementById('bar_abstain').classList.add('animate');
    }, 100);
  });
}

// Reset bars
function resetBars() {
  document.getElementById('bar_for_fill').style.height = '0';
  document.getElementById('bar_against_fill').style.height = '0';
  document.getElementById('bar_abstain_fill').style.height = '0';
  document.getElementById('bar_for').classList.remove('animate');
  document.getElementById('bar_against').classList.remove('animate');
  document.getElementById('bar_abstain').classList.remove('animate');
}

// Load results
async function loadResults(motionId, reveal) {
  if (reveal === undefined) reveal = true;
  if (!motionId) {
    show('chart_container', false);
    show('decision_section', false);
    show('secret_block', false);
    resetBars();
    return;
  }

  try {
    var res = await fetch('/api/v1/ballots_result.php?motion_id=' + encodeURIComponent(motionId));
    var data = await res.json().catch(function() { return null; });

    if (!data || data.ok === false) {
      show('chart_container', false);
      show('decision_section', false);
      return;
    }

    var d = data.data || data;
    var isSecret = !!(d.motion?.secret);
    var t = d.tallies || {};
    var quorum = d.quorum || {};
    var decision = d.decision || {};

    // Secret vote - only show participation
    if (isSecret && !reveal) {
      show('chart_container', false);
      show('decision_section', false);
      show('secret_block', true);

      var expW = d.expressed?.weight ?? 0;
      var eligW = d.eligible?.weight ?? 1;
      var pct = (eligW > 0) ? Math.min(100, Math.max(0, expW / eligW * 100)) : 0;

      document.getElementById('participation_bar').style.width = pct.toFixed(0) + '%';
      document.getElementById('participation_pct').textContent = pct.toFixed(0);
      return;
    }

    // Normal results
    show('secret_block', false);
    show('chart_container', true);
    show('decision_section', true);

    var forCount = t.for?.count ?? 0;
    var againstCount = t.against?.count ?? 0;
    var abstainCount = t.abstain?.count ?? 0;
    var forWeight = t.for?.weight ?? 0;
    var againstWeight = t.against?.weight ?? 0;
    var abstainWeight = t.abstain?.weight ?? 0;

    var totalWeight = forWeight + againstWeight + abstainWeight || 1;
    var forPct = forWeight / totalWeight * 100;
    var againstPct = againstWeight / totalWeight * 100;
    var abstainPct = abstainWeight / totalWeight * 100;

    animateBars(forPct, againstPct, abstainPct, forCount, againstCount, abstainCount, forWeight, againstWeight, abstainWeight);

    // Decision - transform technical status to user-friendly labels
    var decisionEl = document.getElementById('decision_value');
    var statusLabels = {
      'adopted': 'Adopté',
      'rejected': 'Rejeté',
      'no_votes': 'En attente',
      'no_quorum': 'Quorum non atteint',
      'no_policy': 'Sans politique',
      'pending': 'En cours'
    };
    var statusText = statusLabels[decision.status] || decision.status || '\u2014';
    decisionEl.textContent = statusText;
    decisionEl.className = 'decision-value ' + (decision.status === 'adopted' ? 'adopted' : decision.status === 'rejected' ? 'rejected' : 'pending');

    // Hide technical reason for no_votes status
    var reasonText = decision.status === 'no_votes' ? '' : (decision.reason || '');
    document.getElementById('decision_detail').textContent = reasonText;

    // Quorum
    var quorumEl = document.getElementById('quorum_value');
    if (quorum.applied) {
      quorumEl.textContent = quorum.met ? 'Atteint' : 'Non atteint';
      quorumEl.className = 'decision-value ' + (quorum.met ? 'adopted' : 'rejected');
      document.getElementById('quorum_detail').textContent = Math.round((quorum.ratio ?? 0) * 100) + '% / ' + Math.round((quorum.threshold ?? 0) * 100) + '%';
    } else {
      quorumEl.textContent = 'N/A';
      quorumEl.className = 'decision-value pending';
      document.getElementById('quorum_detail').textContent = '';
    }

  } catch (e) {
    console.error('loadResults error:', e);
  }
}

// Update resolution text box
function updateResolution(motionData) {
  var box = document.getElementById('resolution_box');
  var text = document.getElementById('resolution_text');
  var num = document.getElementById('motion_number');

  if (!motionData) {
    show('resolution_box', false);
    if (num) num.textContent = '';
    return;
  }

  // Motion number
  if (num) {
    num.textContent = motionData.position ? 'Resolution n\u00b0' + motionData.position : '';
  }

  // Resolution body text
  var content = motionData.body || motionData.description || '';
  if (content) {
    text.textContent = content;
    show('resolution_box', true);
  } else {
    show('resolution_box', false);
  }
}

// Update footer timestamp
function updateTimestamp() {
  var el = document.getElementById('footer_update');
  if (el) {
    el.textContent = 'Mis \u00e0 jour : ' + new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }
}

// Refresh screen
async function refresh() {
  var badge = document.getElementById('badge');
  var meet = document.getElementById('meeting_title');
  var motion = document.getElementById('motion_title');
  var sub = document.getElementById('motion_sub');
  var err = document.getElementById('error_box');

  try {
    var apiUrl = MEETING_ID
      ? '/api/v1/projector_state.php?meeting_id=' + encodeURIComponent(MEETING_ID)
      : '/api/v1/projector_state.php';
    var res = await fetch(apiUrl);
    var data = await res.json();

    if (!data.ok) {
      if (data.error === 'no_live_meeting') {
        badge.className = 'status-badge off';
        badge.textContent = 'hors seance';
        meet.textContent = '';
        motion.textContent = 'Aucune seance en cours';
        sub.textContent = '';
        err.classList.remove('visible');
        show('waiting_state', false);
        show('chart_container', false);
        show('decision_section', false);
        show('secret_block', false);
        hideMeetingPicker();
        updateResolution(null);
        resetBars();
        var mcEl = document.getElementById('motionCounter');
        if (mcEl) mcEl.hidden = true;
        var vtEl = document.getElementById('voterTotal');
        if (vtEl) vtEl.hidden = true;
        document.getElementById('btnChangeMeeting').hidden = true;
        updateTimestamp();
        return;
      }
      if (data.error === 'meeting_not_found') {
        // Selected meeting no longer available — reset and re-detect
        MEETING_ID = null;
        currentMotionId = null;
        updateUrl();
        document.getElementById('btnChangeMeeting').hidden = true;
        refresh();
        return;
      }
      throw new Error(data.error || 'Erreur inconnue');
    }

    var s = data.data;

    // Multiple live meetings — show picker
    if (s.choose) {
      showMeetingPicker(s.meetings);
      badge.className = 'status-badge idle';
      badge.textContent = 'selection';
      meet.textContent = '';
      motion.textContent = '';
      sub.textContent = '';
      show('waiting_state', false);
      show('chart_container', false);
      show('decision_section', false);
      show('secret_block', false);
      updateResolution(null);
      resetBars();
      updateTimestamp();
      return;
    }

    hideMeetingPicker();
    err.classList.remove('visible');
    meet.textContent = s.meeting_title || '';

    // Auto-lock to this meeting for subsequent polls
    if (!MEETING_ID && s.meeting_id) {
      MEETING_ID = s.meeting_id;
      updateUrl();
    }
    document.getElementById('btnChangeMeeting').hidden = !MEETING_ID;

    // --- KPI: Motion counter ---
    var motionCounterEl = document.getElementById('motionCounter');
    var motionCounterText = document.getElementById('motionCounterText');
    if (motionCounterEl && motionCounterText) {
      var motionIndex = s.motion_index ?? s.motion?.position ?? null;
      var totalMotions = s.total_motions ?? s.motions_count ?? null;
      if (motionIndex != null && totalMotions != null) {
        motionCounterText.textContent = 'R\u00e9solution ' + motionIndex + ' / ' + totalMotions;
        motionCounterEl.hidden = false;
      } else {
        motionCounterEl.hidden = true;
      }
    }

    // --- KPI: Voter total ---
    var voterTotalEl = document.getElementById('voterTotal');
    var voterTotalText = document.getElementById('voterTotalText');
    if (voterTotalEl && voterTotalText) {
      var voterCount = s.eligible_count ?? s.voter_count ?? null;
      if (voterCount != null) {
        voterTotalText.textContent = voterCount + ' votant' + (voterCount !== 1 ? 's' : '');
        voterTotalEl.hidden = false;
      } else {
        voterTotalEl.hidden = true;
      }
    }

    if (s.phase === 'active' && s.motion?.id) {
      // Vote in progress
      badge.className = 'status-badge live';
      badge.textContent = 'vote en cours';
      motion.textContent = s.motion.title || 'Resolution';
      sub.textContent = s.motion.secret ? 'Vote secret' : '';
      show('waiting_state', false);
      updateResolution(s.motion);

      if (currentMotionId !== s.motion.id) {
        resetBars();
        currentMotionId = s.motion.id;
      }

      await loadResults(s.motion.id, !s.motion.secret);

    } else if (s.phase === 'closed' && s.motion?.id) {
      // Final result
      badge.className = 'status-badge idle';
      badge.textContent = 'resultat';
      motion.textContent = s.motion.title || 'Resolution';
      sub.textContent = 'Resultat final';
      show('waiting_state', false);
      updateResolution(s.motion);

      if (currentMotionId !== s.motion.id) {
        resetBars();
        currentMotionId = s.motion.id;
      }

      await loadResults(s.motion.id, true);

    } else if (s.meeting_status === 'paused') {
      // Session paused
      badge.className = 'status-badge idle';
      badge.textContent = 'en pause';
      motion.textContent = 'Séance en pause';
      sub.textContent = 'La séance reprendra sous peu';
      show('waiting_state', true);
      show('chart_container', false);
      show('decision_section', false);
      show('secret_block', false);
      updateResolution(null);
      currentMotionId = null;
      resetBars();

    } else {
      // Waiting
      badge.className = 'status-badge idle';
      badge.textContent = 'en seance';
      motion.textContent = 'En attente';
      sub.textContent = '';
      show('waiting_state', true);
      show('chart_container', false);
      show('decision_section', false);
      show('secret_block', false);
      updateResolution(null);
      currentMotionId = null;
      resetBars();
    }

    updateTimestamp();

  } catch (e) {
    console.error('refresh error:', e);
    err.textContent = 'Erreur : ' + e.message;
    err.classList.add('visible');
  }
}

// Heartbeat
function getDeviceId() {
  try {
    var id = localStorage.getItem('device.id');
    if (!id) { id = crypto?.randomUUID?.() || (Date.now() + '-' + Math.random().toString(16).slice(2)); localStorage.setItem('device.id', id); }
    return id;
  } catch(e) { return 'anon-' + Date.now(); }
}

var _heartbeatFails = 0;
async function heartbeat() {
  if (!MEETING_ID) return;
  try {
    await fetch('/api/v1/device_heartbeat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ meeting_id: MEETING_ID, device_id: getDeviceId(), role: 'projector' })
    });
    _heartbeatFails = 0;
  } catch(e) {
    _heartbeatFails++;
    if (_heartbeatFails >= 3) {
      console.warn('[projection] heartbeat: ' + _heartbeatFails + ' échecs consécutifs');
      var err = document.getElementById('error_box');
      if (err) {
        err.textContent = 'Connexion instable — le projecteur peut ne plus être détecté.';
        err.classList.add('visible');
      }
    }
  }
}

// Fullscreen toggle
function toggleFullscreen() {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(function() {});
  } else {
    document.exitFullscreen().catch(function() {});
  }
}

// Theme toggle — unified with the rest of the app (data-theme + ag-vote-theme key).
// The inline <head> script already applies the theme before first paint.
function toggleTheme() {
  var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  var next = isDark ? 'light' : 'dark';
  localStorage.setItem('ag-vote-theme', next);
  document.documentElement.setAttribute('data-theme', next);
}

// Init
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('btnFullscreen')?.addEventListener('click', toggleFullscreen);
  document.getElementById('btnThemeToggle')?.addEventListener('click', toggleTheme);
  document.getElementById('btnChangeMeeting')?.addEventListener('click', changeMeeting);
  startClock();
  refresh();
  var _refreshInFlight = false;
  setInterval(function() {
    if (document.hidden || _refreshInFlight) return;
    _refreshInFlight = true;
    refresh().finally(function() { _refreshInFlight = false; });
  }, 3000);
  heartbeat();
  setInterval(function() { if (!document.hidden) heartbeat(); }, 15000);
});
