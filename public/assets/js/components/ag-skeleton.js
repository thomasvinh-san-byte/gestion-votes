/**
 * AG-VOTE Skeleton Component (LOADING-V27-01)
 *
 * Placeholder shimmer block for perceived loading. Plug-and-play with the
 * central HTMX listener (loading-states.js) which injects a skeleton if a
 * swap exceeds 300ms.
 *
 * Usage:
 *   <ag-skeleton></ag-skeleton>                       (variant=text, count=1)
 *   <ag-skeleton variant="text" count="3"></ag-skeleton>
 *   <ag-skeleton variant="card"></ag-skeleton>
 *   <ag-skeleton variant="table" rows="5"></ag-skeleton>
 *   <ag-skeleton variant="avatar"></ag-skeleton>
 *
 * Attributes:
 *   - variant: text | card | table | avatar (default: text)
 *   - count:   integer >=1 (default: 1) — duplicate for text/card/avatar
 *   - rows:    integer >=1 (default: 3) — only used by variant=table
 *
 * Tokens:
 *   --color-bg-subtle, --color-border-subtle, --color-border, --radius-base
 *
 * Accessibility:
 *   - role="status" + aria-label="Chargement"
 *   - prefers-reduced-motion: animation disabled, opacity fixed at 0.6
 */
class AgSkeleton extends HTMLElement {
  static get observedAttributes() {
    return ['variant', 'count', 'rows'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() {
    this.render();
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (oldValue !== newValue) {
      this.render();
    }
  }

  _intAttr(name, def, min) {
    var raw = this.getAttribute(name);
    var n = parseInt(raw, 10);
    if (!Number.isFinite(n) || n < min) return def;
    return n;
  }

  render() {
    var variant = this.getAttribute('variant') || 'text';
    var count = this._intAttr('count', 1, 1);
    var rows = this._intAttr('rows', 3, 1);

    var body = '';
    if (variant === 'card') {
      body = this._repeat('<div class="sk sk-card" aria-hidden="true"></div>', count);
    } else if (variant === 'avatar') {
      body = this._repeat('<div class="sk sk-avatar" aria-hidden="true"></div>', count);
    } else if (variant === 'table') {
      var row =
        '<div class="sk-row" aria-hidden="true">' +
          '<div class="sk sk-cell sk-cell-1"></div>' +
          '<div class="sk sk-cell sk-cell-2"></div>' +
          '<div class="sk sk-cell sk-cell-3"></div>' +
        '</div>';
      body = this._repeat(row, rows);
    } else {
      // text (default)
      body = this._repeat('<div class="sk sk-text" aria-hidden="true"></div>', count);
    }

    this.shadowRoot.innerHTML =
      '<style>' +
        ':host { display: block; width: 100%; }' +
        ':host([variant="avatar"]) { display: inline-block; width: auto; }' +
        '.sk {' +
          'background: linear-gradient(90deg,' +
            ' var(--color-bg-subtle, #f1f3f5) 25%,' +
            ' var(--color-border-subtle, var(--color-border, #e9ecef)) 50%,' +
            ' var(--color-bg-subtle, #f1f3f5) 75%' +
          ');' +
          'background-size: 200% 100%;' +
          'border-radius: var(--radius-base, 4px);' +
          'animation: ag-sk-shimmer 1.5s linear infinite;' +
        '}' +
        '.sk-text {' +
          'height: 0.875rem;' +
          'margin-bottom: 0.5rem;' +
        '}' +
        '.sk-text:last-child { margin-bottom: 0; width: 70%; }' +
        '.sk-card {' +
          'height: 120px;' +
          'width: 100%;' +
          'margin-bottom: 0.75rem;' +
        '}' +
        '.sk-card:last-child { margin-bottom: 0; }' +
        '.sk-avatar {' +
          'width: 40px;' +
          'height: 40px;' +
          'border-radius: 50%;' +
          'display: inline-block;' +
          'margin-right: 0.5rem;' +
        '}' +
        '.sk-row {' +
          'display: flex;' +
          'gap: 0.75rem;' +
          'margin-bottom: 0.5rem;' +
        '}' +
        '.sk-row:last-child { margin-bottom: 0; }' +
        '.sk-cell { height: 1rem; }' +
        '.sk-cell-1 { flex: 2; }' +
        '.sk-cell-2 { flex: 3; }' +
        '.sk-cell-3 { flex: 1; }' +
        '@keyframes ag-sk-shimmer { to { background-position: -200% 0; } }' +
        '@media (prefers-reduced-motion: reduce) {' +
          '.sk { animation: none; opacity: 0.6; }' +
        '}' +
      '</style>' +
      '<div role="status" aria-label="Chargement">' + body + '</div>';
  }

  _repeat(html, n) {
    var out = '';
    for (var i = 0; i < n; i++) out += html;
    return out;
  }
}

customElements.define('ag-skeleton', AgSkeleton);

export default AgSkeleton;
