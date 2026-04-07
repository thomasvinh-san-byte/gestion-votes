// @ts-check
/**
 * waitForHtmxSettled — Playwright helper for HTMX page settle detection.
 *
 * Only 2 pages use HTMX (vote.htmx.html, postsession.htmx.html).
 * On all other pages, window.htmx is undefined and this resolves immediately.
 *
 * HTMX 1.9.12 fires htmx:afterSettle after DOM settling (defaultSettleDelay: 20ms).
 */

/**
 * Wait for HTMX to finish settling the DOM after a swap.
 * Resolves immediately if HTMX is not loaded on the page.
 *
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {number} [timeout=5000] - Maximum wait time in ms
 */
async function waitForHtmxSettled(page, timeout = 5000) {
  await page.waitForFunction(
    () => {
      return new Promise((resolve) => {
        // If HTMX is not loaded on this page, resolve immediately
        if (!window.htmx) { resolve(true); return; }
        // Listen for the next afterSettle event
        document.body.addEventListener(
          'htmx:afterSettle',
          () => resolve(true),
          { once: true }
        );
        // Safety timeout — if no HTMX activity is in flight, resolve after 200ms
        setTimeout(() => resolve(true), 200);
      });
    },
    { timeout }
  );
}

module.exports = { waitForHtmxSettled };
