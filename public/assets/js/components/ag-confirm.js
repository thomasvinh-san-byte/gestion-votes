/**
 * AG-VOTE Confirm Dialog Component (Promise-based)
 *
 * Usage:
 *   const ok = await AgConfirm.ask({
 *     title: 'Supprimer la séance ?',
 *     message: 'Cette action est irréversible.',
 *     confirmLabel: 'Supprimer',
 *     variant: 'danger'
 *   });
 *   if (ok) { ... }
 */
class AgConfirm extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this._resolve = null;
  }

  connectedCallback() { /* rendered by static method */ }

  _render(opts) {
    const { title, message, confirmLabel, cancelLabel, variant } = opts;

    // Inline SVG paths per variant — no icon sprite dependency for critical UI
    const variantColors = {
      danger: {
        bg: 'var(--color-danger-subtle, #f2e4e4)',
        color: 'var(--color-danger, #c42828)',
        svg: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
      },
      warning: {
        bg: 'var(--color-warning-subtle, #f5eddf)',
        color: 'var(--color-warning, #b8860b)',
        svg: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
      },
      info: {
        bg: 'var(--color-primary-subtle, #e8edfa)',
        color: 'var(--color-primary, #1650E0)',
        svg: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
      },
      success: {
        bg: 'var(--color-success-subtle, #e4ede4)',
        color: 'var(--color-success, #0b7a40)',
        svg: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
      },
    };

    // "warn" is an alias for "warning"
    variantColors.warn = variantColors.warning;

    const v = variantColors[variant] || variantColors.info;

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: contents; }
        .overlay-backdrop {
          position: fixed; inset: 0;
          background: rgba(0,0,0,.45);
          display: flex; align-items: center; justify-content: center;
          z-index: 10200; padding: 16px;
          backdrop-filter: blur(3px);
          animation: fadeIn var(--duration-fast, 150ms) ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal {
          width: min(420px, 100%);
          background: var(--color-surface-raised, #fff);
          border: 1px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-lg, 16px);
          box-shadow: var(--shadow-lg);
          overflow: hidden;
          animation: modalIn var(--duration-fast, 150ms) cubic-bezier(.34,1.2,.64,1);
        }
        @keyframes modalIn {
          from { opacity: 0; transform: scale(.96) translateY(6px); }
          to { opacity: 1; transform: none; }
        }
        .confirm-dialog { padding: 24px 24px 8px; text-align: center; }
        .confirm-icon-wrap {
          width: 56px; height: 56px; border-radius: 50%;
          display: flex; align-items: center; justify-content: center;
          margin: 0 auto 14px;
          background: ${v.bg};
        }
        .confirm-icon-wrap svg {
          width: 24px; height: 24px; stroke: ${v.color}; stroke-width: 2; fill: none;
        }
        .confirm-title {
          font-family: var(--font-display, 'Fraunces', serif);
          font-size: 18px; font-weight: 700;
          color: var(--color-text-dark, #1a1a1a);
          margin-bottom: 8px;
        }
        .confirm-msg {
          font-size: 14px; color: var(--color-text-muted, #95a3a4);
          line-height: 1.6;
        }
        .modal-f {
          padding: 14px 24px;
          display: flex; justify-content: center; gap: 8px;
        }
        .btn {
          padding: 8px 18px; border-radius: var(--radius, 0.5rem); font-size: 13px; font-weight: 600;
          cursor: pointer; border: 1.5px solid var(--color-border, #d5dbd2);
          background: var(--color-surface, #fff); color: var(--color-text-dark, #1a1a1a);
          transition: background var(--duration-fast, 150ms) ease, border-color var(--duration-fast, 150ms) ease;
        }
        .btn:hover { background: var(--color-bg-subtle, #e8e7e2); }
        .btn-confirm {
          background: ${v.color}; border-color: ${v.color}; color: #fff;
        }
        .btn-confirm:hover { opacity: .9; background: ${v.color}; }
        .btn:focus-visible { outline: 2px solid var(--color-primary, #1650E0); outline-offset: 2px; }
      </style>
      <div class="overlay-backdrop">
        <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="cd-title" aria-describedby="cd-msg">
          <div class="confirm-dialog">
            <div class="confirm-icon-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true">${v.svg}</svg>
            </div>
            <div class="confirm-title" id="cd-title">${this._esc(title)}</div>
            <div class="confirm-msg" id="cd-msg">${this._esc(message)}</div>
          </div>
          <div class="modal-f">
            <button class="btn btn-cancel">${this._esc(cancelLabel)}</button>
            <button class="btn btn-confirm">${this._esc(confirmLabel)}</button>
          </div>
        </div>
      </div>
    `;

    this.shadowRoot.querySelector('.btn-cancel').addEventListener('click', () => this._finish(false));
    this.shadowRoot.querySelector('.btn-confirm').addEventListener('click', () => this._finish(true));
    this.shadowRoot.querySelector('.overlay-backdrop').addEventListener('click', (e) => {
      if (e.target.classList.contains('overlay-backdrop')) this._finish(false);
    });

    this._keyHandler = (e) => {
      if (e.key === 'Escape') this._finish(false);
      if (e.key === 'Tab') {
        const focusable = [...this.shadowRoot.querySelectorAll('button')];
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === this && this.shadowRoot.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && this.shadowRoot.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };
    document.addEventListener('keydown', this._keyHandler);

    this.shadowRoot.querySelector('.btn-confirm').focus();
  }

  _finish(result) {
    document.removeEventListener('keydown', this._keyHandler);
    this.remove();
    if (this._resolve) this._resolve(result);
  }

  _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  static ask(opts = {}) {
    const dialog = document.createElement('ag-confirm');
    document.body.appendChild(dialog);
    return new Promise(resolve => {
      dialog._resolve = resolve;
      dialog._render({
        title: opts.title || 'Confirmer',
        message: opts.message || '',
        confirmLabel: opts.confirmLabel || 'Confirmer',
        cancelLabel: opts.cancelLabel || 'Annuler',
        variant: opts.variant || 'info',
      });
    });
  }
}

customElements.define('ag-confirm', AgConfirm);
window.AgConfirm = AgConfirm;
export default AgConfirm;
