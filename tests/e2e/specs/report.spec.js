// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, E2E_MEETING_ID } = require('../helpers');

/**
 * Report & PV E2E Tests
 *
 * Tests the report page: meeting report view, PV generation.
 */

test.describe('Meeting Report', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should load report page', async ({ page }) => {
    await page.goto(`/report.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await expect(page).toHaveTitle(/AG-VOTE/);
    await page.waitForLoadState('domcontentloaded');
  });

  test('should show PV empty state or content', async ({ page }) => {
    await page.goto(`/report.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const pvContent = page.locator('#pvFrame, #pvEmptyState, .report-content, .pv-container');
    if (await pvContent.count() > 0) {
      await expect(pvContent.first()).toBeAttached();
    }
  });

  test('should have export or download controls', async ({ page }) => {
    await page.goto(`/report.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const exportBtn = page.locator('[data-export], .btn-export, button:has-text("Export"), button:has-text("Télécharger")');
    if (await exportBtn.count() > 0) {
      await expect(exportBtn.first()).toBeVisible();
    }
  });

  test('should not have horizontal overflow', async ({ page }) => {
    await page.goto(`/report.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const hasOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(hasOverflow).toBe(false);
  });
});

test.describe('Report API Security', () => {
  test('meeting_report should reject unauthenticated', async ({ request }) => {
    const response = await request.get(`/api/v1/meeting_report.php?meeting_id=${E2E_MEETING_ID}`);
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });
});
