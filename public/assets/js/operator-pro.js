/* public/assets/js/operator-pro.js
   Operator context: one meeting at a time (meeting_id in URL).
*/
(function(){
  const meetingId = Utils.getMeetingId();
  if(!meetingId){
    Utils.requireMeetingId(document.getElementById('app') || document.body);
    return;
  }

  document.querySelectorAll('[data-meeting-link]').forEach(a => {
    const u = new URL(a.getAttribute('href'), location.origin);
    u.searchParams.set('meeting_id', meetingId);
    a.setAttribute('href', u.pathname + '?' + u.searchParams.toString());
  });
})();
