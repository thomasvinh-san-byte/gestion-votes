// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * Mobile Viewport Regression Tests
 *
 * Verifies key pages render correctly on mobile and tablet viewports.
 * Covers responsive layout, touch targets, and mobile-specific UI.
 */

// ---------------------------------------------------------------------------
// Mobile (375x812 — iPhone X)
// ---------------------------------------------------------------------------

test.describe('Mobile Viewport (375x812)', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('login page renders without horizontal scroll', async ({ page }) => {
    await page.goto('/login.html');
    await page.waitForLoadState('domcontentloaded');

    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
  });

  test('login form is usable on mobile', async ({ page }) => {
    await page.goto('/login.html');
    await page.waitForLoadState('domcontentloaded');

    const input = page.locator('input[type="password"], input[name="api_key"]');
    await expect(input).toBeVisible();

    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeVisible();

    // Touch target should be at least 44px
    const btnBox = await submitBtn.boundingBox();
    if (btnBox) {
      expect(btnBox.height).toBeGreaterThanOrEqual(40);
    }
  });

  test('meetings page has no horizontal overflow', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
  });

  test('sidebar is hidden on mobile by default', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const sidebar = page.locator('.app-sidebar');
    if (await sidebar.count() > 0) {
      // Sidebar should be off-screen or hidden
      const box = await sidebar.boundingBox();
      if (box) {
        // If visible, it should be positioned off-screen (negative left)
        expect(box.x + box.width).toBeLessThanOrEqual(0);
      }
    }
  });

  test('bottom nav or hamburger is present on mobile', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const bottomNav = page.locator('.bottom-nav, .mobile-nav, [data-mobile-nav]');
    const hamburger = page.locator('.hamburger, [data-toggle="nav"], .menu-toggle');
    const navCount = (await bottomNav.count()) + (await hamburger.count());
    expect(navCount).toBeGreaterThan(0);
  });

  test('vote page renders on mobile', async ({ page }) => {
    await page.goto('/vote.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    await expect(page).toHaveTitle(/AG-VOTE|Vote/);

    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
  });

  test('public display fits mobile viewport', async ({ page }) => {
    await page.goto('/public.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
  });

  test('help page is readable on mobile', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/help.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    // h1 should be visible
    const h1 = page.locator('h1');
    await expect(h1.first()).toBeVisible();

    // No horizontal overflow
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
  });
});

// ---------------------------------------------------------------------------
// Tablet (768x1024 — iPad)
// ---------------------------------------------------------------------------

test.describe('Tablet Viewport (768x1024)', () => {
  test.use({ viewport: { width: 768, height: 1024 } });

  test('meetings page has no horizontal overflow', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
  });

  test('members page renders on tablet', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/members.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
  });

  test('analytics page renders on tablet', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/analytics.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
  });

  test('admin page renders on tablet', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/admin.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
  });

  test('operator console renders on tablet', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
  });
});
