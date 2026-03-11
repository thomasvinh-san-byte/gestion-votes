// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, OPERATOR_KEY } = require('../helpers');

/**
 * Accessibility E2E Tests
 * Basic checks for WCAG compliance
 */
test.describe('Accessibility', () => {

  test('login page should have accessible form', async ({ page }) => {
    await page.goto('/login.html');

    const input = page.locator('input[type="password"], input[name="api_key"]');
    await expect(input).toBeVisible();

    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeVisible();
    await expect(submitBtn).toBeEnabled();
  });

  test('help page should have proper heading structure', async ({ page }) => {
    await page.goto('/help.htmx.html');

    const h1 = page.locator('h1');
    await expect(h1.first()).toBeVisible();

    const faqItems = page.locator('.faq-question, .faq-item');
    if (await faqItems.count() > 0) {
      await expect(faqItems.first()).toBeVisible();
    }
  });

  test('navigation should be keyboard accessible', async ({ page }) => {
    await loginAsOperator(page);

    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');

    const focusedElement = page.locator(':focus');
    await expect(focusedElement).toBeVisible();
  });

  test('main content should have proper landmarks', async ({ page }) => {
    await page.goto('/help.htmx.html');

    const main = page.locator('main, [role="main"]');
    await expect(main.first()).toBeVisible();

    const nav = page.locator('nav, [role="navigation"], .app-sidebar');
    if (await nav.count() > 0) {
      await expect(nav.first()).toBeVisible();
    }
  });

});
