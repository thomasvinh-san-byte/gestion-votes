/* Reset demo button: calls /api/v1/meeting_reset_demo.php
   Safety: requires typing RESET and confirms. Works with ?meeting_id=... */
(function(){
  const $ = (sel) => document.querySelector(sel);

  function meetingId(){
    try { return (new URLSearchParams(location.search)).get('meeting_id') || ''; } catch(e){ return ''; }
  }

  async function postJson(url, body){
    const r = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type':'application/json','Accept':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(body||{})
    });
    const text = await r.text();
    let data = null;
    try { data = JSON.parse(text); } catch(e) { data = { raw:text }; }
    if (!r.ok){
      const err = new Error('reset_failed');
      err.status = r.status;
      err.data = data;
      if (window.Utils && Utils.humanizeError) err.message = Utils.humanizeError(err);
      throw err;
    }
    return data;
  }

  async function onReset(){
    const mid = meetingId();
    if (!mid) {
      alert("meeting_id manquant dans l'URL");
      return;
    }
    const ok = confirm("Réinitialiser la séance (DEMO) ?

Cela supprime ballots/tokens/résultats et remet les motions à zéro.
Impossible si la séance est validée.");
    if (!ok) return;

    const typed = prompt("Tape RESET pour confirmer (anti-erreur) :");
    if (typed !== "RESET") {
      if (window.Utils && Utils.toast) Utils.toast("Reset annulé.", "warn");
      return;
    }

    try{
      await postJson('/api/v1/meeting_reset_demo.php', { meeting_id: mid, confirm: 'RESET' });
      if (window.Utils && Utils.toast) Utils.toast("Reset effectué.", "ok");
      else alert("Reset effectué.");
      location.reload();
    }catch(e){
      const msg = (window.Utils && Utils.humanizeError) ? Utils.humanizeError(e) : (e && e.message ? e.message : "Erreur");
      if (window.Utils && Utils.toast) Utils.toast(msg, "error");
      else alert(msg);
    }
  }

  function bind(){
    const btn = $('#btnResetDemo');
    if (!btn) return;
    btn.addEventListener('click', onReset);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
