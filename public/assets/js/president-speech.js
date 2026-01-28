/* public/assets/js/president-speech.js
   Président — gestion de la parole (main levée)
   - Poll speech_queue toutes les 2s
   - Actions: suivant, donner à X, clore, purge
*/
(function(){
  const $ = (s) => document.querySelector(s);

  function apiKey(){ return ($('#presApiKey')?.value || '').trim() || ($('#trustApiKey')?.value || '').trim() || ($('#opApiKey')?.value || '').trim(); }
  function meetingId(){ return ($('#meetingSelect')?.value || '').trim(); }

  async function apiGet(url){
    return Utils.apiGet(url, { apiKey: apiKey() });
  }
  async function apiPost(url, data){
    return Utils.apiPost(url, data, { apiKey: apiKey() });
  }

  function fmtName(m){
    const fn = m.first_name || m.firstName || '';
    const ln = m.last_name || m.lastName || '';
    return (ln + ' ' + fn).trim() || (m.member_id || m.id || '');
  }

  function render(data){
    const speaker = data.speaker;
    const q = data.queue || [];
    const speakerBox = $('#speakerBox');
    const queueBox = $('#queueBox');
    const count = $('#queueCount');

    if (count) count.textContent = String(q.length);

    if (speakerBox){
      if (!speaker){
        speakerBox.innerHTML = '<div class="muted">Aucun orateur.</div>';
      } else {
        speakerBox.innerHTML = `
          <div class="row" style="align-items:flex-start;">
            <div>
              <div class="k" style="font-size:16px;">${fmtName(speaker)}</div>
              <div class="muted tiny">Orateur en cours</div>
            </div>
            <div class="controls">
              <span class="badge ok">SPEAKING</span>
            </div>
          </div>`;
      }
    }

    if (queueBox){
      if (q.length === 0){
        queueBox.innerHTML = '<div class="muted">Aucune main levée.</div>';
      } else {
        queueBox.innerHTML = q.map((m,i)=>`
          <div class="card" style="padding:10px;">
            <div class="row">
              <div>
                <div class="k" style="font-size:14px;">${fmtName(m)}</div>
                <div class="muted tiny">#${i+1} · WAITING</div>
              </div>
              <div class="controls">
                <button class="btn primary" data-grant="${m.member_id}">Donner la parole</button>
              </div>
            </div>
          </div>`).join('');
        queueBox.querySelectorAll('[data-grant]').forEach(btn=>{
          btn.addEventListener('click', async ()=>{
            const mem = btn.getAttribute('data-grant');
            try{
              await apiPost('/api/v1/speech_grant.php', { meeting_id: meetingId(), member_id: mem });
              Utils.toast('success','Parole accordée.');
              await refresh();
            }catch(e){ Utils.toast('error','Impossible de donner la parole'); }
          });
        });
      }
    }
  }

  async function refresh(){
    const mid = meetingId();
    if (!mid) return;
    const data = await apiGet('/api/v1/speech_queue.php?meeting_id=' + encodeURIComponent(mid));
    render(data);
  }

  function bind(){
    $('#btnSpeechRefresh')?.addEventListener('click', ()=>refresh());
    $('#btnSpeechNext')?.addEventListener('click', async ()=>{
      try{
        await apiPost('/api/v1/speech_grant.php', { meeting_id: meetingId() });
        Utils.toast('success','Parole au suivant.');
        await refresh();
      }catch(e){ Utils.toast('error','Impossible'); }
    });
    $('#btnSpeechEnd')?.addEventListener('click', async ()=>{
      try{
        await apiPost('/api/v1/speech_end.php', { meeting_id: meetingId() });
        Utils.toast('success','Parole close.');
        await refresh();
      }catch(e){ Utils.toast('error','Impossible'); }
    });
    $('#btnSpeechClear')?.addEventListener('click', async ()=>{
      if (!confirm('Supprimer l\'historique (finished/cancelled) ?')) return;
      try{
        await apiPost('/api/v1/speech_clear.php', { meeting_id: meetingId() });
        Utils.toast('success','Historique purgé.');
        await refresh();
      }catch(e){ Utils.toast('error','Impossible'); }
    });

    // poll
    setInterval(()=>refresh().catch(()=>{}), 2000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
