/**
 * AG-VOTE Spinner Component
 *
 * Usage:
 *   <ag-spinner></ag-spinner>
 *   <ag-spinner size="lg"></ag-spinner>
 *   <ag-spinner variant="primary" label="Chargement..."></ag-spinner>
 *
 * Attributes:
 *   - size: Size variant (sm|md|lg)
 *   - variant: Color variant (default|primary|white)
 *   - label: Accessible label for screen readers
 */
class AgSpinner extends HTMLElement {
  static get observedAttributes() {
    return ['size', 'variant', 'label'];
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
    const size = this.getAttribute('size') || 'md';
    const variant = this.getAttribute('variant') || 'default';
    const label = this.getAttribute('label') || 'Chargement';

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: inline-flex;
          align-items: center;
          justify-content: center;
        }
        .spinner {
          border-radius: 50%;
          animation: spin 0.8s linear infinite;
        }

        /* Sizes */
        :host([size="sm"]) .spinner {
          width: 16px;
          height: 16px;
          border-width: 2px;
        }
        .spinner {
          width: 24px;
          height: 24px;
          border: 3px solid var(--color-border, #d5dbd2);
          border-top-color: var(--color-primary, #1650E0);
        }
        :host([size="lg"]) .spinner {
          width: 40px;
          height: 40px;
          border-width: 4px;
        }
        :host([size="xl"]) .spinner {
          width: 56px;
          height: 56px;
          border-width: 5px;
        }

        /* Variants */
        :host([variant="primary"]) .spinner {
          border-color: var(--color-primary-subtle, #e8edfa);
          border-top-color: var(--color-primary, #1650E0);
        }
        :host([variant="white"]) .spinner {
          border-color: rgba(255,255,255,0.3);
          border-top-color: white;
        }

        .sr-only {
          position: absolute;
          width: 1px;
          height: 1px;
          padding: 0;
          margin: -1px;
          overflow: hidden;
          clip: rect(0, 0, 0, 0);
          white-space: nowrap;
          border: 0;
        }

        @keyframes spin {
          to { transform: rotate(360deg); }
        }
      </style>
      <div class="spinner" role="status">
        <span class="sr-only">${this.escapeHtml(label)}</span>
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

customElements.define('ag-spinner', AgSpinner);

export default AgSpinner;
