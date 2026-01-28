/* president-gates.js — UX Guardrails v2 (TSX-style)
   Strengthens disabled visuals + inline blockers list.
   Works even if the HTML varies: it searches common ids/classes and injects
   a "blocked reasons" card near the validate action.
*/
(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }

  function findValidateButton() {
    return $('btnValidateFinal') || $('btnValidate') || document.querySelector('[data-action="validate"]');
  }

  function findNameInput() {
    return $('presidentName') || $('inputPresidentName') || document.querySelector('input[name="president_name"]');
  }

  function ensureBlockersBox(anchor) {
    const existing = document.getElementById('presidentBlockersBox');
    if (existing) return existing;
    const box = document.createElement('div');
    box.id = 'presidentBlockersBox';
    box.className = 'card';
    box.style.marginTop = '10px';
    box.innerHTML = `
      <div class="k" style="font-size:14px;">Bloquants</div>
      <div class="muted tiny">Conditions non remplies pour la validation.</div>
      <div class="hr"></div>
      <ul id="presidentBlockersList" style="margin:0; padding-left:18px; display:grid; gap:6px;"></ul>
    `;
    // Insert after the anchor's closest card or after the button row
    let target = anchor && anchor.closest ? anchor.closest('.card') : null;
    if (target && target.parentElement) {
      target.parentElement.insertBefore(box, target.nextSibling);
    } else if (anchor && anchor.parentElement) {
      anchor.parentElement.appendChild(box);
    } else {
      document.body.appendChild(box);
    }
    return box;
  }

  function setDisabled(btn, disabled, reason) {
    if (!btn) return;
    btn.disabled = !!disabled;
    btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    if (disabled) {
      btn.classList.add('is-disabled');
      btn.style.opacity = '0.55';
      btn.style.cursor = 'not-allowed';
      btn.title = reason || 'Bloqué';
    } else {
      btn.classList.remove('is-disabled');
      btn.style.opacity = '';
      btn.style.cursor = '';
      btn.title = '';
    }
  }

  // Best-effort: the page usually fetches /api/v1/meeting_ready_check.php?meeting_id=...
  // We hook into a global variable if present, else we poll the endpoint if meeting_id exists in URL.
  function getMeetingId() {
    const u = new URL(window.location.href);
    return u.searchParams.get('meeting_id') || '';
  }

  async function fetchJson(url) {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
  }

  function renderBlockers(blockers) {
    const btn = findValidateButton();
    const box = ensureBlockersBox(btn);
    const ul = document.getElementById('presidentBlockersList');
    if (!ul) return;
    ul.innerHTML = '';
    (blockers || []).forEach((b) => {
      const li = document.createElement('li');
      li.textContent = String(b);
      ul.appendChild(li);
    });
    box.style.display = (blockers && blockers.length) ? 'block' : 'none';
  }

  async function tick() {
    const btn = findValidateButton();
    if (!btn) return;

    const nameEl = findNameInput();
    const nameOk = !!(nameEl && String(nameEl.value || '').trim());

    // Gather state from globals or fetch
    let rc = window.__readyCheck || null;
    if (!rc) {
      const mid = getMeetingId();
      if (mid) {
        try {
          rc = await fetchJson(`/api/v1/meeting_ready_check.php?meeting_id=${encodeURIComponent(mid)}`);
        } catch (_) {}
      }
    }

    const ready = !!(rc && (rc.ready ?? rc.ok ?? rc.is_ready));
    const blockers = (rc && (rc.blockers || rc.reasons || rc.errors)) || [];
    const disabled = !(ready && nameOk);

    let reason = '';
    if (!nameOk) reason = 'nom du président requis';
    else if (!ready) reason = (blockers && blockers[0]) ? String(blockers[0]) : 'conditions non remplies';

    setDisabled(btn, disabled, reason);
    renderBlockers((!ready ? blockers : []));

    // Optional inline hint under button
    const hintId = 'presidentValidateHint';
    let hint = document.getElementById(hintId);
    if (!hint && btn.parentElement) {
      hint = document.createElement('div');
      hint.id = hintId;
      hint.className = 'muted tiny';
      hint.style.marginTop = '6px';
      btn.parentElement.appendChild(hint);
    }
    if (hint) {
      hint.textContent = disabled ? ('Bloqué : ' + reason) : '';
      hint.style.display = disabled ? 'block' : 'none';
    }
  }

  function bind() {
    const nameEl = findNameInput();
    if (nameEl) nameEl.addEventListener('input', () => { tick().catch(()=>{}); });

    // periodic update
    setInterval(() => { tick().catch(()=>{}); }, 1500);
    tick().catch(()=>{});
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();


async function refreshGates() {
  try{
    const mid = (new URLSearchParams(location.search)).get('meeting_id') || '';
    const btnClose = document.getElementById('btnCloseVote');
    const btnValidate = document.getElementById('btnValidateMeeting');
    const badge = document.getElementById('presidentStatusBadge');

    if (!mid) return;

    // 1) current motion
    let hasCurrent = false;
    try{
      const r = await fetch(`/api/v1/current_motion.php?meeting_id=${encodeURIComponent(mid)}`, { credentials:'same-origin' });
      const data = await r.json();
      hasCurrent = !!(data && data.motion && data.motion.id && !data.motion.closed_at);
    }catch(e){}

    if (btnClose) btnClose.disabled = !hasCurrent;

    // 2) strict ready check
    let readyOk = false;
    let detail = '';
    try{
      const r = await fetch(`/api/v1/meeting_ready_check.php?meeting_id=${encodeURIComponent(mid)}`, { credentials:'same-origin' });
      const data = await r.json();
      readyOk = !!(data && data.ok);
      if (!readyOk) {
        const reasons = (data && data.reasons) ? data.reasons : [];
        const bad = (data && data.bad) ? data.bad : [];
        detail = (bad[0] && (bad[0].detail || bad[0].title)) ? (bad[0].detail || bad[0].title) : (reasons[0] || '');
      }
    }catch(e){}

    if (btnValidate) btnValidate.disabled = !readyOk;

    if (badge){
      badge.textContent = readyOk ? "Prêt à valider" : (detail ? ("Non prêt : " + detail) : "Non prêt");
      badge.className = readyOk ? "badge is-ok" : "badge is-warn";
    }
  }catch(e){}
}

setInterval(refreshGates, 1500);
document.addEventListener('DOMContentLoaded', refreshGates);
