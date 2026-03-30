// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, CREDENTIALS } = require('../helpers');

/**
 * Authentication E2E Tests
 */
test.describe('Authentication', () => {

  test('should reject invalid credentials', async ({ page }) => {
    await page.goto('/login.html');
    await page.fill('#email', 'invalid@example.com');
    await page.fill('#password', 'invalid-password');
    await page.click('#submitBtn');

    await expect(page).toHaveURL(/login/);
  });

  test('should login with valid operator credentials', async ({ page }) => {
    await page.goto('/login.html');
    await page.fill('#email', CREDENTIALS.operator.email);
    await page.fill('#password', CREDENTIALS.operator.password);
    await page.click('#submitBtn');

    await page.waitForURL(/meetings|operator/);
    await expect(page).not.toHaveURL(/login/);
  });

});
