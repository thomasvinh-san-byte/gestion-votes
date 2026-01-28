/* Alerts Panel (TSX-aligned)
 * - Poll notifications for operator/trust/all
 * - Filters: severity, unread, search
 * - Actions: mark read, mark all read, clear
 */
(function(){
  'use strict';

  const $ = (sel) => document.querySelector(sel);
  const box = $('#notif_box');
  if (!box) return;

  let lastId = 0;
  let cache = []; // newest first
  let pollTimer = null;

  function getKey(){ return ($('#opApiKey')?.value || '').trim(); }
  function getMeeting(){ return ($('#meetingSelect')?.value || '').trim(); }

  async function apiGet(url){ return Utils.apiGet(url, { apiKey: getKey() }); }
  async function apiPost(url, data){ return Utils.apiPost(url, data, { apiKey: getKey() }); }

  function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
  }

  function fmtTime(iso){
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return String(iso);
    return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }

  function sevKind(sev){
    if (sev === 'blocking') return 'danger';
    if (sev === 'warn') return 'warn';
    return 'info';
  }

  function mergeNew(items){
    if (!Array.isArray(items) || items.length === 0) return;
    // items expected: newest first
    const existing = new Set(cache.map(n => Number(n.id)));
    for (const n of items) {
      const id = Number(n?.id || 0);
      if (!id || existing.has(id)) continue;
      cache.unshift(n);
      existing.add(id);
    }
    // trim local cache
    if (cache.length > 300) cache = cache.slice(0, 300);
  }

  function getFilters(){
    return {
      audience: ($('#notifAudience')?.value || 'operator').trim(),
      severity: ($('#notifSeverity')?.value || '').trim(),
      unreadOnly: !!$('#notifUnreadOnly')?.checked,
      q: ($('#notifSearch')?.value || '').trim().toLowerCase(),
    };
  }

  function applyFilters(list, f){
    return list.filter(n => {
      if (f.severity && String(n.severity) !== f.severity) return false;
      if (f.unreadOnly && n.read_at) return false;
      if (f.q) {
        const hay = (String(n.message||'') + ' ' + String(n.code||'') + ' ' + JSON.stringify(n.data||{})).toLowerCase();
        if (!hay.includes(f.q)) return false;
      }
      return true;
    });
  }

  function render(){
    const badgeEl = $('#badgeNotifications');
    const counters = $('#notifCounters');
    const f = getFilters();

    const filtered = applyFilters(cache, f);
    const unread = cache.filter(n => !n.read_at).length;
    if (badgeEl) badgeEl.textContent = String(unread);

    if (counters) {
      const total = cache.length;
      const shown = filtered.length;
      counters.innerHTML = `
        <span class="badge">Total ${escapeHtml(total)}</span>
        <span class="badge warn">Non lus ${escapeHtml(unread)}</span>
        <span class="badge info">Affichés ${escapeHtml(shown)}</span>
      `;
    }

    box.innerHTML = '';
    if (filtered.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'muted';
      empty.textContent = 'Aucune notification.';
      box.appendChild(empty);
      return;
    }

    for (const n of filtered.slice(0, 80)) {
      const id = Number(n.id || 0);
      const sev = String(n.severity || 'info');
      const kind = sevKind(sev);
      const created = fmtTime(n.created_at);
      const isUnread = !n.read_at;
      const data = (n.data && typeof n.data === 'object') ? n.data : {};
      const actionUrl = (data.action_url || '').toString();
      const actionLabel = (data.action_label || '').toString();

      const el = document.createElement('div');
      el.className = 'notif-item' + (isUnread ? ' unread' : '');
      el.dataset.id = String(id);
      el.innerHTML = `
        <div class="notif-meta">
          <span class="badge ${escapeHtml(kind)}">${escapeHtml(sev)}</span>
          <span class="muted tiny mono">#${escapeHtml(id)} · ${escapeHtml(created)}</span>
          ${isUnread ? `<span class="badge warn" style="margin-left:6px;">NON LU</span>` : `<span class="badge" style="margin-left:6px;">LU</span>`}
        </div>
        <div class="notif-msg">${escapeHtml(n.message || '')}</div>
        <div class="notif-action" style="display:flex; gap:8px; align-items:center; margin-top:6px;">
          ${actionUrl ? `<a class="btn tiny" href="${escapeHtml(actionUrl)}" target="_blank" rel="noopener">${escapeHtml(actionLabel || 'Ouvrir')}</a>` : ''}
          ${isUnread ? `<button class="btn tiny" data-act="read">Marquer lu</button>` : ''}
        </div>
      `;

      el.addEventListener('click', (ev) => {
        const t = ev.target;
        if (t && t.getAttribute && t.getAttribute('data-act') === 'read') return;
        // Click anywhere on unread card marks read (best-effort)
        if (isUnread) markRead(id).catch(()=>{});
      });
      el.querySelector('[data-act="read"]')?.addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        markRead(id).catch(()=>{});
      });

      box.appendChild(el);
    }
  }

  async function loadRecent(){
    const meetingId = getMeeting();
    if (!meetingId) return;
    const audience = ($('#notifAudience')?.value || 'operator').trim();
    const r = await apiGet(`/api/v1/notifications_recent.php?meeting_id=${encodeURIComponent(meetingId)}&audience=${encodeURIComponent(audience)}&limit=120`);
    const items = r?.data?.notifications || [];
    cache = Array.isArray(items) ? items : [];
    lastId = cache.reduce((m,n)=>Math.max(m, Number(n?.id||0)), 0);
    render();
  }

  async function poll(){
    const meetingId = getMeeting();
    if (!meetingId) return;
    const audience = ($('#notifAudience')?.value || 'operator').trim();
    try{
      const r = await apiGet(`/api/v1/notifications_list.php?meeting_id=${encodeURIComponent(meetingId)}&audience=${encodeURIComponent(audience)}&since_id=${encodeURIComponent(String(lastId))}&limit=50`);
      const items = r?.data?.notifications || [];
      if (Array.isArray(items) && items.length) {
        // list returns ASC by id
        for (const n of items) {
          const id = Number(n?.id||0);
          if (id > lastId) lastId = id;
        }
        // Merge as newest first
        mergeNew(items.slice().reverse());
        render();
      }
    } catch(e){ /* silent */ }
  }

  async function markRead(id){
    const meetingId = getMeeting();
    if (!meetingId || !id) return;
    await apiPost('/api/v1/notifications_mark_read.php', { meeting_id: meetingId, id });
    // update local cache
    cache = cache.map(n => (Number(n.id) === Number(id)) ? { ...n, read_at: (new Date()).toISOString() } : n);
    render();
  }

  async function markAllRead(){
    const meetingId = getMeeting();
    if (!meetingId) return;
    const audience = ($('#notifAudience')?.value || 'operator').trim();
    await apiPost('/api/v1/notifications_mark_all_read.php', { meeting_id: meetingId, audience });
    const now = (new Date()).toISOString();
    cache = cache.map(n => ({ ...n, read_at: n.read_at ? n.read_at : now }));
    render();
  }

  async function clearAll(){
    const meetingId = getMeeting();
    if (!meetingId) return;
    const audience = ($('#notifAudience')?.value || 'operator').trim();
    if (!confirm('Effacer toutes les notifications affichées pour cette audience ?')) return;
    await apiPost('/api/v1/notifications_clear.php', { meeting_id: meetingId, audience });
    cache = [];
    lastId = 0;
    render();
  }

  function startPolling(){
    stopPolling();
    pollTimer = window.setInterval(poll, 5000);
  }
  function stopPolling(){
    if (pollTimer) window.clearInterval(pollTimer);
    pollTimer = null;
  }

  function wire(){
    $('#btnNotifRefresh')?.addEventListener('click', () => loadRecent().catch(console.error));
    $('#btnNotifMarkAllRead')?.addEventListener('click', () => markAllRead().catch(console.error));
    $('#btnNotifClear')?.addEventListener('click', () => clearAll().catch(console.error));
    ['#notifAudience','#notifSeverity','#notifUnreadOnly','#notifSearch'].forEach(sel => {
      $(sel)?.addEventListener('change', () => { render(); });
      $(sel)?.addEventListener('input', () => { render(); });
    });

    // change meeting refreshes
    $('#meetingSelect')?.addEventListener('change', () => { loadRecent().catch(console.error); });

    // initial load when meetings are loaded (best-effort)
    window.setTimeout(() => loadRecent().catch(()=>{}), 800);
    startPolling();
  }

  wire();
})();
