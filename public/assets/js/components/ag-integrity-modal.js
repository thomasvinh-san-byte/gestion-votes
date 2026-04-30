/**
 * AG-VOTE Integrity Modal — EDITORIAL-05
 *
 * Custom element <ag-integrity-modal> qui ouvre un modal pédagogique d'intégrité
 * du procès-verbal. Affiche D'ABORD un préambule en français VERBATIM (rassurant,
 * sans jargon), PUIS la chaîne audit_events (sceaux cryptographiques) en monospace.
 *
 * Usage :
 *   <ag-integrity-modal
 *     data-date="2026-04-30"
 *     data-events='[{"hash":"abc...","prev":"def...","at":"2026-04-30T10:00:00Z"}]'>
 *   </ag-integrity-modal>
 *
 *   document.querySelector('ag-integrity-modal').open();
 *
 * Attributes (observés) :
 *   - data-date    : date du PV (substituée dans le préambule, HTML-escapée)
 *   - data-events  : JSON array d'objets {hash, prev, at} (HTML-escapés en rendu)
 *
 * Sécurité : toute valeur user-injected (date, hash, prev, at) est HTML-escapée
 * via _escape() avant insertion dans innerHTML. Aucun eval / Function / setTimeout(string).
 *
 * B-2 fix : le modal est auto-hidden dans connectedCallback. Sans ce verrou,
 * un utilisateur qui charge la page voit le modal flasher avant que le hidden
 * CSS ne s'applique. Pour ouvrir : appeler .open() (clic "Vérifier l'intégrité").
 *
 * Light DOM volontaire : la cascade laisse passer les tokens design-system.css
 * et le stylesheet companion (ag-integrity-modal.css) cible directement les classes BEM.
 */

class AgIntegrityModal extends HTMLElement {
  static get observedAttributes() {
    return ['data-date', 'data-events'];
  }

  connectedCallback() {
    // B-2 fix : auto-hidden par défaut. Le modal n'apparaît qu'après .open()
    // explicite (clic sur "Vérifier l'intégrité"). Sans ce verrou, un user
    // qui charge la page voit le modal flasher avant le hidden CSS.
    if (!this.hasAttribute('hidden') && !this.hasAttribute('data-keep-open')) {
      this.setAttribute('hidden', '');
    }
    this.classList.add('ag-integrity-modal');
    if (!this.hasAttribute('data-rendered')) {
      this._render();
      this.setAttribute('data-rendered', '');
    }
    this._bindKeyboard();
  }

  disconnectedCallback() {
    if (this._onKeyDown) {
      document.removeEventListener('keydown', this._onKeyDown);
    }
  }

  attributeChangedCallback(name, oldValue, newValue) {
    if (oldValue !== newValue && this.isConnected) {
      this._render();
    }
  }

  open() {
    this.removeAttribute('hidden');
    this._previouslyFocused = document.activeElement;
    this._trapFocus();
  }

  close() {
    this.setAttribute('hidden', '');
    this._releaseFocus();
  }

  _escape(str) {
    // HTML-escape pour XSS prevention — toutes les valeurs user-injected
    // (date, ev.hash, ev.prev, ev.at) DOIVENT passer par cette fonction
    // avant insertion dans innerHTML.
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  _render() {
    const date = this._escape(this.getAttribute('data-date') || '');
    let events = [];
    try {
      events = JSON.parse(this.getAttribute('data-events') || '[]');
      if (!Array.isArray(events)) events = [];
    } catch (e) {
      events = [];
    }

    // PRÉAMBULE VERBATIM EDITORIAL-05 — ne pas modifier un seul caractère.
    // Apostrophes typographiques (’) telles que présentes dans REQUIREMENTS.md.
    const preambleHtml =
      '<p class="ag-integrity-preamble">' +
      "Voici la preuve que ce PV n'a pas été modifié depuis le " + date + '. ' +
      'Chaque ligne ci-dessous est un sceau cryptographique reliant la précédente — ' +
      'modifier une seule virgule briserait la chaîne.' +
      '</p>';

    const eventsHtml = events.map((ev) => {
      const hash = this._escape(ev && ev.hash);
      const prev = this._escape((ev && ev.prev) || '∅');
      const at = this._escape((ev && ev.at) || '');
      return (
        '<li class="ag-integrity-event">' +
          '<code class="ag-integrity-hash">' + hash + '</code>' +
          '<span class="ag-integrity-meta">' +
            '← ' + prev + ' · ' + at +
          '</span>' +
        '</li>'
      );
    }).join('');

    this.innerHTML =
      '<div class="ag-integrity-modal__overlay" data-close></div>' +
      '<div class="ag-integrity-modal__dialog" role="dialog" aria-modal="true" ' +
           'aria-labelledby="ag-integrity-title" tabindex="-1">' +
        '<header>' +
          '<h2 id="ag-integrity-title">Intégrité du procès-verbal</h2>' +
          '<button type="button" class="ag-integrity-close" data-close ' +
                  'aria-label="Fermer">×</button>' +
        '</header>' +
        preambleHtml +
        '<ol class="ag-integrity-chain">' + eventsHtml + '</ol>' +
      '</div>';

    this.querySelectorAll('[data-close]').forEach((el) => {
      el.addEventListener('click', () => this.close());
    });
  }

  _bindKeyboard() {
    if (this._onKeyDown) return;
    this._onKeyDown = (e) => {
      if (e.key === 'Escape' && !this.hasAttribute('hidden')) {
        this.close();
      }
    };
    document.addEventListener('keydown', this._onKeyDown);
  }

  _trapFocus() {
    const dialog = this.querySelector('.ag-integrity-modal__dialog');
    if (dialog && typeof dialog.focus === 'function') {
      dialog.focus();
    }
  }

  _releaseFocus() {
    if (this._previouslyFocused && typeof this._previouslyFocused.focus === 'function') {
      try { this._previouslyFocused.focus(); } catch (e) { /* élément retiré du DOM */ }
    }
    this._previouslyFocused = null;
  }
}

customElements.define('ag-integrity-modal', AgIntegrityModal);
