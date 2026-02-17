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
    this.shadowRoot.querySelector('button').addEventListener('click', () => this.handleClick());
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
          gap: 0.5rem;
          padding: 2rem 1rem;
          font-size: 1.25rem;
          font-weight: 700;
          text-transform: uppercase;
          letter-spacing: 0.05em;
          border: none;
          border-radius: var(--radius-xl, 20px);
          cursor: pointer;
          transition: transform 0.1s ease, box-shadow 0.15s ease, opacity 0.15s;
          -webkit-tap-highlight-color: transparent;
          user-select: none;
        }
        button:active:not(:disabled) {
          transform: scale(0.98);
        }
        button:disabled {
          opacity: 0.5;
          cursor: not-allowed;
          transform: none;
        }
        .icon {
          width: 2.5rem;
          height: 2.5rem;
          stroke: currentColor;
          stroke-width: 2.5;
          fill: none;
        }

        /* Size variants */
        :host([size="md"]) button {
          padding: 1.5rem 1rem;
          font-size: 1rem;
        }
        :host([size="md"]) .icon {
          width: 2rem;
          height: 2rem;
        }
        :host([size="xl"]) button {
          padding: 3rem 1.5rem;
          font-size: 1.5rem;
        }
        :host([size="xl"]) .icon {
          width: 3rem;
          height: 3rem;
        }

        /* Value variants */
        :host([value="for"]) button {
          background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
          color: white;
          box-shadow: 0 10px 30px rgba(22, 163, 74, 0.35);
        }
        :host([value="for"]) button:hover:not(:disabled) {
          box-shadow: 0 14px 40px rgba(22, 163, 74, 0.45);
        }

        :host([value="against"]) button {
          background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
          color: white;
          box-shadow: 0 10px 30px rgba(220, 38, 38, 0.35);
        }
        :host([value="against"]) button:hover:not(:disabled) {
          box-shadow: 0 14px 40px rgba(220, 38, 38, 0.45);
        }

        :host([value="abstain"]) button {
          background: linear-gradient(135deg, #64748b 0%, #475569 100%);
          color: white;
          box-shadow: 0 10px 30px rgba(100, 116, 139, 0.35);
        }
        :host([value="abstain"]) button:hover:not(:disabled) {
          box-shadow: 0 14px 40px rgba(100, 116, 139, 0.45);
        }

        :host([value="nsp"]) button {
          background: linear-gradient(135deg, #95a3a4 0%, #7a8a8b 100%);
          color: white;
          box-shadow: 0 10px 30px rgba(149, 163, 164, 0.35);
        }
        :host([value="nsp"]) button:hover:not(:disabled) {
          box-shadow: 0 14px 40px rgba(149, 163, 164, 0.45);
        }

        /* Selected state */
        :host([selected]) button {
          transform: scale(1.02);
          box-shadow: 0 0 0 4px white, 0 0 0 6px currentColor, 0 14px 40px rgba(0,0,0,0.25);
        }
      </style>
      <button ${disabled ? 'disabled' : ''} aria-pressed="${selected}">
        <svg class="icon" aria-hidden="true">
          <use href="/assets/icons.svg#icon-${iconMap[value] || 'circle'}"></use>
        </svg>
        <span><slot>${labelMap[value] || value}</slot></span>
      </button>
    `;
  }
}

customElements.define('ag-vote-button', AgVoteButton);

export default AgVoteButton;
