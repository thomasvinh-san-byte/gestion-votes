/**
 * AG-VOTE Badge Component
 *
 * Usage:
 *   <ag-badge variant="success">Validé</ag-badge>
 *   <ag-badge variant="live" pulse>En cours</ag-badge>
 *   <ag-badge variant="draft" icon="edit">Brouillon</ag-badge>
 *
 * Attributes:
 *   - variant: Color variant (default|primary|success|warning|danger|info|live|draft)
 *   - icon: Optional Lucide icon name
 *   - pulse: Add pulsing animation (for live status)
 *   - size: Size variant (sm|md)
 */
class AgBadge extends HTMLElement {
  static get observedAttributes() {
    return ['variant', 'icon', 'pulse', 'size'];
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
    const variant = this.getAttribute('variant') || 'default';
    const icon = this.getAttribute('icon');
    const pulse = this.hasAttribute('pulse');
    const size = this.getAttribute('size') || 'md';

    const iconHtml = icon ? `
      <svg class="badge-icon" aria-hidden="true">
        <use href="/assets/icons.svg#icon-${icon}"></use>
      </svg>
    ` : '';

    const pulseHtml = pulse ? '<span class="pulse-dot"></span>' : '';

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: inline-flex;
        }
        .badge {
          display: inline-flex;
          align-items: center;
          gap: 0.375rem;
          padding: 0.25rem 0.625rem;
          font-size: 0.75rem;
          font-weight: 600;
          line-height: 1;
          border-radius: 9999px;
          white-space: nowrap;
          background: var(--color-bg-subtle, #e2e8dd);
          color: var(--color-text-secondary, #697268);
        }
        .badge-icon {
          width: 0.875rem;
          height: 0.875rem;
          stroke: currentColor;
          stroke-width: 2;
          fill: none;
        }
        .pulse-dot {
          width: 6px;
          height: 6px;
          border-radius: 50%;
          background: currentColor;
          animation: pulse 2s ease-in-out infinite;
        }

        /* Size variants */
        :host([size="sm"]) .badge {
          padding: 0.125rem 0.5rem;
          font-size: 0.625rem;
        }
        :host([size="sm"]) .badge-icon {
          width: 0.75rem;
          height: 0.75rem;
        }

        /* Color variants */
        :host([variant="primary"]) .badge {
          background: var(--color-primary-subtle, #eef1eb);
          color: var(--color-primary, #4e5340);
        }
        :host([variant="success"]) .badge {
          background: var(--color-success-subtle, #e4ede4);
          color: var(--color-success-text, #2d4a2e);
        }
        :host([variant="warning"]) .badge {
          background: var(--color-warning-subtle, #f5eddf);
          color: var(--color-warning-text, #6b4f28);
        }
        :host([variant="danger"]) .badge {
          background: var(--color-danger-subtle, #f2e4e4);
          color: var(--color-danger-text, #6b2828);
        }
        :host([variant="info"]) .badge {
          background: var(--color-info-subtle, #e0eef3);
          color: var(--color-info-text, #2a5565);
        }
        :host([variant="live"]) .badge {
          background: var(--color-danger-subtle, #f2e4e4);
          color: var(--color-danger, #a05252);
        }
        :host([variant="draft"]) .badge {
          background: var(--color-bg-subtle, #e2e8dd);
          color: var(--color-text-muted, #95a3a4);
        }
        :host([variant="scheduled"]) .badge {
          background: var(--color-info-subtle, #e0eef3);
          color: var(--color-info-text, #2a5565);
        }
        :host([variant="closed"]) .badge {
          background: var(--color-success-subtle, #e4ede4);
          color: var(--color-success-text, #2d4a2e);
        }
        :host([variant="validated"]) .badge {
          background: var(--color-primary-subtle, #eef1eb);
          color: var(--color-primary, #4e5340);
        }

        @keyframes pulse {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.5; }
        }
      </style>
      <span class="badge">
        ${pulseHtml}
        ${iconHtml}
        <slot></slot>
      </span>
    `;
  }
}

customElements.define('ag-badge', AgBadge);

export default AgBadge;
