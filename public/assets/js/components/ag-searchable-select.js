/**
 * AG-VOTE Searchable Select Component
 *
 * A custom select element with fuzzy search functionality.
 * Replaces native <select> elements for better UX with large lists.
 *
 * Usage:
 *   <ag-searchable-select
 *     id="memberSelect"
 *     placeholder="Rechercher un membre..."
 *     empty-text="Aucun membre trouvé"
 *   ></ag-searchable-select>
 *
 * JavaScript API:
 *   const select = document.getElementById('memberSelect');
 *   select.setOptions([
 *     { value: '1', label: 'Alice Martin', sublabel: 'alice@example.com' },
 *     { value: '2', label: 'Bob Dupont', sublabel: 'bob@example.com', group: 'Groupe A' },
 *   ]);
 *   select.value = '1';
 *   select.addEventListener('change', (e) => console.log(e.detail.value));
 *
 * Attributes:
 *   - placeholder: Search input placeholder text
 *   - empty-text: Text shown when no results match
 *   - disabled: Disable the component
 *   - required: Mark as required for form validation
 *   - value: Currently selected value
 *   - name: Form field name
 */
class AgSearchableSelect extends HTMLElement {
  static get observedAttributes() {
    return ['placeholder', 'empty-text', 'disabled', 'value', 'name', 'required'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this._options = [];
    this._filteredOptions = [];
    this._value = '';
    this._selectedLabel = '';
    this._isOpen = false;
    this._highlightedIndex = -1;
    this._searchTerm = '';
  }

  connectedCallback() {
    this.render();
    this.setupEventListeners();
    // Ensure component participates in form submission
    this._internals = this.attachInternals?.() || null;
  }

  disconnectedCallback() {
    this.removeEventListeners();
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (oldValue === newValue) return;
    if (name === 'value') {
      this._value = newValue || '';
      this.updateSelectedDisplay();
    } else if (name === 'disabled') {
      this.updateDisabledState();
    } else {
      this.render();
    }
  }

  // ==========================================================================
  // PUBLIC API
  // ==========================================================================

  get value() {
    return this._value;
  }

  set value(val) {
    this._value = val || '';
    this.setAttribute('value', this._value);
    this.updateSelectedDisplay();
    if (this._internals) {
      this._internals.setFormValue(this._value);
    }
  }

  get selectedOption() {
    return this._options.find(o => String(o.value) === String(this._value)) || null;
  }

  get options() {
    return this._options;
  }

  /**
   * Set the available options
   * @param {Array<{value: string, label: string, sublabel?: string, group?: string, disabled?: boolean}>} options
   */
  setOptions(options) {
    this._options = options || [];
    this._filteredOptions = [...this._options];
    this.updateSelectedDisplay();
    this.renderOptionsList();
  }

  /**
   * Clear selection
   */
  clear() {
    this._value = '';
    this._selectedLabel = '';
    this.setAttribute('value', '');
    this.updateSelectedDisplay();
    this.dispatchEvent(new CustomEvent('change', {
      detail: { value: '', label: '', option: null },
      bubbles: true
    }));
  }

  /**
   * Focus the search input
   */
  focus() {
    const input = this.shadowRoot?.querySelector('.search-input');
    if (input) input.focus();
  }

  // ==========================================================================
  // FUZZY SEARCH (delegates to Utils.fuzzyMatch)
  // ==========================================================================

  /**
   * Filter and sort options by search term
   * Uses global Utils.fuzzyMatch for consistency across the app
   */
  filterOptions(searchTerm) {
    if (!searchTerm || !searchTerm.trim()) {
      this._filteredOptions = [...this._options];
      return;
    }

    const fuzzyMatch = window.Utils?.fuzzyMatch || this._fallbackFuzzyMatch.bind(this);

    const results = [];
    for (const option of this._options) {
      // Match against label and sublabel
      const labelMatch = fuzzyMatch(searchTerm, option.label);
      const sublabelMatch = option.sublabel
        ? fuzzyMatch(searchTerm, option.sublabel)
        : { score: -1, matches: [] };

      const bestScore = Math.max(labelMatch.score, sublabelMatch.score);

      if (bestScore > 0) {
        results.push({
          ...option,
          _score: bestScore,
          _labelMatches: labelMatch.score > 0 ? labelMatch.matches : [],
          _sublabelMatches: sublabelMatch.score > 0 ? sublabelMatch.matches : []
        });
      }
    }

    // Sort by score (descending)
    results.sort((a, b) => b._score - a._score);
    this._filteredOptions = results;
  }

  /**
   * Fallback fuzzy match if Utils not loaded (standalone usage)
   */
  _fallbackFuzzyMatch(pattern, str) {
    if (!pattern) return { score: 1, matches: [] };
    if (!str) return { score: -1, matches: [] };

    const p = pattern.toLowerCase();
    const s = str.toLowerCase();

    if (s.includes(p)) {
      const idx = s.indexOf(p);
      return { score: 50 + (p.length / s.length) * 30, matches: [[idx, idx + p.length]] };
    }

    let patternIdx = 0, score = 0, lastIdx = -2;
    const matches = [];

    for (let i = 0; i < s.length && patternIdx < p.length; i++) {
      if (s[i] === p[patternIdx]) {
        score += 1 + (i === lastIdx + 1 ? 5 : 0) + (i === 0 || /[\s\-_.,@]/.test(s[i - 1]) ? 10 : 0);
        matches.push([i, i + 1]);
        lastIdx = i;
        patternIdx++;
      }
    }

    if (patternIdx !== p.length) return { score: -1, matches: [] };
    return { score: score / p.length, matches };
  }

  /**
   * Highlight matched characters in text
   */
  highlightMatches(text, matches) {
    // Use Utils if available
    if (window.Utils?.highlightMatches) {
      return window.Utils.highlightMatches(text, matches);
    }

    // Fallback
    if (!matches || !matches.length || !text) {
      return this.escapeHtml(text || '');
    }

    let result = '';
    let lastEnd = 0;

    for (const [start, end] of matches) {
      result += this.escapeHtml(text.substring(lastEnd, start));
      result += '<mark>' + this.escapeHtml(text.substring(start, end)) + '</mark>';
      lastEnd = end;
    }
    result += this.escapeHtml(text.substring(lastEnd));

    return result;
  }

  // ==========================================================================
  // RENDERING
  // ==========================================================================

  render() {
    const placeholder = this.getAttribute('placeholder') || 'Rechercher...';
    const emptyText = this.getAttribute('empty-text') || 'Aucun résultat';
    const isDisabled = this.hasAttribute('disabled');

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
          position: relative;
          font-family: inherit;
        }
        :host([disabled]) {
          opacity: 0.6;
          pointer-events: none;
        }

        .select-container {
          position: relative;
        }

        .select-trigger {
          display: flex;
          align-items: center;
          gap: 0.5rem;
          width: 100%;
          min-height: 2.5rem;
          padding: 0.5rem 2.5rem 0.5rem 0.75rem;
          background: var(--color-surface, #ffffff);
          border: 2px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-md, 8px);
          cursor: pointer;
          transition: border-color 0.15s, background-color 0.15s;
          font-size: 0.875rem;
          color: var(--color-text, #4e5340);
          box-sizing: border-box;
        }
        .select-trigger:hover {
          border-color: var(--color-border-hover, #a0a897);
        }
        .select-trigger:focus-within {
          outline: none;
          border-color: var(--color-primary, #5a7a5b);
          background-color: var(--color-surface, #ffffff);
          /* No box-shadow - border change is the focus indicator */
        }
        .select-trigger.open {
          border-color: var(--color-primary, #5a7a5b);
          border-bottom-left-radius: 0;
          border-bottom-right-radius: 0;
        }

        .select-value {
          flex: 1;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }
        .select-value.placeholder {
          color: var(--color-text-muted, #7a8275);
        }

        .select-arrow {
          position: absolute;
          right: 0.75rem;
          top: 50%;
          transform: translateY(-50%);
          width: 1rem;
          height: 1rem;
          pointer-events: none;
          transition: transform 0.15s;
        }
        .select-trigger.open .select-arrow {
          transform: translateY(-50%) rotate(180deg);
        }

        .dropdown {
          display: none;
          position: absolute;
          top: 100%;
          left: 0;
          right: 0;
          background: var(--color-surface, #ffffff);
          border: 1px solid var(--color-primary, #5a7a5b);
          border-top: none;
          border-radius: 0 0 var(--radius-md, 8px) var(--radius-md, 8px);
          box-shadow: var(--shadow-lg, 0 10px 25px rgba(0,0,0,0.1));
          z-index: 1000;
          max-height: 300px;
          overflow: hidden;
          flex-direction: column;
        }
        .dropdown.open {
          display: flex;
        }

        .search-box {
          padding: 0.5rem;
          border-bottom: 1px solid var(--color-border, #d5dbd2);
          position: sticky;
          top: 0;
          background: var(--color-surface, #ffffff);
        }

        .search-input {
          width: 100%;
          max-width: 100%;
          padding: 0.5rem 0.75rem 0.5rem 2rem;
          border: 1px solid var(--color-border, #d5dbd2);
          border-radius: var(--radius-sm, 6px);
          font-size: 0.875rem;
          background: var(--color-bg-subtle, #f5f7f4);
          transition: border-color 0.15s;
          box-sizing: border-box;
        }
        .search-input:focus {
          outline: none;
          border-color: var(--color-primary, #5a7a5b);
          background: var(--color-surface, #ffffff);
          box-shadow: none; /* No additional ring to prevent overflow */
        }
        .search-input::placeholder {
          color: var(--color-text-muted, #7a8275);
        }

        .search-icon {
          position: absolute;
          left: 1rem;
          top: 50%;
          transform: translateY(-50%);
          width: 1rem;
          height: 1rem;
          color: var(--color-text-muted, #7a8275);
          pointer-events: none;
        }

        .options-list {
          overflow-y: auto;
          flex: 1;
          max-height: 250px;
        }

        .option {
          display: flex;
          flex-direction: column;
          gap: 0.125rem;
          padding: 0.625rem 0.75rem;
          cursor: pointer;
          transition: background-color 0.1s;
        }
        .option:hover,
        .option.highlighted {
          background: var(--color-bg-subtle, #f5f7f4);
        }
        .option.selected {
          background: var(--color-primary-subtle, #e8f0e8);
        }
        .option.disabled {
          opacity: 0.5;
          cursor: not-allowed;
        }

        .option-label {
          font-size: 0.875rem;
          color: var(--color-text, #4e5340);
          line-height: 1.3;
        }
        .option-sublabel {
          font-size: 0.75rem;
          color: var(--color-text-muted, #7a8275);
        }

        .group-header {
          padding: 0.5rem 0.75rem 0.25rem;
          font-size: 0.6875rem;
          font-weight: 600;
          text-transform: uppercase;
          letter-spacing: 0.05em;
          color: var(--color-text-muted, #7a8275);
          background: var(--color-bg-subtle, #f5f7f4);
          position: sticky;
          top: 0;
        }

        .empty-state {
          padding: 1.5rem;
          text-align: center;
          color: var(--color-text-muted, #7a8275);
          font-size: 0.875rem;
        }

        .clear-btn {
          position: absolute;
          right: 2rem;
          top: 50%;
          transform: translateY(-50%);
          width: 1.25rem;
          height: 1.25rem;
          padding: 0;
          border: none;
          background: transparent;
          cursor: pointer;
          color: var(--color-text-muted, #7a8275);
          opacity: 0.6;
          transition: opacity 0.15s;
          display: none;
          align-items: center;
          justify-content: center;
        }
        .clear-btn:hover {
          opacity: 1;
        }
        .select-trigger.has-value .clear-btn {
          display: flex;
        }

        mark {
          background: var(--color-warning-subtle, #fff3cd);
          color: inherit;
          padding: 0;
          border-radius: 2px;
        }

      </style>

      <div class="select-container">
        <div class="select-trigger" tabindex="0" role="combobox" aria-haspopup="listbox" aria-expanded="false" ${isDisabled ? 'aria-disabled="true"' : ''}>
          <span class="select-value placeholder">${this.escapeHtml(placeholder)}</span>
          <button type="button" class="clear-btn" aria-label="Effacer" tabindex="-1">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
          <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </div>

        <div class="dropdown" role="listbox">
          <div class="search-box">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" class="search-input" placeholder="${this.escapeHtml(placeholder)}" autocomplete="off" />
          </div>
          <div class="options-list"></div>
        </div>
      </div>

      <slot></slot>
    `;

    this.renderOptionsList();
    this.updateSelectedDisplay();
  }

  renderOptionsList() {
    const listEl = this.shadowRoot?.querySelector('.options-list');
    if (!listEl) return;

    const emptyText = this.getAttribute('empty-text') || 'Aucun résultat';

    if (!this._filteredOptions.length) {
      listEl.innerHTML = `<div class="empty-state">${this.escapeHtml(emptyText)}</div>`;
      return;
    }

    // Group options if they have a group property
    const grouped = this.groupOptions(this._filteredOptions);
    let html = '';

    for (const [groupName, options] of Object.entries(grouped)) {
      if (groupName && groupName !== '__ungrouped__') {
        html += `<div class="group-header">${this.escapeHtml(groupName)}</div>`;
      }

      for (let i = 0; i < options.length; i++) {
        const opt = options[i];
        const globalIdx = this._filteredOptions.indexOf(opt);
        const isSelected = String(opt.value) === String(this._value);
        const isHighlighted = globalIdx === this._highlightedIndex;
        const isDisabled = opt.disabled;

        const classes = ['option'];
        if (isSelected) classes.push('selected');
        if (isHighlighted) classes.push('highlighted');
        if (isDisabled) classes.push('disabled');

        const labelHtml = opt._labelMatches
          ? this.highlightMatches(opt.label, opt._labelMatches)
          : this.escapeHtml(opt.label);

        const sublabelHtml = opt.sublabel
          ? (opt._sublabelMatches
            ? this.highlightMatches(opt.sublabel, opt._sublabelMatches)
            : this.escapeHtml(opt.sublabel))
          : '';

        html += `
          <div class="${classes.join(' ')}"
               data-value="${this.escapeHtml(String(opt.value))}"
               data-index="${globalIdx}"
               role="option"
               aria-selected="${isSelected}">
            <span class="option-label">${labelHtml}</span>
            ${sublabelHtml ? `<span class="option-sublabel">${sublabelHtml}</span>` : ''}
          </div>
        `;
      }
    }

    listEl.innerHTML = html;
  }

  groupOptions(options) {
    const grouped = {};
    for (const opt of options) {
      const group = opt.group || '__ungrouped__';
      if (!grouped[group]) grouped[group] = [];
      grouped[group].push(opt);
    }
    return grouped;
  }

  updateSelectedDisplay() {
    const valueEl = this.shadowRoot?.querySelector('.select-value');
    const triggerEl = this.shadowRoot?.querySelector('.select-trigger');
    if (!valueEl || !triggerEl) return;

    const placeholder = this.getAttribute('placeholder') || 'Rechercher...';
    const selectedOpt = this._options.find(o => String(o.value) === String(this._value));

    if (selectedOpt) {
      valueEl.textContent = selectedOpt.label;
      valueEl.classList.remove('placeholder');
      triggerEl.classList.add('has-value');
      this._selectedLabel = selectedOpt.label;
    } else {
      valueEl.textContent = placeholder;
      valueEl.classList.add('placeholder');
      triggerEl.classList.remove('has-value');
      this._selectedLabel = '';
    }
  }

  updateDisabledState() {
    const trigger = this.shadowRoot?.querySelector('.select-trigger');
    if (trigger) {
      trigger.setAttribute('aria-disabled', this.hasAttribute('disabled') ? 'true' : 'false');
    }
  }

  // ==========================================================================
  // EVENT HANDLING
  // ==========================================================================

  setupEventListeners() {
    const trigger = this.shadowRoot.querySelector('.select-trigger');
    const dropdown = this.shadowRoot.querySelector('.dropdown');
    const searchInput = this.shadowRoot.querySelector('.search-input');
    const clearBtn = this.shadowRoot.querySelector('.clear-btn');
    const optionsList = this.shadowRoot.querySelector('.options-list');

    // Toggle dropdown on trigger click
    trigger.addEventListener('click', (e) => {
      if (e.target.closest('.clear-btn')) return;
      this.toggleDropdown();
    });

    // Clear button
    clearBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      this.clear();
    });

    // Search input
    searchInput.addEventListener('input', (e) => {
      this._searchTerm = e.target.value;
      this.filterOptions(this._searchTerm);
      this._highlightedIndex = this._filteredOptions.length > 0 ? 0 : -1;
      this.renderOptionsList();
    });

    // Keyboard navigation
    searchInput.addEventListener('keydown', (e) => this.handleKeydown(e));
    trigger.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
        e.preventDefault();
        this.openDropdown();
      }
    });

    // Option selection
    optionsList.addEventListener('click', (e) => {
      const optionEl = e.target.closest('.option');
      if (optionEl && !optionEl.classList.contains('disabled')) {
        this.selectOption(optionEl.dataset.value);
      }
    });

    // Close on outside click
    this._outsideClickHandler = (e) => {
      if (!this.contains(e.target) && !this.shadowRoot.contains(e.target)) {
        this.closeDropdown();
      }
    };
    document.addEventListener('click', this._outsideClickHandler);

    // Close on escape
    this._escapeHandler = (e) => {
      if (e.key === 'Escape' && this._isOpen) {
        this.closeDropdown();
      }
    };
    document.addEventListener('keydown', this._escapeHandler);
  }

  removeEventListeners() {
    if (this._outsideClickHandler) {
      document.removeEventListener('click', this._outsideClickHandler);
    }
    if (this._escapeHandler) {
      document.removeEventListener('keydown', this._escapeHandler);
    }
  }

  handleKeydown(e) {
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        this.highlightNext();
        break;
      case 'ArrowUp':
        e.preventDefault();
        this.highlightPrev();
        break;
      case 'Enter':
        e.preventDefault();
        if (this._highlightedIndex >= 0 && this._filteredOptions[this._highlightedIndex]) {
          this.selectOption(this._filteredOptions[this._highlightedIndex].value);
        }
        break;
      case 'Escape':
        e.preventDefault();
        this.closeDropdown();
        break;
    }
  }

  highlightNext() {
    if (this._filteredOptions.length === 0) return;
    this._highlightedIndex = (this._highlightedIndex + 1) % this._filteredOptions.length;
    this.updateHighlight();
  }

  highlightPrev() {
    if (this._filteredOptions.length === 0) return;
    this._highlightedIndex = this._highlightedIndex <= 0
      ? this._filteredOptions.length - 1
      : this._highlightedIndex - 1;
    this.updateHighlight();
  }

  updateHighlight() {
    const options = this.shadowRoot.querySelectorAll('.option');
    options.forEach((el, idx) => {
      el.classList.toggle('highlighted', idx === this._highlightedIndex);
    });

    // Scroll highlighted option into view
    const highlighted = this.shadowRoot.querySelector('.option.highlighted');
    if (highlighted) {
      highlighted.scrollIntoView({ block: 'nearest' });
    }
  }

  // ==========================================================================
  // DROPDOWN CONTROL
  // ==========================================================================

  toggleDropdown() {
    if (this._isOpen) {
      this.closeDropdown();
    } else {
      this.openDropdown();
    }
  }

  openDropdown() {
    if (this.hasAttribute('disabled')) return;

    this._isOpen = true;
    const trigger = this.shadowRoot.querySelector('.select-trigger');
    const dropdown = this.shadowRoot.querySelector('.dropdown');
    const searchInput = this.shadowRoot.querySelector('.search-input');

    trigger.classList.add('open');
    trigger.setAttribute('aria-expanded', 'true');
    dropdown.classList.add('open');

    // Reset search and show all options
    this._searchTerm = '';
    searchInput.value = '';
    this.filterOptions('');
    this._highlightedIndex = this._filteredOptions.findIndex(
      o => String(o.value) === String(this._value)
    );
    if (this._highlightedIndex < 0 && this._filteredOptions.length > 0) {
      this._highlightedIndex = 0;
    }
    this.renderOptionsList();

    // Focus search input
    setTimeout(() => {
      searchInput.focus();
      this.updateHighlight();
    }, 10);
  }

  closeDropdown() {
    this._isOpen = false;
    const trigger = this.shadowRoot.querySelector('.select-trigger');
    const dropdown = this.shadowRoot.querySelector('.dropdown');

    trigger.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
    dropdown.classList.remove('open');

    // Return focus to trigger
    trigger.focus();
  }

  selectOption(value) {
    const option = this._options.find(o => String(o.value) === String(value));
    if (!option || option.disabled) return;

    this._value = String(value);
    this._selectedLabel = option.label;
    this.setAttribute('value', this._value);
    this.updateSelectedDisplay();
    this.closeDropdown();

    if (this._internals) {
      this._internals.setFormValue(this._value);
    }

    this.dispatchEvent(new CustomEvent('change', {
      detail: { value: this._value, label: option.label, option },
      bubbles: true
    }));
  }

  // ==========================================================================
  // UTILITIES
  // ==========================================================================

  escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
}

// Form-associated custom element
if ('attachInternals' in HTMLElement.prototype) {
  AgSearchableSelect.formAssociated = true;
}

customElements.define('ag-searchable-select', AgSearchableSelect);

// Global export
window.AgSearchableSelect = AgSearchableSelect;

export default AgSearchableSelect;
