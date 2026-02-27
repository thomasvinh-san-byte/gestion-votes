/**
 * AG-VOTE Stepper Component (horizontal steps)
 *
 * Usage:
 *   <ag-stepper steps='["Infos","Participants","Résolutions","Récap"]' current="1"></ag-stepper>
 */
class AgStepper extends HTMLElement {
  static get observedAttributes() { return ['steps', 'current']; }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() { this.render(); }
  attributeChangedCallback() { this.render(); }

  get currentStep() { return parseInt(this.getAttribute('current') || '0', 10); }

  render() {
    let steps = [];
    try { steps = JSON.parse(this.getAttribute('steps') || '[]'); } catch(e) { /* noop */ }
    const current = this.currentStep;

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: block; }
        .stepper {
          display: flex; align-items: flex-start; gap: 0;
          margin-bottom: 14px; max-width: 100%;
        }
        .step {
          flex: 1; display: flex; align-items: center; gap: 6px;
          position: relative; padding: 0 4px;
        }
        .step::after {
          content: '';
          flex: 1; height: 2px;
          background: var(--color-border, #d5dbd2);
          margin-left: 6px;
        }
        .step:last-child::after { display: none; }
        .step.done::after { background: var(--color-success, #0b7a40); }
        .step.active::after { background: var(--color-primary, #1650E0); }
        .dot {
          width: 20px; height: 20px; border-radius: 50%;
          border: 2px solid var(--color-border, #d5dbd2);
          background: var(--color-surface, #fff);
          display: flex; align-items: center; justify-content: center;
          font-family: var(--font-mono, monospace);
          font-size: 11px; font-weight: 700;
          color: var(--color-text-muted, #95a3a4);
          flex-shrink: 0;
          transition: background .15s ease, border-color .15s ease, color .15s ease;
        }
        .done .dot {
          background: var(--color-success, #0b7a40);
          border-color: var(--color-success, #0b7a40);
          color: #fff;
        }
        .active .dot {
          background: var(--color-primary, #1650E0);
          border-color: var(--color-primary, #1650E0);
          color: #fff;
          box-shadow: 0 0 0 3px var(--color-primary-glow, rgba(22,80,224,.18));
        }
        .step-label {
          font-size: 11px; font-weight: 600;
          color: var(--color-text-muted, #95a3a4);
          white-space: nowrap;
        }
        .done .step-label { color: var(--color-success, #0b7a40); }
        .active .step-label { color: var(--color-primary, #1650E0); font-weight: 700; }
      </style>
      <div class="stepper" role="list" aria-label="Étapes">
        ${steps.map((label, i) => {
          const cls = i < current ? 'done' : i === current ? 'active' : '';
          const icon = i < current ? '✓' : String(i + 1);
          return `<div class="step ${cls}" role="listitem" aria-current="${i === current ? 'step' : 'false'}">
            <span class="dot">${icon}</span>
            <span class="step-label">${this._esc(label)}</span>
          </div>`;
        }).join('')}
      </div>
    `;
  }

  _esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
}

customElements.define('ag-stepper', AgStepper);
export default AgStepper;
