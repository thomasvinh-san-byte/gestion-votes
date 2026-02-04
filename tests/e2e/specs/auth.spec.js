// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Authentication E2E Tests
 */
test.describe('Authentication', () => {

  test('should display login page', async ({ page }) => {
    await page.goto('/login.html');

    await expect(page).toHaveTitle(/AG-VOTE|Connexion/);
    await expect(page.locator('input[type="password"], input[name="api_key"]')).toBeVisible();
  });

  test('should reject invalid credentials', async ({ page }) => {
    await page.goto('/login.html');

    // Try invalid API key
    await page.fill('input[type="password"], input[name="api_key"]', 'invalid-key');
    await page.click('button[type="submit"]');

    // Should show error or stay on login page
    await expect(page).toHaveURL(/login/);
  });

  test('should login with valid operator credentials', async ({ page }) => {
    await page.goto('/login.html');

    // Use test credentials from seeds
    await page.fill('input[type="password"], input[name="api_key"]', 'operator-key-2026-secret');
    await page.click('button[type="submit"]');

    // Should redirect to meetings or operator page
    await page.waitForURL(/meetings|operator/);
    await expect(page).not.toHaveURL(/login/);
  });

  test('should logout successfully', async ({ page }) => {
    // Login first
    await page.goto('/login.html');
    await page.fill('input[type="password"], input[name="api_key"]', 'operator-key-2026-secret');
    await page.click('button[type="submit"]');
    await page.waitForURL(/meetings|operator/);

    // Find and click logout
    const logoutBtn = page.locator('[data-action="logout"], .logout-btn, a[href*="logout"]');
    if (await logoutBtn.count() > 0) {
      await logoutBtn.first().click();
      await expect(page).toHaveURL(/login/);
    }
  });

});
