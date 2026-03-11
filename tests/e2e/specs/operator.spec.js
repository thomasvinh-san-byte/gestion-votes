// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, E2E_MEETING_ID } = require('../helpers');

/**
 * Operator Console E2E Tests
 */
test.describe('Operator Console', () => {
  test('should not have horizontal overflow', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto(`/operator.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const hasOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(hasOverflow).toBe(false);
  });
});

test.describe('Operator API Security', () => {
  test('operator_state should reject unauthenticated', async ({ request }) => {
    const response = await request.get(`/api/v1/operator_state.php?meeting_id=${E2E_MEETING_ID}`);
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('operator_audit_events should reject unauthenticated', async ({ request }) => {
    const response = await request.get(`/api/v1/operator_audit_events.php?meeting_id=${E2E_MEETING_ID}`);
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });
});
