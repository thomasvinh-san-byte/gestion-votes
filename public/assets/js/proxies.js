// public/assets/js/proxies.js
(function () {
  const $ = (id) => document.getElementById(id);

  const meetingLine = $('meeting_line');
  const rows = $('rows');
  const pCount = $('p_count');
  const lastAction = $('last_action');

  let meetingId = null;
  let members = [];
  let proxyMap = new Map(); // giver_id -> receiver_id

  function toast(t,d,type){ if(window.UI&&UI.toast){ UI.toast(t,d,type); } }

  async function fetchJson(url, options = {}) {
    const resp = await fetch(url, {
      headers: { 'Accept': 'application/json', ...(options.headers || {}) },
      ...options,
    });
    if (!resp.ok) {
      const txt = await resp.text();
      throw new Error(`HTTP ${resp.status} – ${txt}`);
    }
    return resp.json();
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[c]));
  }

  async function loadMeeting() {
    const data = await fetchJson('/api/v1/meeting_status.php');
    const d = data.data || data;
    meetingId = d.meeting_id || null;
    if (!meetingId) {
      meetingLine.textContent = 'Aucune séance active.';
      return;
    }
    meetingLine.textContent = `Séance: ${d.title || '—'} — statut: ${d.status || '—'}`;
  }

  async function loadMembers() {
    const data = await fetchJson('/api/v1/members.php');
    const d = data.data || data;
    members = d.members || [];
  }

  async function loadProxies() {
    if (!meetingId) return;
    const data = await fetchJson(`/api/v1/proxies.php?meeting_id=${encodeURIComponent(meetingId)}`);
    const d = data.data || data;
    const list = d.proxies || [];
    proxyMap = new Map();
    for (const p of list) {
      proxyMap.set(p.giver_member_id, p.receiver_member_id);
    }
    pCount.textContent = String(d.count ?? list.length ?? 0);
  }

  function render() {
    rows.innerHTML = '';
    for (const giver of members) {
      const receiverId = proxyMap.get(giver.id) || '';

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(giver.full_name || '—')}</td>
        <td>
          <select class="sel" data-giver="${giver.id}">
            <option value="">— Aucun —</option>
            ${members
              .filter((m) => m.id !== giver.id)
              .map((m) => `<option value="${m.id}" ${m.id === receiverId ? 'selected' : ''}>${escapeHtml(m.full_name || '—')}</option>`)
              .join('')}
          </select>
        </td>
        <td><span class="pill">${receiverId ? 'actif' : '—'}</span></td>
      `;
      rows.appendChild(tr);
    }
  }

  async function upsertProxy(giverId, receiverId) {
    if (!meetingId) return;
    lastAction.textContent = 'Envoi…';
    try {
      await fetchJson('/api/v1/proxies_upsert.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          meeting_id: meetingId,
          giver_member_id: giverId,
          receiver_member_id: receiverId || '',
          scope: 'full',
        }),
      });
      lastAction.textContent = receiverId ? 'OK: procuration mise à jour' : 'OK: procuration révoquée';
      toast('Procurations', receiverId ? 'Procuration mise à jour.' : 'Procuration révoquée.', 'ok');
      await loadProxies();
      render();
    } catch (e) {
      lastAction.textContent = String(e.message || e);
      toast('Erreur', String(e.message || e), 'err');
    }
  }

  function wire() {
    rows.addEventListener('change', (ev) => {
      const sel = ev.target.closest('select[data-giver]');
      if (!sel) return;
      const giverId = sel.getAttribute('data-giver');
      const receiverId = sel.value;
      upsertProxy(giverId, receiverId);
    });
  }

  async function main() {
    wire();
    await loadMeeting();
    await loadMembers();
    await loadProxies();
    render();
    setInterval(async () => {
      try {
        await loadMeeting();
        await loadProxies();
        render();
      } catch (_) {}
    }, 3000);
  }

  main().catch((e) => {
    lastAction.textContent = String(e.message || e);
      toast('Erreur', String(e.message || e), 'err');
  });
})();