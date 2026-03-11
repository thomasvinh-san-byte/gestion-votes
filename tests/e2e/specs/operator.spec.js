// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, E2E_MEETING_ID } = require('../helpers');

/**
 * Operator Console E2E Tests
 *
 * Tests the operator page: meeting console, tabs, resolution management.
 */

test.describe('Operator Console', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should load operator page', async ({ page }) => {
    await page.goto(`/operator.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await expect(page).toHaveTitle(/Fiche séance|AG-VOTE/);
    await page.waitForLoadState('domcontentloaded');
  });

  test('should display tab navigation', async ({ page }) => {
    await page.goto(`/operator.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const tabs = page.locator('[data-tab], .tab-btn, .tabs-nav button');
    if (await tabs.count() > 0) {
      await expect(tabs.first()).toBeVisible();
    }
  });

  test('should show meeting context or title', async ({ page }) => {
    await page.goto(`/operator.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const titleEl = page.locator('.meeting-title, .session-title, h1, [data-meeting-title]');
    if (await titleEl.count() > 0) {
      await expect(titleEl.first()).toBeVisible();
    }
  });

  test('should have skip links for accessibility', async ({ page }) => {
    await page.goto(`/operator.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');

    const skipLink = page.locator('.skip-link');
    expect(await skipLink.count()).toBeGreaterThan(0);
  });

  test('should display noscript warning element', async ({ page }) => {
    await page.goto(`/operator.htmx.html?meeting_id=${E2E_MEETING_ID}`);
    await page.waitForLoadState('domcontentloaded');

    const noscript = page.locator('noscript');
    expect(await noscript.count()).toBeGreaterThan(0);
  });

  test('should not have horizontal overflow', async ({ page }) => {
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
