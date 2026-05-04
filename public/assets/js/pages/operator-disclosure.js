/**
 * operator-disclosure.js — Phase v2.4 / COCKPIT-V24-01 / D-03
 *
 * Disclosure "Plus d'actions" / "Plus d'onglets" — persist open/closed state
 * in localStorage and inject a live count of disclosed children into
 * .op-action-disclosure__count.
 *
 * The disclosure pattern uses native <details>/<summary>, so keyboard a11y
 * (Tab + Enter / Space) is provided by the browser without custom JS.
 *
 * Sentinel anti-double-bind: window.AG_OPERATOR_DISCLOSURE = true.
 *
 * Storage key (composite): localStorage[`agOperatorDisclosure:${id}`] = "1" | "0"
 * — falls back to data-default-open attribute if no stored value.
 *
 * Count rule: count direct clickable descendants inside
 * .op-action-disclosure__content (button | a.btn | [role="button"]). The
 * count is injected as plain text "(N)" inside .op-action-disclosure__count.
 */
(function() {
  'use strict';

  if (window.AG_OPERATOR_DISCLOSURE) return;
  window.AG_OPERATOR_DISCLOSURE = true;

  var STORAGE_PREFIX = 'agOperatorDisclosure:';
  var SELECTOR_ROOT = '.op-action-disclosure';
  var SELECTOR_CONTENT = '.op-action-disclosure__content';
  var SELECTOR_COUNT = '.op-action-disclosure__count';
  var SELECTOR_CHILDREN = 'button, a.btn, [role="button"]';

  function safeRead(key) {
    try { return window.localStorage.getItem(key); } catch (e) { return null; }
  }
  function safeWrite(key, value) {
    try { window.localStorage.setItem(key, value); } catch (e) { /* quota / private mode */ }
  }

  /**
   * Count visible-eligible children in the content panel and write to the
   * .op-action-disclosure__count element. Counts ALL clickable children
   * (including [hidden] ones — the count reflects the menu's full size,
   * not the currently-rendered subset). UX rationale: user expects the
   * "(N)" badge to be stable across vote-state transitions.
   */
  function refreshCount(root) {
    var content = root.querySelector(SELECTOR_CONTENT);
    var label = root.querySelector(SELECTOR_COUNT);
    if (!content || !label) return;
    var children = content.querySelectorAll(SELECTOR_CHILDREN);
    var n = children.length;
    label.textContent = '(' + n + ')';
    label.setAttribute('aria-label', n + ' actions disponibles');
  }

  function restoreState(root) {
    var id = root.id || root.getAttribute('data-disclosure-id');
    if (!id) return; // no persistence without an id
    var stored = safeRead(STORAGE_PREFIX + id);
    if (stored === '1') root.setAttribute('open', '');
    else if (stored === '0') root.removeAttribute('open');
    // else fall back to the inline <details open> default in markup
  }

  function bindPersist(root) {
    var id = root.id || root.getAttribute('data-disclosure-id');
    if (!id) return;
    root.addEventListener('toggle', function() {
      safeWrite(STORAGE_PREFIX + id, root.open ? '1' : '0');
    });
  }

  /**
   * Close on outside-click / Esc — UX expectation for a popover-style
   * disclosure. Native <details> doesn't auto-close; we add the behavior
   * for "Plus d'actions" floating panels (.op-action-disclosure__content
   * is positioned absolute → user expects popover semantics).
   */
  function bindAutoClose(root) {
    document.addEventListener('click', function(ev) {
      if (!root.open) return;
      if (root.contains(ev.target)) return;
      root.removeAttribute('open');
    });
    document.addEventListener('keydown', function(ev) {
      if (ev.key === 'Escape' && root.open) {
        root.removeAttribute('open');
        var trigger = root.querySelector('.op-action-disclosure__trigger');
        if (trigger && typeof trigger.focus === 'function') trigger.focus();
      }
    });
  }

  function initOne(root) {
    if (root.dataset.disclosureBound === '1') return;
    root.dataset.disclosureBound = '1';
    refreshCount(root);
    restoreState(root);
    bindPersist(root);
    bindAutoClose(root);
  }

  function initAll() {
    var roots = document.querySelectorAll(SELECTOR_ROOT);
    Array.prototype.forEach.call(roots, initOne);
  }

  // Re-init when content is injected late (HTMX swap, dynamic templates).
  // Cheap MutationObserver scoped to body, only watches childList.
  function observe() {
    if (!window.MutationObserver) return;
    var mo = new MutationObserver(function() { initAll(); });
    mo.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { initAll(); observe(); });
  } else {
    initAll();
    observe();
  }

  // Expose for tests + late callers
  window.OpDisclosure = { initAll: initAll, refreshCount: refreshCount };

})();
