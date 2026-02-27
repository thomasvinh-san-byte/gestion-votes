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
    const variantColors = {
      danger: { bg: 'var(--color-danger-subtle, #f2e4e4)', color: 'var(--color-danger, #c42828)', icon: 'alert-triangle' },
      warning: { bg: 'var(--color-warning-subtle, #f5eddf)', color: 'var(--color-warning, #b8860b)', icon: 'alert-triangle' },
      success: { bg: 'var(--color-success-subtle, #e4ede4)', color: 'var(--color-success, #0b7a40)', icon: 'check-circle' },
      info: { bg: 'var(--color-primary-subtle, #e8edfa)', color: 'var(--color-primary, #1650E0)', icon: 'info' },
    };
    const v = variantColors[variant] || variantColors.info;

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: contents; }
        .overlay-backdrop {
          position: fixed; inset: 0;
          background: rgba(0,0,0,.35);
          display: flex; align-items: center; justify-content: center;
          z-index: 10200; padding: 16px;
          backdrop-filter: blur(3px);
          animation: fadeIn .15s ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal {
          width: min(420px, 100%);
          background: var(--color-surface, #fff);
          border: 1px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-lg, 16px);
          box-shadow: var(--shadow-lg);
          overflow: hidden;
          animation: modalIn .2s cubic-bezier(.34,1.2,.64,1);
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
          padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600;
          cursor: pointer; border: 1.5px solid var(--color-border, #d5dbd2);
          background: var(--color-surface, #fff); color: var(--color-text-dark, #1a1a1a);
          transition: background .15s ease, border-color .15s ease;
        }
        .btn:hover { background: var(--color-bg-subtle, #e8e7e2); }
        .btn-confirm {
          background: ${v.color}; border-color: ${v.color}; color: #fff;
        }
        .btn-confirm:hover { opacity: .9; background: ${v.color}; }
      </style>
      <div class="overlay-backdrop">
        <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="cd-title" aria-describedby="cd-msg">
          <div class="confirm-dialog">
            <div class="confirm-icon-wrap">
              <svg viewBox="0 0 24 24"><use href="/assets/icons.svg#icon-${v.icon}"></use></svg>
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

    this._keyHandler = (e) => { if (e.key === 'Escape') this._finish(false); };
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
