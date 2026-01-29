(function(){
  function getMeetingId(){
    try { return (window.Utils && Utils.getMeetingId) ? Utils.getMeetingId() : null; } catch(e){ return null; }
  }
  function applyMeetingLinks(meetingId){
    document.querySelectorAll('[data-meeting-link-template]').forEach(a => {
      const tpl = a.getAttribute('data-meeting-link-template') || '';
      if(!tpl) return;
      if(!meetingId){
        a.classList.add('disabled');
        a.setAttribute('aria-disabled','true');
        a.removeAttribute('href');
        return;
      }
      a.setAttribute('href', tpl.replace('{MEETING_ID}', encodeURIComponent(meetingId)));
    });
  }
  function renderContext(){
    const mid = getMeetingId();
    const el = document.getElementById('tb-meeting');
    if(el) el.textContent = mid ? ('Séance: ' + mid) : 'Séance: —';
    const roleEl = document.getElementById('tb-role');
    const role = (window.Auth && Auth.role) ? Auth.role : null;
    if(roleEl) roleEl.textContent = role ? ('Rôle: ' + role) : '';
  }
  function boot(){
    applyMeetingLinks(getMeetingId());
    renderContext();
    setTimeout(renderContext, 600);
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
