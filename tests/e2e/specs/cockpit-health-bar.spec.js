// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * E2E v2.3 / Phase 1 / COCKPIT-03 + COCKPIT-04 + COCKPIT-05 + COCKPIT-07
 *
 * Verifies the <ag-health-bar> custom element:
 *  - is registered and renders inside #viewExec
 *  - reacts to attribute changes (quorum-state: met / at-risk / missed)
 *  - pulses on missed quorum AND respects prefers-reduced-motion
 *  - collapses to vertical stack at <768px viewport
 *  - locks the "Votes restants" mental-model (must not regress to "Votants connectés")
 *
 * Test budget: CLAUDE.md mandates max 3 Playwright executions per plan, and
 * "do NOT retry" blindly — if a case fails twice, STOP and surface it to the user.
 */

test.describe('@cockpit-v2.3 ag-health-bar custom element', () => {

  // Navigate to operator exec view as a precondition. We do NOT need a fully-seeded
  // meeting for these visual assertions — the element is rendered with default attrs.
  async function gotoOperatorExec(page) {
    await loginAsOperator(page);
    await page.goto('/operator');
    // Force exec view (Plan 01.3 leaves the bar visible whenever #viewExec is rendered)
    await page.evaluate(() => {
      var v = document.getElementById('viewExec');
      if (v) v.hidden = false;
    });
  }

  test('element is registered and present inside #viewExec', async ({ page }) => {
    await gotoOperatorExec(page);
    const isDefined = await page.evaluate(() => !!customElements.get('ag-health-bar'));
    expect(isDefined).toBe(true);
    const bar = page.locator('ag-health-bar#opHealthBar');
    await expect(bar).toHaveCount(1);
  });

  test('observedAttributes includes the 6 required attribute names', async ({ page }) => {
    await gotoOperatorExec(page);
    const attrs = await page.evaluate(() => {
      const Ctor = customElements.get('ag-health-bar');
      return Ctor && Ctor.observedAttributes ? Ctor.observedAttributes.slice() : [];
    });
    expect(attrs).toEqual(expect.arrayContaining([
      'quorum-state', 'quorum-ratio', 'sse-state', 'votes-remaining', 'motion-number', 'motion-title'
    ]));
    expect(attrs.length).toBe(6);
  });

  test('three quorum states render distinct colors (met / at-risk / missed)', async ({ page }) => {
    await gotoOperatorExec(page);
    const bar = page.locator('ag-health-bar#opHealthBar');

    async function ratioColor(state) {
      await page.evaluate((s) => document.getElementById('opHealthBar').setAttribute('quorum-state', s), state);
      const ratio = bar.locator('.ag-health-bar__quorum-ratio');
      await expect(ratio).toBeVisible();
      return await ratio.evaluate((el) => getComputedStyle(el).color);
    }
    const cMet = await ratioColor('met');
    const cAtRisk = await ratioColor('at-risk');
    const cMissed = await ratioColor('missed');
    // Three distinct rgb() strings
    expect(cMet).not.toEqual(cAtRisk);
    expect(cAtRisk).not.toEqual(cMissed);
    expect(cMet).not.toEqual(cMissed);
  });

  test('missed quorum applies pulse animation; reduced-motion suppresses it', async ({ page, browser }) => {
    // First context: motion ENABLED (default)
    await gotoOperatorExec(page);
    await page.evaluate(() => document.getElementById('opHealthBar').setAttribute('quorum-state', 'missed'));
    const bar = page.locator('ag-health-bar#opHealthBar');
    const animName = await bar.evaluate((el) => getComputedStyle(el).animationName);
    expect(animName).toContain('ag-health-bar-pulse');

    // Second context: prefers-reduced-motion: reduce
    const ctx = await browser.newContext({ reducedMotion: 'reduce' });
    const p2 = await ctx.newPage();
    await loginAsOperator(p2);
    await p2.goto('/operator');
    await p2.evaluate(() => {
      var v = document.getElementById('viewExec'); if (v) v.hidden = false;
      document.getElementById('opHealthBar').setAttribute('quorum-state', 'missed');
    });
    const animNameReduced = await p2.locator('ag-health-bar#opHealthBar').evaluate(
      (el) => getComputedStyle(el).animationName
    );
    // CSS spec: animation: none → animationName is 'none'
    expect(animNameReduced).toBe('none');
    await ctx.close();
  });

  test('at-risk state does NOT trigger the pulse animation (anticipation, not panic)', async ({ page }) => {
    await gotoOperatorExec(page);
    await page.evaluate(() => document.getElementById('opHealthBar').setAttribute('quorum-state', 'at-risk'));
    const animName = await page.locator('ag-health-bar#opHealthBar').evaluate(
      (el) => getComputedStyle(el).animationName
    );
    expect(animName).toBe('none');
  });

  test('responsive stack: below 768px viewport, layout flips to vertical', async ({ page }) => {
    await gotoOperatorExec(page);
    await page.setViewportSize({ width: 600, height: 800 });
    // F-3: wait for the @media query to settle on layout (avoid flake on slow renderers)
    await page.waitForFunction(
      () => getComputedStyle(document.getElementById('opHealthBar')).flexDirection === 'column',
      null,
      { timeout: 2000 }
    );
    const flexDir = await page.locator('ag-health-bar#opHealthBar').evaluate(
      (el) => getComputedStyle(el).flexDirection
    );
    expect(flexDir).toBe('column');
  });

  test('attribute changes trigger re-render (motion-title updates DOM)', async ({ page }) => {
    await gotoOperatorExec(page);
    await page.evaluate(() => document.getElementById('opHealthBar').setAttribute('motion-title', 'Approbation des comptes 2024'));
    const titleText = await page.locator('ag-health-bar#opHealthBar .ag-health-bar__motion-title').textContent();
    expect(titleText).toContain('Approbation des comptes 2024');
  });

  // F-1: lock the "Votes restants" mental-model fix (UX critique B) into a regression test.
  // If a future refactor swaps the label back to "Votants connectés" (the v2.2 anti-pattern
  // the UX review explicitly fixed), this test fails. Belt-and-braces against label drift.
  // STOP — do NOT retry blindly within this plan if it fails twice (CLAUDE.md test budget).
  test('primary indicator label is "Votes restants" — NOT "Votants connectés"', async ({ page }) => {
    await gotoOperatorExec(page);
    await page.evaluate(() => document.getElementById('opHealthBar').setAttribute('votes-remaining', '23 / 142'));
    const bar = page.locator('ag-health-bar#opHealthBar');
    await expect(bar).toContainText('Votes restants');
    // The primary-slot indicator must NOT carry the legacy "Votants connectés" label.
    // (Connectivity, if shown, lives in the ambient pill — not as the people-indicator.)
    const primary = bar.locator('.ag-health-bar__primary');
    await expect(primary).not.toContainText('Votants connectés');
  });

});
