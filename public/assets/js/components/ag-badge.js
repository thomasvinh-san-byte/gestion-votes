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
          padding: 3px 10px;
          font-size: 12px;
          font-weight: 700;
          line-height: 1;
          border-radius: var(--radius-full, 9999px);
          white-space: nowrap;
          background: var(--tag-bg, #e8e7e2);
          color: var(--tag-text, #6b6b6b);
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
          padding: 2px 7px;
          font-size: 10px;
        }
        :host([size="sm"]) .badge-icon {
          width: 0.75rem;
          height: 0.75rem;
        }

        /* Color variants — Acte Officiel tags */
        :host([variant="primary"]) .badge,
        :host([variant="accent"]) .badge {
          background: var(--color-primary-subtle, #e8edfa);
          color: var(--color-primary, #1650E0);
        }
        :host([variant="success"]) .badge {
          background: var(--color-success-subtle, #e4ede4);
          color: var(--color-success, #0b7a40);
        }
        :host([variant="warning"]) .badge {
          background: var(--color-warning-subtle, #f5eddf);
          color: var(--color-warning, #b8860b);
        }
        :host([variant="danger"]) .badge {
          background: var(--color-danger-subtle, #f2e4e4);
          color: var(--color-danger, #c42828);
        }
        :host([variant="info"]) .badge {
          background: var(--color-info-subtle, #e0eef3);
          color: var(--color-info, #2563eb);
        }
        :host([variant="purple"]) .badge {
          background: var(--color-purple-subtle, #f0e8f8);
          color: var(--color-purple, #7c3aed);
        }
        :host([variant="live"]) .badge {
          background: var(--color-danger-subtle, #f2e4e4);
          color: var(--color-danger, #c42828);
        }
        :host([variant="draft"]) .badge {
          background: var(--tag-bg, #e8e7e2);
          color: var(--tag-text, #6b6b6b);
        }
        :host([variant="scheduled"]) .badge {
          background: var(--color-primary-subtle, #e8edfa);
          color: var(--color-primary, #1650E0);
        }
        :host([variant="closed"]) .badge {
          background: var(--color-success-subtle, #e4ede4);
          color: var(--color-success, #0b7a40);
        }
        :host([variant="validated"]) .badge {
          background: var(--color-primary-subtle, #e8edfa);
          color: var(--color-primary, #1650E0);
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
