/**
 * AG-VOTE Mini Bar Chart Component
 *
 * Usage:
 *   <ag-mini-bar values='[80,20]' colors='["var(--color-success)","var(--color-danger)"]' height="8"></ag-mini-bar>
 */
class AgMiniBar extends HTMLElement {
  static get observedAttributes() { return ['values', 'colors', 'height']; }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() { this.render(); }
  attributeChangedCallback() { this.render(); }

  render() {
    let values = [], colors = [];
    try { values = JSON.parse(this.getAttribute('values') || '[]'); } catch(e) { /* noop */ }
    try { colors = JSON.parse(this.getAttribute('colors') || '[]'); } catch(e) { /* noop */ }
    const height = parseInt(this.getAttribute('height') || '8', 10);
    const total = values.reduce((s, v) => s + v, 0);

    const segments = values.map((v, i) => {
      const pct = total > 0 ? (v / total * 100) : 0;
      const color = colors[i] || 'var(--color-primary, #1650E0)';
      return `<div class="seg" style="width:${pct}%;background:${color}" title="${v} (${Math.round(pct)}%)"></div>`;
    }).join('');

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: block; }
        .mini-bar {
          display: flex;
          height: ${height}px;
          border-radius: ${Math.ceil(height / 2)}px;
          overflow: hidden;
          background: var(--color-bg-subtle, #e8e7e2);
        }
        .seg {
          height: 100%;
          transition: width .4s ease;
          min-width: 0;
        }
        .seg:first-child { border-radius: ${Math.ceil(height / 2)}px 0 0 ${Math.ceil(height / 2)}px; }
        .seg:last-child { border-radius: 0 ${Math.ceil(height / 2)}px ${Math.ceil(height / 2)}px 0; }
        .seg:only-child { border-radius: ${Math.ceil(height / 2)}px; }
      </style>
      <div class="mini-bar" role="img" aria-label="Barre de progression">${segments}</div>
    `;
  }
}

customElements.define('ag-mini-bar', AgMiniBar);
export default AgMiniBar;
