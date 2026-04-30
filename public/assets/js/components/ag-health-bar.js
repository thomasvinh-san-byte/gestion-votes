/**
 * AG-VOTE Operator Session Health Bar
 *
 * Self-contained custom element for the operator cockpit. Renders a two-tier
 * status bar : tier 1 (primary) carries decision-grade info (Quorum + Résolution
 * en cours), tier 2 (ambient) carries telemetry pills (état SSE + Votes restants).
 *
 * Usage :
 *   <ag-health-bar
 *     quorum-state="met"           (met | at-risk | missed)
 *     quorum-ratio="142 / 142"
 *     sse-state="live"             (live | reconnecting | offline)
 *     votes-remaining="23 / 142"
 *     motion-number="R-12"
 *     motion-title="Approbation des comptes 2024">
 *   </ag-health-bar>
 *
 * Attributes :
 *   - quorum-state    : "met" (atteint) | "at-risk" (à risque) | "missed" (non-atteint)
 *   - quorum-ratio    : libre (ex. "142 / 142")
 *   - sse-state       : "live" | "reconnecting" | "offline"
 *   - votes-remaining : libre (ex. "23 / 142")
 *   - motion-number   : libre (ex. "R-12")
 *   - motion-title    : libre — texte utilisateur (échappé HTML)
 *
 * Light DOM volontaire : la cascade laisse passer les tokens de design-system.css
 * et le stylesheet companion (ag-health-bar.css) cible directement les classes BEM.
 *
 * L'animation pulse "quorum-missed" est posée par le stylesheet sur
 * `#viewExec[data-quorum-state="missed"]`, pas sur le composant lui-même
 * (Plan 01.3 mirrore l'attribut sur la zone vote).
 */

function _esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

var SSE_LABELS = {
  live: 'En direct',
  reconnecting: 'Reconnexion…',
  offline: 'Hors ligne'
};

var QUORUM_STATES = ['met', 'at-risk', 'missed'];

class AgHealthBar extends HTMLElement {
  static get observedAttributes() {
    return ['quorum-state', 'quorum-ratio', 'sse-state', 'votes-remaining', 'motion-number', 'motion-title'];
  }

  connectedCallback() {
    this.classList.add('ag-health-bar');
    this.render();
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (oldValue !== newValue && this.isConnected) {
      this.render();
    }
  }

  render() {
    var quorumStateRaw = this.getAttribute('quorum-state') || 'met';
    var quorumState = QUORUM_STATES.indexOf(quorumStateRaw) === -1 ? 'met' : quorumStateRaw;
    var quorumRatio = this.getAttribute('quorum-ratio');
    if (quorumRatio == null || quorumRatio === '') quorumRatio = '—';

    var sseStateRaw = this.getAttribute('sse-state') || 'offline';
    var sseState = Object.prototype.hasOwnProperty.call(SSE_LABELS, sseStateRaw) ? sseStateRaw : 'offline';
    var sseLabel = SSE_LABELS[sseState];

    var votesRemaining = this.getAttribute('votes-remaining');
    if (votesRemaining == null || votesRemaining === '') votesRemaining = '—';

    var motionNumber = this.getAttribute('motion-number') || '';
    var motionTitle = this.getAttribute('motion-title');
    if (motionTitle == null || motionTitle === '') motionTitle = 'Aucune résolution active';

    var html = '';
    html += '<div class="ag-health-bar__primary">';
    html +=   '<div class="ag-health-bar__quorum" data-state="' + _esc(quorumState) + '">';
    html +=     '<span class="ag-health-bar__quorum-label">Quorum</span>';
    html +=     '<span class="ag-health-bar__quorum-ratio">' + _esc(quorumRatio) + '</span>';
    html +=   '</div>';
    html +=   '<div class="ag-health-bar__motion">';
    html +=     '<span class="ag-health-bar__motion-number">' + _esc(motionNumber) + '</span>';
    html +=     '<span class="ag-health-bar__motion-title">' + _esc(motionTitle) + '</span>';
    html +=   '</div>';
    html += '</div>';
    html += '<div class="ag-health-bar__ambient">';
    html +=   '<span class="ag-health-bar__sse" data-state="' + _esc(sseState) + '">' + _esc(sseLabel) + '</span>';
    html +=   '<span class="ag-health-bar__votes">Votes restants : ' + _esc(votesRemaining) + '</span>';
    html += '</div>';

    this.innerHTML = html;
  }
}

customElements.define('ag-health-bar', AgHealthBar);
