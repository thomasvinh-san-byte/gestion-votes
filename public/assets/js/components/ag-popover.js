/**
 * AG-VOTE Popover Component
 *
 * A lightweight popover for contextual help and information.
 *
 * Usage:
 *   <ag-popover>
 *     <button slot="trigger">?</button>
 *     <div slot="content">
 *       <strong>Titre</strong>
 *       <p>Description détaillée...</p>
 *     </div>
 *   </ag-popover>
 *
 *   <!-- Or with inline attributes -->
 *   <ag-popover title="Politique de quorum" content="Définit le nombre minimum de participants...">
 *     <button slot="trigger" class="popover-trigger">?</button>
 *   </ag-popover>
 *
 * Attributes:
 *   - title: Popover title (optional, can use slot instead)
 *   - content: Popover content text (optional, can use slot instead)
 *   - position: Positioning (top|bottom|left|right, default: top)
 *   - trigger: Trigger event (hover|click|focus, default: hover)
 *   - width: Max width in pixels (default: 280)
 */
class AgPopover extends HTMLElement {
  static get observedAttributes() {
    return ['title', 'content', 'position', 'trigger', 'width'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this._isOpen = false;
    this._hideTimeout = null;
  }

  connectedCallback() {
    this.render();
    this.setupEventListeners();
  }

  disconnectedCallback() {
    this.removeEventListeners();
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (oldValue !== newValue && this.shadowRoot.innerHTML) {
      this.render();
      this.setupEventListeners();
    }
  }

  get triggerMode() {
    return this.getAttribute('trigger') || 'hover';
  }

  get position() {
    return this.getAttribute('position') || 'top';
  }

  setupEventListeners() {
    const triggerSlot = this.shadowRoot.querySelector('slot[name="trigger"]');
    const trigger = triggerSlot?.assignedElements()[0] || this.querySelector('[slot="trigger"]');
    const popover = this.shadowRoot.querySelector('.popover');

    if (!trigger || !popover) return;

    this.removeEventListeners();

    this._handlers = {
      show: () => this.show(),
      hide: () => this.hide(),
      toggle: (e) => {
        e.stopPropagation();
        this.toggle();
      },
      keydown: (e) => {
        if (e.key === 'Escape' && this._isOpen) {
          this.hide();
          trigger.focus();
        }
      },
      clickOutside: (e) => {
        if (this._isOpen && !this.contains(e.target)) {
          this.hide();
        }
      }
    };

    if (this.triggerMode === 'hover') {
      trigger.addEventListener('mouseenter', this._handlers.show);
      trigger.addEventListener('mouseleave', this._handlers.hide);
      trigger.addEventListener('focus', this._handlers.show);
      trigger.addEventListener('blur', this._handlers.hide);
      popover.addEventListener('mouseenter', () => {
        clearTimeout(this._hideTimeout);
      });
      popover.addEventListener('mouseleave', this._handlers.hide);
    } else if (this.triggerMode === 'click') {
      trigger.addEventListener('click', this._handlers.toggle);
      document.addEventListener('click', this._handlers.clickOutside);
    } else if (this.triggerMode === 'focus') {
      trigger.addEventListener('focus', this._handlers.show);
      trigger.addEventListener('blur', this._handlers.hide);
    }

    document.addEventListener('keydown', this._handlers.keydown);
  }

  removeEventListeners() {
    if (this._handlers) {
      document.removeEventListener('keydown', this._handlers.keydown);
      document.removeEventListener('click', this._handlers.clickOutside);
    }
  }

  show() {
    clearTimeout(this._hideTimeout);
    const popover = this.shadowRoot.querySelector('.popover');
    if (popover) {
      popover.classList.add('visible');
      popover.setAttribute('aria-hidden', 'false');
      this._isOpen = true;
      this.positionPopover();
    }
  }

  hide() {
    this._hideTimeout = setTimeout(() => {
      const popover = this.shadowRoot.querySelector('.popover');
      if (popover) {
        popover.classList.remove('visible');
        popover.setAttribute('aria-hidden', 'true');
        this._isOpen = false;
      }
    }, 100);
  }

  toggle() {
    if (this._isOpen) {
      this.hide();
    } else {
      this.show();
    }
  }

  positionPopover() {
    const popover = this.shadowRoot.querySelector('.popover');
    const trigger = this.querySelector('[slot="trigger"]');
    if (!popover || !trigger) return;

    const triggerRect = trigger.getBoundingClientRect();
    const popoverRect = popover.getBoundingClientRect();
    const viewport = {
      width: window.innerWidth,
      height: window.innerHeight
    };

    // Reset positioning classes
    popover.classList.remove('pos-top', 'pos-bottom', 'pos-left', 'pos-right');

    let pos = this.position;

    // Auto-adjust if not enough space
    if (pos === 'top' && triggerRect.top < popoverRect.height + 10) {
      pos = 'bottom';
    } else if (pos === 'bottom' && triggerRect.bottom + popoverRect.height + 10 > viewport.height) {
      pos = 'top';
    } else if (pos === 'left' && triggerRect.left < popoverRect.width + 10) {
      pos = 'right';
    } else if (pos === 'right' && triggerRect.right + popoverRect.width + 10 > viewport.width) {
      pos = 'left';
    }

    popover.classList.add(`pos-${pos}`);
  }

  render() {
    const title = this.getAttribute('title') || '';
    const content = this.getAttribute('content') || '';
    const width = this.getAttribute('width') || '280';

    const titleHtml = title ? `<div class="popover-title">${title}</div>` : '';
    const contentHtml = content ? `<div class="popover-text">${content}</div>` : '';
    const hasInlineContent = title || content;

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: inline-flex;
          position: relative;
          vertical-align: middle;
        }

        .trigger-wrapper {
          display: inline-flex;
          cursor: help;
        }

        .popover {
          position: absolute;
          z-index: 1000;
          max-width: ${width}px;
          padding: 0.75rem 1rem;
          background: var(--color-surface, #fff);
          border: 1px solid var(--color-border, #d1d5db);
          border-radius: 8px;
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
          font-size: 0.875rem;
          line-height: 1.5;
          color: var(--color-text, #1f2937);
          opacity: 0;
          visibility: hidden;
          transform: scale(0.95);
          transition: opacity 0.15s ease, transform 0.15s ease, visibility 0.15s;
          pointer-events: none;
        }

        .popover.visible {
          opacity: 1;
          visibility: visible;
          transform: scale(1);
          pointer-events: auto;
        }

        /* Positioning */
        .popover.pos-top {
          bottom: calc(100% + 8px);
          left: 50%;
          transform-origin: bottom center;
        }
        .popover.pos-top.visible {
          transform: translateX(-50%) scale(1);
        }
        .popover.pos-top {
          transform: translateX(-50%) scale(0.95);
        }

        .popover.pos-bottom {
          top: calc(100% + 8px);
          left: 50%;
          transform-origin: top center;
        }
        .popover.pos-bottom.visible {
          transform: translateX(-50%) scale(1);
        }
        .popover.pos-bottom {
          transform: translateX(-50%) scale(0.95);
        }

        .popover.pos-left {
          right: calc(100% + 8px);
          top: 50%;
          transform-origin: right center;
        }
        .popover.pos-left.visible {
          transform: translateY(-50%) scale(1);
        }
        .popover.pos-left {
          transform: translateY(-50%) scale(0.95);
        }

        .popover.pos-right {
          left: calc(100% + 8px);
          top: 50%;
          transform-origin: left center;
        }
        .popover.pos-right.visible {
          transform: translateY(-50%) scale(1);
        }
        .popover.pos-right {
          transform: translateY(-50%) scale(0.95);
        }

        /* Arrow */
        .popover::after {
          content: '';
          position: absolute;
          width: 8px;
          height: 8px;
          background: var(--color-surface, #fff);
          border: 1px solid var(--color-border, #d1d5db);
          transform: rotate(45deg);
        }

        .popover.pos-top::after {
          bottom: -5px;
          left: 50%;
          margin-left: -4px;
          border-top: none;
          border-left: none;
        }

        .popover.pos-bottom::after {
          top: -5px;
          left: 50%;
          margin-left: -4px;
          border-bottom: none;
          border-right: none;
        }

        .popover.pos-left::after {
          right: -5px;
          top: 50%;
          margin-top: -4px;
          border-bottom: none;
          border-left: none;
        }

        .popover.pos-right::after {
          left: -5px;
          top: 50%;
          margin-top: -4px;
          border-top: none;
          border-right: none;
        }

        /* Content styling */
        .popover-title {
          font-weight: 600;
          margin-bottom: 0.375rem;
          color: var(--color-text, #1f2937);
        }

        .popover-text {
          color: var(--color-text-muted, #6b7280);
        }

        ::slotted([slot="content"]) {
          display: block;
        }

        ::slotted([slot="content"] strong),
        ::slotted([slot="content"] b) {
          display: block;
          font-weight: 600;
          margin-bottom: 0.375rem;
        }

        ::slotted([slot="content"] p) {
          margin: 0;
          color: var(--color-text-muted, #6b7280);
        }

        /* Default trigger button style */
        .default-trigger {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          width: 18px;
          height: 18px;
          border-radius: 50%;
          border: 1px solid var(--color-border, #d1d5db);
          background: var(--color-bg-subtle, #f3f4f6);
          color: var(--color-text-muted, #6b7280);
          font-size: 0.75rem;
          font-weight: 600;
          cursor: help;
          transition: all 0.15s;
        }

        .default-trigger:hover,
        .default-trigger:focus {
          background: var(--color-primary-subtle, #e0e7ff);
          border-color: var(--color-primary, #4f46e5);
          color: var(--color-primary, #4f46e5);
          outline: none;
        }
      </style>

      <div class="trigger-wrapper">
        <slot name="trigger">
          <button class="default-trigger" aria-label="Plus d'informations" type="button">?</button>
        </slot>
      </div>

      <div class="popover pos-${this.position}" role="tooltip" aria-hidden="true">
        ${hasInlineContent ? `
          ${titleHtml}
          ${contentHtml}
        ` : `
          <slot name="content"></slot>
        `}
      </div>
    `;
  }
}

customElements.define('ag-popover', AgPopover);

export default AgPopover;
