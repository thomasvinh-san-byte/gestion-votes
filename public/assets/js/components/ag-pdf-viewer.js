/**
 * AG-VOTE PDF Viewer Component
 *
 * Usage:
 *   <ag-pdf-viewer mode="inline" src="/api/v1/resolution_document_serve?id=uuid" filename="document.pdf"></ag-pdf-viewer>
 *   <ag-pdf-viewer mode="sheet" allow-download></ag-pdf-viewer>  <!-- mobile bottom sheet -->
 *   <ag-pdf-viewer mode="panel"></ag-pdf-viewer>                 <!-- desktop side panel -->
 *
 *   viewer.open();   // slide in
 *   viewer.close();  // slide out
 *
 * Attributes:
 *   - src:            URL served by /api/v1/resolution_document_serve
 *   - filename:       Display name shown in header
 *   - mode:           inline | sheet | panel (default: inline)
 *   - open:           Presence triggers open state (add/remove via open()/close())
 *   - allow-download: Presence shows download button (omit for voter mode — PDF-10)
 */
class AgPdfViewer extends HTMLElement {
  static get observedAttributes() {
    return ['src', 'filename', 'mode', 'open', 'allow-download'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this._backdropClickHandler = this._onBackdropClick.bind(this);
    this._keyDownHandler = this._onKeyDown.bind(this);
  }

  connectedCallback() {
    this.render();
    document.addEventListener('keydown', this._keyDownHandler);
  }

  disconnectedCallback() {
    document.removeEventListener('keydown', this._keyDownHandler);
  }

  attributeChangedCallback(name, oldVal, newVal) {
    if (!this.shadowRoot.innerHTML) return;

    if (name === 'src') {
      var iframe = this.shadowRoot.querySelector('iframe');
      if (iframe) iframe.src = newVal || '';
    } else if (name === 'filename') {
      var h3 = this.shadowRoot.querySelector('.filename');
      if (h3) h3.textContent = newVal || '';
    } else if (name === 'open') {
      // Handled by CSS :host([open]) selectors — no JS manipulation needed
    } else if (name === 'allow-download') {
      var dlBtn = this.shadowRoot.querySelector('.btn-download');
      if (dlBtn) dlBtn.hidden = !this.hasAttribute('allow-download');
    } else {
      // mode changed — full re-render needed
      this.render();
    }
  }

  open() {
    this.setAttribute('open', '');
    var mode = this.getAttribute('mode') || 'inline';
    if (mode === 'sheet' || mode === 'panel') {
      var backdrop = this.shadowRoot.querySelector('.backdrop');
      if (backdrop) {
        backdrop.style.display = 'block';
        backdrop.addEventListener('click', this._backdropClickHandler, { once: true });
      }
    }
    this.dispatchEvent(new CustomEvent('ag-pdf-viewer-open', { bubbles: true }));
  }

  close() {
    this.removeAttribute('open');
    // Clear iframe src to stop any ongoing load
    var iframe = this.shadowRoot.querySelector('iframe');
    if (iframe) iframe.src = '';
    // Restore src from attribute after a tick (prevents flicker on re-open)
    var src = this.getAttribute('src');
    if (src) {
      // intentionally cleared — user must call open() to reload
    }
    var backdrop = this.shadowRoot.querySelector('.backdrop');
    if (backdrop) backdrop.style.display = 'none';
    this.dispatchEvent(new CustomEvent('ag-pdf-viewer-close', { bubbles: true }));
  }

  _onBackdropClick() {
    this.close();
  }

  _onKeyDown(e) {
    if (e.key === 'Escape' && this.hasAttribute('open')) {
      this.close();
    }
  }

  escapeHtml(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  escapeAttr(s) {
    return String(s || '').replace(/"/g, '&quot;');
  }

  render() {
    var src = this.getAttribute('src') || '';
    var filename = this.getAttribute('filename') || '';
    var mode = this.getAttribute('mode') || 'inline';
    var isOpen = this.hasAttribute('open');
    var allowDownload = this.hasAttribute('allow-download');

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
          box-sizing: border-box;
        }

        /* ── Inline mode: embedded in page flow ── */
        :host([mode="inline"]) {
          width: 100%;
          height: var(--pdf-viewer-height, 600px);
          border-radius: var(--radius-base, 8px);
          overflow: hidden;
          border: 1px solid var(--color-border, #e5e7eb);
        }

        :host([mode="inline"]) .backdrop { display: none !important; }

        :host([mode="inline"]) .viewer-container {
          display: flex;
          flex-direction: column;
          width: 100%;
          height: 100%;
        }

        /* ── Sheet mode: bottom sheet for mobile ── */
        :host([mode="sheet"]) {
          position: fixed;
          inset: auto 0 0 0;
          height: 80dvh;
          background: var(--color-surface, #fafaf7);
          border-radius: var(--radius-base, 8px) var(--radius-base, 8px) 0 0;
          z-index: var(--z-modal, 100);
          transform: translateY(100%);
          transition: transform var(--duration-slow, 0.3s) var(--ease-out, ease-out);
          box-shadow: 0 -4px 24px rgba(0,0,0,0.18);
        }

        :host([mode="sheet"][open]) {
          transform: translateY(0);
        }

        :host([mode="sheet"]) .viewer-container {
          display: flex;
          flex-direction: column;
          width: 100%;
          height: 100%;
        }

        /* ── Panel mode: side panel from right for desktop ── */
        :host([mode="panel"]) {
          position: fixed;
          inset: 0 0 0 auto;
          width: min(480px, 90vw);
          background: var(--color-surface, #fafaf7);
          z-index: var(--z-modal, 100);
          transform: translateX(100%);
          transition: transform var(--duration-slow, 0.3s) var(--ease-out, ease-out);
          box-shadow: -4px 0 24px rgba(0,0,0,0.18);
        }

        :host([mode="panel"][open]) {
          transform: translateX(0);
        }

        :host([mode="panel"]) .viewer-container {
          display: flex;
          flex-direction: column;
          width: 100%;
          height: 100%;
        }

        /* ── Backdrop: semi-transparent overlay for sheet/panel ── */
        .backdrop {
          display: none;
          position: fixed;
          inset: 0;
          background: rgba(0, 0, 0, 0.45);
          z-index: -1;
          backdrop-filter: blur(2px);
        }

        /* ── Header ── */
        .viewer-header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 12px 16px;
          border-bottom: 1px solid var(--color-border, #e5e7eb);
          flex-shrink: 0;
          background: var(--color-surface, #fafaf7);
        }

        .filename {
          margin: 0;
          font-size: 14px;
          font-weight: 600;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
          color: var(--color-text-dark, #1a1a1a);
          flex: 1;
          min-width: 0;
          margin-right: 8px;
        }

        .header-actions {
          display: flex;
          align-items: center;
          gap: 4px;
          flex-shrink: 0;
        }

        .btn-close,
        .btn-download {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 32px;
          height: 32px;
          border: none;
          background: none;
          border-radius: var(--radius-base, 8px);
          cursor: pointer;
          color: var(--color-text-muted, #6b7280);
          font-size: 18px;
          line-height: 1;
          transition: background var(--duration-fast, 150ms) ease, color var(--duration-fast, 150ms) ease;
          padding: 0;
        }

        .btn-close:hover,
        .btn-download:hover {
          background: var(--color-bg-subtle, #e8e7e2);
          color: var(--color-text-dark, #1a1a1a);
        }

        .btn-close svg,
        .btn-download svg {
          width: 16px;
          height: 16px;
          stroke: currentColor;
          stroke-width: 2;
          fill: none;
          stroke-linecap: round;
          stroke-linejoin: round;
          display: block;
        }

        /* ── Body ── */
        .viewer-body {
          flex: 1;
          overflow: hidden;
          display: flex;
          flex-direction: column;
        }

        .viewer-body iframe {
          width: 100%;
          height: 100%;
          border: none;
          display: block;
          flex: 1;
        }
      </style>

      <div class="backdrop" part="backdrop"></div>
      <div class="viewer-container">
        <div class="viewer-header">
          <h3 class="filename">${this.escapeHtml(filename)}</h3>
          <div class="header-actions">
            <button class="btn-download" title="T\u00e9l\u00e9charger" aria-label="T\u00e9l\u00e9charger le document" ${allowDownload ? '' : 'hidden'}>
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
            </button>
            <button class="btn-close" title="Fermer" aria-label="Fermer l'aper\u00e7u">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 6 6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="viewer-body">
          <iframe
            sandbox="allow-same-origin allow-scripts allow-popups"
            title="Aper\u00e7u PDF"
            src="${this.escapeAttr(src)}"
          ></iframe>
        </div>
      </div>
    `;

    // Wire close button
    var closeBtn = this.shadowRoot.querySelector('.btn-close');
    if (closeBtn) closeBtn.addEventListener('click', () => this.close());

    // Wire download button
    var dlBtn = this.shadowRoot.querySelector('.btn-download');
    if (dlBtn) {
      dlBtn.addEventListener('click', () => {
        var currentSrc = this.getAttribute('src') || '';
        if (currentSrc) {
          var a = document.createElement('a');
          a.href = currentSrc;
          a.download = this.getAttribute('filename') || 'document.pdf';
          a.click();
        }
      });
    }

    // Show backdrop for sheet/panel
    if (mode === 'sheet' || mode === 'panel') {
      var backdrop = this.shadowRoot.querySelector('.backdrop');
      if (backdrop && isOpen) {
        backdrop.style.display = 'block';
      }
    }
  }
}

customElements.define('ag-pdf-viewer', AgPdfViewer);
window.AgPdfViewer = AgPdfViewer;
export default AgPdfViewer;
