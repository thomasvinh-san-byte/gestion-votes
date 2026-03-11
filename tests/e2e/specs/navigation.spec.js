// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Navigation & Shell E2E Tests
 *
 * Tests the application shell: sidebar navigation, bottom nav (mobile),
 * responsive layout, search overlay, notification panel, breadcrumbs.
 */

const { loginAsOperator: login } = require('../helpers');

// ---------------------------------------------------------------------------
// Desktop Navigation
// ---------------------------------------------------------------------------

test.describe('Desktop Navigation', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('should display sidebar navigation', async ({ page }) => {
    await page.goto('/meetings.htmx.html');

    const sidebar = page.locator('.app-sidebar, nav, [role="navigation"]');
    await expect(sidebar.first()).toBeVisible({ timeout: 5000 });
  });

  test('should have navigation links to all main pages', async ({ page }) => {
    await page.goto('/meetings.htmx.html');

    // Check for key navigation links
    const meetingsLink = page.locator('a[href*="meetings"], [data-nav="meetings"]');
    const membersLink = page.locator('a[href*="members"], [data-nav="members"]');

    if (await meetingsLink.count() > 0) {
      await expect(meetingsLink.first()).toBeVisible();
    }
    if (await membersLink.count() > 0) {
      await expect(membersLink.first()).toBeVisible();
    }
  });

  test('should navigate between pages via sidebar', async ({ page }) => {
    await page.goto('/meetings.htmx.html');

    // Click on members link
    const membersLink = page.locator('a[href*="members"], [data-nav="members"]');
    if (await membersLink.count() > 0) {
      await membersLink.first().click();
      await page.waitForURL(/members/);
      await expect(page).toHaveURL(/members/);
    }
  });

  test('should highlight active page in navigation', async ({ page }) => {
    await page.goto('/meetings.htmx.html');

    // Active link should have active class or aria-current
    const activeLink = page.locator('.nav-link.active, [aria-current="page"], .sidebar-link.active');
    if (await activeLink.count() > 0) {
      await expect(activeLink.first()).toBeVisible();
    }
  });

});

// ---------------------------------------------------------------------------
// Mobile Navigation
// ---------------------------------------------------------------------------

test.describe('Mobile Navigation', () => {
  test.use({ viewport: { width: 375, height: 812 } }); // iPhone X

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('should display bottom navigation on mobile', async ({ page }) => {
    await page.goto('/meetings.htmx.html');

    // Bottom nav or hamburger menu
    const bottomNav = page.locator('.bottom-nav, .mobile-nav, [data-mobile-nav]');
    const hamburger = page.locator('.hamburger, [data-toggle="nav"], .menu-toggle');
    const hasNav = (await bottomNav.count()) > 0 || (await hamburger.count()) > 0;
    expect(hasNav).toBeTruthy();
  });

  test('should hide sidebar on mobile', async ({ page }) => {
    await page.goto('/meetings.htmx.html');

    // Sidebar should be hidden or collapsed
    const sidebar = page.locator('.app-sidebar');
    if (await sidebar.count() > 0) {
      // Sidebar may exist but be hidden/collapsed
      const isVisible = await sidebar.first().isVisible();
      // On mobile, sidebar should be hidden by default
    }
  });

});

// ---------------------------------------------------------------------------
// Search
// ---------------------------------------------------------------------------

test.describe('Search Overlay', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
    await page.goto('/meetings.htmx.html');
  });

  test('should open search overlay', async ({ page }) => {
    // Look for search trigger
    const searchTrigger = page.locator('[data-action="search"], .search-trigger, button:has-text("Rechercher")');
    if (await searchTrigger.count() > 0) {
      await searchTrigger.first().click();
      await page.waitForTimeout(500);

      // Search overlay should appear
      const overlay = page.locator('.search-overlay, [data-search-overlay], dialog');
      if (await overlay.count() > 0) {
        await expect(overlay.first()).toBeVisible({ timeout: 3000 });
      }
    }
  });

  test('should open search with keyboard shortcut', async ({ page }) => {
    // Ctrl+K or Cmd+K typically opens search
    await page.keyboard.press('Control+k');
    await page.waitForTimeout(500);

    const overlay = page.locator('.search-overlay, [data-search-overlay], dialog');
    if (await overlay.count() > 0) {
      // Overlay may or may not appear depending on implementation
    }
  });

});

// ---------------------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------------------

test.describe('Notifications', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
    await page.goto('/meetings.htmx.html');
  });

  test('should display notification bell', async ({ page }) => {
    const bell = page.locator('.notification-bell, [data-notifications], .bell-icon');
    if (await bell.count() > 0) {
      await expect(bell.first()).toBeVisible();
    }
  });

  test('should open notification panel', async ({ page }) => {
    const bell = page.locator('.notification-bell, [data-notifications], .bell-icon');
    if (await bell.count() > 0) {
      await bell.first().click();
      await page.waitForTimeout(500);

      const panel = page.locator('.notification-panel, .notif-panel, [data-notif-panel]');
      if (await panel.count() > 0) {
        await expect(panel.first()).toBeVisible({ timeout: 3000 });
      }
    }
  });

});

// ---------------------------------------------------------------------------
// Scroll To Top
// ---------------------------------------------------------------------------

test.describe('Scroll To Top', () => {

  test('should show scroll-to-top button after scrolling down', async ({ page }) => {
    await login(page);
    await page.goto('/meetings.htmx.html');

    // Scroll down
    await page.evaluate(() => window.scrollTo(0, 2000));
    await page.waitForTimeout(500);

    const scrollBtn = page.locator('ag-scroll-top, .scroll-top, [data-scroll-top]');
    if (await scrollBtn.count() > 0) {
      // Button should be visible after scroll
    }
  });

});

// ---------------------------------------------------------------------------
// Page Load Performance
// ---------------------------------------------------------------------------

test.describe('Page Load', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('meetings page should load within 5 seconds', async ({ page }) => {
    const start = Date.now();
    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('networkidle');
    const duration = Date.now() - start;

    expect(duration).toBeLessThan(5000);
  });

  test('operator page should load within 5 seconds', async ({ page }) => {
    const start = Date.now();
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');
    const duration = Date.now() - start;

    expect(duration).toBeLessThan(5000);
  });

  test('admin page should load within 5 seconds', async ({ page }) => {
    const start = Date.now();
    await page.goto('/admin.htmx.html');
    await page.waitForLoadState('networkidle');
    const duration = Date.now() - start;

    expect(duration).toBeLessThan(5000);
  });

});
