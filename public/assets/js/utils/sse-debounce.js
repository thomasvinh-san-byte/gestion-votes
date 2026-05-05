/* global module */
/**
 * sse-debounce.js — Debounce helper pour handlers SSE empty-state (Plan 02.2 / ERR-V24-02)
 *
 * Pattern setTimeout/clearTimeout vanilla — aucune dependance externe. Cible :
 *   - <ag-integrity-modal> : debounce sur attributeChangedCallback (data-events / data-date)
 *   - Dashboard hero card live (v2.5+) : meme contrat des qu'un emetteur SSE est cable
 *
 * Usage :
 *   import { createDebouncedSseHandler } from '/assets/js/utils/sse-debounce.js';
 *   const handler = createDebouncedSseHandler(this, () => this._render(), () => this._debounceMs);
 *   handler(event);
 *
 * Ou via namespace global (script classique) :
 *   var handler = window.AgSseDebounce.create(this, function () { self._render(); }, function () { return 250; });
 *
 * Contrat :
 *   - target    : HTMLElement — recoit `data-render-count` incremente apres chaque render effectif
 *   - renderFn  : function — callable a invoquer apres la fenetre debounce
 *   - getMs     : function returning number — lu a chaque event (permet override live via data-sse-debounce-ms)
 *   - retourne une fonction debounced (signature `(event?) => void`).
 *
 * Idempotence : 5 events en 100ms avec debounceMs=250 → 1 seul render, data-render-count incremente de 1.
 *
 * Quand l'utiliser :
 *   - Handlers SSE haute frequence sur composants empty-state ou metriques live
 *   - Custom elements re-rendus par mutation d'attribut observed
 *
 * Quand NE PAS l'utiliser :
 *   - Handlers SSE bas frequence type quorum-state mirror v2.3 P1 (deja testes OK sans debounce)
 *   - Updates necessitant feedback temps-reel < 250ms (input typing, etc.)
 *
 * a11y : aucune animation introduite. `prefers-reduced-motion` respecte par construction
 * (debounce silencieux, pas de transition CSS ajoutee).
 */
(function (global) {
  'use strict';

  /**
   * @param {HTMLElement} target  Element qui recoit data-render-count incremente.
   * @param {Function} renderFn   Callback appele apres la fenetre debounce.
   * @param {Function} [getMs]    Function retournant la duree debounce en ms (default 250).
   * @returns {Function}          Handler debounced (event) => void.
   */
  function createDebouncedSseHandler(target, renderFn, getMs) {
    if (!target || typeof renderFn !== 'function') {
      throw new TypeError('createDebouncedSseHandler: target HTMLElement and renderFn required');
    }
    var resolveMs = typeof getMs === 'function' ? getMs : function () { return 250; };
    var timer = null;

    return function debouncedHandler(event) {
      if (timer) {
        clearTimeout(timer);
      }
      var ms = resolveMs();
      if (typeof ms !== 'number' || isNaN(ms) || ms < 0) {
        ms = 250;
      }
      timer = setTimeout(function () {
        try {
          renderFn(event);
        } finally {
          var current = parseInt(target.getAttribute('data-render-count') || '0', 10);
          if (isNaN(current)) current = 0;
          target.setAttribute('data-render-count', String(current + 1));
          timer = null;
        }
      }, ms);
    };
  }

  // Expose pour modules ES + scripts classiques
  var api = { create: createDebouncedSseHandler, createDebouncedSseHandler: createDebouncedSseHandler };
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  global.AgSseDebounce = api;
})(typeof window !== 'undefined' ? window : globalThis);
