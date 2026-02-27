/**
 * AG-VOTE Breadcrumb Component
 *
 * Usage:
 *   <ag-breadcrumb items='[{"label":"Accueil","href":"/"},{"label":"Séances","href":"/meetings.htmx.html"},{"label":"AG Ordinaire"}]'></ag-breadcrumb>
 */
class AgBreadcrumb extends HTMLElement {
  static get observedAttributes() { return ['items']; }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() { this.render(); }
  attributeChangedCallback() { this.render(); }

  render() {
    let items = [];
    try { items = JSON.parse(this.getAttribute('items') || '[]'); } catch(e) { /* noop */ }

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: block; }
        .breadcrumb {
          display: flex; align-items: center; gap: 6px;
          font-size: 12px; color: var(--color-text-muted, #95a3a4);
        }
        .bc-item {
          color: var(--color-text-muted, #95a3a4);
          text-decoration: none;
          font-weight: 500;
          transition: color .15s ease;
        }
        a.bc-item:hover { color: var(--color-primary, #1650E0); }
        .bc-current {
          color: var(--color-text-dark, #1a1a1a);
          font-weight: 600;
        }
        .bc-sep {
          color: var(--color-text-light, #b5b5b0);
          font-size: 10px;
        }
      </style>
      <nav class="breadcrumb" aria-label="Fil d'Ariane">
        ${items.map((item, i) => {
          const isLast = i === items.length - 1;
          const sep = i > 0 ? '<span class="bc-sep" aria-hidden="true">/</span>' : '';
          if (isLast) {
            return `${sep}<span class="bc-item bc-current" aria-current="page">${this._esc(item.label)}</span>`;
          }
          return `${sep}<a class="bc-item" href="${this._esc(item.href || '#')}">${this._esc(item.label)}</a>`;
        }).join('')}
      </nav>
    `;
  }

  _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
}

customElements.define('ag-breadcrumb', AgBreadcrumb);
export default AgBreadcrumb;
