/**
 * AG-VOTE Quorum Progress Bar Component
 *
 * Usage:
 *   <ag-quorum-bar current="45" required="50" total="100"></ag-quorum-bar>
 *   <ag-quorum-bar current="67" required="50" total="100" label="Quorum atteint"></ag-quorum-bar>
 *
 * Attributes:
 *   - current: Current value (present count or weight)
 *   - required: Required threshold value
 *   - total: Total possible value
 *   - label: Optional label (auto-generated if not provided)
 *   - show-values: Show numeric values (default: true)
 */
class AgQuorumBar extends HTMLElement {
  static get observedAttributes() {
    return ['current', 'required', 'total', 'label', 'show-values'];
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
    const current = parseFloat(this.getAttribute('current') || '0');
    const required = parseFloat(this.getAttribute('required') || '50');
    const total = parseFloat(this.getAttribute('total') || '100');
    const showValues = this.getAttribute('show-values') !== 'false';

    const percentage = total > 0 ? Math.min((current / total) * 100, 100) : 0;
    const thresholdPercent = total > 0 ? Math.min((required / total) * 100, 100) : 0;
    const isReached = current >= required;

    let status = 'critical';
    if (isReached) {
      status = 'reached';
    } else if (percentage >= thresholdPercent * 0.8) {
      status = 'partial';
    }

    const label = this.getAttribute('label') ||
      (isReached ? 'Quorum atteint' : `${Math.round(required - current)} manquants`);

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
        }
        .quorum {
          background: var(--color-surface, #ffffff);
          border: 1px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-lg, 16px);
          padding: 1rem;
        }
        .quorum-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 0.75rem;
        }
        .quorum-label {
          font-size: 0.875rem;
          font-weight: 600;
          color: var(--color-text, #4e5340);
        }
        .quorum-status {
          font-size: 0.75rem;
          font-weight: 600;
          padding: 0.125rem 0.5rem;
          border-radius: 9999px;
        }
        .quorum-status.reached {
          background: var(--color-success-subtle, #e4ede4);
          color: var(--color-success-text, #2d4a2e);
        }
        .quorum-status.partial {
          background: var(--color-warning-subtle, #f5eddf);
          color: var(--color-warning-text, #6b4f28);
        }
        .quorum-status.critical {
          background: var(--color-danger-subtle, #f2e4e4);
          color: var(--color-danger-text, #6b2828);
        }
        .quorum-track {
          height: 12px;
          background: var(--color-bg-subtle, #e2e8dd);
          border-radius: 9999px;
          overflow: hidden;
          position: relative;
        }
        .quorum-fill {
          height: 100%;
          border-radius: 9999px;
          transition: width 0.5s ease-out;
        }
        .quorum-fill.reached { background: var(--color-success, #16a34a); }
        .quorum-fill.partial { background: var(--color-warning, #b8915a); }
        .quorum-fill.critical { background: var(--color-danger, #a05252); }
        .quorum-threshold {
          position: absolute;
          top: 0;
          bottom: 0;
          width: 3px;
          background: var(--color-text, #4e5340);
          transform: translateX(-50%);
        }
        .quorum-threshold::after {
          content: '';
          position: absolute;
          top: -4px;
          left: 50%;
          transform: translateX(-50%);
          width: 0;
          height: 0;
          border-left: 4px solid transparent;
          border-right: 4px solid transparent;
          border-top: 4px solid var(--color-text, #4e5340);
        }
        .quorum-values {
          display: flex;
          justify-content: space-between;
          margin-top: 0.5rem;
          font-size: 0.75rem;
          color: var(--color-text-muted, #95a3a4);
        }
        .quorum-current {
          font-weight: 600;
        }
        .quorum-current.reached { color: var(--color-success, #16a34a); }
        .quorum-current.partial { color: var(--color-warning, #b8915a); }
        .quorum-current.critical { color: var(--color-danger, #a05252); }
      </style>
      <div class="quorum">
        <div class="quorum-header">
          <span class="quorum-label">Quorum</span>
          <span class="quorum-status ${status}">${this.escapeHtml(label)}</span>
        </div>
        <div class="quorum-track">
          <div class="quorum-fill ${status}" style="width: ${percentage}%"></div>
          <div class="quorum-threshold" style="left: ${thresholdPercent}%"></div>
        </div>
        ${showValues ? `
          <div class="quorum-values">
            <span class="quorum-current ${status}">${current} présents</span>
            <span>Requis: ${required} / ${total}</span>
          </div>
        ` : ''}
      </div>
    `;
  }

  escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
}

customElements.define('ag-quorum-bar', AgQuorumBar);

export default AgQuorumBar;
