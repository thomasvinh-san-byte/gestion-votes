/**
 * vote.js - Public voting interface for AG-VOTE.
 *
 * Provides the voter-facing interface for casting ballots.
 * Features: meeting/member selection, motion display, vote casting,
 * device heartbeat, block/kick handling, policy badges.
 *
 * @module vote
 * @requires utils.js
 * @requires MeetingContext (optional, falls back to select element)
 */
(function(){
  'use strict';

  /**
   * DOM query shorthand
   * @param {string} s - CSS selector
   * @returns {Element|null}
   */
  const $ = (s) => document.querySelector(s);

  // -----------------------------
  // Device heartbeat + block/kick handling
  // -----------------------------
  const DEVICE_ROLE = 'voter';
  const HEARTBEAT_URL = '/api/v1/device_heartbeat.php';

  /**
   * Get or generate a unique device identifier.
   * Persisted in localStorage for session continuity.
   * @returns {string} Device UUID
   */
  function getDeviceId(){
    try {
      const k = 'device.id';
      let v = localStorage.getItem(k);
      if (!v) {
        v = (crypto && crypto.randomUUID) ? crypto.randomUUID() : (String(Date.now()) + '-' + Math.random().toString(16).slice(2));
        localStorage.setItem(k, v);
      }
      return v;
    } catch(e){
      return 'anon-' + String(Date.now());
    }
  }

  /**
   * Read device battery status using Battery Status API.
   * @returns {Promise<{level: number, charging: boolean}|null>} Battery info or null if unavailable
   */
  async function readBattery(){
    try {
      if (!navigator.getBattery) return null;
      const b = await navigator.getBattery();
      return {
        level: Math.round((b.level ?? 0) * 100),
        charging: !!b.charging
      };
    } catch(e){
      return null;
    }
  }

  /**
   * Create or return the blocked device overlay element.
   * Shown when device is blocked by operator.
   * @returns {HTMLElement} The overlay element
   */
  function ensureBlockedOverlay(){
    let el = document.getElementById('blockedOverlay');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'blockedOverlay';
    el.style.position = 'fixed';
    el.style.inset = '0';
    el.style.zIndex = '9999';
    Shared.hide(el);
    el.style.background = 'rgba(15, 23, 42, 0.94)';
    el.style.color = '#fff';
    el.innerHTML = `
      <div style="max-width:520px;margin:12vh auto;padding:24px;">
        <div style="font-size:18px;font-weight:700;margin-bottom:8px;">Accès suspendu</div>
        <div id="blockedMsg" style="opacity:0.9;line-height:1.4;">
          Cet appareil a été temporairement bloqué par l'opérateur.
        </div>
        <div style="margin-top:16px;opacity:0.8;font-size:12px;">Restez sur cet écran. L'accès sera rétabli automatiquement.</div>
      </div>`;
    document.body.appendChild(el);
    return el;
  }

  /**
   * Show or hide the blocked device overlay.
   * @param {boolean} on - Whether to show the overlay
   * @param {string} [msg] - Optional message to display
   */
  function setBlocked(on, msg){
    const ov = ensureBlockedOverlay();
    const m = document.getElementById('blockedMsg');
    if (m && msg) m.textContent = msg;
    on ? Shared.show(ov, 'block') : Shared.hide(ov);
    // Disable buttons when blocked
    setVoteButtonsEnabled(!on && !!selectedMemberId());
  }

  /**
   * Send device heartbeat to the server.
   * Reports device status and receives block/kick commands.
   * @returns {Promise<void>}
   */
  async function sendHeartbeat(){
    const meetingId = selectedMeetingId();
    if (!meetingId) return;
    const deviceId = getDeviceId();
    const bat = await readBattery();
    try {
      const r = await fetch(HEARTBEAT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          meeting_id: meetingId,
          device_id: deviceId,
          role: DEVICE_ROLE,
          member_id: selectedMemberId() || null,
          battery_level: bat ? bat.level : null,
          battery_charging: bat ? bat.charging : null,
          user_agent: navigator.userAgent || null
        })
      });
      const out = await r.json().catch(()=>({}));
      const data = out?.data || {};
      if (data.blocked) {
        setBlocked(true, data.block_reason || "Cet appareil a été bloqué.");
      } else {
        setBlocked(false);
      }

      // Soft kick: request reload
      if (data.command && data.command.type === 'kick') {
        notify('error', data.command.message || 'Reconnexion requise.');
        setTimeout(()=>{ location.reload(); }, 800);
      }
    } catch(e){
      // Heartbeat failures are non-blocking
    }
  }

  /**
   * Display a notification message.
   * @param {string} type - Notification type: 'success' or 'error'
   * @param {string} msg - Message to display
   */
  function notify(type, msg){
    const box = $("#notif_box");
    if (!box) return console[type==="error"?"error":"log"](msg);
    box.className = "notif " + (type === "error" ? "error" : "success");
    Shared.show(box, 'block');
    box.textContent = msg;
    setTimeout(()=>{ Shared.hide(box); }, 3000);
  }

  /**
   * Escape HTML entities in a string.
   * @param {*} x - Value to escape
   * @returns {string} Escaped string
   */
  function escapeHtml(x){ return (window.Utils?.escapeHtml ? Utils.escapeHtml(x) : String(x ?? "")); }

  // -----------------------------
  // Policy labels (visual indicator of overrides)
  // -----------------------------
  let _currentMotionId = null;

  let _policiesForMeeting = null;
  let _votePoliciesById = {};
  let _quorumPoliciesById = {};
  let _meetingVotePolicyId = null;
  let _meetingQuorumPolicyId = null;

  async function ensurePolicyMaps(meetingId){
    if (_policiesForMeeting === meetingId) return;
    _policiesForMeeting = meetingId;
    _votePoliciesById = {};
    _quorumPoliciesById = {};
    _meetingVotePolicyId = null;
    _meetingQuorumPolicyId = null;

    const [vp, qp, mv, mq] = await Promise.all([
      apiGet('/api/v1/vote_policies.php').catch(()=>({})),
      apiGet('/api/v1/quorum_policies.php').catch(()=>({})),
      apiGet(`/api/v1/meeting_vote_settings.php?meeting_id=${encodeURIComponent(meetingId)}`).catch(()=>({})),
      apiGet(`/api/v1/meeting_quorum_settings.php?meeting_id=${encodeURIComponent(meetingId)}`).catch(()=>({}))
    ]);
    (vp?.data?.items || vp?.items || []).forEach(p => { _votePoliciesById[p.id] = p; });
    (qp?.data?.items || qp?.items || []).forEach(p => { _quorumPoliciesById[p.id] = p; });
    _meetingVotePolicyId = (mv?.data?.vote_policy_id ?? mv?.vote_policy_id ?? null);
    _meetingQuorumPolicyId = (mq?.data?.quorum_policy_id ?? mq?.quorum_policy_id ?? null);
  }

  function badge(text, kind='badge'){
    return `<span class="${kind}">${escapeHtml(text)}</span>`;
  }

  function motionMetaBadges(m){
    const badges = [];
    if (m?.secret) badges.push(badge('SECRET', 'badge danger'));

    const motionVoteId = m?.vote_policy_id || null;
    const motionQuorumId = m?.quorum_policy_id || null;
    const effVoteId = motionVoteId || _meetingVotePolicyId;
    const effQuorumId = motionQuorumId || _meetingQuorumPolicyId;

    if (effVoteId) {
      const vp = _votePoliciesById[effVoteId];
      const name = vp?.name || 'Majorité';
      const star = motionVoteId ? '★' : '';
      badges.push(badge(`MAJORITÉ${star}: ${name}`, motionVoteId ? 'badge warn' : 'badge'));
    }
    if (effQuorumId) {
      const qp = _quorumPoliciesById[effQuorumId];
      const name = qp?.name || 'Quorum';
      const star = motionQuorumId ? '★' : '';
      badges.push(badge(`QUORUM${star}: ${name}`, motionQuorumId ? 'badge warn' : 'badge'));
    }
    if (!badges.length) return '';
    return `<div class="row" style="gap:6px; flex-wrap:wrap; margin-top:10px;">${badges.join('')}</div>`;
  }

  /**
   * Perform a GET request to the API.
   * Uses Utils.apiGet if available, otherwise falls back to fetch.
   * @param {string} url - API endpoint URL
   * @returns {Promise<Object>} Parsed JSON response
   * @throws {Error} If request fails
   */
  async function apiGet(url){
    if (window.Utils && typeof Utils.apiGet === 'function') return Utils.apiGet(url);
    const r = await fetch(url, { method: 'GET', credentials: 'same-origin' });
    if (!r.ok) { const e = await r.json().catch(()=>({})); throw new Error(e.error || 'request_failed'); }
    return r.json();
  }

  /**
   * Perform a POST request to the API.
   * Uses Utils.apiPost if available, otherwise falls back to fetch.
   * @param {string} url - API endpoint URL
   * @param {Object} data - Data to send in request body
   * @returns {Promise<Object>} Parsed JSON response
   * @throws {Error} If request fails
   */
  async function apiPost(url, data){
    if (window.Utils && typeof Utils.apiPost === 'function') return Utils.apiPost(url, data);
    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(data)
    });
    if (!r.ok) { const e = await r.json().catch(()=>({})); throw new Error(e.error || 'request_failed'); }
    return r.json();
  }

  /**
   * Get the currently selected meeting ID.
   * Uses MeetingContext if available, falls back to select element.
   * @returns {string} Meeting ID or empty string
   */
  function selectedMeetingId(){
    // First try MeetingContext
    if (typeof MeetingContext !== 'undefined' && MeetingContext.get()) {
      return MeetingContext.get();
    }
    // Fallback to select element for vote.php standalone mode
    return ($("#meetingSelect")?.value || "").trim();
  }

  /**
   * Get the currently selected member ID from the select element.
   * @returns {string} Member ID or empty string
   */
  function selectedMemberId(){ return ($("#memberSelect")?.value || "").trim(); }

  /**
   * Check if element is an ag-searchable-select component.
   * @param {Element} el - DOM element to check
   * @returns {boolean} True if element is a searchable select
   */
  function isSearchableSelect(el) {
    return el && el.tagName && el.tagName.toLowerCase() === 'ag-searchable-select';
  }

  /**
   * Load available meetings into the meeting select dropdown.
   * Supports both native select and ag-searchable-select component.
   * @returns {Promise<void>}
   */
  async function loadMeetings(){
    const sel = $("#meetingSelect");
    if (!sel) return;
    const r = await apiGet("/api/v1/meetings_index.php?active_only=1");
    const meetings = r?.data?.meetings || [];

    if (isSearchableSelect(sel)) {
      // Use ag-searchable-select API
      const options = meetings.map(m => {
        const when = (m.created_at || "").toString().slice(0,10);
        const statusLabel = m.status ? ` [${m.status}]` : '';
        return {
          value: m.meeting_id,
          label: m.title || "Séance",
          sublabel: `${when}${statusLabel}`
        };
      });
      sel.setOptions(options);
    } else {
      // Fallback to native select
      sel.innerHTML = "";
      const opt0 = document.createElement("option");
      opt0.value = "";
      opt0.textContent = "— Sélectionner une séance —";
      sel.appendChild(opt0);
      for (const m of meetings){
        const opt = document.createElement("option");
        opt.value = m.meeting_id;
        const when = (m.created_at || "").toString().slice(0,10);
        opt.textContent = `${when} — ${m.title || "Séance"} [${m.status || "—"}]`;
        sel.appendChild(opt);
      }
    }

    // Priority: MeetingContext > saved localStorage > first meeting in list
    const contextId = (typeof MeetingContext !== 'undefined') ? MeetingContext.get() : null;
    const saved = (localStorage.getItem("public.meeting_id") || "").trim();
    const initialId = contextId || saved;
    if (initialId && meetings.some(x=>x.meeting_id===initialId)) {
      sel.value = initialId;
    } else if (meetings.length) {
      sel.value = meetings[0].meeting_id;
    }
    // Sync to MeetingContext
    if (typeof MeetingContext !== 'undefined' && sel.value) {
      MeetingContext.set(sel.value, { updateUrl: false });
    }
    localStorage.setItem("public.meeting_id", sel.value || "");
    await loadMembers();
    await refresh();
  }

  /**
   * Load members for the selected meeting into the member select dropdown.
   * Prioritizes present/remote attendees, falls back to all members.
   * Supports both native select and ag-searchable-select component.
   * @returns {Promise<void>}
   */
  async function loadMembers(){
    const meetingId = selectedMeetingId();
    const sel = $("#memberSelect");
    if (!sel) return;

    const useSearchable = isSearchableSelect(sel);
    const memberOptions = [];

    if (!useSearchable) {
      sel.innerHTML = "";
      const opt0 = document.createElement("option");
      opt0.value = "";
      opt0.textContent = "— Sélectionner un votant —";
      sel.appendChild(opt0);
    }

    if (!meetingId) {
      if (useSearchable) sel.setOptions([]);
      return;
    }

    let filled = 0;
    try {
      const r = await apiGet(`/api/v1/attendances.php?meeting_id=${encodeURIComponent(meetingId)}`);
      const rows = r?.data?.attendances || [];
      const modeLabels = { present: 'Présent', remote: 'À distance', proxy: 'Procuration' };

      for (const x of rows){
        const mode = (x.mode || "");
        if (!["present","remote","proxy"].includes(mode)) continue;

        if (useSearchable) {
          memberOptions.push({
            value: x.member_id,
            label: x.full_name || x.name || "Membre",
            sublabel: modeLabels[mode] || mode,
            group: x.group_name || null
          });
        } else {
          const opt = document.createElement("option");
          opt.value = x.member_id;
          opt.textContent = `${x.full_name || x.name || "Membre"} (${mode})`;
          sel.appendChild(opt);
        }
        filled++;
      }
    } catch(e) {
      // ignore
    }

    // Fallback: if no attendance recorded, still display all members
    if (filled === 0) {
      const r = await apiGet("/api/v1/members.php");
      const rows = r?.data?.members || r?.data?.rows || [];
      for (const x of rows){
        if (useSearchable) {
          memberOptions.push({
            value: x.id || x.member_id,
            label: x.full_name || x.name || "Membre",
            sublabel: x.email || null,
            group: x.group_name || null
          });
        } else {
          const opt = document.createElement("option");
          opt.value = x.id || x.member_id;
          opt.textContent = x.full_name || x.name || "Membre";
          sel.appendChild(opt);
        }
      }
    }

    if (useSearchable) {
      sel.setOptions(memberOptions);
    }

    // Auto-select: try saved value, then try matching Auth user name
    const saved = (localStorage.getItem("public.member_id") || "").trim();
    const allValues = useSearchable
      ? memberOptions.map(o => String(o.value))
      : [...sel.options].map(o => o.value);

    if (saved && allValues.includes(saved)) {
      sel.value = saved;
    } else if (window.Auth && window.Auth.user) {
      autoSelectMember(sel, useSearchable ? memberOptions : null);
    } else if (window.Auth && window.Auth.ready) {
      window.Auth.ready.then(() => autoSelectMember(sel, useSearchable ? memberOptions : null));
    }

    // Update member display in footer
    updateMemberFromSelect(sel);
    sel.addEventListener('change', () => updateMemberFromSelect(sel));
  }

  /**
   * Auto-select the current authenticated user in the member dropdown.
   * Matches by name or email from window.Auth.user.
   * Supports both native select and ag-searchable-select component.
   * @param {HTMLSelectElement|AgSearchableSelect} sel - Member select element
   * @param {Array|null} optionsArray - Options array for searchable select (null for native)
   */
  function autoSelectMember(sel, optionsArray) {
    if (!window.Auth || !window.Auth.user) return;
    const userName = (window.Auth.user.name || '').toLowerCase().trim();
    const userEmail = (window.Auth.user.email || '').toLowerCase().trim();
    if (!userName && !userEmail) return;

    if (optionsArray) {
      // ag-searchable-select
      for (const opt of optionsArray) {
        const label = (opt.label || '').toLowerCase();
        const sublabel = (opt.sublabel || '').toLowerCase();
        if ((userName && label.includes(userName)) ||
            (userEmail && (label.includes(userEmail) || sublabel.includes(userEmail)))) {
          sel.value = opt.value;
          localStorage.setItem("public.member_id", opt.value);
          updateMemberFromSelect(sel);
          break;
        }
      }
    } else {
      // Native select
      for (const opt of sel.options) {
        if (!opt.value) continue;
        const text = opt.textContent.toLowerCase();
        if ((userName && text.includes(userName)) || (userEmail && text.includes(userEmail))) {
          sel.value = opt.value;
          localStorage.setItem("public.member_id", opt.value);
          updateMemberFromSelect(sel);
          break;
        }
      }
    }
  }

  /**
   * Update member display in footer from select element.
   * Supports both native select and ag-searchable-select component.
   * @param {HTMLSelectElement|AgSearchableSelect} sel - Member select element
   */
  function updateMemberFromSelect(sel) {
    if (typeof window.updateMemberDisplay !== 'function') return;

    if (isSearchableSelect(sel)) {
      // ag-searchable-select
      const selectedOpt = sel.selectedOption;
      if (selectedOpt) {
        window.updateMemberDisplay({ name: selectedOpt.label });
      } else {
        window.updateMemberDisplay(null);
      }
    } else {
      // Native select
      const opt = sel.options[sel.selectedIndex];
      if (opt && opt.value) {
        window.updateMemberDisplay({ name: opt.textContent.split('(')[0].trim() });
      } else {
        window.updateMemberDisplay(null);
      }
    }
  }

  /**
   * Update the motion card UI with structured data.
   * Uses dedicated elements instead of replacing innerHTML of the entire card.
   * @param {Object|null} m - Motion data object or null
   */
  function updateMotionCard(m) {
    const title = $("#motionTitle");
    const sub = $("#motionSub");
    const badges = $("#motionBadges");
    const noEl = $("#motionNo");
    const phaseEl = $("#motionPhase");
    const resoDetails = $("#resoDetails");
    const resoText = $("#resoText");
    const resoNote = $("#resoNote");
    const card = $("#motionBox");

    if (!m) {
      if (title) title.textContent = 'En attente d\u2019une résolution';
      if (sub) sub.textContent = '';
      if (badges) badges.innerHTML = '';
      if (noEl) { noEl.textContent = ''; Shared.hide(noEl); }
      if (phaseEl) phaseEl.textContent = 'En attente';
      if (resoDetails) resoDetails.hidden = true;
      if (card) { card.classList.remove('active'); card.classList.add('waiting'); }
      return;
    }

    if (card) { card.classList.add('active'); card.classList.remove('waiting'); }
    if (title) title.textContent = m.title || 'Résolution';
    if (sub) sub.textContent = m.description || '';

    // Motion number pill
    if (noEl) {
      if (m.position) {
        noEl.textContent = '#' + m.position;
        Shared.show(noEl);
      } else {
        noEl.textContent = '';
        Shared.hide(noEl);
      }
    }

    // Phase pill
    if (phaseEl) {
      phaseEl.textContent = m.secret ? 'SECRET' : 'OUVERT';
      phaseEl.className = 'motion-pill' + (m.secret ? ' pill-danger' : ' pill-success');
    }

    // Policy badges
    if (badges) badges.innerHTML = motionMetaBadges(m);

    // Resolution body text
    if (resoDetails && resoText) {
      const bodyText = m.body || '';
      if (bodyText.trim()) {
        resoText.textContent = bodyText;
        resoDetails.hidden = false;
        if (resoNote) resoNote.textContent = 'scroll';
      } else {
        resoDetails.hidden = true;
      }
    }
  }

  /**
   * Update the motion progress stepper ("Resolution X of Y").
   * Shows the current motion index and total count.
   * @param {Object|null} data - API response data containing motion_index and total_motions
   * @param {Object|null} motion - Current motion object (may contain position info)
   */
  function updateMotionProgress(data, motion) {
    const progressEl = $("#motionProgress");
    const progressText = $("#motionProgressText");
    if (!progressEl || !progressText) return;

    const index = data?.motion_index ?? motion?.position ?? null;
    const total = data?.total_motions ?? null;

    if (index && total) {
      progressText.textContent = index + ' / ' + total;
      Shared.show(progressEl);
    } else if (index) {
      progressText.textContent = '#' + index;
      Shared.show(progressEl);
    } else {
      progressText.textContent = '\u2014';
      Shared.hide(progressEl);
    }
  }

  /**
   * Update the non-identifying vote participation indicator.
   * Shows what percentage of eligible voters have cast a ballot.
   * @param {Object|null} data - API response data with participation info
   */
  function updateVoteParticipation(data) {
    const container = $("#voteParticipation");
    const fill = $("#voteParticipationFill");
    const text = $("#voteParticipationText");
    if (!container || !fill || !text) return;

    // Try to extract participation percentage from various possible API fields
    let pct = data?.participation_pct ?? null;

    if (pct === null) {
      const cast = data?.votes_cast ?? data?.ballots_cast ?? null;
      const eligible = data?.eligible_count ?? data?.total_eligible ?? null;
      if (cast !== null && eligible !== null && eligible > 0) {
        pct = Math.round((cast / eligible) * 100);
      }
    }

    if (pct !== null && data?.motion) {
      fill.style.width = Math.min(pct, 100) + '%';
      text.textContent = pct + '% ont voté';
      Shared.show(container);
    } else {
      fill.style.width = '0%';
      text.textContent = '0% ont voté';
      Shared.hide(container);
    }
  }

  /**
   * Refresh the current motion display.
   * Fetches the open motion for the selected meeting and updates the UI.
   * @returns {Promise<void>}
   */
  async function refresh(){
    const meetingId = selectedMeetingId();
    const memberId = selectedMemberId();
    if (meetingId) localStorage.setItem("public.meeting_id", meetingId);
    if (memberId) localStorage.setItem("public.member_id", memberId);

    if (!meetingId){
      updateMotionCard(null);
      updateMotionProgress(null, null);
      updateVoteParticipation(null);
      setVoteButtonsEnabled(false);
      return;
    }

    try{
      await ensurePolicyMaps(meetingId);
      const r = await apiGet(`/api/v1/current_motion.php?meeting_id=${encodeURIComponent(meetingId)}`);
      const d = r?.data;
      const m = d?.motion;
      if (!m){
        _currentMotionId = null;
        updateMotionCard(null);
        updateMotionProgress(null, null);
        updateVoteParticipation(null);
        setVoteButtonsEnabled(false);
        return;
      }
      _currentMotionId = m.id || m.motion_id || null;
      updateMotionCard(m);
      updateMotionProgress(d, m);
      updateVoteParticipation(d);
      setVoteButtonsEnabled(!!memberId);
    } catch(e){
      updateMotionCard(null);
      updateMotionProgress(null, null);
      updateVoteParticipation(null);
      const title = $("#motionTitle");
      if (title) title.textContent = 'Erreur: ' + (e?.message || String(e));
      setVoteButtonsEnabled(false);
    }
  }

  /**
   * Enable or disable vote buttons.
   * @param {boolean} on - Whether to enable buttons
   */
  function setVoteButtonsEnabled(on){
    ["#btnFor","#btnAgainst","#btnAbstain","#btnNone"].forEach(id=>{
      const el=$(id);
      if (el) el.disabled = !on;
    });
  }

  /**
   * Cast a vote for the current motion.
   * @param {string} choice - Vote value: 'for', 'against', 'abstain', or 'none'
   * @returns {Promise<void>}
   */
  async function cast(choice){
    const memberId = selectedMemberId();
    if (!_currentMotionId || !memberId) return;

    // Check offline before attempting
    if (!navigator.onLine) {
      throw new Error('Vous êtes hors ligne. Vérifiez votre connexion.');
    }

    const MAX_RETRIES = 2;
    let lastErr = null;
    for (let attempt = 0; attempt <= MAX_RETRIES; attempt++) {
      try {
        await apiPost("/api/v1/ballots_cast.php", { motion_id: _currentMotionId, member_id: memberId, value: choice });
        notify("success", "Vote enregistré.");
        return; // success — exit
      } catch(e) {
        lastErr = e;
        const isNetwork = !navigator.onLine || (e && /fetch|network|timeout|load/i.test(e.message));
        if (isNetwork && attempt < MAX_RETRIES) {
          // Wait before retry (exponential backoff: 1s, 2s)
          await new Promise(r => setTimeout(r, 1000 * (attempt + 1)));
          continue;
        }
        break; // non-retryable or exhausted retries
      }
    }
    // All retries failed — propagate to caller so buttons are re-enabled
    throw lastErr || new Error('Échec de l\'envoi du vote');
  }

  function wire(){
    // Initialize MeetingContext if available
    if (typeof MeetingContext !== 'undefined') {
      MeetingContext.init();
    }
    if (Utils.bindApiKeyInput) {
      Utils.bindApiKeyInput("public", $("#publicApiKey"), () => loadMeetings().catch(console.error));
    }
    $("#meetingSelect")?.addEventListener("change", async ()=>{
      const newId = ($("#meetingSelect")?.value || "").trim();
      // Sync selection to MeetingContext
      if (typeof MeetingContext !== 'undefined' && newId) {
        MeetingContext.set(newId, { updateUrl: false });
      }
      localStorage.setItem("public.meeting_id", newId);
      await loadMembers();
      await refresh();
    });
    $("#memberSelect")?.addEventListener("change", refresh);
    $("#btnRefresh")?.addEventListener("click", refresh);

    // Only bind direct cast if no confirmation overlay (vote.htmx.html has its own overlay calling submitVote)
    if (!document.getElementById('confirmationOverlay')) {
      document.querySelectorAll("[data-choice]").forEach(btn=>{
        btn.addEventListener("click", ()=>cast(btn.dataset.choice));
      });
    }

    // Load meetings - MeetingContext handles URL param and persistence
    loadMeetings().catch((e)=>notify("error", e?.message || String(e)));

    // Poll current motion (disabled when WebSocket is connected)
    setInterval(()=>{
      // Skip polling if WebSocket is connected and authenticated
      if (typeof AgVoteWebSocket !== 'undefined' && window._wsClient?.isRealTime) return;
      if (!document.hidden) refresh().catch(()=>{});
    }, 3000);
    // Heartbeat always runs (separate from WebSocket heartbeat interval)
    setInterval(()=>{ if(!document.hidden) sendHeartbeat().catch(()=>{}); }, 15000);
  }

  // Expose cast as global submitVote for vote.htmx.html confirmation overlay
  window.submitVote = cast;

  document.addEventListener("DOMContentLoaded", wire);
})();