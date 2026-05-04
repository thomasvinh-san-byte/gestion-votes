// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsAdmin } = require('../helpers');

/**
 * E2E v2.4 / Phase 2 / ERR-V24-02 — SSE burst idempotency
 *
 * Verifies the SSE empty-state debounce (Plan 02.2):
 *  - <ag-integrity-modal> debounces post-initial attribute mutations >=250ms
 *    so a burst of 5 rapid events triggers exactly 1 render
 *  - The data-sse-debounce-ms attribute is honored live (override window)
 *
 * Burst channel under test : `attributeChangedCallback` driven by external
 * mutations of `data-events` on the custom element (audit.htmx.html mounts
 * <ag-integrity-modal hidden data-date="" data-events="[]">). The debounce
 * helper lives in public/assets/js/utils/sse-debounce.js and is loaded
 * before the modal script in audit.htmx.html and report.htmx.html.
 *
 * Assertion mechanism : `data-render-count` attribute incremented once per
 * effective render. Avoids DOM mutation observer flakiness — single read
 * after the debounce window has elapsed.
 *
 * Scope note : the dashboard hero card live channel currently has no SSE
 * emitter (see 02.2-SCOUT.md). When wired in v2.5+, a sibling test will be
 * added here using the same utility. The third originally-planned test
 * (hero card burst) is therefore deferred — the two below cover the modal
 * debounce contract end-to-end.
 *
 * Test budget : CLAUDE.md mandates max 3 Playwright executions per plan.
 */

test.describe('@regression SSE burst idempotency (Plan 02.2)', () => {

  // Open the audit page, ensure the integrity modal element is present and the
  // sse-debounce utility has been loaded. We do not call .open() on the modal
  // because the burst test only exercises the attribute-driven render path,
  // which fires whether the modal is visible or hidden.
  async function gotoAuditWithModal(page) {
    await loginAsAdmin(page);
    await page.goto('/audit.htmx.html', { waitUntil: 'domcontentloaded' });
    // Wait for the custom element to be defined and connected.
    await page.waitForFunction(() => {
      var el = document.querySelector('ag-integrity-modal');
      return !!el && el.hasAttribute('data-rendered');
    }, { timeout: 15000 });
    // Confirm the debounce utility is exposed (otherwise the modal falls back
    // to synchronous render and the burst would produce 5 increments instead
    // of 1 — that would be a real regression to surface).
    const hasUtil = await page.evaluate(() => !!(window.AgSseDebounce && typeof window.AgSseDebounce.create === 'function'));
    expect(hasUtil, 'window.AgSseDebounce.create must be exposed by sse-debounce.js').toBe(true);
  }

  test('ag-integrity-modal: 5 attribute mutations in 100ms produce exactly 1 render', async ({ page }) => {
    test.setTimeout(60000);

    await gotoAuditWithModal(page);

    // Reset the render counter to a known baseline. Initial connectedCallback
    // render does NOT pass through the debounce path (synchronous first paint
    // is preserved), so we zero the counter before the burst.
    await page.evaluate(() => {
      var el = document.querySelector('ag-integrity-modal');
      if (el) el.setAttribute('data-render-count', '0');
    });

    // Inject 5 attribute mutations spaced 20ms apart (total burst = ~80ms).
    // Each setAttribute on data-events fires attributeChangedCallback which
    // routes through the debounced render after the initial render gate.
    await page.evaluate(() => {
      var el = document.querySelector('ag-integrity-modal');
      if (!el) return;
      // Make sure we're past the initial render gate (data-rendered set by
      // connectedCallback). The audit page mounts the element on load, so
      // this is already true; assertion above guarantees it.
      for (var i = 0; i < 5; i++) {
        // Schedule via setTimeout so each mutation is its own task. dispatchEvent
        // would not exercise the real burst channel here (the modal does not
        // listen for 'message'). The realistic SSE producer would call
        // setAttribute('data-events', JSON.stringify(events)) on each event.
        (function (idx) {
          setTimeout(function () {
            el.setAttribute('data-events', JSON.stringify([{ hash: 'h' + idx, prev: '0', at: '2026-05-04T10:00:0' + idx + 'Z' }]));
          }, idx * 20);
        })(i);
      }
    });

    // Wait for: 100ms burst window + 250ms default debounce + 50ms safety margin.
    await page.waitForTimeout(450);

    const renderCount = await page.evaluate(() => {
      var el = document.querySelector('ag-integrity-modal');
      return el ? parseInt(el.getAttribute('data-render-count') || '-1', 10) : -1;
    });

    expect(renderCount, '5 mutations in 100ms should debounce to exactly 1 render').toBe(1);
  });

  test('ag-integrity-modal: data-sse-debounce-ms override (500ms) delays the render past the default window', async ({ page }) => {
    test.setTimeout(60000);

    await gotoAuditWithModal(page);

    // Reset counter and override the debounce window to 500ms before any mutation.
    await page.evaluate(() => {
      var el = document.querySelector('ag-integrity-modal');
      if (!el) return;
      el.setAttribute('data-render-count', '0');
      el.setAttribute('data-sse-debounce-ms', '500');
    });

    // Force a fresh debounced handler by triggering one mutation. The first
    // attributeChangedCallback after attribute set will lazy-create the debounced
    // wrapper using the current data-sse-debounce-ms value.
    await page.evaluate(() => {
      var el = document.querySelector('ag-integrity-modal');
      if (el) el.setAttribute('data-events', '[{"hash":"trigger","prev":"0","at":"2026-05-04T10:00:00Z"}]');
    });

    // After 300ms (well past the 250ms default but well below the 500ms override),
    // no render must have occurred yet — the override is honored.
    await page.waitForTimeout(300);
    const midCount = await page.evaluate(() => {
      var el = document.querySelector('ag-integrity-modal');
      return el ? parseInt(el.getAttribute('data-render-count') || '-1', 10) : -1;
    });
    expect(midCount, 'Override 500ms must NOT have rendered after 300ms').toBe(0);

    // After total 600ms (>500ms), the render must have fired exactly once.
    await page.waitForTimeout(300);
    const finalCount = await page.evaluate(() => {
      var el = document.querySelector('ag-integrity-modal');
      return el ? parseInt(el.getAttribute('data-render-count') || '-1', 10) : -1;
    });
    expect(finalCount, 'Override 500ms must have rendered exactly once after 600ms total').toBe(1);
  });

});
