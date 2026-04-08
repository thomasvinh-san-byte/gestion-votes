// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, loginAsAdmin, OPERATOR_KEY } = require('../helpers');
const { axeAudit } = require('../helpers/axeAudit');

/**
 * Accessibility E2E Tests
 * Basic checks for WCAG compliance
 */
test.describe('Accessibility', () => {

  test('login page should have accessible form', async ({ page }) => {
    await page.goto('/login.html');

    const emailInput = page.locator('#email');
    await expect(emailInput).toBeVisible();

    const passwordInput = page.locator('#password');
    await expect(passwordInput).toBeVisible();

    const submitBtn = page.locator('#submitBtn');
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

/**
 * Axe-core WCAG 2.0 A/AA audits per key page.
 * Each test navigates to the page, waits for content to settle, then runs
 * an automated audit asserting zero critical/serious violations.
 *
 * If an audit fails due to pre-existing violations in source HTML/CSS,
 * the test is skipped with a TODO referencing the rule ID (per TEST-03 scope).
 */
test.describe('Axe audits per key page', () => {
  test('login.html has no critical axe violations', async ({ page }) => {
    await page.goto('/login.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#email')).toBeVisible();
    await axeAudit(page, 'login.html');
  });

  test('dashboard.htmx.html has no critical axe violations', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/dashboard.htmx.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('main, #content, [data-page]')).toBeVisible({ timeout: 10000 });
    await axeAudit(page, 'dashboard.htmx.html');
  });

  test('meetings.htmx.html has no critical axe violations', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/meetings.htmx.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('main, #content, [data-page]')).toBeVisible({ timeout: 10000 });
    await axeAudit(page, 'meetings.htmx.html');
  });

  test('members.htmx.html has no critical axe violations', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/members.htmx.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('main, #content, [data-page]')).toBeVisible({ timeout: 10000 });
    await axeAudit(page, 'members.htmx.html');
  });

  test('operator.htmx.html has no critical axe violations', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/operator.htmx.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('main, #content, [data-page]')).toBeVisible({ timeout: 10000 });
    await axeAudit(page, 'operator.htmx.html');
  });

  test('settings.htmx.html has no critical axe violations', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/settings.htmx.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('main, #content, [data-page]')).toBeVisible({ timeout: 10000 });
    await axeAudit(page, 'settings.htmx.html');
  });

  test('audit.htmx.html has no critical axe violations', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/audit.htmx.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('main, #content, [data-page]')).toBeVisible({ timeout: 10000 });
    await axeAudit(page, 'audit.htmx.html');
  });
});
