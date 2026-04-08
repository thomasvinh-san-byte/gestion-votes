// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator: login } = require('../helpers');

/**
 * Page Load Performance E2E Tests
 */
test.describe('Page Load', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('meetings page should load within 5 seconds', async ({ page }) => {
    const start = Date.now();
    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.app-shell, main, body').first()).toBeVisible({ timeout: 5000 });
    const duration = Date.now() - start;
    expect(duration).toBeLessThan(5000);
  });

  test('operator page should load within 5 seconds', async ({ page }) => {
    const start = Date.now();
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.app-shell, main, body').first()).toBeVisible({ timeout: 5000 });
    const duration = Date.now() - start;
    expect(duration).toBeLessThan(5000);
  });

  test('admin page should load within 5 seconds', async ({ page }) => {
    const start = Date.now();
    await page.goto('/admin.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.app-shell, main, body').first()).toBeVisible({ timeout: 5000 });
    const duration = Date.now() - start;
    expect(duration).toBeLessThan(5000);
  });

});
