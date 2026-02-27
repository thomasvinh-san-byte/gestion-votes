/**
 * AG-VOTE KPI Card Component
 *
 * Usage:
 *   <ag-kpi value="42" label="Présents" variant="success"></ag-kpi>
 *   <ag-kpi value="67%" label="Quorum" variant="primary" icon="users"></ag-kpi>
 *
 * Attributes:
 *   - value: The main value to display
 *   - label: Description text below the value
 *   - variant: Color variant (default|primary|success|warning|danger|info)
 *   - icon: Optional Lucide icon name
 *   - size: Size variant (sm|md|lg)
 */
class AgKpi extends HTMLElement {
  static get observedAttributes() {
    return ['value', 'label', 'variant', 'icon', 'size'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() {
    this.render();
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (oldValue !== newValue) {
      this.render();
    }
  }

  render() {
    const value = this.getAttribute('value') || '0';
    const label = this.getAttribute('label') || '';
    const variant = this.getAttribute('variant') || 'default';
    const icon = this.getAttribute('icon');
    const size = this.getAttribute('size') || 'md';

    const iconHtml = icon ? `
      <svg class="kpi-icon" aria-hidden="true">
        <use href="/assets/icons.svg#icon-${icon}"></use>
      </svg>
    ` : '';

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
        }
        .kpi {
          background: var(--color-surface, #ffffff);
          border: 1px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-lg, 16px);
          padding: 12px 8px;
          text-align: center;
          cursor: default;
          transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .kpi:hover {
          transform: translateY(-2px);
          box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,.06));
          border-color: color-mix(in srgb, var(--color-primary, #1650E0) 18%, var(--color-border, #d5dbd2));
        }
        .kpi-icon {
          width: 1.25rem;
          height: 1.25rem;
          margin: 0 auto 0.375rem;
          stroke: currentColor;
          stroke-width: 2;
          fill: none;
          opacity: 0.7;
        }
        .kpi-value {
          font-family: var(--font-mono, 'JetBrains Mono', monospace);
          font-size: 2rem;
          font-weight: 800;
          line-height: 1;
          letter-spacing: -0.02em;
          color: var(--color-text-dark, #1a1a1a);
        }
        .kpi-label {
          font-size: 13px;
          font-weight: 700;
          text-transform: uppercase;
          letter-spacing: .7px;
          color: var(--color-text-muted, #95a3a4);
          margin-top: 10px;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 6px;
        }

        /* Size variants */
        :host([size="sm"]) .kpi { padding: 8px 6px; }
        :host([size="sm"]) .kpi-value { font-size: 1.5rem; }
        :host([size="sm"]) .kpi-label { font-size: 11px; }

        :host([size="lg"]) .kpi { padding: 16px 12px; }
        :host([size="lg"]) .kpi-value { font-size: 2.5rem; }
        :host([size="lg"]) .kpi-label { font-size: 14px; }

        /* Color variants */
        :host([variant="primary"]) .kpi-value { color: var(--color-primary, #1650E0); }
        :host([variant="success"]) .kpi-value { color: var(--color-success, #0b7a40); }
        :host([variant="warning"]) .kpi-value { color: var(--color-warning, #b8860b); }
        :host([variant="danger"]) .kpi-value { color: var(--color-danger, #c42828); }
        :host([variant="info"]) .kpi-value { color: var(--color-info, #2563eb); }

        :host([variant="primary"]) .kpi-icon { color: var(--color-primary, #1650E0); }
        :host([variant="success"]) .kpi-icon { color: var(--color-success, #0b7a40); }
        :host([variant="warning"]) .kpi-icon { color: var(--color-warning, #b8860b); }
        :host([variant="danger"]) .kpi-icon { color: var(--color-danger, #c42828); }
        :host([variant="info"]) .kpi-icon { color: var(--color-info, #2563eb); }
      </style>
      <div class="kpi">
        ${iconHtml}
        <div class="kpi-value">${this.escapeHtml(value)}</div>
        <div class="kpi-label">${this.escapeHtml(label)}</div>
      </div>
    `;
  }

  escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
}

customElements.define('ag-kpi', AgKpi);

export default AgKpi;
