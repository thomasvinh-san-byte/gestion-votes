(function () {
  const $ = (sel) => document.querySelector(sel);

  // Notifications polling
  let lastNotifId = 0;
  let notifCount = 0;

  function fmtTime(ts) {
    try { return new Date(ts).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }); }
    catch (_) { return String(ts || "").slice(11, 16); }
  }

  function renderNotifications(items) {
    const box = $("#notificationsList");
    const badgeEl = $("#badgeNotifications");
    if (!box) return;

    if (!Array.isArray(items) || items.length === 0) {
      if (notifCount === 0) box.innerHTML = "<div class='muted tiny'>—</div>";
      if (badgeEl) badgeEl.textContent = String(notifCount);
      return;
    }

    const cur = box.querySelectorAll(".notif-item");
    if (cur.length === 0) box.innerHTML = "";

    for (const n of items.slice().reverse()) {
      const sev = n.severity || "info";
      const kind = sev === "blocking" ? "danger" : (sev === "warn" ? "warn" : "idle");
      const t = fmtTime(n.created_at);
      const data = (n.data && typeof n.data === 'object') ? n.data : {};
      const actionUrl = (data.action_url || '').toString();
      const actionLabel = (data.action_label || '').toString();
      const el = document.createElement("div");
      el.className = "notif-item";
      el.innerHTML = `
        <div class="notif-meta">
          <span class="badge ${kind}">${Utils.escapeHtml(sev)}</span>
          <span class="muted tiny mono">${Utils.escapeHtml(t)}</span>
        </div>
        <div class="notif-msg">${Utils.escapeHtml(n.message || "")}</div>
        ${actionUrl ? `<div class="notif-action"><a class="btn tiny" href="${Utils.escapeHtml(actionUrl)}">${Utils.escapeHtml(actionLabel || 'Ouvrir')}</a></div>` : ''}
      `;
      box.prepend(el);
      notifCount++;
      lastNotifId = Math.max(lastNotifId, Number(n.id || 0));
    }

    while (box.children.length > 12) box.removeChild(box.lastElementChild);
    if (badgeEl) badgeEl.textContent = String(notifCount);
  }

  async function pollNotifications(meetingId) {
    if (!meetingId) return;
    const key = getApiKey();
    try {
      const r = await Utils.apiGet(
        `/api/v1/notifications_list.php?meeting_id=${encodeURIComponent(meetingId)}&audience=trust&since_id=${encodeURIComponent(String(lastNotifId))}&limit=30`,
        { apiKey: key }
      );
      const items = r?.data?.notifications || [];
      renderNotifications(items);
    } catch (_) {
      // silencieux
    }
  }

  function getApiKey() {
    return ($("#trustApiKey")?.value || "").trim();
  }

  function saveApiKey(key) {
    localStorage.setItem("trust.api_key", key);
  }

  function loadApiKey() {
    return (localStorage.getItem("trust.api_key") || "").trim();
  }

  function setBusy(on) {
    document.querySelectorAll("[data-busy]").forEach((el) => (el.disabled = !!on));
    const sp = $("#busy");
    if (sp) sp.style.visibility = on ? "visible" : "hidden";
  }

  function toast(type, title, detail) {
    const msg = detail ? `${title}: ${detail}` : title;
    if (typeof setNotif === "function") {
      setNotif(type === "danger" ? "error" : "success", msg);
    } else {
      console[type === "danger" ? "error" : "log"](msg);
    }
  }

  function selectedMeetingId() {
    return ($("#meetingSelect")?.value || "").trim();
  }

  async function loadMeetings() {
    const key = getApiKey();
    const sel = $("#meetingSelect");
    if (!sel) return;

    const r = await Utils.apiGet("/api/v1/meetings_index.php", { apiKey: key });
    const meetings = r?.data?.meetings || [];

    sel.innerHTML = "";
    const opt0 = document.createElement("option");
    opt0.value = "";
    opt0.textContent = "— Sélectionner une séance —";
    sel.appendChild(opt0);

    for (const m of meetings) {
      const opt = document.createElement("option");
      opt.value = m.meeting_id;
      const when = (m.created_at || "").toString().slice(0, 10);
      opt.textContent = `${when} — ${m.title || "Séance"} [${m.status || "—"}]`;
      sel.appendChild(opt);
    }

    const saved = (localStorage.getItem("trust.meeting_id") || "").trim();
    if (saved && meetings.some((x) => x.meeting_id === saved)) sel.value = saved;
    else if (meetings.length > 0) sel.value = meetings[0].meeting_id;

    await refreshAll();
  }

  async function loadStatus(meetingId) {
    const key = getApiKey();
    const box = $("#statusBox");
    if (!box) return;

    if (!meetingId) {
      box.innerHTML = `<div class="muted">Sélectionnez une séance.</div>`;
      return;
    }

    const r = await Utils.apiGet(`/api/v1/meeting_status_for_meeting.php?meeting_id=${encodeURIComponent(meetingId)}`, { apiKey: key });
    const data = r?.data || {};

    const title = Utils.escapeHtml(data.meeting_title || "Séance");
    const status = Utils.escapeHtml(data.meeting_status || "—");
    const sign = Utils.escapeHtml(data.sign_status || "—");
    const msg = Utils.escapeHtml(data.sign_message || "");

    box.innerHTML = `
      <div class="row">
        <div>
          <div class="k">${title}</div>
          <div class="muted">Statut: <span class="badge">${status}</span> <span class="badge muted">${sign}</span></div>
          <div class="muted">${msg}</div>
        </div>
        <div class="right">
          <button class="btn" id="btnOpenPV" data-busy>Ouvrir PV</button>
          <button class="btn" id="btnAudit" data-busy>Voir audit</button>
          <button class="btn danger" id="btnConsolidate" data-busy>Consolider / recalculer</button>
        </div>
      </div>
    `;

    $("#btnOpenPV")?.addEventListener("click", () => openPV(meetingId));
    $("#btnAudit")?.addEventListener("click", () => openAudit(meetingId));
    $("#btnConsolidate")?.addEventListener("click", () => consolidate(meetingId));
  }

  async function loadMotions(meetingId) {
    const key = getApiKey();
    const tbody = $("#motionsTbody");
    if (!tbody) return;

    tbody.innerHTML = "";
    if (!meetingId) return;

    const r = await Utils.apiGet(`/api/v1/trust_overview.php?meeting_id=${encodeURIComponent(meetingId)}`, { apiKey: key });
    const motions = r?.data?.motions || [];

    function badge(text, cls='badge'){
      return `<span class="${cls}">${Utils.escapeHtml(text)}</span>`;
    }

    for (const m of motions) {
      const t = m.tallies || {};
      const dec = m.decision || {};
      const src = Utils.escapeHtml(m.official_source || "—");

      const forW = Number(t.for?.weight ?? 0);
      const agW  = Number(t.against?.weight ?? 0);
      const abW  = Number(t.abstain?.weight ?? 0);
      const totW = Number(t.total?.weight ?? (forW + agW + abW));

      const forC = Number(t.for?.count ?? 0);
      const agC  = Number(t.against?.count ?? 0);
      const abC  = Number(t.abstain?.count ?? 0);
      const totC = forC + agC + abC;

      const ds = (dec.status || "—").toString();
      const badgeClass = ds === "adopted" ? "success" : (ds === "rejected" ? "danger" : "muted");

      const isSecret = !!m.secret;
      const vp = m.vote_policy || {};
      const qp = m.quorum_policy || {};
      const vpName = vp.name || '—';
      const qpName = qp.name || '—';
      const vpStar = vp.overridden ? '★' : '';
      const qpStar = qp.overridden ? '★' : '';

      const motionMetaBadges = [
        isSecret ? badge('SECRET', 'badge danger') : '',
        vp.id ? badge(`MAJORITÉ${vpStar}: ${vpName}`, vp.overridden ? 'badge warn' : 'badge') : '',
        qp.id ? badge(`QUORUM${qpStar}: ${qpName}`, qp.overridden ? 'badge warn' : 'badge') : '',
      ].filter(Boolean).join(' ');

      const decisionReason = (dec.reason || '').toString();

      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td class="p-2 align-top">
          <div class="font-semibold">${Utils.escapeHtml(m.title || "Motion")}</div>
          <div class="muted tiny">${Utils.escapeHtml(m.description || "")}</div>
          ${motionMetaBadges ? `<div class="mt-2" style="display:flex; gap:6px; flex-wrap:wrap;">${motionMetaBadges}</div>` : ''}
          <div class="muted tiny">Ouverture: ${Utils.escapeHtml(m.opened_at || "—")} · Clôture: ${Utils.escapeHtml(m.closed_at || "—")}</div>
        </td>
        <td class="p-2 align-top"><span class="badge muted">${src}</span></td>
        <td class="p-2 align-top">
          <div class="mono tiny">Pour: <strong>${forW}</strong> (${forC})</div>
          <div class="mono tiny">Contre: <strong>${agW}</strong> (${agC})</div>
          <div class="mono tiny">Abst.: <strong>${abW}</strong> (${abC})</div>
          <div class="mono tiny muted">Total: ${totW} (${totC})</div>
        </td>
        <td class="p-2 align-top">
          <div><span class="badge ${badgeClass}">${Utils.escapeHtml(ds)}</span></div>
          ${decisionReason ? `<div class="muted tiny">${Utils.escapeHtml(decisionReason)}</div>` : ''}
        </td>
        <td class="p-2 align-top">${badge(m.closed_at ? 'CLOSED' : (m.opened_at ? 'ACTIVE' : 'PENDING'), m.closed_at ? 'badge' : (m.opened_at ? 'badge ok' : 'badge warn'))}</td>
      `;
      tbody.appendChild(tr);
    }
  }

  async function loadReadyCheck(meetingId) {
    const key = getApiKey();
    const box = $("#readyCheckBox");
    if (!box) return;

    if (!meetingId) {
      box.innerHTML = `<div class="muted">Sélectionnez une séance.</div>`;
      return;
    }

    const r = await Utils.apiGet(`/api/v1/meeting_ready_check.php?meeting_id=${encodeURIComponent(meetingId)}`, { apiKey: key });
    const can = !!r?.data?.can;
    const reasons = r?.data?.reasons || [];

    if (can) {
      box.innerHTML = `<div><span class="badge success">Prêt à signer</span> <span class="muted tiny">Aucune anomalie détectée.</span></div>`;
      return;
    }

    const lis = reasons.map((x) => `<li>${Utils.escapeHtml(x)}</li>`).join("");
    box.innerHTML = `
      <div><span class="badge danger">Pas prêt</span> <span class="muted tiny">Corrigez les points ci-dessous.</span></div>
      <ul style="margin:8px 0 0 18px;">${lis}</ul>
    `;
  }

  function openPV(meetingId) {
    const key = getApiKey();
    const url = `/api/v1/meeting_report.php?meeting_id=${encodeURIComponent(meetingId)}&api_key=${encodeURIComponent(key)}`;
    window.open(url, "_blank", "noopener");
  }

  async function openAudit(meetingId) {
    const key = getApiKey();
    const modal = $("#auditModal");
    const body = $("#auditBody");
    if (!modal || !body) return;

    modal.showModal?.();
    body.innerHTML = `<div class="muted">Chargement…</div>`;

    const r = await Utils.apiGet(`/api/v1/meeting_audit_events.php?meeting_id=${encodeURIComponent(meetingId)}`, { apiKey: key });
    const items = r?.data?.events || [];

    if (!items.length) {
      body.innerHTML = `<div class="muted">Aucun événement d’audit.</div>`;
      return;
    }

    const rows = items.map((ev) => {
      const at = Utils.escapeHtml(ev.created_at || "—");
      const type = Utils.escapeHtml(ev.action || ev.event_type || "—");
      const msg = Utils.escapeHtml(ev.message || "");
      return `<tr><td class="tiny">${at}</td><td>${type}</td><td>${msg}</td></tr>`;
    }).join("");

    body.innerHTML = `
      <table class="tbl">
        <thead><tr><th>Date</th><th>Type</th><th>Détail</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    `;
  }

  async function consolidate(meetingId) {
    const key = getApiKey();
    if (!confirm("Recalculer et consolider les décisions pour cette séance ?")) return;

    setBusy(true);
    try {
      const r = await Utils.apiPost("/api/v1/meeting_consolidate.php", { meeting_id: meetingId }, { apiKey: key });
      toast("success", "Consolidation", `Motions mises à jour: ${r?.data?.updated_motions ?? 0}`);
      await refreshAll();
    } catch (e) {
      toast("danger", "Erreur", e?.message || String(e));
    } finally {
      setBusy(false);
    }
  }

  async function refreshAll() {
    const meetingId = selectedMeetingId();
    if (meetingId) localStorage.setItem("trust.meeting_id", meetingId);

    setBusy(true);
    try {
      await loadStatus(meetingId);
      await loadReadyCheck(meetingId);
      await loadMotions(meetingId);
      await pollNotifications(meetingId);
    } finally {
      setBusy(false);
    }
  }

  function wire() {
    const keyInput = $("#trustApiKey");
    if (keyInput) {
      keyInput.value = loadApiKey();
      keyInput.addEventListener("change", () => {
        const k = keyInput.value.trim();
        saveApiKey(k);
        loadMeetings().catch((e) => toast("danger", "Erreur", e?.message || String(e)));
      });
    }

    $("#meetingSelect")?.addEventListener("change", refreshAll);
    $("#btnRefresh")?.addEventListener("click", refreshAll);
    $("#btnReload")?.addEventListener("click", () => loadMeetings());

    $("#auditClose")?.addEventListener("click", () => $("#auditModal")?.close?.());

    loadMeetings().catch((e) => toast("danger", "Erreur", e?.message || String(e)));

    setInterval(() => {
      if (document.hidden) return;
      if (selectedMeetingId()) refreshAll().catch(() => {});
    }, 4000);
  }

  document.addEventListener("DOMContentLoaded", wire);
})();