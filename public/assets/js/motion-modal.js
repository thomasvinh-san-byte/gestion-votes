(function(){
  const $ = (s) => document.querySelector(s);

  function getApiKey(){ return (window.Utils?.getStoredApiKey ? Utils.getStoredApiKey("operator") : ($("#opApiKey")?.value || "")); }
  function getMeetingId(){ return ($("#meetingSelect")?.value || "").trim(); }

  async function apiGet(url){
    const r = await fetch(url, {headers: {"Accept":"application/json","X-Api-Key": getApiKey()}});
    const j = await r.json().catch(()=>null);
    if (!r.ok || !j || j.ok === false) throw new Error((j && (j.error||j.message)) || ("HTTP "+r.status));
    return j.data ?? j;
  }
  async function apiPost(url, body){
    const r = await fetch(url, {method:"POST", headers: {"Content-Type":"application/json","Accept":"application/json","X-Api-Key": getApiKey()}, body: JSON.stringify(body||{})});
    const j = await r.json().catch(()=>null);
    if (!r.ok || !j || j.ok === false) throw new Error((j && (j.error||j.message)) || ("HTTP "+r.status));
    return j.data ?? j;
  }

  

// --- Effective rules helper (proof of overrides) ---
const __rules = {
  votePolicies: new Map(),
  quorumPolicies: new Map(),
  meetingVotePolicyId: null,
  meetingQuorumPolicyId: null,
};
function policyName(map, id){
  if (!id) return "—";
  return map.get(String(id)) || String(id);
}
async function loadMeetingDefaults(){
  const meetingId = getMeetingId();
  if (!meetingId) return;
  try{
    const [mv, mq] = await Promise.all([
      apiGet("/api/v1/meeting_vote_settings.php?meeting_id=" + encodeURIComponent(meetingId)),
      apiGet("/api/v1/meeting_quorum_settings.php?meeting_id=" + encodeURIComponent(meetingId)),
    ]);
    __rules.meetingVotePolicyId = mv?.vote_policy_id ?? mv?.default_vote_policy_id ?? null;
    __rules.meetingQuorumPolicyId = mq?.quorum_policy_id ?? mq?.default_quorum_policy_id ?? null;
  }catch(e){
    // non bloquant
  }
}
function updateEffectiveLabels(){
  const vEl = $("#motionVoteEffective");
  const qEl = $("#motionQuorumEffective");
  const voteSel = $("#motionVotePolicy");
  const quorumSel = $("#motionQuorumPolicy");
  if (vEl){
    const override = voteSel?.value || "";
    const effective = override || __rules.meetingVotePolicyId || "";
    const star = override ? "★ " : "";
    const note = override ? "override (★)" : "hérité séance";
    vEl.textContent = effective ? (`Effectif: ${star}${policyName(__rules.votePolicies, effective)} — ${note}`) : "Effectif: —";
  }
  if (qEl){
    const override = quorumSel?.value || "";
    const effective = override || __rules.meetingQuorumPolicyId || "";
    const star = override ? "★ " : "";
    const note = override ? "override (★)" : "hérité séance";
    qEl.textContent = effective ? (`Effectif: ${star}${policyName(__rules.quorumPolicies, effective)} — ${note}`) : "Effectif: —";
  }
}
function openModal(){
    const m = $("#modalMotion");
    if (!m) return;
    m.classList.remove("hidden");
    m.setAttribute("aria-hidden","false");
  }
  function closeModal(){
    const m = $("#modalMotion");
    if (!m) return;
    m.classList.add("hidden");
    m.setAttribute("aria-hidden","true");
  }

  async function loadPolicies(){
    const [votes, quorums] = await Promise.all([
      apiGet("/api/v1/vote_policies.php"),
      apiGet("/api/v1/quorum_policies.php"),
    ]);
    __rules.votePolicies.clear();
    (votes||[]).forEach(p=>{ if(p && p.id) __rules.votePolicies.set(String(p.id), p.label||p.name||p.title||p.id); });
    __rules.quorumPolicies.clear();
    (quorums||[]).forEach(p=>{ if(p && p.id) __rules.quorumPolicies.set(String(p.id), p.label||p.name||p.title||p.id); });
    const voteSel = $("#motionVotePolicy");
    const quorumSel = $("#motionQuorumPolicy");
    if (voteSel){
      voteSel.innerHTML = '<option value="">(Hériter de la séance)</option>' + (votes.policies||votes||[]).map(p=>`<option value="${p.id}">${p.label||p.name||p.title||p.id}</option>`).join("");
    }
    if (quorumSel){
      quorumSel.innerHTML = '<option value="">(Hériter de la séance)</option>' + (quorums.policies||quorums||[]).map(p=>`<option value="${p.id}">${p.label||p.name||p.title||p.id}</option>`).join("");
    }
  }
    updateEffectiveLabels();
  }

  function fillForm(motion){
    $("#motionId").value = motion?.id || "";
    $("#motionTitle").value = motion?.title || "";
    $("#motionDescription").value = motion?.description || "";
    $("#motionSecret").checked = !!motion?.secret;
    if (motion?.vote_policy_id) $("#motionVotePolicy").value = motion.vote_policy_id;
    if (motion?.quorum_policy_id) $("#motionQuorumPolicy").value = motion.quorum_policy_id;
  
    updateEffectiveLabels();
  }

  async function loadCurrentForEdit(){
    const meetingId = getMeetingId();
    if (!meetingId) throw new Error("Sélectionne une séance.");
    // Best effort: si une motion est active, on la charge; sinon on ouvre en "création".
    const cur = await apiGet("/api/v1/current_motion.php?meeting_id=" + encodeURIComponent(meetingId)).catch(()=>null);
    if (cur && cur.motion) fillForm(cur.motion);
    else fillForm(null);
  }

  async function saveMotion(){
    const meetingId = getMeetingId();
    if (!meetingId) throw new Error("meeting_id manquant");
    const payload = {
      meeting_id: meetingId,
      motion_id: $("#motionId").value || undefined,
      title: ($("#motionTitle").value||"").trim(),
      description: ($("#motionDescription").value||"").trim(),
      secret: !!$("#motionSecret").checked
    };
    if (!payload.title) throw new Error("Titre obligatoire");
    const res = await apiPost("/api/v1/motions.php", payload);
    const motionId = res.motion_id || res.id || payload.motion_id;
    const votePolicy = $("#motionVotePolicy").value || "";
    const quorumPolicy = $("#motionQuorumPolicy").value || "";
    if (votePolicy !== "" || votePolicy === "") {
      await apiPost("/api/v1/motion_vote_override.php", {motion_id: motionId, vote_policy_id: votePolicy});
    }
    if (quorumPolicy !== "" || quorumPolicy === "") {
      await apiPost("/api/v1/motion_quorum_override.php", {motion_id: motionId, quorum_policy_id: quorumPolicy});
    }
    // Refresh opérateur (même mécanisme que le bouton refresh)
    const btn = $("#btnRefreshMain") || $("#btnRefresh");
    if (btn) btn.click();
    closeModal();
  }

  function bind(){
    const openBtn = $("#btnMotionSettings") || $("#btnMotionModal") || $("#btnMotionEdit");
    if (openBtn){
      openBtn.addEventListener("click", async (e)=>{
        e.preventDefault();
        try{
          await loadMeetingDefaults();
          await loadPolicies();
          await loadCurrentForEdit();
          openModal();
        }catch(err){
          (window.UI?.toast ? UI.toast("error", err.message||String(err)) : alert(err.message||String(err)));
        }
      });
    }
    $("#btnCloseMotionModal")?.addEventListener("click", (e)=>{e.preventDefault(); closeModal();});
    $("#btnSaveMotion")?.addEventListener("click", async (e)=>{
      e.preventDefault();
      try{ await saveMotion(); }
      catch(err){ (window.UI?.toast ? UI.toast("error", err.message||String(err)) : alert(err.message||String(err))); }
    });
    $("#modalMotionBackdrop")?.addEventListener("click", closeModal);

    $("#motionVotePolicy")?.addEventListener("change", updateEffectiveLabels);
    $("#motionQuorumPolicy")?.addEventListener("change", updateEffectiveLabels);
    document.addEventListener("keydown", (ev)=>{ if(ev.key==="Escape") closeModal(); });
  }

  document.addEventListener("DOMContentLoaded", bind);
})();