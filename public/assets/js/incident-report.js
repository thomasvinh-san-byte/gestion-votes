/* Incident reporting UI: posts to /api/v1/vote_incident.php
   Requires ?meeting_id=... */
(function(){
  const $ = (s) => document.querySelector(s);
  function meetingId(){ try { return (new URLSearchParams(location.search)).get('meeting_id') || ''; } catch(e){ return ''; } }

  async function postIncident(payload){
    const r = await fetch('/api/v1/vote_incident.php', {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'Accept':'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const txt = await r.text();
    let data=null;
    try{ data = JSON.parse(txt); }catch(e){ data = { raw: txt }; }
    if (!r.ok){
      const err = new Error('incident_failed');
      err.status = r.status;
      err.data = data;
      if (window.Utils && Utils.humanizeError) err.message = Utils.humanizeError(err);
      throw err;
    }
    return data;
  }

  function bind(){
    const btn = $('#btnDeclareIncident');
    if (!btn) return;

    btn.addEventListener('click', async () => {
      const mid = meetingId();
      if (!mid) return alert("meeting_id manquant dans l'URL");

      const scope = ($('#incidentScope')?.value || 'meeting').trim();
      const kind = ($('#incidentKind')?.value || 'note').trim();
      const details = ($('#incidentDetails')?.value || '').trim();

      if (!details) {
        if (window.Utils && Utils.toast) Utils.toast("Décris l’incident (détails).", "warn");
        else alert("Décris l’incident (détails).");
        return;
      }

      try{
        await postIncident({
          meeting_id: mid,
          scope,
          kind,
          payload: { details, user_agent: navigator.userAgent }
        });
        if ($('#incidentDetails')) $('#incidentDetails').value = '';
        if (window.Utils && Utils.toast) Utils.toast("Incident enregistré.", "ok");
      }catch(e){
        const msg = (window.Utils && Utils.humanizeError) ? Utils.humanizeError(e) : (e && e.message ? e.message : "Erreur");
        if (window.Utils && Utils.toast) Utils.toast(msg, "error");
        else alert(msg);
      }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
