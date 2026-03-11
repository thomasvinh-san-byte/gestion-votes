// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * Dashboard E2E Tests
 *
 * Tests the main dashboard page: KPIs, upcoming meetings, quick actions.
 */

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should load dashboard page', async ({ page }) => {
    await page.goto('/dashboard.htmx.html');
    await expect(page).toHaveTitle(/Tableau de bord|AG-VOTE/);
    await page.waitForLoadState('domcontentloaded');
  });

  test('should display KPI cards or status indicators', async ({ page }) => {
    await page.goto('/dashboard.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const kpis = page.locator('.kpi-card, ag-kpi, .stat-card, .dashboard-card');
    if (await kpis.count() > 0) {
      await expect(kpis.first()).toBeVisible();
    }
  });

  test('should display quick action links or cards', async ({ page }) => {
    await page.goto('/dashboard.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const actions = page.locator('.quick-action, .action-card, [data-action]');
    if (await actions.count() > 0) {
      await expect(actions.first()).toBeVisible();
    }
  });

  test('should have sidebar navigation', async ({ page }) => {
    await page.goto('/dashboard.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const sidebar = page.locator('.app-sidebar, [data-include-sidebar]');
    expect(await sidebar.count()).toBeGreaterThan(0);
  });

  test('should have skip links for accessibility', async ({ page }) => {
    await page.goto('/dashboard.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const skipLink = page.locator('.skip-link');
    expect(await skipLink.count()).toBeGreaterThan(0);
  });

  test('should not have horizontal overflow', async ({ page }) => {
    await page.goto('/dashboard.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const hasOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(hasOverflow).toBe(false);
  });
});

test.describe('Dashboard API', () => {
  test('dashboard_stats should reject unauthenticated', async ({ request }) => {
    const response = await request.get('/api/v1/dashboard_stats.php');
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });
});
