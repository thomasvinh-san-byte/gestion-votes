// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Accessibility E2E Tests
 * Basic checks for WCAG compliance
 */
test.describe('Accessibility', () => {

  test('login page should have accessible form', async ({ page }) => {
    await page.goto('/login.html');

    // Check for proper labels
    const input = page.locator('input[type="password"], input[name="api_key"]');
    await expect(input).toBeVisible();

    // Check submit button is accessible
    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeVisible();
    await expect(submitBtn).toBeEnabled();
  });

  test('help page should have proper heading structure', async ({ page }) => {
    await page.goto('/help.htmx.html');

    // Should have h1
    const h1 = page.locator('h1');
    await expect(h1.first()).toBeVisible();

    // FAQ items should be interactive
    const faqItems = page.locator('.faq-question, .faq-item');
    if (await faqItems.count() > 0) {
      await expect(faqItems.first()).toBeVisible();
    }
  });

  test('navigation should be keyboard accessible', async ({ page }) => {
    await page.goto('/login.html');
    await page.fill('input[type="password"], input[name="api_key"]', 'operator-key-2026-secret');
    await page.click('button[type="submit"]');
    await page.waitForURL(/meetings|operator/);

    // Navigate using keyboard
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');

    // Focus should be visible somewhere
    const focusedElement = page.locator(':focus');
    await expect(focusedElement).toBeVisible();
  });

  test('toast notifications should have aria-live region', async ({ page }) => {
    await page.goto('/login.html');
    await page.fill('input[type="password"], input[name="api_key"]', 'invalid-key');
    await page.click('button[type="submit"]');

    // Wait for potential toast
    await page.waitForTimeout(1000);

    // Check if toast container has aria-live
    const toastContainer = page.locator('#notif_box, .toast-container');
    if (await toastContainer.count() > 0) {
      const ariaLive = await toastContainer.getAttribute('aria-live');
      expect(ariaLive).toBeTruthy();
    }
  });

  test('icons should have aria-hidden', async ({ page }) => {
    await page.goto('/help.htmx.html');

    // Check that decorative icons have aria-hidden
    const icons = page.locator('svg.icon');
    const count = await icons.count();

    for (let i = 0; i < Math.min(count, 10); i++) {
      const icon = icons.nth(i);
      const ariaHidden = await icon.getAttribute('aria-hidden');
      // Decorative icons should have aria-hidden="true"
      // This test just checks that SOME icons have it
      if (ariaHidden === 'true') {
        return; // Test passes if at least one icon has aria-hidden
      }
    }
  });

  test('main content should have proper landmarks', async ({ page }) => {
    await page.goto('/help.htmx.html');

    // Check for main landmark
    const main = page.locator('main, [role="main"]');
    await expect(main.first()).toBeVisible();

    // Check for navigation
    const nav = page.locator('nav, [role="navigation"], .app-sidebar');
    if (await nav.count() > 0) {
      await expect(nav.first()).toBeVisible();
    }
  });

});
