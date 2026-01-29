/* public/assets/js/exports-modal.js — Exports robustes (PV + CSV)
   - Remplace la génération CSV côté client par des endpoints serveur fiables
   - Compatible operator_flow.htmx.html et operator.htmx.html
*/
(function () {
  const $ = (sel) => document.querySelector(sel);

  function apiKey() {
    const el = $('#opApiKey');
    if (el) return (el.value || '').trim();
    try { return (new URLSearchParams(location.search)).get('meeting_id') || ''; } catch(e) { return ''; }
  }
  function meetingId() {
    const el = $('#meetingSelect');
    if (el) return (el.value || '').trim();
    try { return (new URLSearchParams(location.search)).get('meeting_id') || ''; } catch(e) { return ''; }
  }

  function toast(kind, msg) {
    if (window.UI && typeof window.UI.toast === 'function') return window.UI.toast(kind, msg);
    const box = document.getElementById('notif_box');
    if (!box) return alert(msg);
    box.classList.remove('hidden');
    box.textContent = msg;
    setTimeout(() => box.classList.add('hidden'), 2500);
  }

  async function downloadFrom(url, filenameHint) {
    const headers = {};
    const k = apiKey();
    if (k) headers['X-Api-Key'] = k;

    const res = await fetch(url, { headers, credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);

    const blob = await res.blob();
    const cd = res.headers.get('Content-Disposition') || '';
    const m = /filename="([^"]+)"/.exec(cd);
    const filename = (m && m[1]) ? m[1] : filenameHint;

    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 250);
  }

  function openPV() {
    const mid = meetingId();
    if (!mid) return toast('error', 'Sélectionnez une séance.');
    // On ouvre une page HTML serveur (MeetingReportService)
    const url = `/api/v1/export_pv_html.php?meeting_id=${encodeURIComponent(mid)}`;
    window.open(url, '_blank', 'noopener,noreferrer');
  }

  async function exportAttendance() {
    const mid = meetingId();
    if (!mid) return toast('error', 'Sélectionnez une séance.');
    try {
      await downloadFrom(`/api/v1/export_attendance_csv.php?meeting_id=${encodeURIComponent(mid)}`, `presence_${mid}.csv`);
      toast('success', 'CSV émargement exporté.');
    } catch (e) {
      toast('error', 'Export présence impossible.');
    }
  }

  async function exportVotes() {
    const mid = meetingId();
    if (!mid) return toast('error', 'Sélectionnez une séance.');
    try {
      await downloadFrom(`/api/v1/export_votes_csv.php?meeting_id=${encodeURIComponent(mid)}`, `votes_${mid}.csv`);
      toast('success', 'CSV votes exporté.');
    } catch (e) {
      toast('error', 'Export votes impossible.');
    }
  }

  function showModal() {
    const m = document.getElementById('modalExports');
    if (!m) return;
    m.style.display = 'block';
  }
  function hideModal() {
    const m = document.getElementById('modalExports');
    if (!m) return;
    m.style.display = 'none';
  }

  function bind() {
    // Button IDs vary slightly depending on page; support both.
    (document.getElementById('btnExports') || document.getElementById('btnOpenExports'))?.addEventListener('click', showModal);
    document.getElementById('btnCloseExports')?.addEventListener('click', hideModal);
    document.getElementById('modalExportsBackdrop')?.addEventListener('click', hideModal);

    document.getElementById('btnExportPV')?.addEventListener('click', openPV);
    document.getElementById('btnExportAttendance')?.addEventListener('click', exportAttendance);
    document.getElementById('btnExportVotes')?.addEventListener('click', exportVotes);
    document.getElementById('btnExportMotions')?.addEventListener('click', exportMotions);
    document.getElementById('btnExportMembers')?.addEventListener('click', exportMembers);
    document.getElementById('btnExportBallotsAudit')?.addEventListener('click', exportBallotsAudit);

    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideModal(); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
