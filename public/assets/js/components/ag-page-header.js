/**
 * AG-VOTE Page Header Component
 *
 * Usage:
 *   <ag-page-header title="Séances" subtitle="Gérer les assemblées générales"></ag-page-header>
 */
class AgPageHeader extends HTMLElement {
  static get observedAttributes() { return ['title', 'subtitle']; }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() { this.render(); }
  attributeChangedCallback() { if (this.shadowRoot.innerHTML) this.render(); }

  render() {
    const title = this.getAttribute('title') || '';
    const subtitle = this.getAttribute('subtitle') || '';

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: block; margin-bottom: 16px; }
        .page-title {
          font-family: var(--font-display, 'Fraunces', serif);
          font-size: 22px;
          font-weight: 700;
          color: var(--color-text-dark, #1a1a1a);
          display: flex;
          align-items: center;
          gap: 10px;
          line-height: 1.2;
        }
        .bar {
          width: 4px;
          height: 22px;
          border-radius: 2px;
          background: var(--color-primary, #1650E0);
          flex-shrink: 0;
        }
        .page-sub {
          font-size: 14px;
          color: var(--color-text-muted, #95a3a4);
          margin-top: 4px;
          margin-left: 14px;
        }
        ::slotted(*) { margin-top: 8px; }
      </style>
      <h1 class="page-title">
        <span class="bar" aria-hidden="true"></span>
        ${this._esc(title)}
      </h1>
      ${subtitle ? `<p class="page-sub">${this._esc(subtitle)}</p>` : ''}
      <slot></slot>
    `;
  }

  _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
}

customElements.define('ag-page-header', AgPageHeader);
export default AgPageHeader;
