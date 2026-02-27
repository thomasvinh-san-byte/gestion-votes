/**
 * AG-VOTE Pagination Component
 *
 * Usage:
 *   <ag-pagination total="50" page="1" per-page="5"></ag-pagination>
 *
 * Events:
 *   - ag-page-change: { page }
 */
class AgPagination extends HTMLElement {
  static get observedAttributes() {
    return ['total', 'page', 'per-page'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() { this.render(); }

  attributeChangedCallback(n, o, v) {
    if (o !== v) this.render();
  }

  get totalPages() {
    const total = parseInt(this.getAttribute('total') || '0', 10);
    const perPage = parseInt(this.getAttribute('per-page') || '10', 10);
    return Math.max(1, Math.ceil(total / perPage));
  }

  get currentPage() {
    return Math.max(1, parseInt(this.getAttribute('page') || '1', 10));
  }

  _goTo(p) {
    const page = Math.max(1, Math.min(p, this.totalPages));
    this.setAttribute('page', String(page));
    this.dispatchEvent(new CustomEvent('ag-page-change', { bubbles: true, detail: { page } }));
  }

  render() {
    const total = this.totalPages;
    const current = this.currentPage;
    if (total <= 1) { this.shadowRoot.innerHTML = ''; return; }

    const pages = [];
    for (let i = 1; i <= total; i++) {
      if (i === 1 || i === total || (i >= current - 1 && i <= current + 1)) {
        pages.push(i);
      } else if (pages[pages.length - 1] !== '...') {
        pages.push('...');
      }
    }

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: block; }
        .pagination {
          display: flex; align-items: center; justify-content: center;
          gap: 3px; padding: 8px;
          border-top: 1px solid var(--color-border-subtle, #e8e7e2);
        }
        .pg-btn {
          width: 26px; height: 26px; border-radius: 5px;
          display: flex; align-items: center; justify-content: center;
          font-family: var(--font-mono, monospace); font-size: 11px; font-weight: 600;
          border: 1px solid var(--color-border, #d5dbd2);
          background: var(--color-surface, #fff);
          color: var(--color-text-muted, #95a3a4);
          cursor: pointer;
          transition: color .15s ease, background .15s ease, border-color .15s ease;
        }
        .pg-btn:hover:not(.pg-active):not(.pg-dots) {
          border-color: var(--color-primary, #1650E0);
          color: var(--color-primary, #1650E0);
          background: var(--color-primary-subtle, #e8edfa);
        }
        .pg-active {
          background: var(--color-primary, #1650E0);
          border-color: var(--color-primary, #1650E0);
          color: #fff;
          cursor: default;
        }
        .pg-dots { border: none; background: none; cursor: default; }
        .pg-btn svg { width: 12px; height: 12px; stroke: currentColor; stroke-width: 2; fill: none; }
      </style>
      <div class="pagination" role="navigation" aria-label="Pagination">
        <button class="pg-btn" ${current <= 1 ? 'disabled' : ''} data-page="${current - 1}" aria-label="Page précédente">
          <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        ${pages.map(p => p === '...'
          ? '<span class="pg-btn pg-dots">…</span>'
          : `<button class="pg-btn ${p === current ? 'pg-active' : ''}" data-page="${p}" aria-label="Page ${p}" ${p === current ? 'aria-current="page"' : ''}>${p}</button>`
        ).join('')}
        <button class="pg-btn" ${current >= total ? 'disabled' : ''} data-page="${current + 1}" aria-label="Page suivante">
          <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </button>
      </div>
    `;

    this.shadowRoot.querySelectorAll('.pg-btn[data-page]').forEach(btn => {
      btn.addEventListener('click', () => {
        if (!btn.disabled && !btn.classList.contains('pg-active')) {
          this._goTo(parseInt(btn.dataset.page, 10));
        }
      });
    });
  }
}

customElements.define('ag-pagination', AgPagination);
export default AgPagination;
