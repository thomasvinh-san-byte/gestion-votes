/* public/assets/js/meeting-context.js
 * Propagate meeting_id through navigation links for a smooth, app-like UX.
 */
(function(){
  function getMeetingId(){
    const url = new URL(window.location.href);
    const q = url.searchParams.get("meeting_id");
    if (q) return q;
    // fallback: hidden input
    const inp = document.querySelector('input[name="meeting_id"]');
    if (inp && inp.value) return inp.value;
    return null;
  }

  function appendMeetingIdToLinks(meetingId){
    if (!meetingId) return;
    document.querySelectorAll('a[href]').forEach(a=>{
      const href = a.getAttribute('href');
      if (!href) return;
      if (href.startsWith('http')) return;
      if (!href.endsWith('.htmx.html') && !href.endsWith('.php')) return;
      // skip vote token links
      if (href.startsWith('/vote.php')) return;
      const u = new URL(href, window.location.origin);
      if (!u.searchParams.get('meeting_id')) {
        u.searchParams.set('meeting_id', meetingId);
        a.setAttribute('href', u.pathname + '?' + u.searchParams.toString());
      }
    });
  }

  const meetingId = getMeetingId();
  appendMeetingIdToLinks(meetingId);
})();
