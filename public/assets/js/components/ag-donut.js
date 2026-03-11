/**
 * AG-VOTE Donut Chart Component (SVG)
 *
 * Usage:
 *   <ag-donut segments='[{"value":60,"color":"var(--color-success)","label":"Pour"},{"value":30,"color":"var(--color-danger)","label":"Contre"},{"value":10,"color":"var(--color-text-muted)","label":"Abstention"}]' size="120"></ag-donut>
 */
class AgDonut extends HTMLElement {
  static get observedAttributes() { return ['segments', 'size', 'thickness']; }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() { this.render(); }
  attributeChangedCallback() { this.render(); }

  render() {
    let segments = [];
    try { segments = JSON.parse(this.getAttribute('segments') || '[]'); } catch(e) { /* noop */ }

    const size = parseInt(this.getAttribute('size') || '120', 10);
    const thickness = parseInt(this.getAttribute('thickness') || '20', 10);
    const radius = (size - thickness) / 2;
    const cx = size / 2;
    const cy = size / 2;
    const total = segments.reduce((s, seg) => s + (seg.value || 0), 0);

    let currentAngle = -90;
    const paths = segments.map(seg => {
      if (!seg.value || total <= 0) return '';
      const pct = seg.value / total;
      const angle = pct * 360;
      const startAngle = currentAngle;
      const endAngle = currentAngle + angle;
      currentAngle = endAngle;

      const startRad = (startAngle * Math.PI) / 180;
      const endRad = (endAngle * Math.PI) / 180;
      const x1 = cx + radius * Math.cos(startRad);
      const y1 = cy + radius * Math.sin(startRad);
      const x2 = cx + radius * Math.cos(endRad);
      const y2 = cy + radius * Math.sin(endRad);
      const largeArc = angle > 180 ? 1 : 0;

      return `<path d="M ${x1} ${y1} A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2}"
        stroke="${seg.color || '#ccc'}" stroke-width="${thickness}" fill="none" stroke-linecap="round">
        <title>${this._esc(seg.label || '')} : ${seg.value} (${Math.round(pct * 100)}%)</title>
      </path>`;
    }).join('');

    const centerLabel = total > 0 ? Math.round((segments[0]?.value || 0) / total * 100) + '%' : '–';

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: inline-block; }
        .donut-wrap { position: relative; display: inline-flex; align-items: center; justify-content: center; }
        svg { display: block; }
        .center-label {
          position: absolute;
          font-family: var(--font-mono, monospace);
          font-size: ${Math.round(size * 0.18)}px;
          font-weight: 800;
          color: var(--color-text-dark, #1a1a1a);
        }
      </style>
      <div class="donut-wrap" style="width:${size}px;height:${size}px">
        <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" aria-label="Graphique donut">
          ${paths}
        </svg>
        <span class="center-label">${centerLabel}</span>
      </div>
    `;
  }

  _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
}

customElements.define('ag-donut', AgDonut);
export default AgDonut;
