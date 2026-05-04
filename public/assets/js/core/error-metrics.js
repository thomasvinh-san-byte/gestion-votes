/**
 * error-metrics.js — UX instrumentation for ErrorDictionary next-step suggestions.
 *
 * LOG-V25-04 — fire-and-forget POST to /api/v1/metrics/next-step-clicked when
 * a user actually clicks a "next-step" suggestion shown beneath an error.
 *
 * Usage from any next-step rendering site (error toast, inline error block,
 * api-fail handler):
 *
 *   AgErrorMetrics.trackNextStep('already_voted', 'Demandez à l\'opérateur d\'annuler');
 *
 * Or attach to an anchor automatically:
 *
 *   <a href="..." data-next-step data-error-code="already_voted">…</a>
 *   AgErrorMetrics.bindAuto();   // delegated click listener, idempotent
 *
 * Failures (429, 5xx, network) are swallowed — instrumentation must never
 * impact the user journey.
 */
(function () {
  'use strict';

  var bound = false;

  function trackNextStep(errorCode, suggestion) {
    if (!errorCode) return;
    var body = JSON.stringify({
      error_code: String(errorCode).slice(0, 80),
      suggestion: suggestion ? String(suggestion).slice(0, 500) : null,
    });
    try {
      // sendBeacon survives page unload → preferred.
      if (navigator.sendBeacon) {
        var blob = new Blob([body], { type: 'application/json' });
        navigator.sendBeacon('/api/v1/metrics/next-step-clicked', blob);
        return;
      }
    } catch (_) {}
    try {
      fetch('/api/v1/metrics/next-step-clicked', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: body,
        credentials: 'same-origin',
        keepalive: true,
      }).catch(function () {});
    } catch (_) {}
  }

  function bindAuto() {
    if (bound) return;
    bound = true;
    document.addEventListener('click', function (ev) {
      var t = ev.target;
      while (t && t !== document) {
        if (t.dataset && t.dataset.nextStep !== undefined) {
          trackNextStep(t.dataset.errorCode || '', t.textContent || '');
          return;
        }
        t = t.parentNode;
      }
    });
  }

  window.AgErrorMetrics = {
    trackNextStep: trackNextStep,
    bindAuto: bindAuto,
  };
})();
