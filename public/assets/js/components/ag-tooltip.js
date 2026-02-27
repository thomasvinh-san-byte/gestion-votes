/**
 * AG-VOTE Tooltip Component (CSS-only, no JS positioning)
 *
 * Usage:
 *   <ag-tooltip text="Information utile">
 *     <button>Hover me</button>
 *   </ag-tooltip>
 */
class AgTooltip extends HTMLElement {
  static get observedAttributes() { return ['text', 'position']; }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() { this.render(); }
  attributeChangedCallback() { this.render(); }

  render() {
    const text = this.getAttribute('text') || '';
    const position = this.getAttribute('position') || 'top';

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: inline-flex; position: relative; align-items: center; }
        .tip-body {
          position: absolute;
          ${position === 'bottom' ? 'top: calc(100% + 6px);' : 'bottom: calc(100% + 6px);'}
          left: 50%;
          transform: translateX(-50%) translateY(${position === 'bottom' ? '-4px' : '4px'});
          background: var(--color-text-dark, #1a1a1a);
          color: var(--color-surface-raised, #fff);
          font-size: 12px;
          font-weight: 600;
          white-space: nowrap;
          padding: 4px 8px;
          border-radius: 5px;
          opacity: 0;
          visibility: hidden;
          transition: opacity .15s ease, visibility .15s ease, transform .15s ease;
          pointer-events: none;
          z-index: 500;
          box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,.12));
        }
        .tip-body::after {
          content: '';
          position: absolute;
          ${position === 'bottom' ? 'bottom: 100%;' : 'top: 100%;'}
          left: 50%;
          transform: translateX(-50%);
          border: 4px solid transparent;
          ${position === 'bottom' ? 'border-bottom-color: var(--color-text-dark, #1a1a1a);' : 'border-top-color: var(--color-text-dark, #1a1a1a);'}
        }
        :host(:hover) .tip-body,
        :host(:focus-within) .tip-body {
          opacity: 1;
          visibility: visible;
          transform: translateX(-50%) translateY(0);
        }
      </style>
      <slot></slot>
      <span class="tip-body" role="tooltip">${this._esc(text)}</span>
    `;
  }

  _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
}

customElements.define('ag-tooltip', AgTooltip);
export default AgTooltip;
