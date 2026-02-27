/**
 * AG-VOTE Vote Button Component
 *
 * Usage:
 *   <ag-vote-button value="for">Pour</ag-vote-button>
 *   <ag-vote-button value="against">Contre</ag-vote-button>
 *   <ag-vote-button value="abstain">Abstention</ag-vote-button>
 *   <ag-vote-button value="nsp" disabled>NSP</ag-vote-button>
 *
 * Attributes:
 *   - value: Vote value (for|against|abstain|nsp)
 *   - selected: Whether this button is selected
 *   - disabled: Whether the button is disabled
 *   - size: Size variant (md|lg|xl)
 *
 * Events:
 *   - ag-vote: Fired when clicked, detail contains { value }
 */
class AgVoteButton extends HTMLElement {
  static get observedAttributes() {
    return ['value', 'selected', 'disabled', 'size'];
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

  handleClick() {
    if (this.hasAttribute('disabled')) return;

    const value = this.getAttribute('value');
    this.dispatchEvent(new CustomEvent('ag-vote', {
      bubbles: true,
      composed: true,
      detail: { value }
    }));
  }

  render() {
    const value = this.getAttribute('value') || 'for';
    const selected = this.hasAttribute('selected');
    const disabled = this.hasAttribute('disabled');
    const size = this.getAttribute('size') || 'lg';

    const iconMap = {
      for: 'check',
      against: 'x',
      abstain: 'minus',
      nsp: 'help-circle',
    };

    const labelMap = {
      for: 'Pour',
      against: 'Contre',
      abstain: 'Abstention',
      nsp: 'NSP',
    };

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
        }
        button {
          width: 100%;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          gap: 8px;
          padding: 14px 10px;
          font-size: 17px;
          font-weight: 800;
          border: 1.5px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-lg, 16px);
          cursor: pointer;
          transition: background .18s cubic-bezier(.4,0,.2,1), border-color .18s cubic-bezier(.4,0,.2,1), box-shadow .18s cubic-bezier(.4,0,.2,1), transform .18s cubic-bezier(.4,0,.2,1);
          -webkit-tap-highlight-color: transparent;
          user-select: none;
          background: var(--color-surface, #ffffff);
          min-height: 110px;
          position: relative;
          color: var(--color-text-dark, #1a1a1a);
        }
        button:hover:not(:disabled) {
          transform: translateY(-2px);
          box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,.08));
        }
        button:active:not(:disabled) {
          transform: scale(0.98);
        }
        button:disabled {
          opacity: 0.5;
          cursor: not-allowed;
          transform: none;
        }
        .icon-circle {
          width: 46px;
          height: 46px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .icon {
          width: 24px;
          height: 24px;
          stroke: currentColor;
          stroke-width: 2.5;
          fill: none;
        }

        /* Size variants */
        :host([size="md"]) button { padding: 10px 8px; min-height: 90px; font-size: 14px; }
        :host([size="md"]) .icon-circle { width: 36px; height: 36px; }
        :host([size="md"]) .icon { width: 20px; height: 20px; }
        :host([size="xl"]) button { padding: 20px 14px; min-height: 140px; font-size: 20px; }
        :host([size="xl"]) .icon-circle { width: 56px; height: 56px; }
        :host([size="xl"]) .icon { width: 28px; height: 28px; }

        /* Value variants — flat card style from wireframe */
        :host([value="for"]) button {
          border-color: var(--color-success, #0b7a40);
          background: var(--color-success-subtle, #e4ede4);
        }
        :host([value="for"]) button:hover:not(:disabled) { box-shadow: 0 4px 16px rgba(11,122,64,.14); }
        :host([value="for"]) .icon-circle { background: rgba(11,122,64,.12); color: var(--color-success, #0b7a40); }

        :host([value="against"]) button {
          border-color: var(--color-danger, #c42828);
          background: var(--color-danger-subtle, #f2e4e4);
        }
        :host([value="against"]) button:hover:not(:disabled) { box-shadow: 0 4px 16px rgba(196,40,40,.14); }
        :host([value="against"]) .icon-circle { background: rgba(196,40,40,.12); color: var(--color-danger, #c42828); }

        :host([value="abstain"]) button {
          border-color: var(--color-border-dash, #c4c3bc);
          background: var(--color-bg-subtle, #e8e7e2);
        }
        :host([value="abstain"]) .icon-circle { background: rgba(149,163,164,.15); color: var(--color-text-muted, #95a3a4); }

        :host([value="nsp"]) button {
          border-color: var(--color-border, #d5dbd2);
          background: var(--color-bg-subtle, #e8e7e2);
        }
        :host([value="nsp"]) .icon-circle { background: rgba(149,163,164,.15); color: var(--color-text-muted, #95a3a4); }

        /* Selected state */
        :host([value="for"][selected]) button {
          background: var(--color-success, #0b7a40);
          border-color: var(--color-success, #0b7a40);
          color: #fff;
          box-shadow: 0 0 0 3px rgba(11,122,64,.18);
        }
        :host([value="for"][selected]) .icon-circle { background: rgba(255,255,255,.18); }
        :host([value="against"][selected]) button {
          background: var(--color-danger, #c42828);
          border-color: var(--color-danger, #c42828);
          color: #fff;
          box-shadow: 0 0 0 3px rgba(196,40,40,.18);
        }
        :host([value="against"][selected]) .icon-circle { background: rgba(255,255,255,.18); }
        :host([value="abstain"][selected]) button {
          background: var(--color-text-muted, #95a3a4);
          border-color: var(--color-text-muted, #95a3a4);
          color: #fff;
        }
        :host([value="abstain"][selected]) .icon-circle { background: rgba(255,255,255,.18); }
        :host([value="nsp"][selected]) button {
          background: var(--color-text-muted, #95a3a4);
          border-color: var(--color-text-muted, #95a3a4);
          color: #fff;
        }
      </style>
      <button ${disabled ? 'disabled' : ''} aria-pressed="${selected}">
        <span class="icon-circle">
          <svg class="icon" aria-hidden="true">
            <use href="/assets/icons.svg#icon-${iconMap[value] || 'circle'}"></use>
          </svg>
        </span>
        <span class="vb-label"><slot>${labelMap[value] || value}</slot></span>
      </button>
    `;
    this.shadowRoot.querySelector('button').addEventListener('click', () => this.handleClick());
  }
}

customElements.define('ag-vote-button', AgVoteButton);

export default AgVoteButton;
