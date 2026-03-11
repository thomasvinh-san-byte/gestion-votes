// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, E2E_MEETING_ID } = require('../helpers');

/**
 * Report E2E Tests
 */
test.describe('Meeting Report', () => {
  test('should not have horizontal overflow', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto(`/report.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const hasOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(hasOverflow).toBe(false);
  });
});
