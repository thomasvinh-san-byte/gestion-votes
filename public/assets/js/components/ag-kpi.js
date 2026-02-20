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
          padding: var(--space-5, 1.25rem);
          text-align: center;
          transition: transform 0.1s ease, box-shadow 0.1s ease;
        }
        .kpi:hover {
          transform: translateY(-2px);
          box-shadow: var(--shadow-md, 0 10px 15px -3px rgba(0,0,0,0.08));
        }
        .kpi-icon {
          width: 1.5rem;
          height: 1.5rem;
          margin: 0 auto 0.5rem;
          stroke: currentColor;
          stroke-width: 2;
          fill: none;
          opacity: 0.7;
        }
        .kpi-value {
          font-size: 2.25rem;
          font-weight: 800;
          line-height: 1;
          letter-spacing: -0.02em;
        }
        .kpi-label {
          font-size: 0.875rem;
          color: var(--color-text-secondary, #697268);
          margin-top: 0.5rem;
          font-weight: 500;
        }

        /* Size variants */
        :host([size="sm"]) .kpi { padding: var(--space-3, 0.75rem); }
        :host([size="sm"]) .kpi-value { font-size: 1.5rem; }
        :host([size="sm"]) .kpi-label { font-size: 0.75rem; }

        :host([size="lg"]) .kpi { padding: var(--space-6, 1.5rem); }
        :host([size="lg"]) .kpi-value { font-size: 3rem; }
        :host([size="lg"]) .kpi-label { font-size: 1rem; }

        /* Color variants */
        :host([variant="primary"]) .kpi-value { color: var(--color-primary, #4e5340); }
        :host([variant="success"]) .kpi-value { color: var(--color-success, #16a34a); }
        :host([variant="warning"]) .kpi-value { color: var(--color-warning, #b8915a); }
        :host([variant="danger"]) .kpi-value { color: var(--color-danger, #a05252); }
        :host([variant="info"]) .kpi-value { color: var(--color-info, #5a8a9a); }

        :host([variant="primary"]) .kpi-icon { color: var(--color-primary, #4e5340); }
        :host([variant="success"]) .kpi-icon { color: var(--color-success, #16a34a); }
        :host([variant="warning"]) .kpi-icon { color: var(--color-warning, #b8915a); }
        :host([variant="danger"]) .kpi-icon { color: var(--color-danger, #a05252); }
        :host([variant="info"]) .kpi-icon { color: var(--color-info, #5a8a9a); }
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
