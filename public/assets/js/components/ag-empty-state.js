/**
 * AG-VOTE Empty State Component
 *
 * Declarative replacement for Shared.emptyState() in div-container contexts.
 * Uses light DOM (no shadow DOM) so design-system.css classes apply directly.
 *
 * Usage (attribute-only):
 *   <ag-empty-state
 *     icon="meetings"
 *     title="Aucune séance"
 *     description="Créez votre première séance pour commencer."
 *     action-label="Nouvelle séance"
 *     action-href="/wizard.htmx.html">
 *   </ag-empty-state>
 *
 * Usage (slotted action):
 *   <ag-empty-state icon="members" title="Aucun membre" description="...">
 *     <button slot="action" class="btn btn-primary btn-sm">Importer</button>
 *   </ag-empty-state>
 *
 * Icon values: meetings | members | votes | archives | generic
 */

// Inline SVG strings — duplicated from shared.js to keep component self-contained
// and avoid load-order issues (component must not depend on window.Shared).
var EMPTY_SVG = {
  meetings: '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><rect x="12" y="16" width="56" height="48" rx="6" stroke="currentColor" stroke-width="2" opacity=".3"/><rect x="20" y="26" width="24" height="4" rx="2" fill="currentColor" opacity=".2"/><rect x="20" y="34" width="40" height="4" rx="2" fill="currentColor" opacity=".15"/><rect x="20" y="42" width="32" height="4" rx="2" fill="currentColor" opacity=".1"/><circle cx="58" cy="54" r="14" stroke="currentColor" stroke-width="2" opacity=".25"/><path d="M58 48v12M52 54h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".4"/></svg>',
  members: '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><circle cx="32" cy="28" r="10" stroke="currentColor" stroke-width="2" opacity=".3"/><path d="M16 56c0-8.8 7.2-16 16-16s16 7.2 16 16" stroke="currentColor" stroke-width="2" opacity=".2" stroke-linecap="round"/><circle cx="54" cy="32" r="8" stroke="currentColor" stroke-width="2" opacity=".2"/><path d="M42 58c0-6.6 5.4-12 12-12s12 5.4 12 12" stroke="currentColor" stroke-width="2" opacity=".15" stroke-linecap="round"/></svg>',
  votes: '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><rect x="18" y="12" width="44" height="56" rx="6" stroke="currentColor" stroke-width="2" opacity=".3"/><path d="M30 36l6 6 14-14" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" opacity=".35"/><rect x="26" y="52" width="28" height="4" rx="2" fill="currentColor" opacity=".15"/><rect x="26" y="60" width="20" height="4" rx="2" fill="currentColor" opacity=".1"/></svg>',
  archives: '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><rect x="14" y="20" width="52" height="12" rx="4" stroke="currentColor" stroke-width="2" opacity=".3"/><path d="M20 32v28a4 4 0 004 4h32a4 4 0 004-4V32" stroke="currentColor" stroke-width="2" opacity=".2"/><rect x="30" y="40" width="20" height="8" rx="3" stroke="currentColor" stroke-width="2" opacity=".25"/></svg>',
  generic: '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="40" r="24" stroke="currentColor" stroke-width="2" opacity=".2"/><path d="M40 28v12M40 48h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round" opacity=".3"/></svg>'
};

function _esc(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

class AgEmptyState extends HTMLElement {
  static get observedAttributes() {
    return ['icon', 'title', 'description', 'action-label', 'action-href'];
  }

  connectedCallback() { this.render(); }

  attributeChangedCallback() {
    if (this.isConnected) this.render();
  }

  render() {
    var icon = this.getAttribute('icon') || 'generic';
    var title = this.getAttribute('title') || '';
    var desc = this.getAttribute('description') || '';
    var actionLabel = this.getAttribute('action-label');
    var actionHref = this.getAttribute('action-href');

    // Preserve slotted action child if present before overwriting innerHTML
    var slottedAction = this.querySelector('[slot="action"]');

    var svg = EMPTY_SVG[icon] || EMPTY_SVG.generic;
    var h = '<div class="empty-state animate-fade-in">';
    h += '<div class="empty-state-icon">' + svg + '</div>';
    h += '<div class="empty-state-title">' + _esc(title) + '</div>';
    if (desc) h += '<div class="empty-state-description">' + _esc(desc) + '</div>';
    if (actionLabel && actionHref) {
      h += '<a class="btn btn-secondary btn-sm" href="' + _esc(actionHref) + '" style="margin-top:12px;">' + _esc(actionLabel) + '</a>';
    }
    h += '</div>';

    this.innerHTML = h;

    // Re-attach preserved slotted action inside the rendered empty-state div
    if (slottedAction) this.querySelector('.empty-state').appendChild(slottedAction);
  }
}

customElements.define('ag-empty-state', AgEmptyState);
export default AgEmptyState;
