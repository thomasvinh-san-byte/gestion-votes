// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * Archives E2E Tests
 */
test.describe('Archives', () => {
  test('should not have horizontal overflow', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/archives.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const hasOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(hasOverflow).toBe(false);
  });
});
