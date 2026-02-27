/**
 * AG-VOTE Timezone Picker Component
 *
 * Usage:
 *   <ag-tz-picker name="timezone" value="Europe/Paris"></ag-tz-picker>
 */
const TZ_LIST = [
  'Pacific/Midway','Pacific/Honolulu','America/Anchorage','America/Los_Angeles',
  'America/Denver','America/Chicago','America/New_York','America/Caracas',
  'America/Halifax','America/St_Johns','America/Sao_Paulo','America/Argentina/Buenos_Aires',
  'Atlantic/South_Georgia','Atlantic/Azores','Europe/London','Europe/Paris',
  'Europe/Berlin','Europe/Brussels','Europe/Amsterdam','Europe/Rome',
  'Europe/Madrid','Europe/Zurich','Europe/Vienna','Europe/Warsaw',
  'Europe/Prague','Europe/Budapest','Europe/Bucharest','Europe/Helsinki',
  'Europe/Athens','Europe/Istanbul','Europe/Moscow','Asia/Dubai',
  'Asia/Karachi','Asia/Kolkata','Asia/Dhaka','Asia/Bangkok',
  'Asia/Singapore','Asia/Hong_Kong','Asia/Shanghai','Asia/Seoul',
  'Asia/Tokyo','Australia/Brisbane','Australia/Sydney','Australia/Adelaide',
  'Pacific/Guam','Pacific/Noumea','Pacific/Auckland','Pacific/Fiji',
  'Pacific/Tongatapu','Indian/Mauritius','Indian/Reunion','Africa/Casablanca',
  'Africa/Lagos','Africa/Johannesburg','Africa/Nairobi','Africa/Cairo',
  'America/Mexico_City','America/Bogota'
];

class AgTzPicker extends HTMLElement {
  static get observedAttributes() { return ['value', 'name', 'disabled']; }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this._value = 'Europe/Paris';
  }

  connectedCallback() {
    this._value = this.getAttribute('value') || 'Europe/Paris';
    this.render();
  }

  attributeChangedCallback(n, o, v) {
    if (n === 'value' && o !== v) { this._value = v || 'Europe/Paris'; this._updateDisplay(); }
  }

  get value() { return this._value; }
  set value(v) { this._value = v; this.setAttribute('value', v); this._updateDisplay(); }

  _updateDisplay() {
    const sel = this.shadowRoot?.querySelector('select');
    if (sel) sel.value = this._value;
  }

  render() {
    const disabled = this.hasAttribute('disabled');
    const name = this.getAttribute('name') || 'timezone';

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: inline-block; min-width: 220px; }
        select {
          width: 100%;
          padding: 8px 32px 8px 10px;
          border: 1.5px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-sm, 8px);
          background: var(--color-surface, #fff);
          font-family: var(--font-sans, sans-serif);
          font-size: 13px;
          color: var(--color-text-dark, #1a1a1a);
          appearance: none;
          background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2395a3a4' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
          background-repeat: no-repeat;
          background-position: right 10px center;
          cursor: pointer;
          transition: border-color .15s ease;
        }
        select:focus { outline: none; border-color: var(--color-primary, #1650E0); }
        :host([disabled]) select { opacity: .5; pointer-events: none; }
      </style>
      <select name="${name}" ${disabled ? 'disabled' : ''} aria-label="Fuseau horaire">
        ${TZ_LIST.map(tz =>
          `<option value="${tz}" ${tz === this._value ? 'selected' : ''}>${tz.replace(/_/g, ' ')}</option>`
        ).join('')}
      </select>
    `;

    this.shadowRoot.querySelector('select').addEventListener('change', (e) => {
      this._value = e.target.value;
      this.setAttribute('value', this._value);
      this.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: { value: this._value } }));
    });
  }
}

customElements.define('ag-tz-picker', AgTzPicker);
export default AgTzPicker;
