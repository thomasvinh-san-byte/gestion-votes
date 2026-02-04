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
    const duration = parseInt(this.getAttribute('duration') || '5000', 10);
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
          animation: slideIn 0.2s ease-out;
        }
        :host(.dismissing) {
          animation: slideOut 0.3s ease-out forwards;
        }
        .toast {
          display: flex;
          align-items: flex-start;
          gap: 0.75rem;
          padding: 0.75rem 1rem;
          background: var(--color-surface, #ffffff);
          border: 1px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-md, 12px);
          box-shadow: var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1));
          font-size: 0.875rem;
          line-height: 1.4;
        }
        .toast-icon {
          width: 1.25rem;
          height: 1.25rem;
          flex-shrink: 0;
          stroke: currentColor;
          stroke-width: 2;
          fill: none;
        }
        .toast-message {
          flex: 1;
          color: var(--color-text, #4e5340);
        }
        .toast-close {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 1.5rem;
          height: 1.5rem;
          padding: 0;
          border: none;
          background: transparent;
          cursor: pointer;
          border-radius: 4px;
          opacity: 0.6;
          transition: opacity 0.15s;
        }
        .toast-close:hover {
          opacity: 1;
          background: var(--color-bg-subtle, #e2e8dd);
        }
        .toast-close svg {
          width: 1rem;
          height: 1rem;
          stroke: currentColor;
          stroke-width: 2;
          fill: none;
        }

        /* Type variants */
        :host([type="success"]) .toast {
          border-left: 4px solid var(--color-success, #5a7a5b);
        }
        :host([type="success"]) .toast-icon {
          color: var(--color-success, #5a7a5b);
        }
        :host([type="error"]) .toast {
          border-left: 4px solid var(--color-danger, #a05252);
        }
        :host([type="error"]) .toast-icon {
          color: var(--color-danger, #a05252);
        }
        :host([type="warning"]) .toast {
          border-left: 4px solid var(--color-warning, #b8915a);
        }
        :host([type="warning"]) .toast-icon {
          color: var(--color-warning, #b8915a);
        }
        :host([type="info"]) .toast {
          border-left: 4px solid var(--color-info, #5a8a9a);
        }
        :host([type="info"]) .toast-icon {
          color: var(--color-info, #5a8a9a);
        }

        @keyframes slideIn {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        @keyframes slideOut {
          from {
            transform: translateX(0);
            opacity: 1;
          }
          to {
            transform: translateX(100%);
            opacity: 0;
          }
        }
      </style>
      <div class="toast" role="alert">
        <svg class="toast-icon" aria-hidden="true">
          <use href="/assets/icons.svg#icon-${iconMap[type] || 'info'}"></use>
        </svg>
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
      .replace(/"/g, '&quot;');
  }

  /**
   * Static method to show a toast programmatically
   * @param {string} type - 'success' | 'error' | 'warning' | 'info'
   * @param {string} message - Message to display
   * @param {number} duration - Auto-dismiss duration (default 5000, 0 = no auto)
   */
  static show(type, message, duration = 5000) {
    let container = document.getElementById('ag-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'ag-toast-container';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-atomic', 'false');
      container.style.cssText = `
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        pointer-events: none;
        max-width: 400px;
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
