(function(){
  const $ = (sel) => document.querySelector(sel);

  function meetingId(){
    try { return (window.Utils && Utils.getMeetingId) ? Utils.getMeetingId() : (new URLSearchParams(location.search).get('meeting_id') || ''); }
    catch(_) { return ''; }
  }

  function apiKey(){
    return (($('#presidentApiKey')?.value) || '').trim();
  }

  function setBadge(kind, text){
    const el = $('#signBadge');
    if (!el) return;
    const base = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-bold ';
    const cls = kind === 'ok'
      ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
      : (kind === 'warn'
        ? 'bg-amber-50 text-amber-800 border border-amber-200'
        : 'bg-rose-50 text-rose-800 border border-rose-200');
    el.className = base + cls;
    el.textContent = text;
  }

  function renderReasons(reasons){
    const ul = $('#checklistReasons');
    if (!ul) return;
    ul.innerHTML = '';
    if (!Array.isArray(reasons) || reasons.length === 0) {
      const li = document.createElement('li');
      li.className = 'text-sm text-emerald-700 font-semibold';
      li.textContent = 'Aucun blocage détecté.';
      ul.appendChild(li);
      return;
    }
    for (const r of reasons) {
      const li = document.createElement('li');
      li.className = 'text-sm text-slate-800 flex gap-2';
      li.innerHTML = `<span class="mt-0.5">⚠️</span><span>${Utils.escapeHtml(String(r))}</span>`;
      ul.appendChild(li);
    }
  }

  function renderInvalid(list){
    const box = $('#invalidBallotsList');
    if (!box) return;
    box.innerHTML = '';
    if (!Array.isArray(list) || list.length === 0) {
      const div = document.createElement('div');
      div.className = 'text-sm text-emerald-700 font-semibold';
      div.textContent = 'Rien à signaler.';
      box.appendChild(div);
      return;
    }
    for (const it of list) {
      const div = document.createElement('div');
      div.className = 'rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900';
      div.innerHTML = `<div class="font-bold">${Utils.escapeHtml(it.title || 'Motion')}</div><div class="text-xs mt-1">${Utils.escapeHtml(it.detail || '')}</div>`;
      box.appendChild(div);
    }
  }

  async function loadStatus(){
    const mid = meetingId();
    if (!mid) {
      $('#meetingTitle').textContent = 'Séance — (meeting_id manquant)';
      setBadge('bad', 'MEETING_ID');
      $('#signMessage').textContent = 'Ajoute ?meeting_id=... dans l’URL.';
      return;
    }
    const key = apiKey();
    const headers = key ? { 'X-Api-Key': key } : {};

    const s = await UI.fetchJson(`/api/v1/meeting_status_for_meeting.php?meeting_id=${encodeURIComponent(mid)}`, { headers });
    const d = s.data || {};
    $('#meetingTitle').textContent = d.meeting_title ? `Séance: ${d.meeting_title}` : 'Séance';
    if ($('#presidentName') && (!$('#presidentName').value || $('#presidentName').value.trim() === '')) {
      $('#presidentName').value = (d.president_name || '').toString();
    }
    if (d.sign_status === 'validated') {
      setBadge('ok', 'VALIDÉ');
    } else if (d.ready_to_sign) {
      setBadge('ok', 'PRÊT');
    } else {
      setBadge('warn', 'À PRÉPARER');
    }
    $('#signMessage').textContent = (d.sign_message || '').toString();

    const rc = await UI.fetchJson(`/api/v1/meeting_ready_check.php?meeting_id=${encodeURIComponent(mid)}`, { headers });
    const rr = rc.data || {};
    renderReasons(rr.reasons || []);
    renderInvalid(rr.bad_ballots || rr.bad || []);

    const canValidate = !!d.ready_to_sign && d.sign_status !== 'validated';
    const btn = $('#validateBtn');
    if (btn) btn.disabled = !canValidate;
  }

  async function validate(){
    const mid = meetingId();
    if (!mid) return;
    const name = ($('#presidentName')?.value || '').trim();
    if (!name) {
      UI.toast('Président', 'Renseigne le nom du président.', 'warning');
      return;
    }
    const key = apiKey();
    const headers = { 'Content-Type': 'application/json', ...(key ? {'X-Api-Key': key} : {}) };
    await UI.fetchJson('/api/v1/meeting_validate.php', {
      method: 'POST',
      headers,
      body: JSON.stringify({ meeting_id: mid, president_name: name })
    });
    UI.toast('Validation', 'Séance validée.', 'success');
    await loadStatus();
  }

  function wire(){
    // Persist API key like trust
    const storageKey = 'president.api_key';
    const saved = (localStorage.getItem(storageKey) || '').trim();
    if ($('#presidentApiKey') && saved) $('#presidentApiKey').value = saved;

    $('#presidentApiKey')?.addEventListener('change', async () => {
      localStorage.setItem(storageKey, apiKey());
      await loadStatus();
    });
    $('#refreshPresidentBtn')?.addEventListener('click', loadStatus);
    $('#validateBtn')?.addEventListener('click', async () => {
      if (!confirm('Confirmer la validation / archivage ?')) return;
      try { await validate(); } catch(e){ UI.toast('Validation', e.message || 'Erreur', 'error'); }
    });

    loadStatus().catch(()=>{});
    setInterval(() => { if (!document.hidden) loadStatus().catch(()=>{}); }, 2500);
  }

  document.addEventListener('DOMContentLoaded', wire);
})();
