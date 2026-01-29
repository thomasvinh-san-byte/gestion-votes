(function(){
  const $ = (s) => document.querySelector(s);

  function setBusy(on){
    document.querySelectorAll("[data-busy]").forEach(el => el.disabled = !!on);
    const sp = $("#busy");
    if (sp) sp.style.visibility = on ? "visible" : "hidden";
  }

  function notify(type, msg){
    const box = $("#notif_box");
    if (box) {
      box.className = "notif " + (type === "error" ? "error" : "success");
      box.style.display = "block";
      box.textContent = msg;
      setTimeout(()=>{ box.style.display = "none"; }, 3500);
    } else {
      console[type === "error" ? "error" : "log"](msg);
    }
  }

  function escapeHtml(x){ return (window.Utils?.escapeHtml ? Utils.escapeHtml(x) : String(x ?? "")); }

  function getKey(){
    return (Utils.getStoredApiKey ? Utils.getStoredApiKey("operator") : ($("#opApiKey")?.value || "").trim());
  }

// --- Motion rules visual proof (secret / quorum / majority) ---
const __cache = {
  policiesLoaded: false,
  votePolicies: new Map(),   // id -> name
  quorumPolicies: new Map(), // id -> name
  meetingVotePolicyId: null,
  meetingQuorumPolicyId: null,
  motionsById: new Map(),    // motion_id -> motion
  motionsMeetingId: null,
};

async function ensurePolicies(meetingId){
  if (!meetingId) return;
  // reload when meeting changes
  if (__cache.motionsMeetingId !== meetingId){
    __cache.motionsById.clear();
    __cache.motionsMeetingId = meetingId;
  }
  if (__cache.policiesLoaded) return;
  try{
    const [vp, qp] = await Promise.all([
      apiGet('/api/v1/vote_policies.php'),
      apiGet('/api/v1/quorum_policies.php')
    ]);
    (vp || []).forEach(p => { if (p && p.id) __cache.votePolicies.set(String(p.id), p.name || p.label || p.code || String(p.id)); });
    (qp || []).forEach(p => { if (p && p.id) __cache.quorumPolicies.set(String(p.id), p.name || p.label || p.code || String(p.id)); });
    __cache.policiesLoaded = true;
  }catch(e){
    // non-bloquant
  }
}

async function ensureMeetingDefaults(meetingId){
  if (!meetingId) return;
  try{
    const [mv, mq] = await Promise.all([
      apiGet('/api/v1/meeting_vote_settings.php?meeting_id=' + encodeURIComponent(meetingId)),
      apiGet('/api/v1/meeting_quorum_settings.php?meeting_id=' + encodeURIComponent(meetingId)),
    ]);
    __cache.meetingVotePolicyId = mv?.vote_policy_id ?? mv?.votePolicyId ?? mv?.default_vote_policy_id ?? null;
    __cache.meetingQuorumPolicyId = mq?.quorum_policy_id ?? mq?.quorumPolicyId ?? mq?.default_quorum_policy_id ?? null;
  }catch(e){
    // non-bloquant
  }
}

async function ensureMotions(meetingId){
  if (!meetingId) return;
  if (__cache.motionsById.size > 0) return;
  try{
    const data = await apiGet('/api/v1/motions_for_meeting.php?meeting_id=' + encodeURIComponent(meetingId));
    const agendas = data?.agendas || data?.items || [];
    for (const ag of agendas){
      const motions = ag?.motions || [];
      for (const mo of motions){
        if (mo?.id) __cache.motionsById.set(String(mo.id), mo);
      }
    }
  }catch(e){
    // non-bloquant
  }
}

function policyName(map, id){
  if (!id) return '—';
  return map.get(String(id)) || String(id);
}

function renderMotionRulesBadges(motion, meetingDefaults){
  const parts = [];
  const secret = !!(motion?.secret || motion?.is_secret);
  if (secret) parts.push('<span class="badge danger">SECRET</span>');

  const voteOverride = motion?.vote_policy_id || motion?.votePolicyId || null;
  const quorumOverride = motion?.quorum_policy_id || motion?.quorumPolicyId || null;

  const voteEffective = voteOverride || meetingDefaults.vote;
  const quorumEffective = quorumOverride || meetingDefaults.quorum;

  const voteStar = voteOverride ? '★' : '';
  const quorumStar = quorumOverride ? '★' : '';

  parts.push('<span class="badge">MAJORITÉ' + voteStar + ': ' + escapeHtml(policyName(__cache.votePolicies, voteEffective)) + '</span>');
  parts.push('<span class="badge">QUORUM' + quorumStar + ': ' + escapeHtml(policyName(__cache.quorumPolicies, quorumEffective)) + '</span>');

  return parts.join(' ');
}

async function updateMotionProofUI(meetingId, workflow){
  const el = $('#motionRulesBadges');
  if (!el) return;
  el.innerHTML = '';
  if (!meetingId) return;

  await ensurePolicies(meetingId);
  await ensureMeetingDefaults(meetingId);
  await ensureMotions(meetingId);

  const mo = workflow?.motion || {};
  const targetId = mo.open_motion_id || mo.next_motion_id || null;
  if (!targetId){
    el.innerHTML = '<span class="muted tiny">—</span>';
    return;
  }
  const motion = __cache.motionsById.get(String(targetId)) || { id: targetId, secret: mo.open_secret || mo.next_secret };
  const html = renderMotionRulesBadges(motion, { vote: __cache.meetingVotePolicyId, quorum: __cache.meetingQuorumPolicyId });
  el.innerHTML = html;
}


  function selectedMeetingId(){ return ($("#meetingSelect")?.value || "").trim(); }
  function saveMeetingId(id){ localStorage.setItem("operator.meeting_id", id); }
  function loadMeetingId(){ return (localStorage.getItem("operator.meeting_id") || "").trim(); }

  // Notifications polling
  let lastNotifId = 0;
  let notifCount = 0;

  function fmtTime(ts){
    try { return new Date(ts).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); }
    catch(_) { return String(ts || '').slice(11,16); }
  }

  function renderNotifications(items){
    const box = $("#notificationsList");
    const badgeEl = $("#badgeNotifications");
    if (!box) return;

    if (!Array.isArray(items) || items.length === 0) {
      if (notifCount === 0) box.innerHTML = "<div class='muted tiny'>—</div>";
      if (badgeEl) badgeEl.textContent = String(notifCount);
      return;
    }

    // On prepend les nouvelles, mais on garde la liste courte
    const cur = box.querySelectorAll('.notif-item');
    if (cur.length === 0) box.innerHTML = "";

    for (const n of items.slice().reverse()){
      const sev = (n.severity || 'info');
      const kind = (sev === 'blocking') ? 'danger' : (sev === 'warn' ? 'warn' : 'idle');
      const t = fmtTime(n.created_at);
      const data = (n.data && typeof n.data === 'object') ? n.data : {};
      const actionUrl = (data.action_url || '').toString();
      const actionLabel = (data.action_label || '').toString();
      const el = document.createElement('div');
      el.className = 'notif-item';
      el.innerHTML = `
        <div class="notif-meta">
          <span class="badge ${kind}">${escapeHtml(sev)}</span>
          <span class="muted tiny mono">${escapeHtml(t)}</span>
        </div>
        <div class="notif-msg">${escapeHtml(n.message || '')}</div>
        ${actionUrl ? `<div class="notif-action"><a class="btn tiny" href="${escapeHtml(actionUrl)}">${escapeHtml(actionLabel || 'Ouvrir')}</a></div>` : ''}
      `;
      box.prepend(el);
      notifCount++;
      lastNotifId = Math.max(lastNotifId, Number(n.id || 0));
    }

    // Trim: max 12
    while (box.children.length > 12) box.removeChild(box.lastElementChild);
    if (badgeEl) badgeEl.textContent = String(notifCount);
  }

  async function pollNotifications(meetingId){
    if (!meetingId) return;
    try{
      const r = await apiGet(`/api/v1/notifications_list.php?meeting_id=${encodeURIComponent(meetingId)}&audience=operator&since_id=${encodeURIComponent(String(lastNotifId))}&limit=30`);
      const items = r?.data?.notifications || [];
      renderNotifications(items);
    } catch(e){ /* silencieux */ }
  }

  // Mode dégradé (saisie manuel)
  let degradedMotionId = '';
  let degradedMotionTitle = '';

  function openDegradedModal(motionId, title){
    degradedMotionId = motionId || '';
    degradedMotionTitle = title || '';
    const m = $("#modalDegraded");
    const lbl = $("#degradedMotionLabel");
    if (lbl) lbl.textContent = degradedMotionTitle ? `Motion: ${degradedMotionTitle}` : `Motion: ${degradedMotionId}`;
    if (m) m.style.display = 'flex';
  }

  function closeDegradedModal(){
    const m = $("#modalDegraded");
    if (m) m.style.display = 'none';
  }

  function resetDegraded(){
    ["#degTotal","#degFor","#degAgainst","#degAbstain","#degJustification"].forEach(s=>{ const el=$(s); if(el) el.value=''; });
  }

  async function apiGet(url){ return Utils.apiGet(url, { apiKey: getKey() }); }
  async function apiPost(url, data){ return Utils.apiPost(url, data, { apiKey: getKey() }); }

  function badge(el, kind, text){
    if (!el) return;
    el.className = "badge" + (kind ? " " + kind : "");
    el.textContent = text;
  }

  function setHtml(sel, html){
    const el = $(sel);
    if (!el) return;
    el.innerHTML = html;
  }

  async function loadMeetings(){
    const sel = $("#meetingSelect");
    if (!sel) return;

    const r = await apiGet("/api/v1/meetings_index.php");
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

    const saved = loadMeetingId();
    if (saved && meetings.some(x => x.meeting_id === saved)) sel.value = saved;
    else if (meetings.length) sel.value = meetings[0].meeting_id;

    await refresh();
  }

  async function refresh(){
    const meetingId = selectedMeetingId();
    if (meetingId) saveMeetingId(meetingId);

    if (!meetingId){
      setHtml("#meetingSummary", "<span class='muted'>Sélectionnez une séance.</span>");
      return;
    }

    setBusy(true);
    try{
      const r = await apiGet(`/api/v1/operator_workflow_state.php?meeting_id=${encodeURIComponent(meetingId)}`);
      const d = r?.data || {};

      setHtml("#meetingSummary", `
        <div><strong>${escapeHtml(d.meeting?.title || "Séance")}</strong> <span class="badge">${escapeHtml(d.meeting?.status || "—")}</span></div>
        <div class="muted tiny">Président: ${escapeHtml(d.meeting?.president_name || "—")} · Motions: ${escapeHtml(String(d.motions?.total ?? 0))} (ouvertes: ${escapeHtml(String(d.motions?.open ?? 0))})</div>
      `);

      const att = d.attendance || {};
      badge($("#badgeAttendance"), att.ok ? "success" : "warn", att.ok ? "OK" : "À faire");
      setHtml("#attendanceDetail", `Présents: <span class="mono">${escapeHtml(String(att.present_count ?? 0))}</span> (poids <span class="mono">${escapeHtml(String(att.present_weight ?? 0))}</span>) / Total: <span class="mono">${escapeHtml(String(att.total_count ?? 0))}</span> (poids <span class="mono">${escapeHtml(String(att.total_weight ?? 0))}</span>)`);

      const pr = d.proxies || {};
      badge($("#badgeProxies"), "muted", `${escapeHtml(String(pr.active_count ?? 0))} active(s)`);
      setHtml("#proxiesDetail", pr.notes ? escapeHtml(pr.notes) : "Procurations actives comptabilisées dans l’éligibilité des votes proxy.");

      badge($("#badgeVote"), "muted", "RBAC");
      setHtml("#voteDetail", "Tokens/invitations désactivés. Accès via clé API (X-Api-Key) pendant le dev.");

      const mo = d.motion || {};
      const hasOpen = !!mo.open_motion_id;
      badge($("#badgeMotion"), hasOpen ? "warn" : "success", hasOpen ? "Ouverte" : "Aucune ouverte");
      setHtml("#motionDetail", hasOpen
        ? `Motion ouverte: <strong>${escapeHtml(mo.open_title || "—")}</strong> · Votes: <span class="mono">${escapeHtml(String(mo.open_ballots ?? 0))}</span>`
        : `Aucune motion ouverte. Prochaine: <strong>${escapeHtml(mo.next_title || "—")}</strong>`);

      updateMotionProofUI(meetingId, d);

      const btnOpenMotion = $("#btnOpenMotion");
      const btnCloseMotion = $("#btnCloseMotion");
      const btnGenTokens = $("#btnGenTokens");
      const tokenOutput = $("#tokenOutput");
      const tokenMeta = $("#tokenMeta");
      const btnShowQrs = $("#btnShowQrs");
      const btnClearQrs = $("#btnClearQrs");
      const qrGrid = $("#qrGrid");

      const btnDegraded = $("#btnDegraded");
      if (btnOpenMotion) btnOpenMotion.disabled = !mo.can_open_next;
      if (btnCloseMotion) btnCloseMotion.disabled = !mo.can_close_open;
      if (btnDegraded) btnDegraded.disabled = !(mo.open_motion_id || mo.last_closed_motion_id);

      if (btnOpenMotion) btnOpenMotion.onclick = async () => {
        if (!mo.next_motion_id) return;
        if (!confirm("Ouvrir la motion suivante ?")) return;
        setBusy(true);
        try{
          // Clôture explicite puis ouverture (backend refuse si une autre motion est active)
          if (mo.open_motion_id) {
            await apiPost("/api/v1/motions_close.php", { motion_id: mo.open_motion_id });
          }
          await apiPost("/api/v1/motions_open.php", { meeting_id: meetingId, motion_id: mo.next_motion_id });
          notify("success", "Motion ouverte.");
        } catch(e){ notify("error", e?.message || String(e)); }
        finally { setBusy(false); }
        await refresh();
      };

      if (btnCloseMotion) btnCloseMotion.onclick = async () => {
        if (!mo.open_motion_id) return;
        if (!confirm("Fermer la motion ouverte ?")) return;
        setBusy(true);
        try{
          await apiPost("/api/v1/motions_close.php", { meeting_id: meetingId, motion_id: mo.open_motion_id });
          notify("success", "Motion fermée.");
        } catch(e){ notify("error", e?.message || String(e)); }
        finally { setBusy(false); }
        await refresh();
      };

function clearQrGrid(){
  if (!qrGrid) return;
  qrGrid.innerHTML = "";
}

function renderQrsFromTextarea(){
  if (!qrGrid || !tokenOutput) return;
  clearQrGrid();
  const lines = tokenOutput.value.split("\n").map(s => s.trim()).filter(Boolean);
  if (!lines.length) { notify("warning","Aucun lien à convertir en QR."); return; }

  // Each line: "Name<TAB>URL"
  for (const line of lines){
    const parts = line.split(/\t+/);
    const name = (parts[0] || "—").trim();
    const url = (parts[1] || "").trim();
    if (!url) continue;

    const card = document.createElement("div");
    card.className = "card";
    card.style.padding = "10px";
    card.style.display = "flex";
    card.style.flexDirection = "column";
    card.style.gap = "8px";

    const title = document.createElement("div");
    title.className = "tiny";
    title.style.fontWeight = "700";
    title.textContent = name;

    const qrBox = document.createElement("div");
    qrBox.style.width = "160px";
    qrBox.style.maxWidth = "100%";
    qrBox.innerHTML = (window.QR && window.QR.toSvg) ? window.QR.toSvg(url, 160) : "<div class='muted tiny'>QR lib manquante</div>";

    const link = document.createElement("div");
    link.className = "muted tiny";
    link.style.wordBreak = "break-all";
    link.textContent = url;

    card.appendChild(title);
    card.appendChild(qrBox);
    card.appendChild(link);
    qrGrid.appendChild(card);
  }
  notify("success", "QR générés.");
}

if (btnShowQrs) btnShowQrs.onclick = () => renderQrsFromTextarea();
if (btnClearQrs) btnClearQrs.onclick = () => { clearQrGrid(); if (tokenMeta) tokenMeta.textContent = "—"; if (tokenOutput) tokenOutput.value=""; };

if (btnGenTokens) btnGenTokens.onclick = async () => {
  if (!mo.open_motion_id) {
    notify("warning", "Aucune motion ouverte : ouvrez une motion avant de générer les tokens.");
    return;
  }
  setBusy(true);
  try{
    const r = await apiPost("/api/v1/vote_tokens_generate.php", {
      meeting_id: meetingId,
      motion_id: mo.open_motion_id,
      ttl_minutes: 180
    });
    const tokens = (r.data && r.data.tokens) ? r.data.tokens : [];
    const count = (r.data && r.data.count) ? r.data.count : tokens.length;
    const exp = (r.data && r.data.expires_in) ? r.data.expires_in : 180;

    if (tokenMeta) tokenMeta.textContent = `Générés: ${count} — expiration: ~${exp} min`;
    if (tokenOutput) {
      tokenOutput.value = tokens.map(t => `${t.member_name}\t${location.origin}${t.url}`).join("\n");
    }
    notify("success", "Tokens générés.");
  } catch(e){
    notify("error", e?.message || String(e));
  } finally {
    setBusy(false);
  }
};


      if (btnDegraded) btnDegraded.onclick = () => {
        const mid = mo.open_motion_id || mo.last_closed_motion_id || '';
        const title = mo.open_title || mo.last_closed_title || '';
        if (!mid) return;
        openDegradedModal(mid, title);
      };

      const co = d.consolidation || {};
      badge($("#badgeConsolidate"), co.can ? "success" : "warn", co.can ? "Possible" : "Bloqué");
      setHtml("#consolidateDetail", escapeHtml(co.detail || "—"));
      const btnConsolidate = $("#btnConsolidate");
      if (btnConsolidate) {
        btnConsolidate.disabled = !co.can;
        btnConsolidate.onclick = async () => {
          if (!confirm("Consolider/recalculer les décisions pour la séance ?")) return;
          setBusy(true);
          try{ await apiPost("/api/v1/meeting_consolidate.php", { meeting_id: meetingId }); notify("success","Consolidation effectuée."); }
          catch(e){ notify("error", e?.message || String(e)); }
          finally { setBusy(false); }
          await refresh();
        };
      }

      const va = d.validation || {};
      badge($("#badgeValidate"), va.ready ? "success" : "warn", va.ready ? "Prêt" : "Pas prêt");
      const rs = (va.reasons || []).map(x => `<li>${escapeHtml(x)}</li>`).join("");
      setHtml("#validateDetail", va.ready
        ? "Prêt pour validation Président (via console Trust)."
        : `<div>Bloquants :</div><ul class="list">${rs || "<li>—</li>"}</ul>`);

      // notifications: gérées par alerts-panel.js (UI TSX)

    } catch(e){
      console.error(e);
      notify("error", e?.message || String(e));
      setHtml("#meetingSummary", `<span class='muted'>Erreur: ${escapeHtml(e?.message || String(e))}</span>`);
    } finally { setBusy(false); }
  }

  function wire(){
    if (Utils.bindApiKeyInput) {
      Utils.bindApiKeyInput("operator", $("#opApiKey"), () => loadMeetings().catch(console.error));
    }
    $("#btnRefresh")?.addEventListener("click", refresh);
    $("#meetingSelect")?.addEventListener("change", refresh);

    // modal degraded
    $("#btnCloseDegraded")?.addEventListener("click", closeDegradedModal);
    $("#modalDegraded")?.addEventListener("click", (e)=>{
      if (e.target && e.target.id === 'modalDegraded') closeDegradedModal();
    });
    $("#btnDegradedReset")?.addEventListener("click", resetDegraded);
    $("#btnDegradedSubmit")?.addEventListener("click", async () => {
      if (!degradedMotionId) return;
      const payload = {
        motion_id: degradedMotionId,
        manual_total: parseInt($("#degTotal")?.value || '0', 10),
        manual_for: parseInt($("#degFor")?.value || '0', 10),
        manual_against: parseInt($("#degAgainst")?.value || '0', 10),
        manual_abstain: parseInt($("#degAbstain")?.value || '0', 10),
        justification: String($("#degJustification")?.value || '').trim(),
      };
      setBusy(true);
      try{
        await apiPost('/api/v1/degraded_tally.php', payload);
        notify('success', 'Comptage manuel enregistré (mode dégradé).');
        closeDegradedModal();
        resetDegraded();
      } catch(e){
        notify('error', e?.message || String(e));
      } finally {
        setBusy(false);
      }
      await refresh();
    });

    loadMeetings().catch((e)=>notify("error", e?.message || String(e)));
    setInterval(() => { if (!document.hidden && selectedMeetingId()) refresh().catch(()=>{}); }, 4000);
  }

  document.addEventListener("DOMContentLoaded", wire);
})();