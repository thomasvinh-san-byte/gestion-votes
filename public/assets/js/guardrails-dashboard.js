/* public/assets/js/guardrails-dashboard.js
   Guardrails UX (TSX-style) for operator dashboard:
   - Shows gate chips in top bar
   - Disables step links when prerequisites are not met
   Depends on /api/v1/operator_workflow_state.php
*/
(() => {
  const $ = (sel) => document.querySelector(sel);

  function apiKey() {
    const el = $('#opApiKey');
    return el ? (el.value || '').trim() : '';
  }
  function meetingId() {
    const el = $('#meetingSelect');
    return el ? (el.value || '').trim() : '';
  }

  function chip(label, ok, hint) {
    const cls = ok
      ? 'inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-900'
      : 'inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-900';
    const title = hint ? ` title="${String(hint).replaceAll('"','&quot;')}"` : '';
    return `<span class="${cls}"${title}>${ok ? '✓' : '⛔'} ${label}</span>`;
  }

  function setDisabledLink(a, disabled, reason) {
    if (!a) return;
    const parent = a.parentElement;
    a.dataset.guardrail = disabled ? '1' : '0';
    if (disabled) {
      a.setAttribute('aria-disabled', 'true');
      a.style.pointerEvents = 'none';
      a.style.opacity = '0.55';
      a.style.filter = 'grayscale(0.2)';
    } else {
      a.removeAttribute('aria-disabled');
      a.style.pointerEvents = '';
      a.style.opacity = '';
      a.style.filter = '';
    }

    // inline reason block under link (create once)
    let r = parent ? parent.querySelector('.guardrail-reason') : null;
    if (!r && parent) {
      r = document.createElement('div');
      r.className = 'guardrail-reason muted tiny';
      r.style.marginTop = '6px';
      parent.appendChild(r);
    }
    if (r) {
      if (disabled && reason) {
        r.textContent = `Bloqué : ${reason}`;
        r.style.display = 'block';
      } else {
        r.textContent = '';
        r.style.display = 'none';
      }
    }
  }

  async function fetchWorkflow() {
    const mid = meetingId();
    if (!mid) return null;

    const headers = { 'Accept': 'application/json' };
    const k = apiKey();
    if (k) headers['X-Api-Key'] = k;

    const res = await fetch(`/api/v1/operator_workflow_state.php?meeting_id=${encodeURIComponent(mid)}`, {
      headers,
      credentials: 'same-origin',
    });
    if (!res.ok) return null;
    const j = await res.json();
    return j && j.ok ? j.data : j;
  }

  function renderChips(state) {
    const box = $('#topChips');
    if (!box) return;
    if (!state) { box.innerHTML = ''; return; }

    const attOk = !!state.attendance?.ok;
    const proxyOk = !!state.proxies?.ok;
    const tokenOk = !!state.tokens?.ok;
    const quorumOk = !!state.attendance?.quorum_ok;
    const validationOk = !!state.validation?.ready;

    const chips = [
      chip('Quorum', quorumOk, state.attendance?.detail || ''),
      chip('Présences', attOk, state.attendance?.detail || ''),
      chip('Procurations', proxyOk, state.proxies?.detail || ''),
      chip('Tokens', tokenOk, state.tokens?.detail || ''),
      chip('Validation', validationOk, (state.validation?.reasons || []).join(' · ')),
    ];
    box.innerHTML = chips.join(' ');
  }

  function applyGuardrails(state) {
    if (!state) return;

    const attOk = !!state.attendance?.ok;
    const proxyOk = !!state.proxies?.ok;
    const tokenOk = !!state.tokens?.ok;

    // Step links exist with data-meeting-link; we discriminate by href
    const links = Array.from(document.querySelectorAll('a[data-meeting-link]'));
    const aAttendance = links.find(a => (a.getAttribute('href') || '').includes('attendance'));
    const aProxies = links.find(a => (a.getAttribute('href') || '').includes('proxies'));
    const aTokens = links.find(a => (a.getAttribute('href') || '').includes('invitations'));

    setDisabledLink(aAttendance, false, '');
    setDisabledLink(aProxies, !attOk, 'Compléter les présences d’abord');
    setDisabledLink(aTokens, !proxyOk, 'Finaliser les procurations d’abord');

    // Optional: block tokens also if attendance not ok (stricter linear flow)
    if (!attOk) setDisabledLink(aTokens, true, 'Compléter les présences d’abord');

    // If you have a dashboard link card, keep it enabled
  }

  let timer = null;

  async function tick() {
    try {
      const state = await fetchWorkflow();
      renderChips(state);
      applyGuardrails(state);
    } catch {
      // ignore
    }
  }

  function start() {
    if (timer) clearInterval(timer);
    timer = setInterval(tick, 5000);
    tick();
  }

  // Start after DOM ready; also re-tick when meeting changes
  function bind() {
    start();
    $('#meetingSelect')?.addEventListener('change', () => tick());
    $('#btnRefresh')?.addEventListener('click', () => setTimeout(tick, 250));
    $('#opApiKey')?.addEventListener('change', () => tick());
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
