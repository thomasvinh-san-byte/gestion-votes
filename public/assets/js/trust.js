/* global UI */
(function () {
  function qs(sel) { return document.querySelector(sel); }
  function qsa(sel) { return Array.from(document.querySelectorAll(sel)); }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function apiHeaders() {
    return {
      'X-Api-Key': window.APP_API_KEY,
      'Accept': 'application/json'
    };
  }

  function setBusy(on) {
    qsa('[data-busy]').forEach((el) => el.disabled = !!on);
    const sp = qs('#busy');
    if (sp) sp.style.visibility = on ? 'visible' : 'hidden';
  }

  async function loadMeetings() {
    const sel = qs('#meetingSelect');
    if (!sel) return;

    const r = await UI.fetchJson('/api/v1/meetings_index.php', { headers: apiHeaders() });
    const meetings = (r.data && r.data.meetings) ? r.data.meetings : [];

    sel.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = '— Sélectionner une séance —';
    sel.appendChild(opt0);

    meetings.forEach((m) => {
      const opt = document.createElement('option');
      opt.value = m.meeting_id;
      const when = (m.created_at || '').toString().slice(0, 10);
      opt.textContent = `${when} — ${m.title || 'Séance'} [${m.status || '—'}]`;
      sel.appendChild(opt);
    });

    // Restore last selection
    const saved = localStorage.getItem('trust.meeting_id') || '';
    if (saved && meetings.some(x => x.meeting_id === saved)) {
      sel.value = saved;
    } else if (meetings.length > 0) {
      sel.value = meetings[0].meeting_id;
    }

    await refreshAll();
  }

  function meetingId() {
    return (qs('#meetingSelect')?.value || '').trim();
  }

  async function loadStatus(mid) {
    const box = qs('#statusBox');
    if (!box) return;
    if (!mid) {
      box.innerHTML = '<div class="muted">Sélectionnez une séance.</div>';
      return;
    }
    const r = await UI.fetchJson(`/api/v1/meeting_status_for_meeting.php?meeting_id=${encodeURIComponent(mid)}`, { headers: apiHeaders() })
      .catch(() => null);

    // Backward compat: older meeting_status returns "current meeting" without meeting_id input support.
    const data = r && r.data ? r.data : null;
    if (!data) {
      box.innerHTML = '<div class="muted">Impossible de charger le statut (meeting_status.php ne supporte peut-être pas meeting_id).</div>';
      return;
    }

    const s = data.sign_status || '—';
    const msg = data.sign_message || '';
    const badge = (data.meeting_status === 'live') ? 'live' : (data.meeting_status === 'archived' ? 'closed' : 'unknown');

    box.innerHTML = `
      <div class="row">
        <div>
          <div class="k">${escapeHtml(data.meeting_title || 'Séance')}</div>
          <div class="muted">Statut: <span class="badge ${badge}">${escapeHtml(data.meeting_status || '—')}</span>
            <span class="badge muted">${escapeHtml(s)}</span>
          </div>
          <div class="muted">${escapeHtml(msg)}</div>
        </div>
        <div class="right">
          <button class="btn" id="btnOpenPV" data-busy>Ouvrir PV</button>
          <button class="btn" id="btnAudit" data-busy>Voir audit</button>
          <button class="btn danger" id="btnConsolidate" data-busy>Consolider / recalculer</button>
        </div>
      </div>
    `;

    qs('#btnOpenPV')?.addEventListener('click', () => openMeetingReport(mid));
    qs('#btnAudit')?.addEventListener('click', () => openAudit(mid));
    qs('#btnConsolidate')?.addEventListener('click', () => consolidate(mid));
  }

  async function loadMotions(mid) {
    const tbody = qs('#motionsTbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!mid) return;

    const r = await UI.fetchJson(`/api/v1/trust_overview.php?meeting_id=${encodeURIComponent(mid)}`, { headers: apiHeaders() });
    const motions = (r.data && r.data.motions) ? r.data.motions : [];

    motions.forEach((m) => {
      const t = m.tallies || {};
      const dec = m.decision || {};
      const src = m.official_source || '—';

      const forW = Number(t.for?.weight ?? 0);
      const agW  = Number(t.against?.weight ?? 0);
      const abW  = Number(t.abstain?.weight ?? 0);

      const forC = Number(t.for?.count ?? 0);
      const agC  = Number(t.against?.count ?? 0);
      const abC  = Number(t.abstain?.count ?? 0);

      const ds = (dec.status || '—').toString();
      const badgeClass = ds === 'adopted' ? 'success' : (ds === 'rejected' ? 'danger' : 'muted');

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <div><strong>${escapeHtml(m.title || 'Motion')}</strong></div>
          <div class="muted">${escapeHtml(m.description || '')}</div>
          <div class="muted tiny">Ouverture: ${escapeHtml(m.opened_at || '—')} · Clôture: ${escapeHtml(m.closed_at || '—')}</div>
        </td>
        <td><span class="badge muted">${escapeHtml(src)}</span></td>
        <td class="num">${forW}</td>
        <td class="num">${forC}</td>
        <td class="num">${agW}</td>
        <td class="num">${agC}</td>
        <td class="num">${abW}</td>
        <td class="num">${abC}</td>
        <td><span class="badge ${badgeClass}">${escapeHtml(ds)}</span></td>
      `;
      tbody.appendChild(tr);
    });
  }

  async function openMeetingReport(mid) {
    // meeting_report.php expects api_key in query (iframe-friendly)
    const url = `/api/v1/meeting_report.php?meeting_id=${encodeURIComponent(mid)}&api_key=${encodeURIComponent(window.APP_API_KEY || '')}`;
    window.open(url, '_blank', 'noopener');
  }

  async function openAudit(mid) {
    const modal = qs('#auditModal');
    const body = qs('#auditBody');
    if (!modal || !body) return;
    modal.showModal?.();

    body.innerHTML = '<div class="muted">Chargement…</div>';
    try {
      const r = await UI.fetchJson(`/api/v1/meeting_audit.php?meeting_id=${encodeURIComponent(mid)}`, { headers: apiHeaders() });
      const items = (r.data && r.data.events) ? r.data.events : (r.data || []);
      if (!Array.isArray(items) || items.length === 0) {
        body.innerHTML = '<div class="muted">Aucun événement d’audit.</div>';
        return;
      }
      const rows = items.map((ev) => {
        const at = escapeHtml(ev.created_at || ev.at || '—');
        const type = escapeHtml(ev.event_type || ev.type || '—');
        const who = escapeHtml(ev.actor_name || ev.actor || ev.user || '—');
        const msg = escapeHtml(ev.message || ev.detail || '');
        return `<tr><td class="tiny">${at}</td><td>${type}</td><td>${who}</td><td>${msg}</td></tr>`;
      }).join('');
      body.innerHTML = `<table class="tbl"><thead><tr><th>Date</th><th>Type</th><th>Acteur</th><th>Détail</th></tr></thead><tbody>${rows}</tbody></table>`;
    } catch (e) {
      body.innerHTML = `<div class="muted">Erreur audit: ${escapeHtml(e?.message || e)}</div>`;
    }
  }

  async function consolidate(mid) {
    if (!confirm('Recalculer et consolider les décisions pour cette séance ?')) return;
    setBusy(true);
    try {
      const r = await UI.fetchJson('/api/v1/meeting_consolidate.php', {
        method: 'POST',
        headers: { ...apiHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ meeting_id: mid })
      });
      UI.toast('Consolidation', `Motions mises à jour: ${r.data.updated_motions ?? 0}`, 'success');
      await refreshAll();
    } catch (e) {
      UI.toast('Erreur', e?.message || String(e), 'danger');
    } finally {
      setBusy(false);
    }
  }

  async function refreshAll() {
    const mid = meetingId();
    if (mid) localStorage.setItem('trust.meeting_id', mid);
    setBusy(true);
    try {
      await loadStatus(mid);
      await loadMotions(mid);
    } finally {
      setBusy(false);
    }
  }

  function wire() {
    const sel = qs('#meetingSelect');
    sel?.addEventListener('change', refreshAll);

    qs('#btnReload')?.addEventListener('click', async () => {
      setBusy(true);
      try { await loadMeetings(); }
      finally { setBusy(false); }
    });

    qs('#btnRefresh')?.addEventListener('click', refreshAll);
    qs('#auditClose')?.addEventListener('click', () => qs('#auditModal')?.close?.());

    // first load
    setBusy(true);
    loadMeetings().catch((e) => UI.toast('Erreur', e?.message || String(e), 'danger')).finally(() => setBusy(false));

    // light auto-refresh
    setInterval(() => {
      if (document.hidden) return;
      const mid = meetingId();
      if (mid) refreshAll().catch(() => {});
    }, 4000);
  }

  document.addEventListener('DOMContentLoaded', wire);
})();