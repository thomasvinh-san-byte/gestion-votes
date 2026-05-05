/**
 * AG-VOTE Loading States (LOADING-V27-01 + LOADING-V27-02)
 *
 * Centralized perceived-loading layer:
 *
 * 1. HTMX skeleton swap: when an HTMX swap exceeds 300 ms, the target's
 *    contents are replaced by an <ag-skeleton> until the response arrives.
 *    Variant inferred from the target's data-skeleton attribute (default: text).
 *
 * 2. Submit spinner + anti-double-submit:
 *    - HTMX form submitters automatically enter a disabled+spinner state for
 *      the duration of the request.
 *    - Native POST forms with attribute `data-submit-spinner` get the same
 *      treatment via a global capture-phase submit listener.
 *
 * Usage patterns (no per-page glue required):
 *   HTMX:        <button hx-post="/api/foo" hx-target="#out">Envoyer</button>
 *   Form natif:  <form method="post" action="..." data-submit-spinner>
 *                  ... <button type="submit">Envoyer</button>
 *                </form>
 *   Skeleton:    <div id="list" data-skeleton="card" data-skeleton-count="3"
 *                     hx-get="/api/foo" hx-trigger="load"></div>
 *
 * Public API: window.LoadingStates
 *   - SKELETON_DELAY_MS (number)
 *   - applyOptimistic(el, mutate, request, rollback) — for fetch-based flows
 *   - enterSubmitState(btn) / exitSubmitState(btn)   — manual control
 *
 * Coexists with utils.js HTMX hooks (configRequest CSRF/idempotency,
 * afterSwap CSRF re-init, response/sendError toasts). Adds new listeners,
 * does NOT modify existing ones.
 */
(function () {
  'use strict';

  var SKELETON_DELAY_MS = 300;

  // WeakMap to avoid leaks: cleared automatically when targets are GC'd.
  var skeletonTimers = new WeakMap();
  var skeletonOriginals = new WeakMap();

  // ---------------------------------------------------------------------------
  // Skeleton injection
  // ---------------------------------------------------------------------------

  function inferVariant(target) {
    return (target.getAttribute && target.getAttribute('data-skeleton')) || 'text';
  }

  function buildSkeletonMarkup(variant, target) {
    var count = (target.getAttribute && target.getAttribute('data-skeleton-count')) || '3';
    var rows = (target.getAttribute && target.getAttribute('data-skeleton-rows')) || '5';
    var attrs = ' variant="' + variant + '"';
    if (variant === 'table') attrs += ' rows="' + rows + '"';
    else attrs += ' count="' + count + '"';
    return '<ag-skeleton' + attrs + '></ag-skeleton>';
  }

  function startSkeletonTimer(target) {
    if (!target || target === document.body || target === document.documentElement) return;
    // Cancel any in-flight timer for this target (rapid successive requests).
    var existing = skeletonTimers.get(target);
    if (existing) clearTimeout(existing);
    var t = setTimeout(function () {
      try {
        skeletonOriginals.set(target, target.innerHTML);
        target.innerHTML = buildSkeletonMarkup(inferVariant(target), target);
        target.setAttribute('aria-busy', 'true');
      } catch (e) {
        // Defensive: never break the request flow because of a UI tweak.
      }
    }, SKELETON_DELAY_MS);
    skeletonTimers.set(target, t);
  }

  function clearSkeletonTimer(target, successful) {
    if (!target) return;
    var t = skeletonTimers.get(target);
    if (t) clearTimeout(t);
    skeletonTimers.delete(target);
    if (target.removeAttribute) target.removeAttribute('aria-busy');
    // If the request failed AFTER the skeleton was already injected,
    // restore the original markup so the user doesn't stare at a shimmer.
    if (!successful && skeletonOriginals.has(target)) {
      try {
        target.innerHTML = skeletonOriginals.get(target);
      } catch (e) {
        // No-op
      }
    }
    skeletonOriginals.delete(target);
  }

  // ---------------------------------------------------------------------------
  // Submit spinner + anti-double-submit
  // ---------------------------------------------------------------------------

  function isMutatingButton(btn) {
    if (!btn || btn.tagName !== 'BUTTON' && btn.tagName !== 'INPUT') return false;
    var type = (btn.getAttribute('type') || '').toLowerCase();
    if (type === 'submit') return true;
    // HTMX-driven button without form: still treat as mutating if hx-post/patch/delete.
    return !!(btn.hasAttribute('hx-post') || btn.hasAttribute('hx-patch') || btn.hasAttribute('hx-delete'));
  }

  function enterSubmitState(btn) {
    if (!btn) return;
    if (btn.dataset && btn.dataset.submitting === 'true') return;
    if (btn.dataset) btn.dataset.submitting = 'true';
    btn.disabled = true;
    // Prepend an inline ag-spinner if not already present.
    if (!btn.querySelector('.btn-spinner-slot')) {
      var slot = document.createElement('ag-spinner');
      slot.setAttribute('size', 'sm');
      slot.setAttribute('variant', 'primary');
      slot.className = 'btn-spinner-slot';
      btn.insertBefore(slot, btn.firstChild);
    }
  }

  function exitSubmitState(btn) {
    if (!btn) return;
    var slot = btn.querySelector && btn.querySelector('.btn-spinner-slot');
    if (slot && slot.parentNode === btn) btn.removeChild(slot);
    btn.disabled = false;
    if (btn.dataset) delete btn.dataset.submitting;
  }

  // ---------------------------------------------------------------------------
  // Wire HTMX events
  // ---------------------------------------------------------------------------

  document.body.addEventListener('htmx:beforeRequest', function (evt) {
    var detail = evt.detail || {};
    // Skeleton timer on the swap target.
    startSkeletonTimer(detail.target);
    // Submit spinner on the triggering element if it's a mutating button.
    var elt = detail.elt;
    if (elt && isMutatingButton(elt)) {
      enterSubmitState(elt);
    }
  });

  document.body.addEventListener('htmx:afterRequest', function (evt) {
    var detail = evt.detail || {};
    var successful = detail.successful !== false;
    clearSkeletonTimer(detail.target, successful);
    var elt = detail.elt;
    if (elt && isMutatingButton(elt)) {
      exitSubmitState(elt);
    }
  });

  // Safety net: if HTMX bails out very early (e.g. swapError) we still want
  // the spinner to clear so the user can retry.
  document.body.addEventListener('htmx:swapError', function (evt) {
    var detail = evt.detail || {};
    clearSkeletonTimer(detail.target, false);
    if (detail.elt) exitSubmitState(detail.elt);
  });

  // ---------------------------------------------------------------------------
  // Native form submit (data-submit-spinner)
  // ---------------------------------------------------------------------------

  document.addEventListener(
    'submit',
    function (e) {
      var form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      if (!form.hasAttribute('data-submit-spinner')) return;
      var btn = form.querySelector('button[type=submit], input[type=submit]');
      if (!btn) return;
      // Anti-double-submit: if already submitting, swallow the event.
      if (btn.dataset && btn.dataset.submitting === 'true') {
        e.preventDefault();
        return;
      }
      enterSubmitState(btn);
      // We do NOT exitSubmitState here: native POST navigates/reloads.
      // If the page survives (e.g. JS-handled submit), the caller is
      // responsible for calling LoadingStates.exitSubmitState(btn).
    },
    true
  );

  // ---------------------------------------------------------------------------
  // Optimistic-UI helper for fetch-based flows
  // ---------------------------------------------------------------------------

  function applyOptimistic(el, mutate, request, rollback) {
    if (el && el.classList) el.classList.add('is-pending');
    try {
      mutate();
    } catch (e) {
      if (el && el.classList) el.classList.remove('is-pending');
      throw e;
    }
    var p;
    try {
      p = request();
    } catch (e) {
      if (el && el.classList) el.classList.remove('is-pending');
      if (typeof rollback === 'function') rollback(e);
      throw e;
    }
    if (!p || typeof p.then !== 'function') {
      if (el && el.classList) el.classList.remove('is-pending');
      return Promise.resolve(p);
    }
    return p
      .then(function (res) {
        if (el && el.classList) el.classList.remove('is-pending');
        return res;
      })
      .catch(function (err) {
        if (el && el.classList) el.classList.remove('is-pending');
        if (typeof rollback === 'function') rollback(err);
        throw err;
      });
  }

  // ---------------------------------------------------------------------------
  // Expose for debug + manual use
  // ---------------------------------------------------------------------------

  window.LoadingStates = {
    SKELETON_DELAY_MS: SKELETON_DELAY_MS,
    applyOptimistic: applyOptimistic,
    enterSubmitState: enterSubmitState,
    exitSubmitState: exitSubmitState,
  };
})();
