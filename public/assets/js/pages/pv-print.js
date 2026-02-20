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

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML;
  }

  function pill(text) {
    return `<span class="pill">${esc(text || '—')}</span>`;
  }

  function td(c, right) {
    return `<td style="padding:8px; border-bottom:1px solid var(--border-color);${right ? ' text-align:right;' : ''}">${c}</td>`;
  }

  async function load() {
    const mid = q('meeting_id');
    const errors = [];

    let meeting = null;
    try {
      const raw = await fetchJson(`/api/v1/operator_workflow_state.php?meeting_id=${encodeURIComponent(mid)}`);
      meeting = raw?.data || raw;
    } catch (_) { errors.push('séance'); }

    let att = null;
    try {
      const raw = await fetchJson(`/api/v1/attendances.php?meeting_id=${encodeURIComponent(mid)}`);
      att = raw?.data || raw;
    } catch (_) { errors.push('présences'); }
    const members = att?.attendances || att?.members || [];

    let mot = null;
    try {
      const raw = await fetchJson(`/api/v1/motions_for_meeting.php?meeting_id=${encodeURIComponent(mid)}`);
      mot = raw?.data || raw;
    } catch (_) { errors.push('résolutions'); }

    if (errors.length) {
      const el = document.getElementById('pvMeta');
      if (el) el.textContent += ' — Chargement partiel (données manquantes : ' + errors.join(', ') + ')';
    }
    const motions = (mot?.motions || []).map(m => ({
      ...m,
      id: m.motion_id || m.id,
      title: m.motion_title || m.title || '',
      description: m.motion_description || m.description || '',
    }));

    const title = meeting?.meeting?.title || 'Séance';
    const status = meeting?.meeting?.status || '—';

    $('#pvMeta').textContent = `${title} · Séance ${mid}`;
    $('#pvStatus').innerHTML = pill(status);

    const present = members.filter(m => {
      const mode = String(m.mode || '').toLowerCase();
      return mode === 'present' || mode === 'remote';
    }).length;
    $('#pvPresent').textContent = String(present);
    $('#pvQuorum').textContent = (att?.summary?.quorum_pct !== undefined) ? `${Math.round(parseFloat(att.summary.quorum_pct))}%` : '—';

    const tbody = $('#pvAttendanceBody');
    tbody.innerHTML = members.map(m => {
      const name = esc(m.full_name || m.name || '');
      const st = (m.mode || 'absent').toUpperCase();
      const w = parseFloat(m.voting_power) || 1;
      const wFmt = Number.isInteger(w) ? String(w) : w.toFixed(2).replace(/\.?0+$/, '');
      return `<tr>${td(name)}${td('')}${td(pill(st))}${td(`<span style="font-variant-numeric:tabular-nums;">${wFmt}</span>`, true)}</tr>`;
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
            <div class="k" style="font-size:14px;">${esc(t)}</div>
            <div class="text-muted text-xs" style="white-space:pre-wrap; margin-top:4px;">${esc(d)}</div>
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
