/* public/assets/js/settings-modal.js
 * Settings modal — TSX-inspired (Option 1: keep operator-flow.js as orchestrator)
 * Uses existing endpoints:
 * - GET/POST /api/v1/meeting_quorum_settings.php
 * - GET/POST /api/v1/meeting_vote_settings.php
 * - GET/POST /api/v1/meeting_late_rules.php (requires operator)
 * - GET /api/v1/quorum_policies.php
 * - GET /api/v1/vote_policies.php
 */
(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);

  const modal = $('modalSettings');
  const btnOpen = $('btnOpenSettings');
  const btnClose = $('btnCloseSettings');
  const btnReload = $('btnSettingsReload');
  const btnSave = $('btnSettingsSave');

  const selQuorum = $('settingsQuorumPolicy');
  const selConvocation = $('settingsConvocation');
  const selVote = $('settingsVotePolicy');
  const chkLateQ = $('settingsLateQuorum');
  const chkLateV = $('settingsLateVote');

  const quorumDesc = $('settingsQuorumDesc');
  const voteDesc = $('settingsVoteDesc');
  const statusEl = $('settingsStatus');

  function apiKey() {
    return ($('opApiKey')?.value || '').trim();
  }
  function meetingId() {
    return ($('meetingSelect')?.value || '').trim();
  }

  function setStatus(text, kind) {
    if (!statusEl) return;
    statusEl.textContent = text || '—';
    statusEl.className = 'text-xs ' + (kind === 'ok'
      ? 'text-emerald-700'
      : kind === 'warn'
        ? 'text-amber-700'
        : kind === 'err'
          ? 'text-rose-700'
          : 'text-slate-600');
  }

  async function fetchJson(url) {
    if (!window.UI || !UI.fetchJson) {
      throw new Error('UI.fetchJson missing (utils.js)');
    }
    const k = apiKey();
    if (!k) throw new Error('missing_api_key');
    return UI.fetchJson(url, { apiKey: k });
  }

  async function postJson(url, body) {
    const k = apiKey();
    if (!k) throw new Error('missing_api_key');
    return UI.fetchJson(url, { apiKey: k, method: 'POST', body });
  }

  function openModal() {
    if (!modal) return;
    modal.classList.remove('hidden');
  }
  function closeModal() {
    if (!modal) return;
    modal.classList.add('hidden');
  }

  function fillSelect(select, items, placeholder) {
    if (!select) return;
    select.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = placeholder || '—';
    select.appendChild(opt0);

    for (const it of items) {
      const opt = document.createElement('option');
      opt.value = it.id;
      opt.textContent = it.name || it.id;
      opt.dataset.description = it.description || '';
      select.appendChild(opt);
    }
  }

  function updateDescs() {
    if (selQuorum && quorumDesc) {
      const opt = selQuorum.options[selQuorum.selectedIndex];
      quorumDesc.textContent = (opt && opt.dataset.description) ? opt.dataset.description : '—';
    }
    if (selVote && voteDesc) {
      const opt = selVote.options[selVote.selectedIndex];
      voteDesc.textContent = (opt && opt.dataset.description) ? opt.dataset.description : '—';
    }
  }

  async function loadAll() {
    const mid = meetingId();
    if (!mid) {
      setStatus('Sélectionne une séance.', 'warn');
      return;
    }

    try {
      setStatus('Chargement…');
      // Policies lists
      const [qp, vp] = await Promise.all([
        fetchJson(`/api/v1/quorum_policies.php`),
        fetchJson(`/api/v1/vote_policies.php`)
      ]);

      fillSelect(selQuorum, (qp.items || []), '— (aucune)');
      fillSelect(selVote, (vp.items || []), '— (aucune)');

      // Meeting settings
      const [mq, mv, lr] = await Promise.all([
        fetchJson(`/api/v1/meeting_quorum_settings.php?meeting_id=${encodeURIComponent(mid)}`),
        fetchJson(`/api/v1/meeting_vote_settings.php?meeting_id=${encodeURIComponent(mid)}`),
        fetchJson(`/api/v1/meeting_late_rules.php?meeting_id=${encodeURIComponent(mid)}`)
      ]);

      // Apply values
      if (selQuorum) selQuorum.value = (mq.quorum_policy_id || '') + '';
      if (selConvocation) selConvocation.value = (mq.convocation_no || 1) + '';
      if (selVote) selVote.value = (mv.vote_policy_id || '') + '';
      if (chkLateQ) chkLateQ.checked = !!lr.late_rule_quorum;
      if (chkLateV) chkLateV.checked = !!lr.late_rule_vote;

      updateDescs();
      setStatus('Paramètres chargés.', 'ok');
    } catch (e) {
      console.error(e);
      setStatus('Erreur de chargement des paramètres.', 'err');
      if (window.UI && UI.toast) UI.toast('Erreur chargement paramètres', 'danger');
    }
  }

  async function saveAll() {
    const mid = meetingId();
    if (!mid) {
      setStatus('Sélectionne une séance.', 'warn');
      return;
    }

    const quorum_policy_id = selQuorum ? selQuorum.value : '';
    const convocation_no = selConvocation ? parseInt(selConvocation.value || '1', 10) : 1;
    const vote_policy_id = selVote ? selVote.value : '';
    const late_rule_quorum = chkLateQ && chkLateQ.checked ? 1 : 0;
    const late_rule_vote = chkLateV && chkLateV.checked ? 1 : 0;

    try {
      setStatus('Enregistrement…');
      await postJson('/api/v1/meeting_quorum_settings.php', {
        meeting_id: mid,
        quorum_policy_id: quorum_policy_id,
        convocation_no: convocation_no
      });
      await postJson('/api/v1/meeting_vote_settings.php', {
        meeting_id: mid,
        vote_policy_id: vote_policy_id
      });
      await postJson('/api/v1/meeting_late_rules.php', {
        meeting_id: mid,
        late_rule_quorum: late_rule_quorum,
        late_rule_vote: late_rule_vote
      });

      setStatus('Enregistré.', 'ok');
      if (window.UI && UI.toast) UI.toast('Paramètres enregistrés', 'success');

      // Ask operator-flow to refresh its state if it exposes a hook; otherwise click refresh.
      const refreshBtn = document.getElementById('btnRefreshMain') || document.getElementById('btnRefresh');
      if (refreshBtn) refreshBtn.click();
    } catch (e) {
      console.error(e);
      setStatus('Erreur lors de l’enregistrement.', 'err');
      if (window.UI && UI.toast) UI.toast('Erreur enregistrement paramètres', 'danger');
    }
  }

  function bind() {
    if (!modal || !btnOpen || !btnClose || !btnSave || !btnReload) return;

    btnOpen.addEventListener('click', async () => {
      openModal();
      await loadAll();
    });

    btnClose.addEventListener('click', () => closeModal());

    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) closeModal();
    });

    btnReload.addEventListener('click', loadAll);
    btnSave.addEventListener('click', saveAll);

    if (selQuorum) selQuorum.addEventListener('change', updateDescs);
    if (selVote) selVote.addEventListener('change', updateDescs);

    // Esc to close
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
