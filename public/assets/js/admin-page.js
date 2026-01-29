(function(){
  const sysBox = document.getElementById('sysBox');
  const refreshSys = document.getElementById('refreshSys');
  const quorumList = document.getElementById('quorumList');
  const voteList = document.getElementById('voteList');
  const newQuorumBtn = document.getElementById('newQuorumBtn');
  const newVoteBtn = document.getElementById('newVoteBtn');

  const modal = document.getElementById('modal');
  const modalTitle = document.getElementById('modalTitle');
  const modalBody = document.getElementById('modalBody');

  function openModal(title, html){
    modalTitle.textContent = title;
    modalBody.innerHTML = html;
    modal.style.display = 'block';
  }
  function closeModal(){
    modal.style.display = 'none';
    modalBody.innerHTML = '';
  }
  modal.addEventListener('click', (e) => {
    const t = e.target;
    if (t && t.getAttribute && t.getAttribute('data-close') === '1') closeModal();
  });

  function kv(label, value){
    return `<div class="row between"><div class="muted tiny">${Utils.escapeHtml(label)}</div><div>${Utils.escapeHtml(String(value ?? '—'))}</div></div>`;
  }

  async function loadSystem(){
    sysBox.innerHTML = '<div class="muted">Chargement…</div>';
    try {
      const r = await Utils.apiGet('/api/v1/admin_system_status.php');
      const d = r && (r.data || r);
      const s = d && d.system ? d.system : d;
      sysBox.innerHTML = `
        <div class="card">
          ${kv('Horloge serveur', s.server_time)}
          ${kv('Latence DB (ms)', s.db_latency_ms)}
          ${kv('Connexions DB actives', s.db_active_connections)}
          ${kv('Meetings', s.count_meetings)}
          ${kv('Motions', s.count_motions)}
          ${kv('Tokens vote', s.count_vote_tokens)}
          ${kv('Audit events', s.count_audit_events)}
        </div>
      `;
    } catch(e) {
      sysBox.innerHTML = '<div class="badge danger">Erreur chargement système</div>';
    }
  }

  function quorumRow(p){
    const name = Utils.escapeHtml(p.name || '—');
    const mode = Utils.escapeHtml(p.mode || 'single');
    const den = Utils.escapeHtml(p.denominator || 'eligible_members');
    const thr = Utils.escapeHtml(String(p.threshold ?? ''));
    const den2 = Utils.escapeHtml(p.denominator2 || '');
    const thr2 = Utils.escapeHtml(String(p.threshold2 ?? ''));
    const call2 = Utils.escapeHtml(String(p.threshold_call2 ?? ''));
    return `
      <div class="card">
        <div class="row between" style="gap:12px; flex-wrap:wrap;">
          <div class="grow">
            <div style="font-weight:700;">${name}</div>
            <div class="muted tiny">mode=${mode} · ${den} ≥ ${thr}${(mode==='evolving' && call2)?(' · call2 ≥ '+call2):''}${(mode==='double' && den2)?(' · '+den2+' ≥ '+thr2):''}</div>
            <div class="muted tiny">${Utils.escapeHtml(p.description || '')}</div>
          </div>
          <div class="row" style="gap:8px; flex-wrap:wrap;">
            <button class="btn" data-q-edit="${p.id}">Éditer</button>
          </div>
        </div>
      </div>
    `;
  }

  function voteRow(p){
    const name = Utils.escapeHtml(p.name || '—');
    const base = Utils.escapeHtml(p.base || 'expressed');
    const thr = Utils.escapeHtml(String(p.threshold ?? ''));
    const abst = p.abstention_as_against ? 'abst→contre' : 'abst neutre';
    return `
      <div class="card">
        <div class="row between" style="gap:12px; flex-wrap:wrap;">
          <div class="grow">
            <div style="font-weight:700;">${name}</div>
            <div class="muted tiny">${base} ≥ ${thr} · ${abst}</div>
            <div class="muted tiny">${Utils.escapeHtml(p.description || '')}</div>
          </div>
          <div class="row" style="gap:8px; flex-wrap:wrap;">
            <button class="btn" data-v-edit="${p.id}">Éditer</button>
          </div>
        </div>
      </div>
    `;
  }

  function quorumForm(p){
    const id = p?.id || '';
    return `
      <form id="qForm">
        <input type="hidden" name="id" value="${Utils.escapeHtml(id)}" />
        <label class="tiny muted">Nom</label>
        <input class="input" name="name" value="${Utils.escapeHtml(p?.name||'')}" required />
        <label class="tiny muted">Description</label>
        <textarea class="input" name="description">${Utils.escapeHtml(p?.description||'')}</textarea>

        <div class="row" style="gap:8px; flex-wrap:wrap;">
          <div class="grow">
            <label class="tiny muted">Mode</label>
            <select class="input" name="mode">
              <option value="single">single</option>
              <option value="evolving">evolving</option>
              <option value="double">double</option>
            </select>
          </div>
          <div class="grow">
            <label class="tiny muted">Dénominateur</label>
            <select class="input" name="denominator">
              <option value="eligible_members">eligible_members</option>
              <option value="eligible_weight">eligible_weight</option>
            </select>
          </div>
          <div class="grow">
            <label class="tiny muted">Seuil (0..1)</label>
            <input class="input" name="threshold" value="${Utils.escapeHtml(String(p?.threshold??''))}" placeholder="0.5" />
          </div>
        </div>

        <div class="row" style="gap:8px; flex-wrap:wrap;">
          <div class="grow">
            <label class="tiny muted">Seuil 2e convocation (evolving)</label>
            <input class="input" name="threshold_call2" value="${Utils.escapeHtml(String(p?.threshold_call2??''))}" placeholder="0.0" />
          </div>
          <div class="grow">
            <label class="tiny muted">Dénominateur #2 (double)</label>
            <select class="input" name="denominator2">
              <option value="">—</option>
              <option value="eligible_members">eligible_members</option>
              <option value="eligible_weight">eligible_weight</option>
            </select>
          </div>
          <div class="grow">
            <label class="tiny muted">Seuil #2 (double)</label>
            <input class="input" name="threshold2" value="${Utils.escapeHtml(String(p?.threshold2??''))}" placeholder="0.6" />
          </div>
        </div>

        <div class="row" style="gap:8px; flex-wrap:wrap;">
          <label class="row" style="gap:6px; align-items:center;">
            <input type="checkbox" name="include_proxies" ${p?.include_proxies ? 'checked' : ''} />
            <span class="tiny">Inclure procurations</span>
          </label>
          <label class="row" style="gap:6px; align-items:center;">
            <input type="checkbox" name="count_remote" ${p?.count_remote ? 'checked' : ''} />
            <span class="tiny">Inclure remote</span>
          </label>
        </div>

        <div class="row" style="gap:8px; justify-content:flex-end; margin-top:10px;">
          <button class="btn" type="button" data-close="1">Annuler</button>
          <button class="btn primary" type="submit">Enregistrer</button>
        </div>
      </form>
    `;
  }

  function voteForm(p){
    const id = p?.id || '';
    return `
      <form id="vForm">
        <input type="hidden" name="id" value="${Utils.escapeHtml(id)}" />
        <label class="tiny muted">Nom</label>
        <input class="input" name="name" value="${Utils.escapeHtml(p?.name||'')}" required />
        <label class="tiny muted">Description</label>
        <textarea class="input" name="description">${Utils.escapeHtml(p?.description||'')}</textarea>

        <div class="row" style="gap:8px; flex-wrap:wrap;">
          <div class="grow">
            <label class="tiny muted">Base</label>
            <select class="input" name="base">
              <option value="expressed">expressed</option>
              <option value="total_eligible">total_eligible</option>
            </select>
          </div>
          <div class="grow">
            <label class="tiny muted">Seuil (0..1)</label>
            <input class="input" name="threshold" value="${Utils.escapeHtml(String(p?.threshold??''))}" placeholder="0.5" />
          </div>
          <div class="grow">
            <label class="tiny muted">Abstention</label>
            <select class="input" name="abstention_as_against">
              <option value="0">neutre</option>
              <option value="1">compte contre</option>
            </select>
          </div>
        </div>

        <div class="row" style="gap:8px; justify-content:flex-end; margin-top:10px;">
          <button class="btn" type="button" data-close="1">Annuler</button>
          <button class="btn primary" type="submit">Enregistrer</button>
        </div>
      </form>
    `;
  }

  async function loadQuorumPolicies(){
    quorumList.innerHTML = '<div class="muted">Chargement…</div>';
    try{
      const r = await Utils.apiGet('/api/v1/admin_quorum_policies.php');
      const d = r && (r.data || r);
      const items = d.items || [];
      quorumList.innerHTML = items.map(quorumRow).join('') || '<div class="muted">Aucune policy.</div>';
    } catch(e){
      quorumList.innerHTML = '<div class="badge danger">Erreur policies quorum</div>';
    }
  }

  async function loadVotePolicies(){
    voteList.innerHTML = '<div class="muted">Chargement…</div>';
    try{
      const r = await Utils.apiGet('/api/v1/admin_vote_policies.php');
      const d = r && (r.data || r);
      const items = d.items || [];
      voteList.innerHTML = items.map(voteRow).join('') || '<div class="muted">Aucune policy.</div>';
    } catch(e){
      voteList.innerHTML = '<div class="badge danger">Erreur policies vote</div>';
    }
  }

  function findById(items, id){ return (items||[]).find(x => String(x.id) === String(id)); }

  async function openEditQuorum(id){
    const r = await Utils.apiGet('/api/v1/admin_quorum_policies.php');
    const items = (r && ((r.data||r).items)) ? (r.data||r).items : [];
    const p = id ? findById(items, id) : null;
    openModal(id ? 'Éditer politique de quorum' : 'Nouvelle politique de quorum', quorumForm(p));
    const form = document.getElementById('qForm');
    if(!form) return;
    if(p){
      form.mode.value = p.mode || 'single';
      form.denominator.value = p.denominator || 'eligible_members';
      form.denominator2.value = p.denominator2 || '';
    }
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const payload = Object.fromEntries(fd.entries());
      payload.include_proxies = form.include_proxies.checked ? 1 : 0;
      payload.count_remote = form.count_remote.checked ? 1 : 0;
      try{
        await Utils.apiPost('/api/v1/admin_quorum_policies.php', payload);
        Utils.toast('Policy quorum enregistrée');
        closeModal();
        loadQuorumPolicies();
      }catch(err){
        Utils.toast('Erreur sauvegarde quorum', 'danger');
      }
    });
  }

  async function openEditVote(id){
    const r = await Utils.apiGet('/api/v1/admin_vote_policies.php');
    const items = (r && ((r.data||r).items)) ? (r.data||r).items : [];
    const p = id ? findById(items, id) : null;
    openModal(id ? 'Éditer politique de majorité' : 'Nouvelle politique de majorité', voteForm(p));
    const form = document.getElementById('vForm');
    if(!form) return;
    if(p){
      form.base.value = p.base || 'expressed';
      form.abstention_as_against.value = String(p.abstention_as_against ? 1 : 0);
    }
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const payload = Object.fromEntries(fd.entries());
      try{
        await Utils.apiPost('/api/v1/admin_vote_policies.php', payload);
        Utils.toast('Policy majorité enregistrée');
        closeModal();
        loadVotePolicies();
      }catch(err){
        Utils.toast('Erreur sauvegarde majorité', 'danger');
      }
    });
  }

  document.addEventListener('click', (e) => {
    const t = e.target;
    if(!t || !t.getAttribute) return;
    const qid = t.getAttribute('data-q-edit');
    const vid = t.getAttribute('data-v-edit');
    if(qid){ openEditQuorum(qid); }
    if(vid){ openEditVote(vid); }
  });

  newQuorumBtn.addEventListener('click', () => openEditQuorum(''));
  newVoteBtn.addEventListener('click', () => openEditVote(''));
  refreshSys.addEventListener('click', loadSystem);

  loadSystem();
  loadQuorumPolicies();
  loadVotePolicies();
})();
