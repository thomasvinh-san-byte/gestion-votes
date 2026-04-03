/**
 * AG-VOTE Toast Notification Component
 *
 * Usage (programmatic):
 *   AgToast.show('success', 'Vote enregistré');
 *   AgToast.show('error', 'Erreur de connexion', 0); // No auto-dismiss
 *
 * Usage (declarative):
 *   <ag-toast type="success" message="Opération réussie"></ag-toast>
 *
 * Attributes:
 *   - type: Toast type (success|error|warning|info)
 *   - message: Message to display
 *   - duration: Auto-dismiss duration in ms (0 = no auto-dismiss)
 *              Defaults: success/info = 5000ms, warning/error = 8000ms
 */
class AgToast extends HTMLElement {
  static get observedAttributes() {
    return ['type', 'message', 'duration'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() {
    this.render();
    this.setupAutoDismiss();
  }

  disconnectedCallback() {
    if (this._dismissTimeout) {
      clearTimeout(this._dismissTimeout);
    }
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (oldValue !== newValue && this.shadowRoot.innerHTML) {
      this.render();
      if (name === 'duration') {
        this.setupAutoDismiss();
      }
    }
  }

  setupAutoDismiss() {
    const type = this.getAttribute('type') || 'info';
    const defaultDurations = { success: 5000, info: 5000, warning: 8000, error: 8000 };
    const duration = this.hasAttribute('duration')
      ? parseInt(this.getAttribute('duration'), 10)
      : (defaultDurations[type] || 5000);
    if (duration > 0) {
      this._dismissTimeout = setTimeout(() => this.dismiss(), duration);
    }
  }

  dismiss() {
    this.classList.add('dismissing');
    setTimeout(() => this.remove(), 300);
  }

  render() {
    const type = this.getAttribute('type') || 'info';
    const message = this.getAttribute('message') || '';

    // Inline SVG icons per type — no icon sprite dependency
    const iconMap = {
      success: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
      error: '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
      warning: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
      info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    };

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
          pointer-events: auto;
          animation: toastIn var(--duration-normal, 200ms) var(--ease-out, cubic-bezier(0, 0, 0.2, 1));
        }
        :host(.dismissing) {
          animation: toastOut var(--duration-deliberate, 300ms) ease forwards;
        }
        @media (prefers-reduced-motion: reduce) {
          :host, :host(.dismissing) { animation: none; }
        }
        .toast {
          display: flex;
          align-items: flex-start;
          gap: var(--space-3, 12px);
          padding: var(--space-3, 12px) var(--space-4, 16px);
          background: var(--color-surface-raised, #ffffff);
          border: 1px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-base, 8px);
          box-shadow: var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1));
          width: var(--toast-width, 356px);
          font-size: var(--text-sm, 0.875rem);
          font-weight: 500;
          line-height: 1.45;
        }
        .toast-icon {
          width: 20px;
          height: 20px;
          border-radius: var(--radius-full, 9999px);
          display: flex;
          align-items: center;
          justify-content: center;
          flex-shrink: 0;
          margin-top: 1px;
        }
        .toast-icon svg {
          width: 12px;
          height: 12px;
          stroke: currentColor;
          stroke-width: 2.5;
          fill: none;
        }
        .toast-message {
          flex: 1;
          color: var(--color-text, #1a1a1a);
          font-size: var(--text-xs, 0.75rem);
        }
        .toast-close {
          margin-left: auto;
          flex-shrink: 0;
          width: 22px;
          height: 22px;
          display: flex;
          align-items: center;
          justify-content: center;
          border-radius: var(--radius-base, 8px);
          cursor: pointer;
          color: var(--color-text-muted, #95a3a4);
          background: none;
          border: none;
          transition: background var(--duration-fast, 150ms) var(--ease-default, ease);
        }
        .toast-close:hover {
          background: var(--color-bg-subtle, #e8e7e2);
          color: var(--color-text-dark, #1a1a1a);
        }
        .toast-close:focus-visible {
          outline: none;
          box-shadow: var(--shadow-focus);
        }
        .toast-close svg {
          width: 14px;
          height: 14px;
          stroke: currentColor;
          stroke-width: 2;
          fill: none;
        }

        /* Type variants — inset box-shadow accent stripe + icon bg */
        :host([type="success"]) .toast {
          box-shadow: var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1)), inset var(--toast-accent-width, 3px) 0 0 var(--color-success, #0b7a40);
        }
        :host([type="success"]) .toast-icon { background: var(--color-success-subtle, #e4ede4); color: var(--color-success, #0b7a40); }
        :host([type="error"]) .toast {
          box-shadow: var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1)), inset var(--toast-accent-width, 3px) 0 0 var(--color-danger, #c42828);
        }
        :host([type="error"]) .toast-icon { background: var(--color-danger-subtle, #f2e4e4); color: var(--color-danger, #c42828); }
        :host([type="warning"]) .toast {
          box-shadow: var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1)), inset var(--toast-accent-width, 3px) 0 0 var(--color-warning, #b8860b);
        }
        :host([type="warning"]) .toast-icon { background: var(--color-warning-subtle, #f5eddf); color: var(--color-warning, #b8860b); }
        :host([type="info"]) .toast {
          box-shadow: var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1)), inset var(--toast-accent-width, 3px) 0 0 var(--color-info);
        }
        :host([type="info"]) .toast-icon { background: var(--color-info-subtle, #EBF0FF); color: var(--color-info); }

        @keyframes toastIn {
          from { opacity: 0; transform: translateX(20px) scale(.96); }
          to { opacity: 1; transform: none; }
        }
        @keyframes toastOut {
          from { opacity: 1; transform: none; }
          to { opacity: 0; transform: translateX(20px) scale(.96); }
        }
      </style>
      <div class="toast" role="alert" aria-live="polite">
        <span class="toast-icon">
          <svg viewBox="0 0 24 24" aria-hidden="true">${iconMap[type] || iconMap.info}</svg>
        </span>
        <span class="toast-message">${this.escapeHtml(message)}</span>
        <button class="toast-close" aria-label="Fermer">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
    `;

    this.shadowRoot.querySelector('.toast-close').addEventListener('click', () => this.dismiss());
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

  /**
   * Static method to show a toast programmatically
   * @param {string} type - 'success' | 'error' | 'warning' | 'info'
   * @param {string} message - Message to display
   * @param {number} [duration] - Auto-dismiss duration in ms (0 = no auto). Defaults: success/info=5000, warning/error=8000
   */
  static show(type, message, duration) {
    let container = document.getElementById('ag-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'ag-toast-container';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-atomic', 'false');
      container.style.cssText = `
        position: fixed;
        top: var(--space-5, 20px);
        right: var(--space-5, 20px);
        z-index: var(--z-toast, 10100);
        display: flex;
        flex-direction: column;
        gap: var(--space-2, 8px);
        pointer-events: none;
      `;
      document.body.appendChild(container);
    }

    // Limit to 3 toasts — remove oldest when 4th arrives
    const existing = container.querySelectorAll('ag-toast');
    if (existing.length >= 3) {
      existing[0].remove();
    }

    const toast = document.createElement('ag-toast');
    toast.setAttribute('type', type);
    toast.setAttribute('message', message);

    // Only set explicit duration attribute if caller passed a value.
    // Otherwise connectedCallback will apply type-based defaults.
    if (duration !== undefined) {
      toast.setAttribute('duration', String(duration));
    }

    container.appendChild(toast);

    return toast;
  }
}

customElements.define('ag-toast', AgToast);

// Global shortcut
window.AgToast = AgToast;

export default AgToast;
