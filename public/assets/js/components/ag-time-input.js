/**
 * AG-VOTE Time Input Component (HH:MM)
 *
 * Usage:
 *   <ag-time-input name="start_time" value="14:30"></ag-time-input>
 *
 * Features:
 *   - Auto-advance from HH to MM on 2 digits
 *   - Paste support (14:30 or 1430)
 *   - Arrow keys increment/decrement
 */
class AgTimeInput extends HTMLElement {
  static get observedAttributes() { return ['value', 'name', 'disabled']; }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this._hours = '';
    this._minutes = '';
  }

  connectedCallback() {
    this._parseValue(this.getAttribute('value') || '');
    this.render();
    this._setupEvents();
  }

  attributeChangedCallback(name, old, val) {
    if (name === 'value' && old !== val) {
      this._parseValue(val || '');
      this._updateDisplay();
    }
  }

  get value() {
    if (this._hours && this._minutes) return `${this._hours}:${this._minutes}`;
    return '';
  }

  set value(v) {
    this._parseValue(v || '');
    this.setAttribute('value', this.value);
    this._updateDisplay();
  }

  _parseValue(v) {
    const clean = v.replace(/[^\d:]/g, '');
    if (clean.includes(':')) {
      const [h, m] = clean.split(':');
      this._hours = (h || '').padStart(2, '0').slice(0, 2);
      this._minutes = (m || '').padStart(2, '0').slice(0, 2);
    } else if (clean.length >= 4) {
      this._hours = clean.slice(0, 2);
      this._minutes = clean.slice(2, 4);
    } else {
      this._hours = '';
      this._minutes = '';
    }
  }

  _emit() {
    this.setAttribute('value', this.value);
    this.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: { value: this.value } }));
  }

  _updateDisplay() {
    const hEl = this.shadowRoot?.querySelector('.ti-h');
    const mEl = this.shadowRoot?.querySelector('.ti-m');
    if (hEl) hEl.value = this._hours;
    if (mEl) mEl.value = this._minutes;
  }

  _setupEvents() {
    const hEl = this.shadowRoot.querySelector('.ti-h');
    const mEl = this.shadowRoot.querySelector('.ti-m');

    hEl.addEventListener('input', () => {
      let v = hEl.value.replace(/\D/g, '').slice(0, 2);
      if (parseInt(v, 10) > 23) v = '23';
      hEl.value = v;
      this._hours = v.padStart(2, '0');
      if (v.length === 2) mEl.focus();
    });

    mEl.addEventListener('input', () => {
      let v = mEl.value.replace(/\D/g, '').slice(0, 2);
      if (parseInt(v, 10) > 59) v = '59';
      mEl.value = v;
      this._minutes = v.padStart(2, '0');
      if (v.length === 2) this._emit();
    });

    mEl.addEventListener('blur', () => this._emit());
    hEl.addEventListener('blur', () => { this._hours = (hEl.value || '').padStart(2, '0'); });

    // Paste support
    hEl.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text');
      this._parseValue(text);
      this._updateDisplay();
      this._emit();
    });
  }

  render() {
    const disabled = this.hasAttribute('disabled');

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: inline-flex; }
        .ti-wrap {
          display: flex; align-items: center; gap: 2px;
          background: var(--color-surface, #fff);
          border: 1.5px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-sm, 8px);
          padding: 4px 8px;
          font-family: var(--font-mono, monospace);
          font-size: 15px;
          font-weight: 600;
          transition: border-color .15s ease;
        }
        .ti-wrap:focus-within { border-color: var(--color-primary, #1650E0); }
        :host([disabled]) .ti-wrap { opacity: .5; pointer-events: none; }
        .ti-field {
          width: 24px; border: none; background: none;
          text-align: center; font: inherit; color: var(--color-text-dark, #1a1a1a);
          outline: none; padding: 2px 0;
        }
        .ti-field::placeholder { color: var(--color-text-light, #b5b5b0); }
        .ti-sep { color: var(--color-text-muted, #95a3a4); font-weight: 700; }
      </style>
      <div class="ti-wrap">
        <input class="ti-field ti-h" type="text" inputmode="numeric" maxlength="2" placeholder="HH" value="${this._hours}" ${disabled ? 'disabled' : ''} aria-label="Heures" />
        <span class="ti-sep">:</span>
        <input class="ti-field ti-m" type="text" inputmode="numeric" maxlength="2" placeholder="MM" value="${this._minutes}" ${disabled ? 'disabled' : ''} aria-label="Minutes" />
      </div>
    `;
  }
}

customElements.define('ag-time-input', AgTimeInput);
export default AgTimeInput;
