// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, loginAsVoter } = require('../helpers');
const { waitForHtmxSettled } = require('../helpers/waitForHtmxSettled');

/**
 * Phase 02 -- Overlay hittest sweep (OVERLAY-03).
 *
 * Verifies that the global :where([hidden]) { display: none !important }
 * rule in design-system.css correctly forces display:none on elements
 * that have display:flex/grid in their normal CSS, when [hidden] is set.
 *
 * Tests 3 representative pages:
 *   1. Operator -- .op-transition-card (display: flex in operator.css)
 *   2. Settings -- .settings-panel (display: flex in settings.css)
 *   3. Vote -- .blocked-overlay (display: flex in vote.css)
 */

test.describe('Overlay hittest -- [hidden] forces display:none', () => {

  test('operator: .op-transition-card[hidden] has computed display:none', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/operator.htmx.html', { waitUntil: 'domcontentloaded' });
    await waitForHtmxSettled(page);

    const display = await page.evaluate(() => {
      const el = document.querySelector('.op-transition-card');
      if (!el) return 'ELEMENT_NOT_FOUND';
      el.setAttribute('hidden', '');
      return getComputedStyle(el).display;
    });
    expect(display).toBe('none');
  });

  test('settings: .settings-panel[hidden] has computed display:none', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/settings.htmx.html', { waitUntil: 'domcontentloaded' });
    await waitForHtmxSettled(page);

    const display = await page.evaluate(() => {
      const el = document.querySelector('.settings-panel');
      if (!el) return 'ELEMENT_NOT_FOUND';
      el.setAttribute('hidden', '');
      return getComputedStyle(el).display;
    });
    expect(display).toBe('none');
  });

  test('vote: .blocked-overlay[hidden] has computed display:none', async ({ page }) => {
    await loginAsVoter(page);
    await page.goto('/vote.htmx.html', { waitUntil: 'domcontentloaded' });
    await waitForHtmxSettled(page);

    const display = await page.evaluate(() => {
      const el = document.querySelector('.blocked-overlay');
      if (!el) return 'ELEMENT_NOT_FOUND';
      el.setAttribute('hidden', '');
      return getComputedStyle(el).display;
    });
    expect(display).toBe('none');
  });

  test('global rule exists in design-system.css', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/operator.htmx.html', { waitUntil: 'domcontentloaded' });

    // Verify the global rule is active by checking a dynamically-created element
    const display = await page.evaluate(() => {
      const el = document.createElement('div');
      el.style.display = 'flex';
      el.setAttribute('hidden', '');
      document.body.appendChild(el);
      const computed = getComputedStyle(el).display;
      el.remove();
      return computed;
    });
    expect(display).toBe('none');
  });

});
