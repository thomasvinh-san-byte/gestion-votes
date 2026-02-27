/**
 * AG-VOTE Scroll-to-Top Button Component
 *
 * Usage:
 *   <ag-scroll-top threshold="300"></ag-scroll-top>
 */
class AgScrollTop extends HTMLElement {
  static get observedAttributes() { return ['threshold']; }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() {
    this.render();
    const threshold = parseInt(this.getAttribute('threshold') || '300', 10);
    const main = document.querySelector('.app-main') || window;
    const target = main === window ? document.documentElement : main;

    this._onScroll = () => {
      const scrollY = target.scrollTop || window.scrollY;
      const btn = this.shadowRoot.querySelector('.scroll-top');
      if (btn) btn.classList.toggle('visible', scrollY > threshold);
    };

    (main === window ? window : main).addEventListener('scroll', this._onScroll, { passive: true });
  }

  disconnectedCallback() {
    const main = document.querySelector('.app-main') || window;
    (main === window ? window : main).removeEventListener('scroll', this._onScroll);
  }

  render() {
    this.shadowRoot.innerHTML = `
      <style>
        :host { display: contents; }
        .scroll-top {
          position: fixed;
          bottom: 24px;
          right: 24px;
          width: 40px; height: 40px;
          border-radius: 50%;
          background: var(--color-primary, #1650E0);
          color: #fff;
          display: flex; align-items: center; justify-content: center;
          border: none; cursor: pointer;
          box-shadow: 0 4px 14px rgba(22,80,224,.28);
          opacity: 0; visibility: hidden;
          transform: translateY(10px);
          transition: opacity .2s ease, visibility .2s ease, transform .2s ease;
          z-index: 80;
        }
        .scroll-top.visible {
          opacity: 1; visibility: visible; transform: translateY(0);
        }
        .scroll-top:hover {
          background: var(--color-primary-hover, #1241b8);
          box-shadow: 0 6px 20px rgba(22,80,224,.35);
        }
        .scroll-top svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2.5; fill: none; }
      </style>
      <button class="scroll-top" aria-label="Retour en haut" type="button">
        <svg viewBox="0 0 24 24"><path d="m18 15-6-6-6 6"/></svg>
      </button>
    `;

    this.shadowRoot.querySelector('.scroll-top').addEventListener('click', () => {
      const main = document.querySelector('.app-main');
      if (main) {
        main.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  }
}

customElements.define('ag-scroll-top', AgScrollTop);
export default AgScrollTop;
