/* Validate page: runs meeting_ready_check and renders actionable reasons. */
(function(){
  const $ = (sel) => document.querySelector(sel);

  function meetingId(){
    try { return (new URLSearchParams(location.search)).get('meeting_id') || ''; } catch(e){ return ''; }
  }

  function render(state){
    const box = $('#readyCheckBox');
    const list = $('#readyCheckList');
    const meta = $('#readyCheckMeta');
    if (!box || !list) return;

    list.innerHTML = '';
    const reasons = (state && state.reasons) ? state.reasons : [];
    const bad = (state && state.bad) ? state.bad : [];
    const ok = !!(state && state.ok);

    box.classList.remove('ok','warn','bad');
    box.classList.add(ok ? 'ok' : (bad && bad.length ? 'bad' : 'warn'));

    if (meta) {
      const m = state && state.meta ? state.meta : {};
      const parts = [];
      if (m.eligible_count != null) parts.push(`Éligibles: ${m.eligible_count}`);
      if (m.fallback_eligible_used) parts.push('fallback éligibles');
      meta.textContent = parts.join(' · ');
    }

    const addItem = (txt, kind) => {
      const li = document.createElement('li');
      li.textContent = txt;
      li.className = kind || '';
      list.appendChild(li);
    };

    if (ok) {
      addItem('OK — prêt à valider.', 'ok');
      return;
    }

    reasons.forEach(r => addItem(r, 'warn'));
    bad.forEach(b => {
      const t = (b && (b.detail || b.title)) ? (b.detail || b.title) : JSON.stringify(b);
      addItem(t, 'bad');
    });

    if (!reasons.length && !bad.length) addItem('Non prêt à valider (détails indisponibles).', 'warn');
  }

  async function fetchReady(){
    const mid = meetingId();
    if (!mid) return render({ ok:false, reasons:['meeting_id manquant'] });
    const r = await fetch(`/api/v1/meeting_ready_check.php?meeting_id=${encodeURIComponent(mid)}`, { credentials:'same-origin' });
    const txt = await r.text();
    let data=null;
    try{ data = JSON.parse(txt); }catch(e){ data = { ok:false, reasons:[txt] }; }
    render(data);
  }

  function bind(){
    $('#btnReadyCheck')?.addEventListener('click', () => fetchReady().catch(()=>{}));
    // auto-run
    fetchReady().catch(()=>{});
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
