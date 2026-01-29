/* public/assets/js/log-panel.js
   Log panel (TSX-style) with quick filter chips:
   - Tout, Parole (speech_*), Appareils (device_*), Validation, Erreurs, Autres
   - Uses local cache; filtering doesn't refetch.
   - Expects backend endpoint: /api/v1/operator_audit_events.php
   - Works even if the host page doesn't define dedicated chip containers.
*/
(() => {
  const $ = (sel, root=document) => root.querySelector(sel);

  function getApiKey() {
    const el = $('#opApiKey');
    return el ? (el.value || '').trim() : '';
  }
  function getMeetingId() {
    const el = $('#meetingSelect') || $('#meetingId');
    return el ? (el.value || '').trim() : '';
  }
  function toast(kind, msg) {
    if (window.UI && typeof window.UI.toast === 'function') return window.UI.toast(kind, msg);
    const box = $('#notif_box');
    if (box) {
      box.classList.remove('hidden');
      box.textContent = msg;
      setTimeout(() => box.classList.add('hidden'), 2500);
      return;
    }
    console.log(`[${kind}]`, msg);
  }

  async function fetchJson(url) {
    const headers = { 'Accept': 'application/json' };
    const k = getApiKey();
    if (k) headers['X-Api-Key'] = k;
    const res = await fetch(url, { headers, credentials: 'same-origin' });
    if (!res.ok) {
      const t = await res.text().catch(() => '');
      const e = new Error(`HTTP ${res.status} ${url}`);
      e.status = res.status;
      e.body = t;
      throw e;
    }
    return res.json();
  }

  // categorization
  const isSpeech = (a) => /^speech_/.test(a || '');
  const isDevice = (a) => /^device_/.test(a || '');
  function isValidation(a) {
    const s = (a || '').toLowerCase();
    return s.includes('validate') || s.includes('validated') || s.includes('consolidat') ||
           s.includes('freeze') || s.includes('final') || s.includes('close_meeting') ||
           s.includes('meeting_closed') || s.includes('pv') || s.includes('export');
  }
  function isErrorish(ev) {
    const lvl = (ev.level || ev.severity || '').toString().toLowerCase();
    if (['error','critical','fatal'].includes(lvl)) return true;
    const a = (ev.action || '').toLowerCase();
    if (a.includes('error') || a.includes('failed') || a.includes('exception')) return true;
    const msg = (ev.message || '').toLowerCase();
    if (msg.includes('error') || msg.includes('échec') || msg.includes('failed') || msg.includes('exception')) return true;
    const p = ev.payload;
    if (p && typeof p === 'object') {
      const j = JSON.stringify(p).toLowerCase();
      if (j.includes('error') || j.includes('failed') || j.includes('exception')) return true;
    }
    return false;
  }
  function categoryOf(ev) {
    const a = ev.action || '';
    if (isSpeech(a)) return 'speech';
    if (isDevice(a)) return 'device';
    if (isValidation(a)) return 'validation';
    if (isErrorish(ev)) return 'error';
    return 'other';
  }

  function labelForAction(action, ev) {
    const a = (action || '').toLowerCase();

    // Speech
    if (a === 'speech_requested') return { title: 'Main levée', tone: 'info' };
    if (a === 'speech_cancelled') return { title: 'Main baissée', tone: 'muted' };
    if (a === 'speech_granted') return { title: 'Parole donnée', tone: 'ok' };
    if (a === 'speech_granted_next') return { title: 'Parole donnée (suivant)', tone: 'ok' };
    if (a === 'speech_ended') return { title: 'Parole clôturée', tone: 'warn' };
    if (a === 'speech_cleared') return { title: 'File parole purgée', tone: 'muted' };

    // Device
    if (a === 'device_blocked') return { title: 'Appareil bloqué', tone: 'bad' };
    if (a === 'device_unblocked') return { title: 'Appareil débloqué', tone: 'ok' };
    if (a === 'device_kicked') return { title: 'Appareil kick', tone: 'warn' };

    // Validation / consolidation
    if (a.includes('consolid')) return { title: 'Consolidation', tone: 'info' };
    if (a.includes('validate')) return { title: 'Validation', tone: 'ok' };
    if (a.includes('freeze')) return { title: 'Gel / Figeage', tone: 'ok' };

    // Fallback
    return { title: action || 'Événement', tone: (ev && isErrorish(ev)) ? 'bad' : 'neutral' };
  }

  function fmtTime(ts) {
    if (!ts) return '—';
    try {
      const d = new Date(ts);
      if (Number.isNaN(d.getTime())) return String(ts);
      return d.toLocaleString();
    } catch { return String(ts); }
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
  }

  function badge(text, tone='neutral') {
    const cls = tone === 'ok' ? 'bg-emerald-50 text-emerald-900 border-emerald-200'
      : tone === 'bad' ? 'bg-rose-50 text-rose-900 border-rose-200'
      : tone === 'warn' ? 'bg-amber-50 text-amber-900 border-amber-200'
      : tone === 'info' ? 'bg-blue-50 text-blue-900 border-blue-200'
      : tone === 'muted' ? 'bg-slate-50 text-slate-700 border-slate-200'
      : 'bg-slate-100 text-slate-800 border-slate-200';
    return `<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${cls}">${escapeHtml(text)}</span>`;
  }

  function renderPayloadDetails(ev) {
    const p = ev.payload;
    if (!p || typeof p !== 'object') return '';
    const lines = [];
    const known = [
      ['member_name','Membre'], ['member_id','MemberID'], ['request_id','Request'],
      ['device_id','Device'], ['role','Rôle'], ['ip','IP'], ['battery','Batterie'],
      ['user_agent','UA'], ['reason','Motif'], ['message','Message'],
      ['deleted','Supprimés'], ['meeting_id','Séance']
    ];
    for (const [k, label] of known) {
      if (p[k] !== undefined && p[k] !== null && String(p[k]).length) {
        lines.push(`<div class="text-xs text-slate-700"><span class="text-slate-500">${label}:</span> ${escapeHtml(p[k])}</div>`);
      }
    }
    if (lines.length === 0) {
      const j = JSON.stringify(p);
      if (j && j !== '{}' && j !== '[]') {
        lines.push(`<div class="text-xs text-slate-700"><span class="text-slate-500">Payload:</span> <span class="font-mono">${escapeHtml(j)}</span></div>`);
      }
    }
    return lines.length ? `<div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-3">${lines.join('')}</div>` : '';
  }

  function ensureChipsHost() {
    let host = $('#logChips');
    if (host) return host;

    const logList = $('#logList') || $('#auditList') || $('#eventsList');
    if (!logList) return null;

    host = document.createElement('div');
    host.id = 'logChips';
    host.className = 'mb-3 flex flex-wrap gap-2';
    logList.parentElement.insertBefore(host, logList);
    return host;
  }

  function ensureListHost() {
    return $('#logList') || $('#auditList') || $('#eventsList');
  }

  function chipBtn(id, label, count, active) {
    const cls = active
      ? 'bg-slate-900 text-white border-slate-900'
      : 'bg-white text-slate-900 border-slate-200 hover:bg-slate-50';
    return `<button data-chip="${id}"
      class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold ${cls}">
      ${escapeHtml(label)}
      <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">${count}</span>
    </button>`;
  }

  function renderEvent(ev) {
    const { title, tone } = labelForAction(ev.action, ev);
    const time = fmtTime(ev.created_at || ev.timestamp || ev.time);
    const actor = ev.actor || ev.user || ev.by || '';
    const msg = ev.message || '';
    const action = ev.action || '';

    const topRight = [
      badge(title, tone),
      action ? badge(action, 'muted') : '',
      isErrorish(ev) ? badge('Erreur', 'bad') : ''
    ].filter(Boolean).join(' ');

    const meta = [
      actor ? `<span class="text-slate-600">par</span> <span class="font-medium text-slate-900">${escapeHtml(actor)}</span>` : '',
      `<span class="text-slate-500">${escapeHtml(time)}</span>`
    ].filter(Boolean).join(' · ');

    return `
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-sm font-semibold text-slate-900">${escapeHtml(ev.title || title)}</div>
            <div class="mt-1 text-xs text-slate-500">${meta}</div>
          </div>
          <div class="flex flex-wrap justify-end gap-1">${topRight}</div>
        </div>
        ${msg ? `<div class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">${escapeHtml(msg)}</div>` : ''}
        ${renderPayloadDetails(ev)}
      </article>
    `;
  }

  // state
  let cache = [];
  let activeChip = 'all';
  let pollTimer = null;

  function countsByCategory(events) {
    const c = { all: events.length, speech:0, device:0, validation:0, error:0, other:0 };
    for (const ev of events) c[categoryOf(ev)]++;
    return c;
  }

  function filterByChip(events, chip) {
    if (chip === 'all') return events;
    if (chip === 'speech') return events.filter(e => categoryOf(e) === 'speech');
    if (chip === 'device') return events.filter(e => categoryOf(e) === 'device');
    if (chip === 'validation') return events.filter(e => categoryOf(e) === 'validation');
    if (chip === 'error') return events.filter(e => isErrorish(e));
    if (chip === 'other') return events.filter(e => categoryOf(e) === 'other');
    return events;
  }

  function render() {
    const list = ensureListHost();
    if (!list) return;

    const host = ensureChipsHost();
    const counts = countsByCategory(cache);

    if (host) {
      host.innerHTML =
        chipBtn('all','Tout',counts.all, activeChip==='all') +
        chipBtn('speech','Parole',counts.speech, activeChip==='speech') +
        chipBtn('device','Appareils',counts.device, activeChip==='device') +
        chipBtn('validation','Validation',counts.validation, activeChip==='validation') +
        chipBtn('error','Erreurs',counts.error, activeChip==='error') +
        chipBtn('other','Autres',counts.other, activeChip==='other');

      host.querySelectorAll('button[data-chip]').forEach(btn => {
        btn.onclick = () => {
          activeChip = btn.getAttribute('data-chip') || 'all';
          render();
        };
      });
    }

    const shown = filterByChip(cache, activeChip);
    list.innerHTML = shown.map(renderEvent).join('') || `
      <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
        Aucun événement à afficher.
      </div>`;
  }

  async function refresh() {
    const mid = getMeetingId();
    if (!mid) return;
    try {
      const url = `/api/v1/operator_audit_events.php?meeting_id=${encodeURIComponent(mid)}&limit=200`;
      const data = await fetchJson(url);
      const events = data.events || data.items || data || [];
      cache = events.map(ev => {
        if (ev && typeof ev.payload === 'string') {
          try { ev.payload = JSON.parse(ev.payload); } catch {}
        }
        return ev;
      });
      render();
    } catch (e) {
      toast('error', 'Impossible de charger le Log.');
      console.warn(e);
    }
  }

  function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(refresh, 8000);
  }

  function bindMeetingChange() {
    const ms = $('#meetingSelect') || $('#meetingId');
    if (!ms) return;
    ms.addEventListener('change', () => {
      cache = [];
      activeChip = 'all';
      refresh();
    });
  }

  function init() {
    bindMeetingChange();
    refresh();
    startPolling();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
