
// public/assets/js/proxy-modal.js
(function(){
  const $ = (id) => document.getElementById(id);

  const modal = $('proxyModal');
  const btnOpen = $('btnProxies');
  const btnClose = $('btnProxyClose');
  const btnRefresh = $('btnProxyRefresh');
  const listEl = $('proxyList');
  const statsEl = $('proxyStats');
  const searchEl = $('proxySearch');
  const filterEl = $('proxyFilter');

  const opApiKeyEl = $('opApiKey');
  const meetingSelectEl = $('meetingSelect');

  let members = [];
  let proxies = []; // {giver_member_id, receiver_member_id, giver_name, receiver_name}
  let receiverCounts = new Map(); // receiverId -> count
  let currentMeetingId = null;

  function apiKey(){
    return (opApiKeyEl && opApiKeyEl.value ? opApiKeyEl.value.trim() : '');
  }

  async function fetchJson(url, options = {}) {
    const headers = Object.assign({}, options.headers || {});
    headers['Accept'] = 'application/json';
    // API key header (match existing convention in app/api.php)
    const k = apiKey();
    if (k) headers['X-Api-Key'] = k;
    const res = await fetch(url, Object.assign({}, options, { headers }));
    const txt = await res.text();
    let data;
    try { data = JSON.parse(txt); } catch { data = { ok:false, raw: txt }; }
    if (!res.ok) {
      const msg = (data && data.error) ? data.error : ('HTTP ' + res.status);
      throw new Error(msg);
    }
    return data.data || data;
  }

  function esc(s){
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function open(){
    if (!modal) return;
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden','false');
    refresh();
  }
  function close(){
    if (!modal) return;
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
  }

  function recomputeCounts(){
    receiverCounts = new Map();
    for (const p of proxies) {
      const rid = p.receiver_member_id;
      receiverCounts.set(rid, (receiverCounts.get(rid) || 0) + 1);
    }
  }

  function currentProxyReceiver(giverId){
    const found = proxies.find(p => p.giver_member_id === giverId);
    return found ? found.receiver_member_id : '';
  }

  function buildReceiverOptions(giverId){
    const current = currentProxyReceiver(giverId);
    let opts = `<option value="" ${current===''?'selected':''}>— Aucun —</option>`;
    for (const m of members) {
      if (m.id === giverId) continue;
      const c = receiverCounts.get(m.id) || 0;
      opts += `<option value="${esc(m.id)}" ${m.id===current?'selected':''}>${esc(m.full_name)}${c?`  ·  reçoit ${c}`:''}</option>`;
    }
    return opts;
  }

  function passesFilters(member){
    const q = (searchEl?.value || '').trim().toLowerCase();
    const f = (filterEl?.value || 'all');
    const name = (member.full_name || '').toLowerCase();
    if (q && !name.includes(q)) return false;

    const receiverId = currentProxyReceiver(member.id);
    const hasProxy = receiverId !== '';
    const isReceiver = (receiverCounts.get(member.id) || 0) > 0;

    if (f === 'hasProxy' && !hasProxy) return false;
    if (f === 'noProxy' && hasProxy) return false;
    if (f === 'isReceiver' && !isReceiver) return false;

    return true;
  }

  async function upsert(giverId, receiverId){
    if (!currentMeetingId) throw new Error('meeting_id manquant');
    await fetchJson('/api/v1/proxies_upsert.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        meeting_id: currentMeetingId,
        giver_member_id: giverId,
        receiver_member_id: receiverId || '',
        scope: 'full'
      })
    });
  }

  function render(){
    if (!listEl) return;
    const rows = [];
    const shown = members.filter(passesFilters);
    const totalProxy = proxies.length;
    statsEl && (statsEl.textContent = `${totalProxy} procuration(s) · ${shown.length}/${members.length} membres`);

    for (const m of shown) {
      const giverId = m.id;
      const receiverId = currentProxyReceiver(giverId);
      const recv = receiverId ? members.find(x => x.id === receiverId) : null;
      const recvCount = receiverId ? (receiverCounts.get(receiverId) || 0) : 0;
      const gives = receiverId ? 1 : 0;
      const receives = receiverCounts.get(giverId) || 0;

      rows.push(`
        <div class="proxy-card">
          <div class="proxy-left">
            <div class="proxy-title">${esc(m.full_name)}</div>
            <div class="proxy-meta">
              <span class="badge gray">Poids: ${esc(m.voting_power ?? '')}</span>
              ${m.role ? `<span class="badge gray">Rôle: ${esc(m.role)}</span>` : ''}
              ${gives ? `<span class="badge">Mandant</span>` : `<span class="badge gray">Sans mandat donné</span>`}
              ${receives ? `<span class="badge">Reçoit: ${receives}</span>` : ''}
            </div>
          </div>
          <div class="proxy-right">
            <select data-giver="${esc(giverId)}" class="proxy-select">
              ${buildReceiverOptions(giverId)}
            </select>
            <button class="btn small" data-action="revoke" data-giver="${esc(giverId)}" ${receiverId===''?'disabled':''}>Révoquer</button>
          </div>
        </div>
      `);
    }
    listEl.innerHTML = rows.join('');
  }

  async function refresh(){
    try {
      currentMeetingId = meetingSelectEl && meetingSelectEl.value ? meetingSelectEl.value : null;
      if (!currentMeetingId) {
        // try meeting_status as fallback
        const ms = await fetchJson('/api/v1/meeting_status.php');
        currentMeetingId = ms.meeting_id || ms.data?.meeting_id || null;
      }
      members = (await fetchJson('/api/v1/members.php')).members || [];
      proxies = (await fetchJson('/api/v1/proxies.php?meeting_id=' + encodeURIComponent(currentMeetingId))).proxies || [];
      recomputeCounts();
      render();
    } catch (e) {
      listEl && (listEl.innerHTML = `<div class="card"><div class="muted tiny">Erreur: ${esc(e.message || e)}</div></div>`);
    }
  }

  function wireEvents(){
    if (!modal) return;

    // close on backdrop click
    modal.addEventListener('click', (ev)=>{
      const target = ev.target;
      if (target && target.classList.contains('agmodal-backdrop')) close();
    });

    listEl?.addEventListener('change', async (ev)=>{
      const sel = ev.target;
      if (!sel || !sel.classList.contains('proxy-select')) return;
      const giverId = sel.getAttribute('data-giver');
      const receiverId = sel.value;
      try{
        await upsert(giverId, receiverId);
        // reload proxies only
        proxies = (await fetchJson('/api/v1/proxies.php?meeting_id=' + encodeURIComponent(currentMeetingId))).proxies || [];
        recomputeCounts();
        render();
      } catch(e){ alert(e.message || e); }
    });

    listEl?.addEventListener('click', async (ev)=>{
      const btn = ev.target;
      if (!btn || !btn.getAttribute) return;
      if (btn.getAttribute('data-action') !== 'revoke') return;
      const giverId = btn.getAttribute('data-giver');
      try{
        await upsert(giverId, '');
        proxies = (await fetchJson('/api/v1/proxies.php?meeting_id=' + encodeURIComponent(currentMeetingId))).proxies || [];
        recomputeCounts();
        render();
      } catch(e){ alert(e.message || e); }
    });

    const onTool = ()=>render();
    searchEl?.addEventListener('input', onTool);
    filterEl?.addEventListener('change', onTool);

    btnRefresh?.addEventListener('click', refresh);
    btnClose?.addEventListener('click', close);
    btnOpen?.addEventListener('click', open);

    // escape to close
    document.addEventListener('keydown', (e)=>{
      if (e.key === 'Escape' && modal.style.display === 'block') close();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireEvents);
  } else {
    wireEvents();
  }
})();
