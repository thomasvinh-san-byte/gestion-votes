/* public/assets/js/guardrails-nav.js
   UX guardrails (TSX-inspired):
   - badges OK/KO on top nav links (Présences/Procurations/Tokens/Dashboard)
   - top chips summarizing gates (Quorum, Présences, Procurations, Tokens, Motion, Validation)
   - disables links when gate is not ready, with inline reason.
   Safe: does not depend on operator-flow.js internals; best-effort fetches workflow state.
*/
(function(){
  const $ = (s) => document.querySelector(s);

  function apiKey(){ return ($('#opApiKey')?.value || '').trim(); }
  function meetingId(){ return ($('#meetingSelect')?.value || '').trim(); }

  function badgeClass(kind){
    // relies on app.css classes used elsewhere: badge / ok / danger / warn
    if (kind === 'ok') return 'badge ok';
    if (kind === 'danger') return 'badge danger';
    if (kind === 'warn') return 'badge warn';
    return 'badge';
  }

  function setBadge(el, text, kind){
    if (!el) return;
    el.className = badgeClass(kind || 'neutral');
    el.textContent = text;
  }

  function normalizeGate(v){
    // Accept boolean, string, or object with ready/ok/status/detail/reason
    if (typeof v === 'boolean') return { ready: v, label: v ? 'OK' : 'KO' };
    if (typeof v === 'string') {
      const up = v.toUpperCase();
      if (['OK','READY','DONE','TRUE','YES'].includes(up)) return { ready:true, label:'OK' };
      if (['KO','BLOCKED','NO','FALSE'].includes(up)) return { ready:false, label:'KO', reason:v };
      return { ready: up.includes('OK') || up.includes('READY'), label: v };
    }
    if (v && typeof v === 'object'){
      const ready = ('ready' in v) ? !!v.ready : (('ok' in v) ? !!v.ok : false);
      const label = v.label || v.status || (ready ? 'OK' : 'KO');
      const reason = v.reason || v.detail || v.message || '';
      return { ready, label, reason, raw: v };
    }
    return { ready:false, label:'—' };
  }

  async function fetchState(){
    const mid = meetingId();
    if (!mid) return null;
    const headers = { 'Accept':'application/json' };
    const k = apiKey();
    if (k) headers['X-Api-Key'] = k;
    const url = `/api/v1/operator_workflow_state.php?meeting_id=${encodeURIComponent(mid)}`;
    const res = await fetch(url, { headers, credentials:'same-origin' });
    if (!res.ok) return null;
    return res.json();
  }

  function getGate(state, keys){
    for (const k of keys){
      if (state && k in state) return normalizeGate(state[k]);
    }
    return null;
  }

  function applyNavGuard(anchorSel, badgeSel, gate, defaultLabel){
    const a = $(anchorSel);
    const b = $(badgeSel);
    if (!a || !gate) {
      if (b) setBadge(b, defaultLabel || '—', 'neutral');
      return;
    }
    const ok = !!gate.ready;
    setBadge(b, ok ? 'OK' : 'KO', ok ? 'ok' : 'danger');

    // disable if not ok
    a.setAttribute('aria-disabled', ok ? 'false' : 'true');
    a.dataset.disabled = ok ? '0' : '1';
    if (!ok){
      a.classList.add('is-disabled');
      const r = gate.reason ? String(gate.reason) : 'Pré-requis non rempli';
      a.title = `Bloqué : ${r}`;
    } else {
      a.classList.remove('is-disabled');
      a.title = '';
    }
  }

  function applyChips(state){
    const wrap = $('#topChips');
    if (!wrap) return;
    const gates = [
      { key:['quorum','qc','quorum_check'], label:'Quorum' },
      { key:['attendance','presence','att'], label:'Présences' },
      { key:['proxies','proxy','px'], label:'Procurations' },
      { key:['tokens','invitations','tk'], label:'Tokens' },
      { key:['motion','current_motion','mo'], label:'Motion' },
      { key:['validate','validation','va'], label:'Validation' }
    ];
    wrap.innerHTML = '';
    for (const g of gates){
      const gate = getGate(state, g.key);
      if (!gate) continue;
      const s = document.createElement('span');
      s.className = badgeClass(gate.ready ? 'ok' : 'danger');
      s.textContent = `${g.label}:${gate.ready ? 'OK' : 'KO'}`;
      if (!gate.ready && gate.reason) s.title = String(gate.reason);
      wrap.appendChild(s);
    }
  }

  function installClickBlockers(){
    // prevent navigation for disabled links
    document.addEventListener('click', (e) => {
      const a = e.target.closest && e.target.closest('a.btn');
      if (!a) return;
      if (a.dataset && a.dataset.disabled === '1'){
        e.preventDefault();
        e.stopPropagation();
        // show inline message near the link (under the card controls)
        try{
          let msg = a.nextElementSibling;
          if (!msg || !msg.classList.contains('guard-reason')){
            msg = document.createElement('div');
            msg.className = 'muted tiny guard-reason';
            msg.style.marginTop = '6px';
            msg.style.maxWidth = '420px';
            a.parentNode.appendChild(msg);
          }
          msg.textContent = a.title || 'Bloqué : pré-requis non rempli';
          msg.style.display = 'block';
          setTimeout(()=>{ msg.style.display='none'; }, 3500);
        }catch(_){}
      }
    }, true);

    // minimal CSS helper (once)
    if (!document.getElementById('guardrailsNavStyle')){
      const st = document.createElement('style');
      st.id = 'guardrailsNavStyle';
      st.textContent = `
        a.btn.is-disabled { opacity: .55; cursor: not-allowed; pointer-events: auto; }
      `;
      document.head.appendChild(st);
    }
  }

  async function tick(){
    const st = await fetchState();
    if (!st) return;

    const gAttendance = getGate(st, ['attendance','presence','att']);
    const gProxies = getGate(st, ['proxies','proxy','px']);
    const gTokens = getGate(st, ['tokens','invitations','tk']);
    // dashboard always accessible, but show readiness summary if present
    const gDashboard = getGate(st, ['validate','validation','va']) || { ready:true };

    applyNavGuard('a[href="/attendance.htmx.html"]', '#navAttendanceBadge', gAttendance, '—');
    applyNavGuard('a[href="/proxies.htmx.html"]', '#navProxiesBadge', gProxies, '—');
    applyNavGuard('a[href="/invitations.htmx.html"]', '#navTokensBadge', gTokens, '—');
    // dashboard never blocked; just status badge
    setBadge($('#navDashboardBadge'), gDashboard && gDashboard.ready ? 'OK' : 'KO', gDashboard && gDashboard.ready ? 'ok' : 'danger');

    applyChips(st);
  }

  function start(){
    installClickBlockers();
    tick();
    setInterval(tick, 5000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
