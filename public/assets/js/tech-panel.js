(function(){
  const $ = (s) => document.querySelector(s);

  function apiKey(){
    return ($('#opApiKey')?.value || '').trim();
  }

  function meetingId(){
    return ($('#meetingSelect')?.value || '').trim();
  }

  function headers(){
    const k = apiKey();
    return k ? { 'X-Api-Key': k } : {};
  }

  async function getJSON(url){
    const r = await fetch(url, { headers: headers(), credentials: 'same-origin' });
    const out = await r.json().catch(()=>({}));
    if (!r.ok || out?.ok === false) throw new Error(out?.error || 'request_failed');
    return out?.data || {};
  }

  async function postJSON(url, body){
    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...headers() },
      credentials: 'same-origin',
      body: JSON.stringify(body || {})
    });
    const out = await r.json().catch(()=>({}));
    if (!r.ok || out?.ok === false) throw new Error(out?.error || 'request_failed');
    return out?.data || {};
  }

  function relTime(iso){
    if (!iso) return '—';
    const t = Date.parse(iso);
    if (!isFinite(t)) return '—';
    const s = Math.max(0, Math.floor((Date.now() - t) / 1000));
    if (s < 10) return 'à l’instant';
    if (s < 60) return `il y a ${s}s`;
    const m = Math.floor(s/60);
    if (m < 60) return `il y a ${m}min`;
    const h = Math.floor(m/60);
    return `il y a ${h}h`;
  }

  function statusLabel(st){
    const map = { online: 'En ligne', stale: 'Inactif', blocked: 'Bloqué' };
    return map[st] || '—';
  }

  function badgeClass(st){
    if (st === 'online') return 'badge success';
    if (st === 'blocked') return 'badge danger';
    if (st === 'stale') return 'badge warning';
    return 'badge';
  }

  function renderDevices(devices){
    const list = $('#devicesList');
    if (!list) return;

    const q = ($('#deviceSearch')?.value || '').trim().toLowerCase();
    const fs = ($('#deviceFilterStatus')?.value || 'all');
    const fr = ($('#deviceFilterRole')?.value || 'all');

    let rows = Array.isArray(devices) ? devices.slice() : [];
    if (fs !== 'all') rows = rows.filter(d => (d.status || '') === fs);
    if (fr !== 'all') rows = rows.filter(d => (d.role || '') === fr);
    if (q) {
      rows = rows.filter(d => {
        const hay = `${d.role||''} ${d.ip||''} ${d.device_id||''} ${d.member_id||''} ${d.user_agent||''}`.toLowerCase();
        return hay.includes(q);
      });
    }

    list.innerHTML = '';

    if (!rows.length){
      list.innerHTML = `<div class="muted tiny" style="padding:8px 4px;">Aucun appareil ne correspond aux filtres.</div>`;
      return;
    }

    for (const d of rows){
      const el = document.createElement('div');
      el.className = 'card' ;
      el.style.padding = '12px';
      el.style.marginBottom = '10px';

      const bat = (d.battery_level === null || d.battery_level === undefined) ? '—' : `${d.battery_level}%${d.battery_charging ? ' ⚡' : ''}`;
      const ua = (d.user_agent || '').toString();
      const uaShort = ua.length > 64 ? ua.slice(0, 64) + '…' : ua;
      const blockedReason = d.block_reason ? `<div class="muted tiny" style="margin-top:6px;">Motif: ${escapeHtml(d.block_reason)}</div>` : '';

      el.innerHTML = `
        <div class="row" style="justify-content:space-between;gap:10px;align-items:flex-start;">
          <div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <span class="${badgeClass(d.status)}">${statusLabel(d.status)}</span>
              <span class="badge">${escapeHtml(d.role || 'device')}</span>
              ${d.member_id ? `<span class="badge">Membre: ${escapeHtml(d.member_id)}</span>` : ''}
            </div>
            <div class="k" style="margin-top:6px;font-size:14px;">${escapeHtml(d.device_id || '—')}</div>
            <div class="muted tiny" style="margin-top:2px;">IP: ${escapeHtml(d.ip || '—')} · Vu: ${escapeHtml(relTime(d.last_seen))} · Batterie: ${escapeHtml(bat)}</div>
            <div class="muted tiny" style="margin-top:2px;">${escapeHtml(uaShort || '')}</div>
            ${blockedReason}
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;min-width:150px;">
            <button class="btn ${d.status==='blocked' ? '' : 'danger'}" data-act="${d.status==='blocked' ? 'unblock' : 'block'}" data-device="${escapeHtmlAttr(d.device_id)}">${d.status==='blocked' ? 'Débloquer' : 'Bloquer'}</button>
            <button class="btn" data-act="kick" data-device="${escapeHtmlAttr(d.device_id)}">Kick</button>
          </div>
        </div>
      `;

      list.appendChild(el);
    }
  }

  function escapeHtml(str){
    return String(str ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');
  }
  function escapeHtmlAttr(str){
    return escapeHtml(str).replaceAll('`','');
  }

  async function refresh(){
    const mid = meetingId();
    if (!mid) return;

    const box = $('#devicesCounters');
    const list = $('#devicesList');
    if (list) list.setAttribute('data-loading', '1');

    try {
      const data = await getJSON(`/api/v1/devices_list.php?meeting_id=${encodeURIComponent(mid)}`);
      const devices = data.devices || [];
      if (box) {
        const c = data.counts || {};
        box.innerHTML = `
          <span class="badge success">En ligne: ${c.online ?? 0}</span>
          <span class="badge warning">Inactifs: ${c.stale ?? 0}</span>
          <span class="badge danger">Bloqués: ${c.blocked ?? 0}</span>
          <span class="badge">Total: ${c.total ?? devices.length}</span>
        `;
      }
      renderDevices(devices);
    } catch(e){
      if (list) list.innerHTML = `<div class="muted tiny">Erreur: ${escapeHtml(e.message || String(e))}</div>`;
    } finally {
      if (list) list.removeAttribute('data-loading');
    }
  }

  async function handleAction(act, deviceId){
    const mid = meetingId();
    if (!mid || !deviceId) return;

    try {
      if (act === 'block') {
        const reason = prompt('Motif du blocage (optionnel) :') || '';
        await postJSON('/api/v1/device_block.php', { meeting_id: mid, device_id: deviceId, reason });
      } else if (act === 'unblock') {
        await postJSON('/api/v1/device_unblock.php', { meeting_id: mid, device_id: deviceId });
      } else if (act === 'kick') {
        const msg = prompt('Message (optionnel) :', 'Reconnexion requise.') || '';
        await postJSON('/api/v1/device_kick.php', { meeting_id: mid, device_id: deviceId, message: msg });
      }
      refresh();
    } catch(e){
      (window.Utils?.toast ? Utils.toast : alert)(e.message || String(e));
    }
  }

  function wire(){
    const mount = $('#techDevicesPanel');
    if (!mount) return;

    $('#btnDevicesRefresh')?.addEventListener('click', refresh);
    ['#deviceFilterStatus','#deviceFilterRole','#deviceSearch'].forEach(sel => {
      $(sel)?.addEventListener('input', refresh);
      $(sel)?.addEventListener('change', refresh);
    });

    mount.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-act]');
      if (!btn) return;
      const act = btn.getAttribute('data-act');
      const dev = btn.getAttribute('data-device');
      handleAction(act, dev);
    });

    setInterval(()=>{ if (!document.hidden) refresh(); }, 10000);
    refresh();
  }

  document.addEventListener('DOMContentLoaded', wire);
})();
