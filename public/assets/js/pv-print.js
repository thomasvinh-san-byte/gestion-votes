/* public/assets/js/pv-print.js — PV (print-friendly)
   Best-effort : utilise des endpoints existants si présents.
*/
(function () {
  const $ = (sel) => document.querySelector(sel);

  function q(name) {
    const u = new URL(window.location.href);
    return u.searchParams.get(name) || '';
  }

  function apiKey() {
    try { return (sessionStorage.getItem('pv.apiKey') || '').trim(); } catch (_) { return ''; }
  }

  async function fetchJson(url) {
    const headers = { 'Accept': 'application/json' };
    const k = apiKey();
    if (k) headers['X-Api-Key'] = k;
    const res = await fetch(url, { headers, credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status} ${url}`);
    return res.json();
  }

  function pill(text) {
    return `<span class="pill">${String(text || '—')}</span>`;
  }

  function td(c, right) {
    return `<td style="padding:8px; border-bottom:1px solid var(--border-color);${right ? ' text-align:right;' : ''}">${c}</td>`;
  }

  async function load() {
    const mid = q('meeting_id');

    let meeting = null;
    const meetingCandidates = [
      `/api/v1/meeting.php?id=${encodeURIComponent(mid)}`,
      `/api/v1/meeting_state.php?meeting_id=${encodeURIComponent(mid)}`,
      `/api/v1/operator_workflow_state.php?meeting_id=${encodeURIComponent(mid)}`
    ];
    for (const u of meetingCandidates) {
      try { meeting = await fetchJson(u); break; } catch (_) {}
    }

    let att = null;
    const attCandidates = [
      `/api/v1/attendances.php?meeting_id=${encodeURIComponent(mid)}`,
      `/api/v1/members.php?meeting_id=${encodeURIComponent(mid)}`
    ];
    for (const u of attCandidates) {
      try { att = await fetchJson(u); break; } catch (_) {}
    }
    const members = att?.members || att?.items || att || [];

    let mot = null;
    const motCandidates = [
      `/api/v1/motions_list.php?meeting_id=${encodeURIComponent(mid)}`,
      `/api/v1/motions.php?meeting_id=${encodeURIComponent(mid)}`
    ];
    for (const u of motCandidates) {
      try { mot = await fetchJson(u); break; } catch (_) {}
    }
    const motions = mot?.motions || mot?.items || mot || [];

    const title = meeting?.title || meeting?.meeting?.title || 'Séance';
    const status = meeting?.status || meeting?.meeting?.status || meeting?.meeting_status || '—';

    $('#pvMeta').textContent = `${title} · Séance ${mid}`;
    $('#pvStatus').innerHTML = pill(status);

    const present = members.filter(m => {
      const st = String(m.status || '').toUpperCase();
      return st === 'PRESENT' || st === 'REMOTE' || m.is_present === true;
    }).length;
    $('#pvPresent').textContent = String(present);
    $('#pvQuorum').textContent = (att?.stats?.quorum_pct !== undefined) ? `${att.stats.quorum_pct}%` : '—';

    const tbody = $('#pvAttendanceBody');
    tbody.innerHTML = members.map(m => {
      const ln = m.last_name || m.lastName || '';
      const fn = m.first_name || m.firstName || '';
      const st = m.status || (m.is_present ? 'PRESENT' : 'ABSENT');
      const w = (m.weight ?? m.tantiemes ?? '');
      return `<tr>${td(ln)}${td(fn)}${td(pill(st))}${td(`<span style="font-variant-numeric:tabular-nums;">${w}</span>`, true)}</tr>`;
    }).join('');

    const wrap = $('#pvMotions');
    wrap.innerHTML = motions.map(m => {
      const t = m.title || 'Résolution';
      const d = m.description || '';
      const st = m.status || '—';
      const res = m.result || m.computed_result || null;
      const verdict = (res && typeof res.passed !== 'undefined')
        ? (res.passed ? '<span class="badge ok">ADOPTÉE</span>' : '<span class="badge danger">REJETÉE</span>')
        : '<span class="badge">—</span>';

      return `<div class="card" style="padding:12px;">
        <div class="row" style="align-items:flex-start;">
          <div style="flex:1;">
            <div class="k" style="font-size:14px;">${t}</div>
            <div class="muted tiny" style="white-space:pre-wrap; margin-top:4px;">${d}</div>
          </div>
          <div class="controls" style="flex-direction:column; align-items:flex-end; gap:6px;">
            ${pill(st)}
            ${verdict}
          </div>
        </div>
      </div>`;
    }).join('');
  }

  function bind() {
    const bp = $('#btnPrint');
    if (bp) bp.addEventListener('click', () => window.print());
    const bc = $('#btnClose');
    if (bc) bc.addEventListener('click', () => window.close());
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { bind(); load(); });
  } else {
    bind(); load();
  }
})();
