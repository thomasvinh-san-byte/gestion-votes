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
 *   - duration: Auto-dismiss duration in ms (default: 5000, 0 = no auto-dismiss)
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
    const duration = parseInt(this.getAttribute('duration') || '4200', 10);
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

    const iconMap = {
      success: 'check-circle',
      error: 'x-circle',
      warning: 'alert-triangle',
      info: 'info',
    };

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
          pointer-events: auto;
          animation: toastIn .22s cubic-bezier(.34, 1.1, .64, 1);
        }
        :host(.dismissing) {
          animation: toastOut .18s ease forwards;
        }
        .toast {
          display: flex;
          align-items: flex-start;
          gap: 10px;
          padding: 12px 16px;
          background: var(--color-surface-raised, #ffffff);
          border: 1px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-md, 12px);
          box-shadow: var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1));
          max-width: 340px;
          min-width: 240px;
          font-size: 13px;
          font-weight: 500;
          line-height: 1.45;
        }
        .toast-icon {
          width: 20px;
          height: 20px;
          border-radius: 50%;
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
          color: var(--color-text-muted, #95a3a4);
          font-size: 12px;
        }
        .toast-close {
          margin-left: auto;
          flex-shrink: 0;
          width: 22px;
          height: 22px;
          display: flex;
          align-items: center;
          justify-content: center;
          border-radius: 4px;
          cursor: pointer;
          color: var(--color-text-muted, #95a3a4);
          background: none;
          border: none;
          transition: background .15s ease;
        }
        .toast-close:hover {
          background: var(--color-bg-subtle, #e8e7e2);
          color: var(--color-text-dark, #1a1a1a);
        }
        .toast-close svg {
          width: 14px;
          height: 14px;
          stroke: currentColor;
          stroke-width: 2;
          fill: none;
        }

        /* Type variants — border-left + icon bg */
        :host([type="success"]) .toast { border-left: 3px solid var(--color-success, #0b7a40); }
        :host([type="success"]) .toast-icon { background: var(--color-success-subtle, #e4ede4); color: var(--color-success, #0b7a40); }
        :host([type="error"]) .toast { border-left: 3px solid var(--color-danger, #c42828); }
        :host([type="error"]) .toast-icon { background: var(--color-danger-subtle, #f2e4e4); color: var(--color-danger, #c42828); }
        :host([type="warning"]) .toast { border-left: 3px solid var(--color-warning, #b8860b); }
        :host([type="warning"]) .toast-icon { background: var(--color-warning-subtle, #f5eddf); color: var(--color-warning, #b8860b); }
        :host([type="info"]) .toast { border-left: 3px solid var(--color-primary, #1650E0); }
        :host([type="info"]) .toast-icon { background: var(--color-primary-subtle, #e8edfa); color: var(--color-primary, #1650E0); }

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
          <svg aria-hidden="true">
            <use href="/assets/icons.svg#icon-${iconMap[type] || 'info'}"></use>
          </svg>
        </span>
        <span class="toast-message">${this.escapeHtml(message)}</span>
        <button class="toast-close" aria-label="Fermer">
          <svg aria-hidden="true"><use href="/assets/icons.svg#icon-x"></use></svg>
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
   * @param {number} duration - Auto-dismiss duration (default 5000, 0 = no auto)
   */
  static show(type, message, duration = 4200) {
    let container = document.getElementById('ag-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'ag-toast-container';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-atomic', 'false');
      container.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 10100;
        display: flex;
        flex-direction: column;
        gap: 8px;
        pointer-events: none;
      `;
      document.body.appendChild(container);
    }

    // Limit to 3 toasts
    const existing = container.querySelectorAll('ag-toast');
    if (existing.length >= 3) {
      existing[0].remove();
    }

    const toast = document.createElement('ag-toast');
    toast.setAttribute('type', type);
    toast.setAttribute('message', message);
    toast.setAttribute('duration', String(duration));
    container.appendChild(toast);

    return toast;
  }
}

customElements.define('ag-toast', AgToast);

// Global shortcut
window.AgToast = AgToast;

export default AgToast;
