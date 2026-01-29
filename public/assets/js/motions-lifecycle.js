/* public/assets/js/motions-lifecycle.js
   Motion lifecycle complet (TSX-like):
   - create/edit/delete
   - secret + overrides vote/quorum
   - garde-fous: interdiction edit/delete si opened/closed
*/
(() => {
  const $ = (s) => document.querySelector(s);
  const state = {
    meetingId: '',
    agendas: [],
    motions: [],
    votePolicies: [],
    quorumPolicies: [],
  };

  function setMsg(el, kind, text) {
    if (!el) return;
    if (!text) { el.classList.add('hidden'); el.textContent=''; return; }
    el.classList.remove('hidden');
    el.className = 'rounded-xl border px-3 py-2 text-sm ' + (
      kind === 'error' ? 'bg-rose-50 border-rose-200 text-rose-900' :
      kind === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' :
      'bg-slate-50 border-slate-200 text-slate-900'
    );
    el.textContent = text;
  }

  async function apiGet(url) {
    const key = ($('#apiKey')?.value || '').trim();
    const headers = { 'Accept': 'application/json' };
    if (key) headers['X-Api-Key'] = key;
    const r = await fetch(url, { headers, credentials: 'same-origin' });
    const body = await r.json().catch(() => null);
    return { status: r.status, body };
  }
  async function apiPost(url, payload) {
    const key = ($('#apiKey')?.value || '').trim();
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (key) headers['X-Api-Key'] = key;
    const r = await fetch(url, { method: 'POST', headers, body: JSON.stringify(payload), credentials: 'same-origin' });
    const body = await r.json().catch(() => null);
    return { status: r.status, body };
  }

  function fillSelect(sel, items, emptyLabel) {
    if (!sel) return;
    sel.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = emptyLabel || '— Hériter —';
    sel.appendChild(opt0);
    for (const it of items) {
      const o = document.createElement('option');
      o.value = it.id;
      o.textContent = it.name || it.title || it.label || it.id;
      sel.appendChild(o);
    }
  }

  function render() {
    const q = ($('#q')?.value || '').trim().toLowerCase();
    const list = $('#motionsList');
    if (!list) return;

    const motions = state.motions.filter(m => {
      if (!q) return true;
      const t = (m.title || '').toLowerCase();
      const d = (m.description || '').toLowerCase();
      return t.includes(q) || d.includes(q) || String(m.id).includes(q);
    });

    const agendaById = Object.fromEntries(state.agendas.map(a => [a.id, a]));
    list.innerHTML = '';

    for (const m of motions) {
      const isOpen = !!m.opened_at && !m.closed_at;
      const isClosed = !!m.closed_at;

      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'w-full text-left rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:bg-slate-50';
      card.dataset.motionId = m.id;

      const agendaTitle = agendaById[m.agenda_id]?.title || '—';
      const badges = [];
      if (m.secret) badges.push('<span class="inline-flex items-center rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-900">SECRET</span>');
      badges.push(isOpen
        ? '<span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-900">OUVERTE</span>'
        : isClosed
          ? '<span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">CLÔTURÉE</span>'
          : '<span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900">À OUVRIR</span>'
      );

      if (m.vote_policy_id) badges.push('<span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-900">MAJORITÉ*</span>');
      if (m.quorum_policy_id) badges.push('<span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-900">QUORUM*</span>');

      card.innerHTML = `
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-sm font-semibold">${m.title || '(sans titre)'}</div>
            <div class="mt-1 text-sm text-slate-600 line-clamp-2">${(m.description || '').replace(/</g,'&lt;')}</div>
            <div class="mt-2 text-xs text-slate-500">Agenda : ${agendaTitle}</div>
          </div>
          <div class="text-right space-y-2">
            <div class="flex flex-wrap justify-end gap-1">${badges.join('')}</div>
            <div class="text-xs text-slate-500 font-mono">${String(m.id).slice(0,8)}…</div>
          </div>
        </div>
      `;

      card.addEventListener('click', () => openEdit(m));
      list.appendChild(card);
    }
  }

  function openModal() {
    $('#modal')?.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }
  function closeModal() {
    $('#modal')?.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    setMsg($('#modalMsg'), '', '');
  }

  function setDeleteGuard(m) {
    const btn = $('#btnDelete');
    const hint = $('#modalHint');
    if (!btn || !hint) return;

    const isOpen = !!m.opened_at && !m.closed_at;
    const isClosed = !!m.closed_at;

    if (isOpen || isClosed) {
      btn.disabled = true;
      btn.classList.add('opacity-50','cursor-not-allowed');
      hint.textContent = isOpen ? 'Résolution ouverte : suppression interdite.' : 'Résolution clôturée : suppression interdite.';
    } else {
      btn.disabled = false;
      btn.classList.remove('opacity-50','cursor-not-allowed');
      hint.textContent = 'Édition autorisée tant que la résolution n’est pas ouverte.';
    }
  }

  function openEdit(m) {
    $('#motionId').value = m.id;
    $('#titleEdit').value = m.title || '';
    $('#descEdit').value = m.description || '';
    $('#secretEdit').checked = !!m.secret;

    // Agenda select (locked to same meeting; changing agenda is allowed only within meeting)
    const agendaSel = $('#agendaIdEdit');
    agendaSel.innerHTML='';
    for (const a of state.agendas) {
      const o=document.createElement('option');
      o.value=a.id;
      o.textContent=a.title || a.id;
      if (a.id === m.agenda_id) o.selected = true;
      agendaSel.appendChild(o);
    }

    fillSelect($('#votePolicyEdit'), state.votePolicies, '— Hériter séance —');
    fillSelect($('#quorumPolicyEdit'), state.quorumPolicies, '— Hériter séance —');
    $('#votePolicyEdit').value = m.vote_policy_id || '';
    $('#quorumPolicyEdit').value = m.quorum_policy_id || '';

    setDeleteGuard(m);
    openModal();
  }

  async function loadPolicies() {
    const mid = state.meetingId;
    const [vp, qp] = await Promise.all([
      apiGet('/api/v1/vote_policies.php'),
      apiGet('/api/v1/quorum_policies.php'),
    ]);
    state.votePolicies = vp.body?.vote_policies || vp.body?.items || [];
    state.quorumPolicies = qp.body?.quorum_policies || qp.body?.items || [];
    fillSelect($('#votePolicyId'), state.votePolicies, '— Hériter séance —');
    fillSelect($('#quorumPolicyId'), state.quorumPolicies, '— Hériter séance —');
  }

  async function load() {
    const mid = ($('#meetingId')?.value || '').trim();
    state.meetingId = mid;
    if (!mid) return;

    await loadPolicies();

    const { body } = await apiGet('/api/v1/motions_for_meeting.php?meeting_id=' + encodeURIComponent(mid));
    state.agendas = body?.agendas || [];
    state.motions = body?.motions || [];

    // Agenda select for create
    const agSel = $('#agendaId');
    agSel.innerHTML='';
    for (const a of state.agendas) {
      const o=document.createElement('option');
      o.value=a.id;
      o.textContent=a.title || a.id;
      agSel.appendChild(o);
    }

    render();
  }

  async function create() {
    const mid = state.meetingId;
    if (!mid) return setMsg($('#msg'),'error','Sélectionne une séance.');
    const agendaId = ($('#agendaId')?.value || '').trim();
    const title = ($('#title')?.value || '').trim();
    const description = ($('#description')?.value || '').trim();
    const secret = !!$('#secret')?.checked;
    const votePolicyId = ($('#votePolicyId')?.value || '').trim();
    const quorumPolicyId = ($('#quorumPolicyId')?.value || '').trim();

    if (!agendaId) return setMsg($('#msg'),'error','Agenda obligatoire.');
    if (!title) return setMsg($('#msg'),'error','Titre obligatoire.');
    if (title.length > 80) return setMsg($('#msg'),'error','Titre trop long (≤ 80).');

    setMsg($('#msg'),'','');
    const { status, body } = await apiPost('/api/v1/motions.php', {
      agenda_id: agendaId,
      title,
      description,
      secret,
      vote_policy_id: votePolicyId,
      quorum_policy_id: quorumPolicyId
    });
    if (!body?.ok) return setMsg($('#msg'),'error','Erreur création: ' + (body?.error || status));

    $('#title').value='';
    $('#description').value='';
    $('#secret').checked=false;
    $('#votePolicyId').value='';
    $('#quorumPolicyId').value='';
    setMsg($('#msg'),'success','Résolution créée.');
    await load();
  }

  async function saveEdit() {
    const motionId = ($('#motionId')?.value || '').trim();
    const agendaId = ($('#agendaIdEdit')?.value || '').trim();
    const title = ($('#titleEdit')?.value || '').trim();
    const description = ($('#descEdit')?.value || '').trim();
    const secret = !!$('#secretEdit')?.checked;
    const votePolicyId = ($('#votePolicyEdit')?.value || '').trim();
    const quorumPolicyId = ($('#quorumPolicyEdit')?.value || '').trim();

    if (!motionId) return;
    if (!agendaId) return setMsg($('#modalMsg'),'error','Agenda obligatoire.');
    if (!title) return setMsg($('#modalMsg'),'error','Titre obligatoire.');
    if (title.length > 80) return setMsg($('#modalMsg'),'error','Titre trop long (≤ 80).');

    setMsg($('#modalMsg'),'','');
    const { status, body } = await apiPost('/api/v1/motions.php', {
      motion_id: motionId,
      agenda_id: agendaId,
      title,
      description,
      secret,
      vote_policy_id: votePolicyId,
      quorum_policy_id: quorumPolicyId
    });

    if (!body?.ok) return setMsg($('#modalMsg'),'error','Erreur: ' + (body?.error || status));
    setMsg($('#modalMsg'),'success','Enregistré.');
    await load();
  }

  async function del() {
    const motionId = ($('#motionId')?.value || '').trim();
    if (!motionId) return;
    if (!confirm('Supprimer cette résolution ? (uniquement avant ouverture)')) return;

    const { status, body } = await apiPost('/api/v1/motion_delete.php', { motion_id: motionId });
    if (!body?.ok) return setMsg($('#modalMsg'),'error','Erreur suppression: ' + (body?.error || status));
    setMsg($('#modalMsg'),'success','Supprimée.');
    await load();
    closeModal();
  }

  async function loadMeetings() {
    // This project usually has /api/v1/meetings_list.php
    const { body } = await apiGet('/api/v1/meetings_list.php');
    const items = body?.meetings || body?.items || [];
    const sel = $('#meetingId');
    sel.innerHTML = '<option value="">Sélectionner une séance…</option>';
    for (const m of items) {
      const o=document.createElement('option');
      o.value=m.id;
      o.textContent = (m.title || 'Séance') + ' · ' + String(m.status || '');
      sel.appendChild(o);
    }
  }

  function bind() {
    $('#btnReload')?.addEventListener('click', load);
    $('#meetingId')?.addEventListener('change', load);
    $('#btnCreate')?.addEventListener('click', create);
    $('#q')?.addEventListener('input', render);

    $('#btnClose')?.addEventListener('click', closeModal);
    $('#modalBg')?.addEventListener('click', (e) => { if (e.target?.id === 'modalBg') closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    $('#btnSave')?.addEventListener('click', saveEdit);
    $('#btnDelete')?.addEventListener('click', del);
  }

  async function init() {
    bind();
    await loadMeetings();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
