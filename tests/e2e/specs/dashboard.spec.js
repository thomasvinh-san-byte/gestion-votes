// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * Dashboard E2E Tests
 */
test.describe('Dashboard', () => {
  test('should not have horizontal overflow', async ({ page }) => {
    await loginAsOperator(page);
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
