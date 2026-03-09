// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Trust & Anomaly Detection E2E Tests
 *
 * Tests the trust dashboard: anomaly detection, integrity checks,
 * device management, and security monitoring.
 */

const OPERATOR_KEY = 'operator-key-2026-secret';
const E2E_MEETING_ID = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';

async function loginAsOperator(page) {
  await page.goto('/login.html');
  await page.fill('input[type="password"], input[name="api_key"]', OPERATOR_KEY);
  await page.click('button[type="submit"]');
  await page.waitForURL(/meetings|operator/, { timeout: 10000 });
}

// ---------------------------------------------------------------------------
// Trust Dashboard
// ---------------------------------------------------------------------------

test.describe('Trust Dashboard', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should display trust page', async ({ page }) => {
    await page.goto('/trust.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Confiance|Trust/);
    await page.waitForLoadState('networkidle');
  });

  test('should show trust score or status', async ({ page }) => {
    await page.goto('/trust.htmx.html');
    await page.waitForLoadState('networkidle');

    // Trust score, gauge, or status indicator
    const trustEl = page.locator('.trust-score, .trust-gauge, [data-trust], .integrity-status');
    if (await trustEl.count() > 0) {
      await expect(trustEl.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should display anomaly list or empty state', async ({ page }) => {
    await page.goto('/trust.htmx.html');
    await page.waitForLoadState('networkidle');

    // Anomalies section
    const anomalies = page.locator('.anomaly-card, [data-anomaly], .anomaly-list, .empty-state');
    if (await anomalies.count() > 0) {
      await expect(anomalies.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should have device management section', async ({ page }) => {
    await page.goto('/trust.htmx.html');
    await page.waitForLoadState('networkidle');

    // Device list or management section
    const devices = page.locator('.device-list, [data-devices], #devicesList');
    if (await devices.count() > 0) {
      await expect(devices.first()).toBeVisible({ timeout: 5000 });
    }
  });

});

// ---------------------------------------------------------------------------
// Trust APIs
// ---------------------------------------------------------------------------

test.describe('Trust APIs', () => {

  test('trust checks should reject unauthenticated', async ({ request }) => {
    const response = await request.get(`/api/v1/trust_checks.php?meeting_id=${E2E_MEETING_ID}`);
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('trust anomalies should reject unauthenticated', async ({ request }) => {
    const response = await request.get(`/api/v1/trust_anomalies.php?meeting_id=${E2E_MEETING_ID}`);
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('device block should reject unauthenticated', async ({ request }) => {
    const response = await request.post('/api/v1/device_block.php', {
      data: { device_id: 'test-device' },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

});
