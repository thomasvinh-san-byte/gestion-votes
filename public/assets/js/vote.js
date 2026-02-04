(function(){
  const $ = (s) => document.querySelector(s);

  // -----------------------------
  // Tech: device heartbeat + block/kick handling
  // -----------------------------
  const DEVICE_ROLE = 'voter';
  const HEARTBEAT_URL = '/api/v1/device_heartbeat.php';

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

  function ensureBlockedOverlay(){
    let el = document.getElementById('blockedOverlay');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'blockedOverlay';
    el.style.position = 'fixed';
    el.style.inset = '0';
    el.style.zIndex = '9999';
    el.style.display = 'none';
    el.style.background = 'rgba(15, 23, 42, 0.94)';
    el.style.color = '#fff';
    el.innerHTML = `
      <div style="max-width:520px;margin:12vh auto;padding:24px;">
        <div style="font-size:18px;font-weight:700;margin-bottom:8px;">Accès suspendu</div>
        <div id="blockedMsg" style="opacity:0.9;line-height:1.4;">
          Cet appareil a été temporairement bloqué par l’opérateur.
        </div>
        <div style="margin-top:16px;opacity:0.8;font-size:12px;">Reste sur cet écran. L’accès sera rétabli automatiquement après déblocage.</div>
      </div>`;
    document.body.appendChild(el);
    return el;
  }

  function setBlocked(on, msg){
    const ov = ensureBlockedOverlay();
    const m = document.getElementById('blockedMsg');
    if (m && msg) m.textContent = msg;
    ov.style.display = on ? 'block' : 'none';
    // Disable buttons when blocked
    setVoteButtonsEnabled(!on && !!selectedMemberId());
  }

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
      // heartbeat failures are non-blocking
    }
  }

  function notify(type, msg){
    const box = $("#notif_box");
    if (!box) return console[type==="error"?"error":"log"](msg);
    box.className = "notif " + (type === "error" ? "error" : "success");
    box.style.display = "block";
    box.textContent = msg;
    setTimeout(()=>{ box.style.display = "none"; }, 3000);
  }

  function escapeHtml(x){ return (window.Utils?.escapeHtml ? Utils.escapeHtml(x) : String(x ?? "")); }

  // -----------------------------
  // Policy labels (visual proof of overrides)
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

  // Prefer Utils.apiGet/apiPost (loaded from utils.js before this file).
  // Falls back to a minimal local fetch if Utils is somehow unavailable.
  async function apiGet(url){
    if (window.Utils && typeof Utils.apiGet === 'function') return Utils.apiGet(url);
    const r = await fetch(url, { method: 'GET', credentials: 'same-origin' });
    if (!r.ok) { const e = await r.json().catch(()=>({})); throw new Error(e.error || 'request_failed'); }
    return r.json();
  }
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

  function selectedMeetingId(){ return ($("#meetingSelect")?.value || "").trim(); }
  function selectedMemberId(){ return ($("#memberSelect")?.value || "").trim(); }

  async function loadMeetings(){
    const sel = $("#meetingSelect");
    if (!sel) return;
    const r = await apiGet("/api/v1/meetings_index.php?active_only=1");
    const meetings = r?.data?.meetings || [];
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
    const saved = (localStorage.getItem("public.meeting_id") || "").trim();
    if (saved && meetings.some(x=>x.meeting_id===saved)) sel.value = saved;
    else if (meetings.length) sel.value = meetings[0].meeting_id;
    localStorage.setItem("public.meeting_id", sel.value || "");
    await loadMembers();
    await refresh();
  }

  async function loadMembers(){
    const meetingId = selectedMeetingId();
    const sel = $("#memberSelect");
    if (!sel) return;
    sel.innerHTML = "";
    const opt0 = document.createElement("option");
    opt0.value = "";
    opt0.textContent = "— Sélectionner un membre —";
    sel.appendChild(opt0);

    if (!meetingId) return;

    let filled = 0;
    try {
      const r = await apiGet(`/api/v1/attendances.php?meeting_id=${encodeURIComponent(meetingId)}`);
      const rows = r?.data?.attendances || [];
      for (const x of rows){
        const mode = (x.mode || "");
        if (!["present","remote","proxy"].includes(mode)) continue;
        const opt = document.createElement("option");
        opt.value = x.member_id;
        opt.textContent = `${x.full_name || x.name || "Membre"} (${mode})`;
        sel.appendChild(opt);
        filled++;
      }
    } catch(e) {
      // ignore
    }

    // Fallback: si aucune présence saisie, on affiche quand même les membres
    if (filled === 0) {
      const r = await apiGet("/api/v1/members.php");
      const rows = r?.data?.members || r?.data?.rows || [];
      for (const x of rows){
        const opt = document.createElement("option");
        opt.value = x.id || x.member_id;
        opt.textContent = x.full_name || x.name || "Membre";
        sel.appendChild(opt);
      }
    }

    // Auto-select: try saved, then try Auth user name matching
    const saved = (localStorage.getItem("public.member_id") || "").trim();
    if (saved && [...sel.options].some(o=>o.value===saved)) {
      sel.value = saved;
    } else if (window.Auth && window.Auth.user) {
      autoSelectMember(sel);
    } else if (window.Auth && window.Auth.ready) {
      window.Auth.ready.then(() => autoSelectMember(sel));
    }

    // Update member display in footer
    updateMemberFromSelect(sel);
    sel.addEventListener('change', () => updateMemberFromSelect(sel));
  }

  function autoSelectMember(sel) {
    if (!window.Auth || !window.Auth.user) return;
    const userName = (window.Auth.user.name || '').toLowerCase().trim();
    const userEmail = (window.Auth.user.email || '').toLowerCase().trim();
    if (!userName && !userEmail) return;

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

  function updateMemberFromSelect(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (typeof window.updateMemberDisplay === 'function') {
      if (opt && opt.value) {
        window.updateMemberDisplay({ name: opt.textContent.split('(')[0].trim() });
      } else {
        window.updateMemberDisplay(null);
      }
    }
  }

  async function refresh(){
    const meetingId = selectedMeetingId();
    const memberId = selectedMemberId();
    if (meetingId) localStorage.setItem("public.meeting_id", meetingId);
    if (memberId) localStorage.setItem("public.member_id", memberId);

    if (!meetingId){
      $("#motionBox").innerHTML = "<span class='muted'>Sélectionnez une séance.</span>";
      setVoteButtonsEnabled(false);
      return;
    }

    try{
      await ensurePolicyMaps(meetingId);
      const r = await apiGet(`/api/v1/current_motion.php?meeting_id=${encodeURIComponent(meetingId)}`);
      const m = r?.data?.motion;
      if (!m){
        _currentMotionId = null;
        $("#motionBox").innerHTML = "<span class='muted'>Aucune motion ouverte.</span>";
        setVoteButtonsEnabled(false);
        return;
      }
      _currentMotionId = m.id || m.motion_id || null;
      $("#motionBox").innerHTML = `
        <div><strong>${escapeHtml(m.title || "Motion")}</strong></div>
        <div class='muted tiny'>${escapeHtml(m.description || "")}</div>
        ${motionMetaBadges(m)}
      `;
      setVoteButtonsEnabled(!!memberId);
    } catch(e){
      $("#motionBox").innerHTML = `<span class='muted'>Erreur: ${escapeHtml(e?.message || String(e))}</span>`;
      setVoteButtonsEnabled(false);
    }
  }

  function setVoteButtonsEnabled(on){
    ["#btnFor","#btnAgainst","#btnAbstain","#btnNone"].forEach(id=>{
      const el=$(id);
      if (el) el.disabled = !on;
    });
  }

  async function cast(choice){
    const memberId = selectedMemberId();
    if (!_currentMotionId || !memberId) return;

    try{
      await apiPost("/api/v1/ballots_cast.php", { motion_id: _currentMotionId, member_id: memberId, value: choice });
      notify("success", "Vote enregistré.");
    } catch(e){
      notify("error", e?.message || String(e));
    }
  }

  function wire(){
    const urlMeetingId = (new URLSearchParams(location.search).get('meeting_id') || '').trim();
    if (Utils.bindApiKeyInput) {
      Utils.bindApiKeyInput("public", $("#publicApiKey"), () => loadMeetings().catch(console.error));
    }
    $("#meetingSelect")?.addEventListener("change", async ()=>{ await loadMembers(); await refresh(); });
    $("#memberSelect")?.addEventListener("change", refresh);
    $("#btnRefresh")?.addEventListener("click", refresh);

    // Only bind direct cast if no confirmation overlay (vote.htmx.html has its own overlay that calls submitVote)
    if (!document.getElementById('confirmationOverlay')) {
      document.querySelectorAll("[data-choice]").forEach(btn=>{
        btn.addEventListener("click", ()=>cast(btn.dataset.choice));
      });
    }

    loadMeetings().then(async () => {
      if (urlMeetingId) {
        const sel = $("#meetingSelect");
        if (sel && [...sel.options].some(o => o.value === urlMeetingId)) {
          sel.value = urlMeetingId;
          localStorage.setItem("public.meeting_id", urlMeetingId);
          await loadMembers();
          await refresh();
        }
      }
    }).catch((e)=>notify("error", e?.message || String(e)));

    // Poll current motion + heartbeat
    setInterval(()=>{ if(!document.hidden) refresh().catch(()=>{}); }, 3000);
    setInterval(()=>{ if(!document.hidden) sendHeartbeat().catch(()=>{}); }, 15000);
  }

  // Expose cast as global submitVote for vote.htmx.html confirmation overlay
  window.submitVote = cast;

  document.addEventListener("DOMContentLoaded", wire);
})();