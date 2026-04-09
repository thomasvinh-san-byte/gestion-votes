// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, loginAsAdmin, loginAsVoter, OPERATOR_KEY } = require('../helpers');
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

// Axe audit matrix — A11Y-01 — 21 HTMX pages + login.
// loginFn: null = anonymous. requiredLocator: must be visible before axe runs (HTMX hydration safety).
// extraDisabled: per-page waivers (D-09/D-10); keep empty until baseline run in plan 16-02.
const PAGES = [
  { path: '/login.html',                loginFn: null,            requiredLocator: '#email' },
  { path: '/dashboard.htmx.html',       loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/meetings.htmx.html',        loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/members.htmx.html',         loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/operator.htmx.html',        loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/settings.htmx.html',        loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
  { path: '/audit.htmx.html',           loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
  { path: '/admin.htmx.html',           loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
  { path: '/analytics.htmx.html',       loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/archives.htmx.html',        loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/docs.htmx.html',            loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/email-templates.htmx.html', loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
  { path: '/help.htmx.html',            loginFn: null,            requiredLocator: 'h1' },
  { path: '/hub.htmx.html',             loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/postsession.htmx.html',     loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/public.htmx.html',          loginFn: null,            requiredLocator: '.projection-header, main, [data-page]' },
  { path: '/report.htmx.html',          loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/trust.htmx.html',           loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' }, // RESEARCH Pitfall 5: admin fallback
  { path: '/users.htmx.html',           loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
  { path: '/validate.htmx.html',        loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/vote.htmx.html',            loginFn: loginAsVoter,    requiredLocator: '#meetingSelect, [data-page], main' },
  { path: '/wizard.htmx.html',          loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' }, // wizard step 1 only, per RESEARCH p.362
];

test.describe('Axe audits — 21 HTMX pages + login (A11Y-01)', () => {
  for (const p of PAGES) {
    test(`${p.path} has no critical/serious axe violations`, async ({ page }) => {
      if (p.loginFn) await p.loginFn(page);
      await page.goto(p.path, { waitUntil: 'domcontentloaded' });
      await expect(page.locator(p.requiredLocator).first()).toBeVisible({ timeout: 10000 });
      await axeAudit(page, p.path, { extraDisabledRules: p.extraDisabled || [] });
    });
  }
});
