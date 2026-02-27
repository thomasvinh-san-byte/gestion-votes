/**
 * AG-VOTE Modal Dialog Component
 *
 * Usage:
 *   <ag-modal id="myModal" title="Confirmer">
 *     <p>Contenu de la modale</p>
 *     <div slot="footer"><button class="btn btn-primary">Valider</button></div>
 *   </ag-modal>
 *
 *   document.getElementById('myModal').open();
 *
 * Attributes:
 *   - title: Modal title
 *   - size: sm|md|lg (default: md)
 *   - closable: Show close button (default: true)
 */
class AgModal extends HTMLElement {
  static get observedAttributes() {
    return ['title', 'size', 'closable'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this._isOpen = false;
    this._previousFocus = null;
  }

  connectedCallback() {
    this.render();
    this._onKeydown = (e) => {
      if (e.key === 'Escape' && this._isOpen) this.close();
      if (e.key === 'Tab' && this._isOpen) this._trapFocus(e);
    };
    document.addEventListener('keydown', this._onKeydown);
  }

  disconnectedCallback() {
    document.removeEventListener('keydown', this._onKeydown);
  }

  attributeChangedCallback() {
    if (this.shadowRoot.innerHTML) this.render();
  }

  open() {
    this._previousFocus = document.activeElement;
    this._isOpen = true;
    this.setAttribute('aria-hidden', 'false');
    const backdrop = this.shadowRoot.querySelector('.overlay-backdrop');
    if (backdrop) {
      backdrop.style.display = 'flex';
      requestAnimationFrame(() => backdrop.classList.add('open'));
    }
    const first = this.shadowRoot.querySelector('.modal-close') || this.shadowRoot.querySelector('.modal');
    if (first) first.focus();
    this.dispatchEvent(new CustomEvent('ag-modal-open', { bubbles: true }));
  }

  close() {
    this._isOpen = false;
    this.setAttribute('aria-hidden', 'true');
    const backdrop = this.shadowRoot.querySelector('.overlay-backdrop');
    if (backdrop) {
      backdrop.classList.remove('open');
      setTimeout(() => { backdrop.style.display = 'none'; }, 200);
    }
    if (this._previousFocus) this._previousFocus.focus();
    this.dispatchEvent(new CustomEvent('ag-modal-close', { bubbles: true }));
  }

  _trapFocus(e) {
    const focusable = this.shadowRoot.querySelectorAll('button, [tabindex]:not([tabindex="-1"])');
    const slotFocusable = this.querySelectorAll('button, input, select, textarea, a[href], [tabindex]:not([tabindex="-1"])');
    const all = [...focusable, ...slotFocusable].filter(el => !el.disabled);
    if (!all.length) return;
    const first = all[0];
    const last = all[all.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  render() {
    const title = this.getAttribute('title') || '';
    const size = this.getAttribute('size') || 'md';
    const closable = this.getAttribute('closable') !== 'false';
    const sizeMap = { sm: '420px', md: '520px', lg: '720px' };
    const maxW = sizeMap[size] || sizeMap.md;

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: contents; }
        .overlay-backdrop {
          display: none;
          position: fixed;
          inset: 0;
          background: rgba(0,0,0,.35);
          align-items: center;
          justify-content: center;
          z-index: 100;
          padding: 16px;
          backdrop-filter: blur(3px);
          opacity: 0;
          transition: opacity .2s ease;
        }
        .overlay-backdrop.open { opacity: 1; }
        .modal {
          width: min(${maxW}, 100%);
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
        .modal-h {
          padding: 13px 18px;
          border-bottom: 1px solid var(--color-border-subtle, #e8e7e2);
          display: flex;
          align-items: center;
          justify-content: space-between;
        }
        .modal-h .t {
          font-size: 13px;
          font-weight: 700;
          color: var(--color-text-dark, #1a1a1a);
        }
        .modal-close {
          width: 28px; height: 28px;
          display: flex; align-items: center; justify-content: center;
          border: none; background: none; border-radius: 6px;
          cursor: pointer; color: var(--color-text-muted, #95a3a4);
          transition: background .15s ease;
        }
        .modal-close:hover { background: var(--color-bg-subtle, #e8e7e2); }
        .modal-close svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; fill: none; }
        .modal-b { padding: 16px 18px; }
        .modal-f {
          padding: 10px 18px;
          border-top: 1px solid var(--color-border-subtle, #e8e7e2);
          display: flex;
          justify-content: flex-end;
          gap: 6px;
        }
      </style>
      <div class="overlay-backdrop" role="dialog" aria-modal="true" aria-label="${this.escapeAttr(title)}">
        <div class="modal">
          <div class="modal-h">
            <span class="t">${this.escapeHtml(title)}</span>
            ${closable ? '<button class="modal-close" aria-label="Fermer"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button>' : ''}
          </div>
          <div class="modal-b"><slot></slot></div>
          <div class="modal-f"><slot name="footer"></slot></div>
        </div>
      </div>
    `;

    const backdrop = this.shadowRoot.querySelector('.overlay-backdrop');
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop && closable) this.close();
    });
    const closeBtn = this.shadowRoot.querySelector('.modal-close');
    if (closeBtn) closeBtn.addEventListener('click', () => this.close());
  }

  escapeHtml(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  escapeAttr(s) { return String(s || '').replace(/"/g,'&quot;'); }
}

customElements.define('ag-modal', AgModal);
window.AgModal = AgModal;
export default AgModal;
