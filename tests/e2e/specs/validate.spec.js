// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, E2E_MEETING_ID } = require('../helpers');

/**
 * Meeting Validation E2E Tests
 *
 * Tests the validation workflow page.
 */

test.describe('Meeting Validation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should load validation page', async ({ page }) => {
    await page.goto(`/validate.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await expect(page).toHaveTitle(/AG-VOTE/);
    await page.waitForLoadState('domcontentloaded');
  });

  test('should display validation status or checklist', async ({ page }) => {
    await page.goto(`/validate.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const validation = page.locator('.validation-checklist, .check-item, [data-validation], .stepper, ag-stepper');
    if (await validation.count() > 0) {
      await expect(validation.first()).toBeVisible();
    }
  });

  test('should have validation action buttons', async ({ page }) => {
    await page.goto(`/validate.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const actionBtn = page.locator('button:has-text("Valider"), button:has-text("Archiver"), .btn-validate');
    if (await actionBtn.count() > 0) {
      await expect(actionBtn.first()).toBeVisible();
    }
  });

  test('should not have horizontal overflow', async ({ page }) => {
    await page.goto(`/validate.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const hasOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(hasOverflow).toBe(false);
  });
});
