// public/assets/js/evote.js

async function fetchJson(url, options = {}) {
  const resp = await fetch(url, {
    headers: {
      'Accept': 'application/json',
      ...(options.headers || {})
    },
    ...options
  });

  if (!resp.ok) {
    const txt = await resp.text();
    throw new Error(`HTTP ${resp.status} – ${txt}`);
  }

  return resp.json();
}

/**
 * Met à jour l'affichage d'une motion à partir du résultat API.
 */
function renderMotionResult(root, data) {
  const d = data.data || data; // suivant comment json_ok encapsule la réponse

  const tallies = d.tallies || {};
  const quorum = d.quorum || {};
  const majority = d.majority || {};
  const decision = d.decision || {};

  const setText = (selector, value) => {
    const el = root.querySelector(selector);
    if (el) el.textContent = value;
  };

  // Décision globale
  setText('[data-role="decision-status"]', decision.status || '-');
  setText('[data-role="decision-reason"]', decision.reason || '');

  // Votes par valeur
  const vals = ['for', 'against', 'abstain', 'nsp'];
  vals.forEach(v => {
    const t = tallies[v] || { count: 0, weight: 0 };
    setText(`[data-role="${v}-count"]`, t.count ?? 0);
    setText(`[data-role="${v}-weight"]`, (t.weight ?? 0).toString());
  });

  // Quorum
  if (quorum.applied) {
    setText('[data-role="quorum-status"]', quorum.met ? 'atteint' : 'non atteint');
    setText('[data-role="quorum-ratio"]', (quorum.ratio ?? 0).toFixed(2));
    setText('[data-role="quorum-threshold"]', (quorum.threshold ?? 0).toFixed(2));
  } else {
    setText('[data-role="quorum-status"]', 'non défini');
    setText('[data-role="quorum-ratio"]', '-');
    setText('[data-role="quorum-threshold"]', '-');
  }

  // Tu peux ajouter l'affichage des bases éligibles / exprimés si tu veux
}

/**
 * Charge le résultat d'une motion et met à jour le DOM.
 */
async function loadMotionResult(motionId, root) {
  try {
    const data = await fetchJson(`/api/v1/ballots_result.php?motion_id=${encodeURIComponent(motionId)}`);
    renderMotionResult(root, data);
  } catch (err) {
    console.error('Erreur loadMotionResult', motionId, err);
  }
}

/**
 * Rafraîchit toutes les motions présentes dans la page.
 */
function refreshAllMotionResults() {
  document.querySelectorAll('[data-motion-id]').forEach(card => {
    const motionId = card.getAttribute('data-motion-id');
    if (!motionId) return;
    loadMotionResult(motionId, card);
  });
}

// Expose global pour pouvoir appeler depuis d'autres scripts si besoin
window.evoteRefreshAll = refreshAllMotionResults;

document.addEventListener('DOMContentLoaded', () => {
  // Premier chargement
  refreshAllMotionResults();

  // Rafraîchissement automatique toutes les 5 secondes
  setInterval(refreshAllMotionResults, 5000);
});

