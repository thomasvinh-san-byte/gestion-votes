/* global UI */
(function () {
  const $ = (id) => document.getElementById(id);

  const state = {
    apiKey: '',
    meetingId: null,
    refreshTimer: null,
  };

  function apiHeaders() {
    return { 'X-Api-Key': state.apiKey || '' };
  }

  async function loadMeetings() {
    const r = await UI.fetchJson('/api/v1/dashboard.php', { headers: apiHeaders() });
    const meetings = r.data.meetings || [];
    const sel = $('meetingSelect');
    sel.innerHTML = '';
    for (const m of meetings) {
      const opt = document.createElement('option');
      opt.value = m.id;
      opt.textContent = `${m.title} — ${m.status}`;
      sel.appendChild(opt);
    }
    if (!state.meetingId) {
      state.meetingId = r.data.suggested_meeting_id || (meetings[0] ? meetings[0].id : null);
    }
    if (state.meetingId) sel.value = state.meetingId;
  }

  function fmtTs(ts) {
    if (!ts) return '—';
    try {
      return new Date(ts).toLocaleString();
    } catch {
      return ts;
    }
  }

  function setText(id, v) { $(id).textContent = (v === null || v === undefined || v === '') ? '—' : String(v); }

  function fillDashboard(d) {
    const m = d.meeting || {};
    setText('meetingStatus', m.status || '—');
    setText('meetingMeta', `Prévue: ${fmtTs(m.scheduled_at)} • Démarrée: ${fmtTs(m.started_at)} • Terminée: ${fmtTs(m.ended_at)}`);

    setText('presentCount', `${d.attendance.present_count ?? 0} / ${d.attendance.eligible_count ?? '—'}`);
    setText('presentWeight', `Poids présent: ${d.attendance.present_weight ?? 0} • Poids total: ${d.attendance.eligible_weight ?? '—'}`);

    setText('proxyCount', d.proxies.count ?? 0);
    setText('proxyHint', `Actives: ${d.proxies.count ?? 0}`);

    const cm = d.current_motion || null;
    if (!cm) {
      $('motionState').textContent = 'Aucune';
      $('motionState').className = 'pill';
      setText('motionTitle', '—');
      setText('motionBody', '—');
      $('closeMotionBtn').disabled = true;
      $('ballotsCount').textContent = '0';
      $('votesFor').textContent = '0';
      $('votesAgainst').textContent = '0';
      $('votesAbstain').textContent = '0';
      setText('motionHint', 'Aucune motion ouverte.');
    } else {
      const open = !!cm.opened_at && !cm.closed_at;
      $('motionState').textContent = open ? 'Ouverte' : (cm.closed_at ? 'Fermée' : '—');
      $('motionState').className = 'pill ' + (open ? 'pill--ok' : 'pill--warn');
      setText('motionTitle', cm.title || '—');
      setText('motionBody', cm.body || cm.description || '—');
      $('closeMotionBtn').disabled = !open;

      const v = d.current_motion_votes || {};
      setText('ballotsCount', v.ballots_count ?? 0);
      setText('votesFor', v.weight_for ?? 0);
      setText('votesAgainst', v.weight_against ?? 0);
      setText('votesAbstain', v.weight_abstain ?? 0);
      setText('motionHint', `Ouverture: ${fmtTs(cm.opened_at)} • Fermeture: ${fmtTs(cm.closed_at)}`);
    }

    // motions open picker
    const list = d.openable_motions || [];
    const pick = $('motionToOpen');
    pick.innerHTML = '';
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = '— Choisir —';
    pick.appendChild(empty);
    for (const mo of list) {
      const opt = document.createElement('option');
      opt.value = mo.id;
      opt.textContent = mo.title;
      pick.appendChild(opt);
    }
    $('openMotionBtn').disabled = (list.length === 0);

    // ready to sign
    const ready = d.ready_to_sign || {};
    const can = !!ready.can;
    $('readyState').textContent = can ? 'Oui' : 'Non';
    $('readyState').className = 'pill ' + (can ? 'pill--ok' : 'pill--warn');
    $('blockingReasons').textContent = (ready.reasons && ready.reasons.length)
      ? ready.reasons.map((x) => `• ${x}`).join('\n')
      : (can ? 'Tout est prêt.' : '—');

    $('goTrustBtn').onclick = () => {
      const q = new URLSearchParams();
      if (state.apiKey) q.set('api_key', state.apiKey);
      window.location.href = '/trust.htmx.html' + (q.toString() ? ('?' + q.toString()) : '');
    };
    $('goReportBtn').onclick = () => {
      if (!state.meetingId) return;
      const q = new URLSearchParams({ meeting_id: state.meetingId });
      if (state.apiKey) q.set('api_key', state.apiKey);
      window.open('/api/v1/meeting_report.php?' + q.toString(), '_blank');
    };
  }

  async function refreshDashboard() {
    if (!state.meetingId) return;
    const q = new URLSearchParams({ meeting_id: state.meetingId });
    const r = await UI.fetchJson('/api/v1/dashboard.php?' + q.toString(), { headers: apiHeaders() });
    fillDashboard(r.data);
  }

  async function openMotion() {
    const motionId = $('motionToOpen').value;
    if (!motionId) {
      UI.toast('Motion', 'Sélectionnez une motion à ouvrir.', 'warning');
      return;
    }
    const payload = { meeting_id: state.meetingId, motion_id: motionId };
    await UI.fetchJson('/api/v1/motions_open.php', {
      method: 'POST',
      headers: { ...apiHeaders(), 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    UI.toast('Motion', 'Motion ouverte.', 'success');
    await refreshDashboard();
  }

  async function closeMotion() {
    const payload = { meeting_id: state.meetingId };
    await UI.fetchJson('/api/v1/motions_close.php', {
      method: 'POST',
      headers: { ...apiHeaders(), 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    UI.toast('Motion', 'Motion fermée.', 'success');
    await refreshDashboard();
  }

  function wire() {
    $('apiKey').addEventListener('change', async () => {
      state.apiKey = $('apiKey').value.trim();
      try {
        await loadMeetings();
        await refreshDashboard();
        UI.toast('Dashboard', 'Connecté.', 'success');
      } catch (e) {
        UI.toast('Dashboard', e.message || 'Erreur', 'error');
      }
    });

    $('meetingSelect').addEventListener('change', async () => {
      state.meetingId = $('meetingSelect').value || null;
      await refreshDashboard();
    });

    $('refreshBtn').addEventListener('click', async () => {
      await refreshDashboard();
    });

    $('openMotionBtn').addEventListener('click', async () => {
      try { await openMotion(); } catch (e) { UI.toast('Motion', e.message || 'Erreur', 'error'); }
    });

    $('closeMotionBtn').addEventListener('click', async () => {
      try { await closeMotion(); } catch (e) { UI.toast('Motion', e.message || 'Erreur', 'error'); }
    });

    // auto refresh
    state.refreshTimer = setInterval(() => {
      if (state.apiKey && state.meetingId) refreshDashboard().catch(() => {});
    }, 2000);
  }

  document.addEventListener('DOMContentLoaded', async () => {
    wire();
    // allow api_key in URL for convenience
    const u = new URL(window.location.href);
    const k = u.searchParams.get('api_key');
    if (k) {
      $('apiKey').value = k;
      state.apiKey = k;
      try {
        await loadMeetings();
        await refreshDashboard();
      } catch (e) {
        UI.toast('Dashboard', e.message || 'Erreur', 'error');
      }
    } else {
      // best-effort populate meetings even without key (will likely fail)
      try { await loadMeetings(); } catch (_) {}
    }
  });
})();