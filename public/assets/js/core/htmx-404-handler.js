/**
 * htmx-404-handler.js — RACE-V27-01
 *
 * Listener global qui intercepte les réponses HTMX 404 dont le body JSON
 * contient un code d'erreur reconnu (meeting_not_found, motion_not_found)
 * et substitue le contenu cible par un <ag-empty-state variant="resource-deleted">
 * au lieu du toast d'erreur rouge générique.
 *
 * Utilise htmx:beforeOnLoad (avant la décision swap/error de HTMX) plutôt que
 * htmx:responseError pour empêcher le toast générique de se déclencher
 * (e.detail.isError = false).
 *
 * Loading strategy
 * ----------------
 * Ce fichier est la **source de vérité** du handler 404 graceful. Il est
 * indépendant et auto-enregistrable (IIFE). Cependant, parce que utils.js est
 * déjà chargé par 21 templates HTMX, la même logique a été inlinée dans
 * utils.js (en tête de son IIFE, avant le handler générique htmx:responseError
 * lignes ~339-345) pour éviter d'éditer chaque template. Si on souhaite à
 * l'avenir charger ce fichier directement (ex. test isolé, refactor du
 * pipeline JS), inclure :
 *   <script src="/assets/js/core/htmx-404-handler.js" nonce="..."></script>
 * AVANT utils.js. Dans ce cas, retirer le bloc inliné dans utils.js (sinon
 * double registration → double hijack).
 *
 * Threat model : T-03-01 mitigated via escapeHtml() — voir 03-01-PLAN.md.
 */
(function () {
  'use strict';

  var KNOWN_404_CODES = {
    'meeting_not_found': {
      fallbackMessage: 'Cette séance n\'existe plus.',
      ctaLabel: 'Retour aux séances',
      ctaHref: '/dashboard.htmx.html'
    },
    'motion_not_found': {
      fallbackMessage: 'Cette résolution n\'existe plus.',
      ctaLabel: 'Retour à la séance',
      ctaHref: '/operator'
    }
  };

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function renderEmptyStateHtml(code, message) {
    var cfg = KNOWN_404_CODES[code];
    var msg = message || cfg.fallbackMessage;
    return '<ag-empty-state variant="resource-deleted"'
      + ' title="' + escapeHtml(msg) + '"'
      + ' description=""'
      + ' action-label="' + escapeHtml(cfg.ctaLabel) + '"'
      + ' action-href="' + escapeHtml(cfg.ctaHref) + '">'
      + '</ag-empty-state>';
  }

  document.body.addEventListener('htmx:beforeOnLoad', function (e) {
    var xhr = e.detail && e.detail.xhr;
    if (!xhr || xhr.status !== 404) return;

    var body = null;
    try { body = JSON.parse(xhr.responseText); } catch (_) { return; }

    var code = body && body.error;
    if (!code || !KNOWN_404_CODES[code]) return;

    // Hijack the response: tell HTMX to swap our empty-state instead.
    e.detail.shouldSwap = true;
    e.detail.isError = false; // empêche htmx:responseError + toast générique
    e.detail.serverResponse = renderEmptyStateHtml(code, body.message);
  });
})();
