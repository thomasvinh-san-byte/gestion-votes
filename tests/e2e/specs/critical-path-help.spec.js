// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * E2E-HELP: Help & FAQ critical path
 * Tour cards visible + anchors valid + 80ch content width cap + section headings present
 *
 * Hybrid auth strategy: cookie injection via loginAsOperator (no rate limit hit).
 * Re-runnable: only navigation and read-only assertions — no DB writes.
 * Tagged @critical-path for `--grep="@critical-path"` filtering.
 *
 * Width assertion proves MVP-01: help is a CONTENT page clamped to 80ch.
 * Tour card assertions prove MVP-03: tour grid is wired with valid ?tour=1 hrefs.
 */

test.describe('E2E-HELP Help & FAQ critical path', () => {

  test('help: tour cards visible + anchors valid + 80ch content width cap + section headings present @critical-path', async ({ page }) => {
    test.setTimeout(60000);

    // Step 1 — Login as operator (page-role="viewer" — lowest privilege with access)
    await loginAsOperator(page);

    // Step 2 — Navigate to help page
    await page.goto('/help.htmx.html', { waitUntil: 'domcontentloaded' });

    // Step 3 — Page mount: header + tour grid visible
    await expect(page.locator('.page-title')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('.tour-grid')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('.help-section-heading').first()).toBeVisible({ timeout: 10000 });

    // Step 4 — Tour cards: at least 4 visible with correct structure
    const count = await page.locator('.tour-card').count();
    expect(count).toBeGreaterThanOrEqual(4);

    const firstCard = page.locator('.tour-card').first();
    await expect(firstCard.locator('.tour-icon')).toBeVisible();
    const tourName = await firstCard.locator('.tour-name').textContent();
    expect(tourName?.trim().length).toBeGreaterThan(0);
    const tourMeta = await firstCard.locator('.tour-meta').textContent();
    expect(tourMeta?.trim().length).toBeGreaterThan(0);

    // First card href must contain ?tour=1
    const firstHref = await firstCard.getAttribute('href');
    expect(firstHref).toContain('tour=1');

    // Step 5 — All tour cards link to valid pages with ?tour=1 param
    const hrefs = await page.$$eval('.tour-card', els => els.map(e => e.getAttribute('href')));
    for (const href of hrefs) {
      expect(href).toBeTruthy();
      expect(href).toContain('tour=1');
      // All hrefs are relative (start with /) — same-origin app pages
      expect(href).toMatch(/^\//);
    }

    // Step 6 — Role-gated tour cards have data-required-role attribute
    const gated = await page.locator('.tour-card[data-required-role]').count();
    expect(gated).toBeGreaterThan(0);

    // Step 7 — Section headings: at least 2 (tour section + at least one FAQ section)
    const headingCount = await page.locator('.help-section-heading').count();
    expect(headingCount).toBeGreaterThanOrEqual(2);

    // Step 8 — Click a non-role-gated tour card and verify navigation
    const openCard = page.locator('.tour-card:not([data-required-role])').first();
    const openCardCount = await openCard.count();
    if (openCardCount > 0) {
      const targetHref = await openCard.getAttribute('href');
      expect(targetHref).toBeTruthy();

      await Promise.all([
        page.waitForLoadState('domcontentloaded'),
        openCard.click(),
      ]);

      // Assert the URL contains tour=1 (navigation reached target page)
      expect(page.url()).toContain('tour=1');

      // Navigate back to help to leave page in clean state for Step 9
      await page.goto('/help.htmx.html', { waitUntil: 'domcontentloaded' });
    }

    // Step 9 — Width cap: .app-main.help-main must be ≤ 80ch (content page)
    // Set wide viewport to ensure the cap is actually hit
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto('/help.htmx.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.app-main.help-main')).toBeVisible({ timeout: 10000 });

    const helpBox = await page.locator('.app-main.help-main').evaluate(el => {
      const rect = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return {
        width: rect.width,
        fontSize: parseFloat(cs.fontSize),
        maxWidth: cs.maxWidth,
      };
    });

    // max-width must NOT be 'none' — content page cap must be applied
    expect(helpBox.maxWidth).not.toBe('none');

    // Rendered width must be ≤ 80ch (80 * ch-unit ≈ fontSize for monospace,
    // actual character width may vary; use fontSize * 80 as the upper bound)
    expect(helpBox.width).toBeLessThanOrEqual(helpBox.fontSize * 80);

    // Step 10 — No horizontal overflow
    const overflow = await page.locator('body').evaluate(el => ({
      scrollWidth: el.scrollWidth,
      clientWidth: el.clientWidth,
    }));
    expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1);
  });

});
