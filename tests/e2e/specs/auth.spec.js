// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Authentication E2E Tests
 */
test.describe('Authentication', () => {

  test('should reject invalid credentials', async ({ page }) => {
    await page.goto('/login.html');
    await page.fill('input[type="password"], input[name="api_key"]', 'invalid-key');
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/login/);
  });

  test('should login with valid operator credentials', async ({ page }) => {
    await page.goto('/login.html');
    await page.fill('input[type="password"], input[name="api_key"]', 'operator-key-2026-secret');
    await page.click('button[type="submit"]');

    await page.waitForURL(/meetings|operator/);
    await expect(page).not.toHaveURL(/login/);
  });

});
