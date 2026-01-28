(function(){
  function meetingId(){
    try { return Utils.getMeetingId(); } catch(e){ return null; }
  }
  const STEPS = [
    { key:'attendance', label:'Présences' },
    { key:'proxies', label:'Procurations' },
    { key:'tokens', label:'Accès tablettes' },
    { key:'votes', label:'Votes' },
    { key:'consolidation', label:'Consolidation' },
    { key:'validation', label:'Validation' },
  ];

  function setHTML(id, html){
    const el = document.getElementById(id);
    if(el) el.innerHTML = html;
  }

  function renderStepper(activeKey, doneKeys){
    return '<div class="stepper">' + STEPS.map(s => {
      const cls = (s.key === activeKey) ? 'step active' : (doneKeys.has(s.key) ? 'step done' : 'step');
      return `<span class="${cls}"><span class="dot"></span>${s.label}</span>`;
    }).join('') + '</div>';
  }

  function compute(state){
    const done = new Set();
    if (state && state.attendance && state.attendance.done) done.add('attendance');
    if (state && state.proxies && state.proxies.done) done.add('proxies');
    if (state && state.tokens && state.tokens.done) done.add('tokens');
    if (state && state.votes && state.votes.done) done.add('votes');
    if (state && state.consolidation && state.consolidation.done) done.add('consolidation');
    if (state && state.validation && state.validation.ready) done.add('validation');

    const next = STEPS.find(s => !done.has(s.key)) || STEPS[STEPS.length-1];
    const activeKey = next.key;

    let msg = '', hint = '';
    if (activeKey === 'attendance'){ msg='Marquer les présences'; hint='Indique qui est présent, absent, représenté ou arrivé en retard.'; }
    if (activeKey === 'proxies'){ msg='Vérifier les procurations'; hint='Attribue les procurations et vérifie les plafonds.'; }
    if (activeKey === 'tokens'){ msg='Préparer l’accès tablettes'; hint='Prépare l’accès des votants (QR / codes).'; }
    if (activeKey === 'votes'){ msg='Lancer les votes'; hint='Ouvre la résolution, surveille les votes, gère les incidents.'; }
    if (activeKey === 'consolidation'){ msg='Consolider les résultats'; hint='Vérifie les totaux et les anomalies avant validation.'; }
    if (activeKey === 'validation'){ msg='Soumettre au Président'; hint='Tout est prêt. Le Président peut valider et archiver.'; }

    return { done, activeKey, msg, hint };
  }

  async function load(){
    const mid = meetingId();
    if(!mid) return;
    try{
      const r = await Utils.apiGet('/api/v1/operator_workflow_state.php?meeting_id=' + encodeURIComponent(mid));
      const d = r && (r.data || r);
      const st = d && (d.state || d);
      const c = compute(st);

      setHTML('workflowStepper', renderStepper(c.activeKey, c.done));
      setHTML('nextAction', `
        <div class="next-action">
          <div>
            <div class="title">Prochaine action : ${c.msg}</div>
            <div class="hint">${c.hint}</div>
          </div>
          <div class="row" style="gap:8px; flex-wrap:wrap;">
            <button class="btn primary" id="nextActionBtn">Continuer</button>
          </div>
        </div>
      `);

      const btn = document.getElementById('nextActionBtn');
      if(btn){
        btn.addEventListener('click', () => {
          const map = {
            attendance: 'step-attendance',
            proxies: 'step-proxies',
            tokens: 'step-tokens',
            votes: 'step-motion',
            consolidation: 'step-consolidation',
            validation: 'step-validation'
          };
          const id = map[c.activeKey];
          const el = id ? document.getElementById(id) : null;
          if(el) el.scrollIntoView({behavior:'smooth', block:'start'});
        }, { once:true });
      }
    }catch(e){}
  }

  if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', load); } else { load(); }
  setInterval(load, 10000);
})();
