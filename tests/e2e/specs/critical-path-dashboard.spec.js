// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsAdmin } = require('../helpers');

/**
 * E2E-DASH: Dashboard critical path
 * KPI tiles → urgent card → next-sessions list → quick-access nav
 *
 * Hybrid auth strategy: cookie injection via loginAsAdmin (no rate limit hit).
 * Re-runnable: only navigation and read-only assertions — no DB writes.
 * Tagged @critical-path for `--grep="@critical-path"` filtering.
 *
 * KPI assertion proves DEBT-01 wiring (Phase 11 getDashboardStats()) actually
 * populates the dashboard tiles with numeric values instead of placeholder "-".
 */

test.describe('E2E-DASH Dashboard critical path', () => {

  test('dashboard: KPIs render real values, urgent card, sessions list, nav links @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // Step 1 — Login as admin (sees full dashboard)
    await loginAsAdmin(page);

    // Step 2 — Load the dashboard page
    await page.goto('/dashboard.htmx.html', { waitUntil: 'domcontentloaded' });

    // Assert #kpiSeances is visible (proves page is up and JS loaded)
    await expect(page.locator('#kpiSeances')).toBeVisible({ timeout: 15000 });

    // Step 3 — KPI tiles render real values (proves DEBT-01 getDashboardStats() wiring)
    // After JS fetches from /api/v1/dashboard_stats.php, the .loading class drops and
    // each KPI value should be a number (even "0"), NOT the placeholder "-".
    // All 4 KPI IDs: kpiSeances, kpiEnCours, kpiConvoc, kpiPV
    const kpiIds = ['kpiSeances', 'kpiEnCours', 'kpiConvoc', 'kpiPV'];
    for (const id of kpiIds) {
      const locator = page.locator('#' + id);
      await expect(locator).toBeVisible({ timeout: 10000 });
      // Placeholder "-" means JS did not wire the stat — assert it's replaced
      await expect(locator).not.toHaveText('-', { timeout: 10000 });
    }

    // Step 4 — Urgent action card: handle both valid states
    // State A: a live session exists → card is visible and links to /hub
    // State B: no live session in test DB → card remains hidden
    const urgentCard = page.locator('#actionUrgente');
    const urgentVisible = await urgentCard.isVisible().catch(() => false);
    if (urgentVisible) {
      // Card revealed by JS → must point to /hub
      const href = await urgentCard.getAttribute('href');
      expect(href).toBeTruthy();
      expect(href).toContain('/hub');
    } else {
      // Card stays hidden (no active session) — verify hidden attribute still present
      await expect.soft(urgentCard).toHaveAttribute('hidden', /.*/, { timeout: 2000 });
    }

    // Step 5 — Next-sessions list renders (loading class must drop)
    const prochainesList = page.locator('#prochaines');
    await expect(prochainesList).toBeVisible({ timeout: 15000 });
    // Once JS data arrives the .loading class is removed
    await expect(prochainesList).not.toHaveClass(/loading/, { timeout: 15000 });

    // Step 6 — Quick-access nav links are wired (no bare "#" hrefs)
    const navLinks = await page.locator('.dashboard-aside a[href]').all();
    expect(navLinks.length).toBeGreaterThan(0);
    for (const link of navLinks) {
      const href = await link.getAttribute('href');
      expect(href).toBeTruthy();
      expect(href).not.toBe('#');
    }

    // Step 7 — Click first quick-access link and assert navigation succeeds
    const firstLink = page.locator('.dashboard-aside a[href]').first();
    const firstHref = await firstLink.getAttribute('href');
    expect(firstHref).toBeTruthy();

    // Navigate and wait for page to settle
    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      firstLink.click(),
    ]);

    // Assert we actually navigated to the expected page
    const currentUrl = page.url();
    // The href is a path like /wizard — check the pathname segment
    const hrefSegment = firstHref.replace(/^\//, '').split('/')[0];
    expect(currentUrl).toContain(hrefSegment);
  });

});
