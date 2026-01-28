(function(){
  const $ = (s) => document.querySelector(s);

  const modal = $("#modalDegraded");
  if (!modal) return;

  const btnOpen = $("#btnDegradedOpen");
  const btnClose = $("#btnCloseDegraded");
  const btnReset = $("#btnDegradedReset");
  const btnSubmit = $("#btnDegradedSubmit");

  const elTitle = $("#degMotionLabel");
  const elMotionId = $("#degMotionId");
  const elTotal = $("#degTotal");
  const elFor = $("#degFor");
  const elAgainst = $("#degAgainst");
  const elAbstain = $("#degAbstain");
  const elJustif = $("#degJustification");
  const elSum = $("#degSum");
  const elTotalEcho = $("#degTotalEcho");
  const elConsistency = $("#degConsistency");
  const elError = $("#degError");

  function apiKey(){
    const v = $("#opApiKey")?.value || "";
    return v.trim();
  }
  function meetingId(){
    return ($("#meetingSelect")?.value || "").trim();
  }

  async function fetchJson(url, opts){
    const headers = Object.assign({"Content-Type":"application/json"}, (opts && opts.headers) ? opts.headers : {});
    const k = apiKey();
    if (k) headers["X-Api-Key"] = k;
    const r = await fetch(url, Object.assign({credentials:"same-origin", headers}, opts || {}));
    const data = await r.json().catch(()=> ({}));
    if (!r.ok) throw Object.assign(new Error("request_failed"), {status:r.status, data});
    return data;
  }

  function showError(msg){
    if (!elError) return;
    elError.style.display = "block";
    elError.textContent = msg;
  }
  function clearError(){
    if (!elError) return;
    elError.style.display = "none";
    elError.textContent = "";
  }

  function openModal(){
    modal.style.display = "block";
  }
  function closeModal(){
    modal.style.display = "none";
    clearError();
  }

  function recalc(){
    const t = parseInt(elTotal?.value || "0", 10) || 0;
    const f = parseInt(elFor?.value || "0", 10) || 0;
    const a = parseInt(elAgainst?.value || "0", 10) || 0;
    const ab = parseInt(elAbstain?.value || "0", 10) || 0;
    const sum = f + a + ab;
    if (elSum) elSum.textContent = String(sum);
    if (elTotalEcho) elTotalEcho.textContent = String(t);

    if (!elConsistency) return;
    if (t <= 0) {
      elConsistency.className = "badge warn";
      elConsistency.textContent = "Total requis";
      return;
    }
    if (sum !== t) {
      elConsistency.className = "badge warn";
      elConsistency.textContent = "Incohérent";
      return;
    }
    elConsistency.className = "badge ok";
    elConsistency.textContent = "OK";
  }

  function resetFields(){
    if (elTotal) elTotal.value = "";
    if (elFor) elFor.value = "";
    if (elAgainst) elAgainst.value = "";
    if (elAbstain) elAbstain.value = "";
    if (elJustif) elJustif.value = "";
    recalc();
    clearError();
  }

  async function loadContext(){
    const mid = meetingId();
    if (!mid) {
      if (elTitle) elTitle.textContent = "Aucune séance sélectionnée";
      if (elMotionId) elMotionId.value = "";
      return;
    }

    // 1) workflow_state pour choisir motion cible (open en priorité, sinon dernière fermée)
    const st = await fetchJson(`/api/v1/operator_workflow_state.php?meeting_id=${encodeURIComponent(mid)}`);
    const motionId = st?.data?.motion?.open_motion_id || st?.data?.motion?.last_closed_motion_id || "";
    const title = st?.data?.motion?.open_title || st?.data?.motion?.last_closed_title || "(aucune résolution)";

    if (elTitle) elTitle.textContent = title;
    if (elMotionId) elMotionId.value = motionId;

    // 2) charger tally actuel si motion
    if (motionId) {
      try {
        const tally = await fetchJson(`/api/v1/motion_tally.php?motion_id=${encodeURIComponent(motionId)}`);
        const d = tally?.data || {};
        if (elTotal) elTotal.value = String(d.manual_total ?? "");
        if (elFor) elFor.value = String(d.manual_for ?? "");
        if (elAgainst) elAgainst.value = String(d.manual_against ?? "");
        if (elAbstain) elAbstain.value = String(d.manual_abstain ?? "");
      } catch(_) {
        // best-effort
      }
    }

    recalc();
  }

  async function submit(){
    clearError();
    const motionId = (elMotionId?.value || "").trim();
    if (!motionId) {
      showError("Aucune résolution cible. Ouvre ou ferme au moins une résolution pour pouvoir saisir un comptage manuel.");
      return;
    }

    const total = parseInt(elTotal?.value || "0", 10) || 0;
    const vFor = parseInt(elFor?.value || "0", 10) || 0;
    const vAgainst = parseInt(elAgainst?.value || "0", 10) || 0;
    const vAbstain = parseInt(elAbstain?.value || "0", 10) || 0;
    const justif = (elJustif?.value || "").trim();

    if (!justif) {
      showError("Justification obligatoire en mode dégradé.");
      return;
    }

    try {
      btnSubmit && (btnSubmit.disabled = true);
      await fetchJson(`/api/v1/degraded_tally.php`, {
        method: "POST",
        body: JSON.stringify({
          motion_id: motionId,
          manual_total: total,
          manual_for: vFor,
          manual_against: vAgainst,
          manual_abstain: vAbstain,
          justification: justif,
        })
      });
      window.Utils?.toast?.("Comptage manuel enregistré", "ok");
      closeModal();
      // refresh opérateur
      const r = $("#btnRefresh") || $("#btnRefreshMain");
      if (r) r.click();
    } catch (e) {
      window.Utils?.toast?.("Échec enregistrement", "error");
      showError(window.Utils?.humanizeError ? Utils.humanizeError(e) : (e?.data?.detail || "Erreur"));
    } finally {
      btnSubmit && (btnSubmit.disabled = false);
    }
  }

  // Bindings
  btnOpen?.addEventListener("click", async () => {
    openModal();
    resetFields();
    try {
      await loadContext();
    } catch (e) {
      showError(window.Utils?.humanizeError ? Utils.humanizeError(e) : (e?.data?.detail || "Impossible de charger le contexte."));
    }
  });
  btnClose?.addEventListener("click", closeModal);
  modal.querySelector(".modal-backdrop")?.addEventListener("click", closeModal);
  btnReset?.addEventListener("click", resetFields);
  btnSubmit?.addEventListener("click", submit);

  [elTotal, elFor, elAgainst, elAbstain].forEach((el) => el?.addEventListener("input", recalc));
})();
