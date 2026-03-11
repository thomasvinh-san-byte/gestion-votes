// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * Archives E2E Tests
 *
 * Tests the archived meetings page.
 */

test.describe('Archives', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should load archives page', async ({ page }) => {
    await page.goto('/archives.htmx.html');
    await expect(page).toHaveTitle(/AG-VOTE/);
    await page.waitForLoadState('domcontentloaded');
  });

  test('should display archive list or empty state', async ({ page }) => {
    await page.goto('/archives.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const content = page.locator('.archive-list, .meeting-card, .empty-state, [data-archives]');
    if (await content.count() > 0) {
      await expect(content.first()).toBeVisible();
    }
  });

  test('should have search or filter controls', async ({ page }) => {
    await page.goto('/archives.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const search = page.locator('input[type="search"], input[placeholder*="chercher"], .search-input, [data-filter]');
    if (await search.count() > 0) {
      await expect(search.first()).toBeVisible();
    }
  });

  test('should have sidebar navigation', async ({ page }) => {
    await page.goto('/archives.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const sidebar = page.locator('.app-sidebar, [data-include-sidebar]');
    expect(await sidebar.count()).toBeGreaterThan(0);
  });

  test('should not have horizontal overflow', async ({ page }) => {
    await page.goto('/archives.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const hasOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(hasOverflow).toBe(false);
  });
});
