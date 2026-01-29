/* public/assets/js/guardrails-pages.js
   Gates banner for sub-pages (attendance/proxies/invitations).
   - Reads meeting_id from URL.
   - Polls /api/v1/operator_workflow_state.php to display chips and block navigation when prerequisites are missing.
*/
(() => {
  const $ = (s) => document.querySelector(s);

  function getMeetingId() {
    const u = new URL(window.location.href);
    return u.searchParams.get('meeting_id') || u.searchParams.get('meeting') || '';
  }

  function getApiKey() {
    // Best-effort: shared key from operator pages if present
    const keys = ['opApiKey', 'apiKey', 'AGVOTE_API_KEY', 'API_KEY'];
    for (const k of keys) {
      try {
        const v = (localStorage.getItem(k) || '').trim();
        if (v) return v;
      } catch {}
    }
    return '';
  }

  function setMeetingLinks(meetingId) {
    document.querySelectorAll('[data-meeting-link]').forEach(a => {
      try {
        const u = new URL(a.getAttribute('href'), window.location.origin);
        u.searchParams.set('meeting_id', meetingId);
        a.setAttribute('href', u.pathname + u.search);
      } catch {}
    });
  }

  function chip(text, kind='neutral') {
    const cls = kind === 'ok'
      ? 'bg-emerald-50 text-emerald-900 border-emerald-200'
      : kind === 'ko'
        ? 'bg-rose-50 text-rose-900 border-rose-200'
        : kind === 'warn'
          ? 'bg-amber-50 text-amber-900 border-amber-200'
          : 'bg-slate-50 text-slate-900 border-slate-200';
    return `<span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium ${cls}">${text}</span>`;
  }

  function renderBanner(state) {
    const host = $('#guardrailsBanner');
    if (!host) return;

    const co = state?.checklist || state?.constraints || state || {};
    const quorumOk = !!(co.quorum_ok ?? co.quorumOk ?? co.quorum?.ok);
    const attendanceOk = !!(co.attendance_ok ?? co.attendanceOk ?? co.attendance?.ok);
    const proxiesOk = !!(co.proxies_ok ?? co.proxiesOk ?? co.proxies?.ok);
    const tokensOk = !!(co.tokens_ok ?? co.tokensOk ?? co.tokens?.ok);
    const readyOk = !!(co.ready_for_validation ?? co.ready ?? co.validation?.ready);

    const blockers = [];
    if (!attendanceOk) blockers.push('Présences non finalisées');
    if (attendanceOk && !proxiesOk) blockers.push('Procurations à vérifier');
    if ((attendanceOk && proxiesOk) && !tokensOk) blockers.push('Tokens à générer/distribuer');
    if (!quorumOk) blockers.push('Quorum non atteint');

    const chips = [
      chip(`Quorum ${quorumOk ? 'OK' : 'KO'}`, quorumOk ? 'ok' : 'ko'),
      chip(`Présences ${attendanceOk ? 'OK' : 'KO'}`, attendanceOk ? 'ok' : 'ko'),
      chip(`Procurations ${proxiesOk ? 'OK' : 'KO'}`, proxiesOk ? 'ok' : 'ko'),
      chip(`Tokens ${tokensOk ? 'OK' : 'KO'}`, tokensOk ? 'ok' : 'ko'),
      chip(`Validation ${readyOk ? 'OK' : 'KO'}`, readyOk ? 'ok' : 'ko'),
    ].join(' ');

    host.innerHTML = `
      <div class="container" style="margin-top:10px;">
        <div class="card" style="border:1px solid var(--border-color);">
          <div class="row" style="align-items:flex-start; gap:10px;">
            <div style="flex:1;">
              <div class="k" style="font-size:14px;">Garde-fous</div>
              <div class="muted tiny">État global de la séance (bloquants visibles).</div>
              <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;">
                ${chips}
              </div>
              ${blockers.length ? `<div class="notif warn" style="display:block; margin-top:10px;">
                <strong>Bloquants :</strong> ${blockers.map(b => `<span class="kbd">${b}</span>`).join(' ')}
              </div>` : `<div class="notif ok" style="display:block; margin-top:10px;">
                <strong>OK :</strong> parcours cohérent, vous pouvez continuer.
              </div>`}
            </div>
            <div class="controls" style="gap:6px;">
              <a class="btn primary" data-meeting-link href="/operator_flow.htmx.html">Retour workflow</a>
              <a class="btn" data-meeting-link href="/operator.htmx.html">Opérateur</a>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  async function fetchState(meetingId) {
    const url = `/api/v1/operator_workflow_state.php?meeting_id=${encodeURIComponent(meetingId)}`;
    const headers = { 'Accept': 'application/json' };
    const k = getApiKey();
    if (k) headers['X-Api-Key'] = k;

    const res = await fetch(url, { headers, credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  async function tick() {
    const meetingId = getMeetingId();
    if (!meetingId) return;
    try {
      const state = await fetchState(meetingId);
      renderBanner(state);
    } catch (e) {
      // if endpoint protected, still show minimal banner
      renderBanner({ checklist: {} });
    }
  }

  function init() {
    const meetingId = getMeetingId();
    if (!meetingId) return;
    setMeetingLinks(meetingId);
    tick();
    setInterval(tick, 5000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
