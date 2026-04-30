/**
 * operator-keybindings.js — Raccourcis clavier du cockpit opérateur (vue exécution).
 *
 * Raccourcis pris en charge :
 *   L            → Lancer le vote actif (clic sur #opBtnLaunchVote, fallback #opBtnToggleVote)
 *   F            → Fermer le scrutin actif (clic sur #opBtnCloseVote, fallback #opBtnToggleVote)
 *   →  ou  N     → Résolution suivante (O.fn.selectNextMotion ou .op-agenda-item suivant non voté)
 *   ?            → Afficher / masquer l'aide des raccourcis (overlay #agShortcutsOverlay)
 *   Échap        → Fermer l'overlay s'il est visible
 *
 * Anti-trap (COCKPIT-06) : aucun raccourci ne se déclenche si :
 *   - le focus est dans un <input>, <textarea>, <select> ou un élément contenteditable,
 *   - une touche modificateur est tenue (meta / ctrl / alt),
 *   - hors mode 'exec' (sauf '?', qui reste accessible comme aide en lecture).
 *
 * Charge unique : le sentinel window.AG_OPERATOR_KEYBINDINGS empêche tout double bind
 * en cas de chargement multiple du script. Aucune autre pollution globale.
 *
 * Plan 01.3 câblera ce module dans operator.htmx.html via une balise <script>
 * et supprimera le keydown handler existant de operator-exec.js (lignes 417-436).
 */
(function() {
  'use strict';

  if (window.AG_OPERATOR_KEYBINDINGS) return;
  window.AG_OPERATOR_KEYBINDINGS = true;

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  /**
   * Renvoie true si l'utilisateur est en train de saisir du texte.
   * Inclut input/textarea/select ET les éléments contenteditable (fix COCKPIT-06).
   */
  function _isTypingContext() {
    var el = document.activeElement;
    if (!el) return false;
    var tag = (el.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
    if (el.isContentEditable) return true;
    return false;
  }

  /**
   * Renvoie true si on est en mode exécution (ou si l'objet O n'existe pas,
   * pour faciliter les tests unitaires hors page).
   */
  function _isExecMode() {
    return !window.O || window.O.currentMode === 'exec';
  }

  /**
   * Clique sur un bouton si présent et activable.
   * @returns {boolean} true si le clic a eu lieu, false sinon (laissant la place au fallback).
   */
  function _click(id) {
    var btn = document.getElementById(id);
    if (!btn) return false;
    if (btn.disabled) return false;
    if (btn.hidden) return false;
    if (btn.getAttribute('aria-hidden') === 'true') return false;
    btn.click();
    return true;
  }

  /**
   * Sélectionne la résolution suivante. Préfère l'API programmatique
   * O.fn.selectNextMotion ; à défaut, clique sur la première
   * .op-agenda-item:not(.voted) située après .op-agenda-item.current.
   */
  function _nextMotion() {
    if (window.O && window.O.fn && typeof window.O.fn.selectNextMotion === 'function') {
      window.O.fn.selectNextMotion();
      return;
    }
    var items = Array.prototype.slice.call(document.querySelectorAll('.op-agenda-item'));
    var currentIdx = -1;
    for (var i = 0; i < items.length; i++) {
      if (items[i].classList.contains('current')) { currentIdx = i; break; }
    }
    for (var j = currentIdx + 1; j < items.length; j++) {
      if (!items[j].classList.contains('voted')) { items[j].click(); return; }
    }
  }

  // -------------------------------------------------------------------------
  // Overlay des raccourcis (#agShortcutsOverlay)
  // -------------------------------------------------------------------------

  function _ensureOverlay() {
    var ov = document.getElementById('agShortcutsOverlay');
    if (ov) return ov;

    ov = document.createElement('div');
    ov.id = 'agShortcutsOverlay';
    ov.className = 'ag-shortcuts-overlay';
    ov.setAttribute('role', 'dialog');
    ov.setAttribute('aria-modal', 'true');
    ov.setAttribute('aria-label', 'Liste des raccourcis clavier');
    ov.hidden = true;
    ov.innerHTML = '<div class="ag-shortcuts-overlay__card">' +
      '<h2 class="ag-shortcuts-overlay__title">Raccourcis clavier</h2>' +
      '<dl class="ag-shortcuts-overlay__list">' +
        '<dt><kbd>L</kbd></dt><dd>Lancer le vote actif</dd>' +
        '<dt><kbd>F</kbd></dt><dd>Fermer le scrutin actif</dd>' +
        '<dt><kbd>&rarr;</kbd> <kbd>N</kbd></dt><dd>Résolution suivante</dd>' +
        '<dt><kbd>?</kbd></dt><dd>Afficher / masquer cette aide</dd>' +
        '<dt><kbd>Échap</kbd></dt><dd>Fermer cette aide</dd>' +
      '</dl>' +
      '<button type="button" class="ag-shortcuts-overlay__close" aria-label="Fermer">Fermer</button>' +
    '</div>';
    document.body.appendChild(ov);

    ov.addEventListener('click', function(e) {
      if (e.target === ov) { _hideOverlay(); return; }
      if (e.target && e.target.classList && e.target.classList.contains('ag-shortcuts-overlay__close')) {
        _hideOverlay();
      }
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && !ov.hidden) {
        e.preventDefault();
        _hideOverlay();
      }
    });

    return ov;
  }

  function _toggleOverlay() {
    var ov = _ensureOverlay();
    if (ov.hidden) {
      ov.hidden = false;
      var closeBtn = ov.querySelector('.ag-shortcuts-overlay__close');
      if (closeBtn) closeBtn.focus();
    } else {
      _hideOverlay();
    }
  }

  function _hideOverlay() {
    var ov = document.getElementById('agShortcutsOverlay');
    if (ov) ov.hidden = true;
  }

  // -------------------------------------------------------------------------
  // Dispatcher principal — un seul listener document-level
  // -------------------------------------------------------------------------

  document.addEventListener('keydown', function(e) {
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    if (_isTypingContext()) return;

    // L'aide '?' reste disponible quel que soit le mode (documentation passive).
    if (e.key === '?') {
      e.preventDefault();
      _toggleOverlay();
      return;
    }

    if (!_isExecMode()) return;

    var k = e.key;
    if (k === 'l' || k === 'L') {
      e.preventDefault();
      if (!_click('opBtnLaunchVote')) _click('opBtnToggleVote');
      return;
    }
    if (k === 'f' || k === 'F') {
      e.preventDefault();
      if (!_click('opBtnCloseVote')) _click('opBtnToggleVote');
      return;
    }
    if (k === 'ArrowRight' || k === 'n' || k === 'N') {
      e.preventDefault();
      _nextMotion();
      return;
    }
  });
}());
