(function(){
  const token = (new URLSearchParams(location.search)).get('token') || '';
  const tokenHash = window.VOTE_TOKEN_HASH || '';

  const netEl = document.getElementById('netStatus');
  const msgEl = document.getElementById('voteMsg');
  const retryBtn = document.getElementById('retryBtn');
  const buttons = Array.from(document.querySelectorAll('[data-vote]'));
  const STORAGE_KEY = 'gv_pending_vote_' + token;

  function setNet(state, text){
    if(!netEl) return;
    netEl.dataset.state = state;
    netEl.textContent = text;
  }
  function setMsg(type, text){
    if(!msgEl) return;
    msgEl.dataset.type = type;
    msgEl.textContent = text;
  }
  function setRetryVisible(v){
    if(!retryBtn) return;
    retryBtn.style.display = v ? '' : 'none';
  }

  function savePending(vote){
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify({ vote, at: Date.now() })); } catch(e){}
  }
  function loadPending(){
    try { const raw = localStorage.getItem(STORAGE_KEY); return raw ? JSON.parse(raw) : null; } catch(e){ return null; }
  }
  function clearPending(){
    try { localStorage.removeItem(STORAGE_KEY); } catch(e){}
  }

  async function ping(){
    try{
      const r = await fetch('/api/v1/ping.php', { cache:'no-store', credentials:'same-origin' });
      return r.ok;
    }catch(e){ return false; }
  }

  let lastOk = Date.now();
  let lastIncidentSentAt = 0;

  async function reportIncident(kind, detail){
    const now = Date.now();
    if(now - lastIncidentSentAt < 30000) return;
    lastIncidentSentAt = now;
    try{
      await fetch('/api/v1/vote_incident.php', {
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ kind, detail, token_hash: tokenHash })
      });
    }catch(e){}
  }

  async function watchdogTick(){
    const ok = await ping();
    if(ok){
      lastOk = Date.now();
      setNet('ok', 'Réseau : OK');
      return;
    }
    setNet('down', 'Réseau : instable');
    const delta = Date.now() - lastOk;
    if(delta > 30000){
      setNet('down', 'Réseau : coupé (30s+)');
      setMsg('warn', 'Connexion instable. Préviens le secrétaire (mode dégradé possible).');
      reportIncident('network_30s', 'Ping KO > 30s');
    }
  }

  async function submitVote(vote){
    buttons.forEach(b => b.disabled = true);
    setRetryVisible(false);
    setMsg('info', 'Envoi du vote…');

    const body = new URLSearchParams();
    body.set('vote', vote);

    try{
      const r = await fetch('/vote.php?token=' + encodeURIComponent(token), {
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: body.toString()
      });

      const text = await r.text();
      if(!r.ok) throw new Error(text || ('HTTP ' + r.status));

      clearPending();
      setMsg('ok', 'Vote pris en compte ✅');
      setRetryVisible(false);
      buttons.forEach(b => b.disabled = true);
    }catch(e){
      savePending(vote);
      buttons.forEach(b => b.disabled = false);
      setMsg('danger', 'Impossible d’envoyer le vote (réseau). Réessaie ou préviens le secrétaire.');
      setRetryVisible(true);
      reportIncident('vote_submit_failed', String(e && e.message ? e.message : e));
    }
  }

  function init(){
    setRetryVisible(false);
    setNet(navigator.onLine ? 'ok' : 'down', navigator.onLine ? 'Réseau : OK' : 'Réseau : hors-ligne');

    const pending = loadPending();
    if(pending && pending.vote){
      setMsg('warn', 'Vote en attente (réseau). Clique sur “Réessayer”.');
      setRetryVisible(true);
    } else {
      setMsg('info', 'Sélectionne ton vote.');
    }

    buttons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const v = btn.getAttribute('data-vote');
        if(v) submitVote(v);
      });
    });

    if(retryBtn){
      retryBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const p = loadPending();
        if(p && p.vote) submitVote(p.vote);
        else setRetryVisible(false);
      });
    }

    window.addEventListener('online', () => {
      setNet('ok', 'Réseau : OK');
      const p = loadPending();
      if(p && p.vote) submitVote(p.vote);
    });
    window.addEventListener('offline', () => {
      setNet('down', 'Réseau : hors-ligne');
      setMsg('warn', 'Hors-ligne. Préviens le secrétaire.');
      reportIncident('offline', 'navigator.offline');
    });

    watchdogTick();
    setInterval(watchdogTick, 5000);
  }

  init();
})();
