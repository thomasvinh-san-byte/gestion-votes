/* global UI */
(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);

  const els = {
    apiKey: $("apiKey"),
    meetingId: $("meetingId"),
    search: $("search"),
    btnRefresh: $("btnRefresh"),
    netState: $("netState"),
    // TSX-only renderer (no legacy table)
    countShown: $("countShown"),
    kpiPresent: $("kpiPresent"),
    kpiWeight: $("kpiWeight"),
    kpiTotal: $("kpiTotal"),
    kpiTotalWeight: $("kpiTotalWeight"),

    // Optional: TSX-like list layout
    membersList: $("membersList"),

    // Optional: quorum card
    kpiQuorumPct: $("kpiQuorumPct"),
    kpiQuorumBadge: $("kpiQuorumBadge"),
    kpiQuorumDetail: $("kpiQuorumDetail"),
  };

  const LS = {
    key: "gv_operator_api_key",
    meeting: "gv_operator_meeting_id",
    filter: "gv_att_filter",
  };

  let state = {
    rows: [],
    filter: "all",
    search: "",
  };

  function toast(type, title, detail) {
    if (window.UI && UI.toast) return UI.toast(title, detail || "", type || "info");
    alert(title + (detail ? "\n\n" + detail : ""));
  }

  async function fetchJson(url, options) {
    if (window.UI && UI.fetchJson) return UI.fetchJson(url, options);
    const res = await fetch(url, options);
    const txt = await res.text();
    let data = null;
    try { data = txt ? JSON.parse(txt) : null; } catch (_) {}
    if (!res.ok) throw new Error((data && (data.error || data.message)) || (txt || res.statusText));
    return data;
  }

  function apiKey() {
    return (els.apiKey.value || "").trim();
  }

  function setNet(ok) {
    els.netState.textContent = ok ? "Connecté" : "Hors-ligne";
    els.netState.classList.toggle("muted", !ok);
  }

  function currentMeetingId() {
    return els.meetingId.value || "";
  }

  function setKpis(summary) {
    // summary is best-effort; we tolerate absent keys
    const presentCount = summary.present_count ?? summary.present_members ?? summary.present ?? null;
    const presentWeight = summary.present_weight ?? summary.weight_present ?? null;
    const totalCount = summary.total_count ?? summary.total_members ?? null;
    const totalWeight = summary.total_weight ?? summary.weight_total ?? null;

    els.kpiPresent.textContent = presentCount != null ? String(presentCount) : "—";
    els.kpiWeight.textContent = presentWeight != null ? String(presentWeight) : "—";
    els.kpiTotal.textContent = totalCount != null ? String(totalCount) : "—";
    els.kpiTotalWeight.textContent = totalWeight != null ? String(totalWeight) : "—";

    // quorum % (best-effort, non-breaking)
    if (els.kpiQuorumPct) {
      const p = (presentWeight != null && totalWeight != null && Number(totalWeight) > 0)
        ? (Number(presentWeight) / Number(totalWeight))
        : (presentCount != null && totalCount != null && Number(totalCount) > 0)
          ? (Number(presentCount) / Number(totalCount))
          : null;

      els.kpiQuorumPct.textContent = p == null ? "—" : `${(p * 100).toFixed(1)}%`;

      // if backend provides quorum_required, show OK/KO; else keep neutral
      const qReq = summary.quorum_required ?? summary.default_quorum_required ?? null;
      const qOk = (qReq != null && p != null) ? (p >= Number(qReq)) : null;

      if (els.kpiQuorumBadge) {
        const badge = els.kpiQuorumBadge;
        if (qOk == null) {
          badge.textContent = "QUORUM";
          badge.className = "inline-flex px-2 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold";
        } else {
          badge.textContent = qOk ? "QUORUM OK" : "QUORUM KO";
          badge.className = qOk
            ? "inline-flex px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-bold"
            : "inline-flex px-2 py-1 rounded-lg bg-red-50 text-red-700 text-xs font-bold";
        }
      }
      if (els.kpiQuorumDetail) {
        els.kpiQuorumDetail.textContent = (qReq != null)
          ? `Seuil requis: ${(Number(qReq) * 100).toFixed(0)}%`
          : "Seuil quorum non fourni par l'API";
      }
    }
  }

  async function loadMeetings() {
    const k = apiKey();
    if (!k) return;

    // prefer meetings.php
    const res = await fetchJson(`/api/v1/meetings.php`, { headers: { "X-API-Key": k } });
    const list = (res && res.data) || [];

    els.meetingId.innerHTML = "";
    if (!list.length) {
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = "Aucune séance";
      els.meetingId.appendChild(opt);
      return;
    }

    for (const m of list) {
      const opt = document.createElement("option");
      opt.value = m.id || m.meeting_id || "";
      const title = m.title || m.name || "Séance";
      const status = m.status ? ` (${m.status})` : "";
      opt.textContent = `${title}${status}`;
      els.meetingId.appendChild(opt);
    }

    const saved = localStorage.getItem(LS.meeting);
    if (saved) {
      const found = Array.from(els.meetingId.options).some((o) => o.value === saved);
      if (found) els.meetingId.value = saved;
    }
  }

  function normalize(s) {
    return (s || "").toLowerCase();
  }

  function filteredRows() {
    const q = normalize(state.search);
    return state.rows.filter((r) => {
      const status = normalize(r.status);
      if (state.filter !== "all" && status !== state.filter) return false;
      if (q) {
        const name = normalize(r.name || r.member_name || "");
        if (!name.includes(q)) return false;
      }
      return true;
    });
  }

  function statusLabel(status) {
    switch (status) {
      case "present": return "Présent";
      case "remote": return "Remote";
      case "absent": return "Absent";
      default: return status || "—";
    }
  }

  async function setAttendance(memberId, status) {
    const k = apiKey();
    const meetingId = currentMeetingId();
    if (!k || !meetingId) return;

    const payload = { meeting_id: meetingId, member_id: memberId, status: status };

    try {
      await fetchJson(`/api/v1/attendances_upsert.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-API-Key": k },
        body: JSON.stringify(payload),
      });
      toast("success", "Mis à jour", `Statut: ${statusLabel(status)}.`);
      await loadAttendances();
    } catch (e) {
      toast("error", "Échec", e.message || String(e));
    }
  }

  function render() {
    const rows = filteredRows();
    if (!els.membersList) return;
    els.membersList.innerHTML = "";

    for (const r of rows) {
      const id = r.member_id || r.id;
      const name = r.name || r.member_name || "—";
      const weight = r.weight ?? r.vote_weight ?? "—";
      const status = normalize(r.status || "");

      // TSX-like card layout
        const li = document.createElement("li");

        const dotClass = (status === "present") ? "bg-emerald-500" : (status === "remote") ? "bg-indigo-500" : "bg-slate-300";
        const badgeClass = (status === "present")
          ? "bg-emerald-50 text-emerald-700"
          : (status === "remote")
            ? "bg-indigo-50 text-indigo-700"
            : "bg-slate-100 text-slate-700";

        li.className = "p-3 rounded-2xl shadow-sm flex flex-col border border-slate-200 bg-white overflow-hidden";
        li.innerHTML = `
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center min-w-0">
              <div class="w-2 h-2 rounded-full mr-2 flex-shrink-0 ${dotClass}"></div>
              <div class="min-w-0">
                <div class="text-sm font-semibold text-slate-900 truncate">${escapeHtml(name)}</div>
                <div class="mt-1 flex flex-wrap gap-1">
                  <span class="text-[10px] text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded whitespace-nowrap">Poids: ${escapeHtml(String(weight))}</span>
                  <span class="text-[10px] px-1.5 py-0.5 rounded whitespace-nowrap ${badgeClass}">${escapeHtml(statusLabel(status))}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1 flex-shrink-0">
              <button class="h-7 px-2 rounded-lg text-[10px] font-bold border ${status === "present" ? "border-slate-200 text-slate-400" : "border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100"}" data-act="present">PRÉS</button>
              <button class="h-7 px-2 rounded-lg text-[10px] font-bold border ${status === "remote" ? "border-slate-200 text-slate-400" : "border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100"}" data-act="remote">REMOTE</button>
              <button class="h-7 px-2 rounded-lg text-[10px] font-bold border ${status === "absent" ? "border-slate-200 text-slate-400" : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50"}" data-act="absent">ABS</button>
            </div>
          </div>
        `;

        li.querySelectorAll("button[data-act]").forEach((btn) => {
          btn.addEventListener("click", () => setAttendance(id, btn.getAttribute("data-act")));
        });

      els.membersList.appendChild(li);
    }

    els.countShown.textContent = `${rows.length} affiché(s)`;
  }

  async function loadAttendances() {
    const k = apiKey();
    const meetingId = currentMeetingId();
    if (!k || !meetingId) {
      state.rows = [];
      render();
      setKpis({});
      return;
    }

    try {
      const res = await fetchJson(`/api/v1/attendances.php?meeting_id=${encodeURIComponent(meetingId)}`, {
        headers: { "X-API-Key": k },
      });

      // Support different response shapes
      const data = res && res.data ? res.data : {};
      const rows = data.rows || data.attendances || data || [];
      state.rows = Array.isArray(rows) ? rows : [];
      setKpis(data.summary || data);
      setNet(true);
      render();
    } catch (e) {
      setNet(false);
      toast("error", "Impossible de charger", e.message || String(e));
    }
  }

  function applyFilterUI() {
    document.querySelectorAll(".tag[data-filter]").forEach((t) => {
      const f = t.getAttribute("data-filter");
      t.classList.toggle("active", f === state.filter);
    });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c]));
  }

  async function refreshAll() {
    const k = apiKey();
    if (!k) return;
    localStorage.setItem(LS.key, k);
    await loadMeetings();
    const meetingId = currentMeetingId();
    if (meetingId) localStorage.setItem(LS.meeting, meetingId);
    await loadAttendances();
  }

  function wire() {
    els.apiKey.value = localStorage.getItem(LS.key) || "dev-operator-key";
    state.filter = localStorage.getItem(LS.filter) || "all";
    applyFilterUI();

    els.btnRefresh.addEventListener("click", () => refreshAll());

    els.meetingId.addEventListener("change", async () => {
      localStorage.setItem(LS.meeting, currentMeetingId());
      await loadAttendances();
    });

    els.search.addEventListener("input", () => {
      state.search = els.search.value || "";
      render();
    });

    document.querySelectorAll(".tag[data-filter]").forEach((t) => {
      t.addEventListener("click", async () => {
        state.filter = t.getAttribute("data-filter");
        localStorage.setItem(LS.filter, state.filter);
        applyFilterUI();
        render();
      });
    });

    // light auto refresh
    setInterval(() => {
      if (!apiKey() || !currentMeetingId()) return;
      loadAttendances();
    }, 5000);
  }

  wire();
  refreshAll();
})();