(function(){
  function getMeetingId(){
    try {
      const u = new URL(window.location.href);
      return u.searchParams.get('meeting_id') || '';
    } catch(e){ return ''; }
  }

  const meetingId = getMeetingId();
  const hidden = document.getElementById('meetingIdHidden');
  if (hidden) hidden.value = meetingId;

  function el(id){ return document.getElementById(id); }
  function setText(id, txt){ const e = el(id); if(e) e.textContent = txt; }
  function setBadge(id, cls, txt){
    const e = el(id); if(!e) return;
    e.className = 'badge ' + (cls||'idle');
    e.textContent = txt || '—';
  }

  async function loadStatus(){
    if(!meetingId) {
      setBadge('session-badge','danger','Séance manquante');
      setText('current-motion-box','Ouvre cette page avec ?meeting_id=...');
      return;
    }

    try {
      const r = await Utils.apiGet('/api/v1/meeting_status_for_meeting.php?meeting_id=' + encodeURIComponent(meetingId));
      const d = r && (r.data || r);
      // Best-effort fields
      const status = d.status || d.meeting_status || d.meeting?.status || '—';
      setBadge('session-badge', status === 'archived' ? 'success' : 'idle', status);
      setText('side-status', status);

      // Quorum (if endpoint provides ready_to_sign/validation etc.)
      if (d.quorum && typeof d.quorum.justification === 'string') {
        setText('side-quorum', d.quorum.justification);
      } else if (d.quorum && (d.quorum.met !== undefined)) {
        setText('side-quorum', d.quorum.met ? 'atteint' : 'non atteint');
      } else {
        setText('side-quorum', '—');
      }

      // Current motion
      const cur = d.current_motion || d.motion || null;
      if(cur && cur.title){
        setText('current-motion-box', cur.title);
        setBadge('motion-state-badge', cur.closed_at ? 'success' : (cur.opened_at ? 'warn' : 'idle'), cur.opened_at ? (cur.closed_at ? 'clôturée' : 'ouverte') : '—');
      } else {
        setText('current-motion-box', 'Aucune résolution active.');
        setBadge('motion-state-badge','idle','—');
      }

      // votes quick
      if (d.votes && typeof d.votes === 'object') {
        const done = d.votes.done ?? d.votes.count ?? null;
        const total = d.votes.total ?? null;
        if(done !== null && total !== null) setText('side-votes', `${done}/${total}`);
      }
    } catch(e) {
      setBadge('session-badge','danger','Erreur');
      setText('current-motion-box', Utils.humanizeError ? Utils.humanizeError(e) : 'Impossible de charger.');
    }
  }

  async function loadNotifications(){
    if(!meetingId) return;
    try{
      const r = await Utils.apiGet('/api/v1/notifications_list.php?meeting_id=' + encodeURIComponent(meetingId) + '&audience=operator');
      const d = r && (r.data || r);
      const items = d.items || d.notifications || [];
      const box = el('mini-log');
      if(!box) return;
      if(!items.length){ box.textContent = '—'; return; }
      box.innerHTML = items.slice(-8).reverse().map(n => {
        const t = (n.title || n.code || 'info');
        const m = (n.message || '');
        return `<div style="margin-bottom:8px;"><b>${Utils.escapeHtml(t)}</b><div class="muted tiny">${Utils.escapeHtml(m)}</div></div>`;
      }).join('');
    }catch(e){}
  }

  loadStatus();
  loadNotifications();
  setInterval(loadStatus, 5000);
  setInterval(loadNotifications, 7000);
})();
