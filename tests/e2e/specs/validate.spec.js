// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, E2E_MEETING_ID } = require('../helpers');

/**
 * Meeting Validation E2E Tests
 */
test.describe('Meeting Validation', () => {
  test('should not have horizontal overflow', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto(`/validate.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const hasOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(hasOverflow).toBe(false);
  });
});
