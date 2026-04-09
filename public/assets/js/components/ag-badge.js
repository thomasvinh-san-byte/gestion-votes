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
    const icon = this.getAttribute('icon');
    const pulse = this.hasAttribute('pulse');

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
          gap: var(--space-1, 4px);
          padding: var(--space-1, 4px) var(--space-2, 8px);
          font-size: var(--text-xs, 0.75rem);
          font-weight: var(--font-medium, 500);
          line-height: 1;
          border-radius: var(--radius-badge, 9999px);
          white-space: nowrap;
          background: var(--color-bg-subtle);
          color: var(--color-text-muted);
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
          padding: 2px var(--space-1-5, 6px);
          font-size: var(--text-2xs, 0.625rem);
        }
        :host([size="sm"]) .badge-icon {
          width: 0.75rem;
          height: 0.75rem;
        }

        /* Color variants — Acte Officiel tags */
        :host([variant="primary"]) .badge,
        :host([variant="accent"]) .badge {
          background: var(--color-primary-subtle);
          color: var(--color-primary);
        }
        :host([variant="success"]) .badge {
          background: var(--color-success-subtle);
          color: var(--color-success);
        }
        :host([variant="warning"]) .badge {
          background: var(--color-warning-subtle);
          color: var(--color-warning);
        }
        :host([variant="danger"]) .badge {
          background: var(--color-danger-subtle);
          color: var(--color-danger);
        }
        :host([variant="info"]) .badge {
          background: var(--color-info-subtle);
          color: var(--color-info);
        }
        :host([variant="purple"]) .badge {
          background: var(--color-purple-subtle);
          color: var(--color-purple);
        }
        :host([variant="live"]) .badge {
          background: var(--color-danger-subtle);
          color: var(--color-danger);
        }
        :host([variant="draft"]) .badge {
          background: var(--color-bg-subtle);
          color: var(--color-text-muted);
        }
        :host([variant="warn"]) .badge {
          background: var(--color-warning-subtle);
          color: var(--color-warning);
        }
        :host([variant="scheduled"]) .badge {
          background: var(--color-primary-subtle);
          color: var(--color-primary);
        }
        :host([variant="closed"]) .badge {
          background: var(--color-success-subtle);
          color: var(--color-success);
        }
        :host([variant="validated"]) .badge {
          background: var(--color-primary-subtle);
          color: var(--color-primary);
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
