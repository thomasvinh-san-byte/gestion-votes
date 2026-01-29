(function(){
  function getMeetingId(){
    try{
      const u = new URL(window.location.href);
      return u.searchParams.get('meeting_id') || (window.Utils && Utils.getMeetingId ? Utils.getMeetingId() : '');
    }catch(e){ return ''; }
  }
  const meetingId = getMeetingId();
  function el(id){ return document.getElementById(id); }
  function set(id, txt){ const e = el(id); if(e) e.textContent = txt ?? '—'; }
  function setPill(id, cls, txt){
    const e = el(id); if(!e) return;
    e.className = 'pill ' + (cls||'');
    e.textContent = txt ?? '—';
  }

  async function load(){
    if(!meetingId) return;
    try{
      const r = await Utils.apiGet('/api/v1/meeting_status_for_meeting.php?meeting_id=' + encodeURIComponent(meetingId));
      const d = r && (r.data || r);
      const status = d.status || d.meeting_status || (d.meeting && d.meeting.status) || '—';
      setPill('sc-status', status === 'archived' ? 'success' : 'warn', status);

      if (d.quorum && d.quorum.met === true) setPill('sc-quorum','success','atteint');
      else if (d.quorum && d.quorum.met === false) setPill('sc-quorum','danger','non atteint');
      else setPill('sc-quorum','','—');

      const vdone = d.votes && (d.votes.done ?? d.votes.count);
      const vtot  = d.votes && d.votes.total;
      if(vdone != null && vtot != null) set('sc-votes', `${vdone}/${vtot}`);
      else set('sc-votes','—');

      const cur = d.current_motion || d.motion || null;
      set('sc-motion', (cur && cur.title) ? cur.title : '—');
      set('sc-quorum-hint', (d.quorum && d.quorum.justification) ? d.quorum.justification : '—');
    }catch(e){
      setPill('sc-status','danger','erreur');
      set('sc-quorum-hint', (window.Utils && Utils.humanizeError) ? Utils.humanizeError(e) : 'Impossible de charger.');
    }
  }

  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', load);
  else load();
  setInterval(load, 5000);
})();
