function toast(t,d,type){ if(window.UI&&UI.toast){ UI.toast(t,d,type); } }
/* public/assets/js/report.js */

async function apiGet(path, apiKey) {
  try {
    if (window.UI && UI.fetchJson) {
      return await UI.fetchJson(path, { headers: apiKey ? { 'X-API-Key': apiKey } : {} });
    }
    const res = await fetch(path, { headers: apiKey ? { 'X-API-Key': apiKey } : {} });
    if (!res.ok) {
      const txt = await res.text().catch(() => '');
      throw new Error(`HTTP ${res.status} ${txt}`);
    }
    return res.json();
  } catch (e) {
    throw e;
  }
}

function el(id) { return document.getElementById(id); }

async function loadMeetings() {
  const select = el('meetingSelect');
  const key = el('apiKey').value.trim();
  select.innerHTML = '';

  // On s'appuie sur meetings_archive (déjà présent) pour lister.
  const data = await apiGet('/api/v1/meetings_archive.php', key);
  const meetings = (data.meetings || []);

  if (meetings.length === 0) {
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = 'Aucune séance';
    select.appendChild(opt);
    return;
  }

  for (const m of meetings) {
    const opt = document.createElement('option');
    opt.value = m.id;
    opt.textContent = `${m.title} — ${m.status} — ${m.created_at}`;
    select.appendChild(opt);
  }
}

function openReport() {
  const meetingId = el('meetingSelect').value;
  const key = el('apiKey').value.trim();
  const frame = el('reportFrame');
  const printBtn = el('printBtn');

  if (!meetingId) {
    alert('Sélectionnez une séance.');
    return;
  }

  // Pour un iframe, on ne peut pas facilement injecter des headers.
  // MVP: on passe la clé en query (acceptable en DEV) ; ou vous ouvrez le PV dans un nouvel onglet avec un proxy.
  const url = `/api/v1/meeting_report.php?meeting_id=${encodeURIComponent(meetingId)}&api_key=${encodeURIComponent(key)}`;
  frame.src = url;
  toast('PV', 'Chargement du PV…', 'ok');
  printBtn.disabled = false;
}

function printFrame() {
  const frame = el('reportFrame');
  try {
    frame.contentWindow.focus();
    frame.contentWindow.print();
  } catch (e) {
    alert('Impossible d\'imprimer via iframe (cross-origin / bloqueur). Utilisez le bouton Imprimer dans le PV.');
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  el('openBtn').addEventListener('click', openReport);
  el('printBtn').addEventListener('click', printFrame);

  // Valeur par défaut cohérente avec config/config.php
  if (!el('apiKey').value) {
    el('apiKey').value = 'dev-trust-key';
  }

  try {
    await loadMeetings();
  } catch (e) {
    console.error(e);
    toast('Erreur', String(e.message||e), 'err');
    alert('Impossible de charger la liste des séances (vérifiez la clé API).');
  }
});