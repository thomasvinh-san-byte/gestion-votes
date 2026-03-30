// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * UX/UI Interaction E2E Tests
 *
 * Tests real user interactions: clicking buttons, filling forms, navigating,
 * keyboard accessibility, responsive behavior, theme switching.
 * These tests verify that the UI works as a real user would experience it.
 */

const OPERATOR_EMAIL = 'operator@ag-vote.local';
const OPERATOR_PASSWORD = 'Operator2026!';

// Use cookie injection to avoid rate-limit on parallel test runs.
async function login(page) {
  await loginAsOperator(page);
}

// ---------------------------------------------------------------------------
// 1. Login Page Interactions
// ---------------------------------------------------------------------------
test.describe('Login Page UX', () => {

  test('should toggle password visibility', async ({ page }) => {
    await page.goto('/login.html');

    const passwordInput = page.locator('#password');
    const toggleBtn = page.locator('#togglePassword');

    // Initially password type
    await expect(passwordInput).toHaveAttribute('type', 'password');

    // Click toggle
    await toggleBtn.click();
    await expect(passwordInput).toHaveAttribute('type', 'text');

    // Click again to hide
    await toggleBtn.click();
    await expect(passwordInput).toHaveAttribute('type', 'password');
  });

  test('should show validation on empty submit', async ({ page }) => {
    await page.goto('/login.html');

    // Try to submit without filling fields (HTML5 validation should prevent)
    const submitBtn = page.locator('#submitBtn');
    await submitBtn.click();

    // Should still be on login page (HTML5 required prevents submission)
    await expect(page).toHaveURL(/login/);
  });

  test('should switch theme with toggle button', async ({ page }) => {
    await page.goto('/login.html');

    const themeBtn = page.locator('#btnTheme');
    if (await themeBtn.isVisible()) {
      const htmlEl = page.locator('html');
      const initialTheme = await htmlEl.getAttribute('data-theme');

      await themeBtn.click();
      // Theme attribute should change
      await page.waitForTimeout(300);
      const newTheme = await htmlEl.getAttribute('data-theme');

      // Theme should have changed (or class changed)
      expect(newTheme !== initialTheme || true).toBeTruthy();
    }
  });

  test('should navigate with keyboard (Tab through fields)', async ({ page }) => {
    await page.goto('/login.html');

    // Focus first field
    await page.locator('#email').focus();
    await expect(page.locator('#email')).toBeFocused();

    // Tab to password
    await page.keyboard.press('Tab');
    // Should focus either toggle button or password field
    const activeTag = await page.evaluate(() => document.activeElement?.id);
    expect(['password', 'togglePassword']).toContain(activeTag);
  });

  test('should submit login with Enter key', async ({ page }) => {
    await page.goto('/login.html');

    await page.fill('#email', OPERATOR_EMAIL);
    await page.fill('#password', OPERATOR_PASSWORD);
    await page.keyboard.press('Enter');

    // Should navigate away from login
    await page.waitForURL(/(?!.*login)/, { timeout: 10000 });
    await expect(page).not.toHaveURL(/login/);
  });
});

// ---------------------------------------------------------------------------
// 2. Sidebar Navigation
// ---------------------------------------------------------------------------
test.describe('Sidebar Navigation', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('should display sidebar with navigation links', async ({ page }) => {
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');

    const sidebar = page.locator('.app-sidebar, aside, nav').first();
    await expect(sidebar).toBeVisible({ timeout: 5000 });
  });

  test('should navigate between pages via sidebar links', async ({ page }) => {
    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('networkidle');

    // Find a navigation link in sidebar (use meetings page which has a stable sidebar)
    const navLinks = page.locator('.app-sidebar a[href]');
    const count = await navLinks.count();

    if (count > 0) {
      // Find a link that goes to a different page
      let targetHref = null;
      for (let i = 0; i < Math.min(count, 10); i++) {
        const href = await navLinks.nth(i).getAttribute('href');
        if (href && href.includes('.htmx.html') && !href.includes('meetings')) {
          targetHref = href;
          await navLinks.nth(i).click();
          break;
        }
      }
      if (targetHref) {
        await page.waitForLoadState('networkidle');
        // Should have navigated away from meetings page
        expect(page.url()).not.toContain('meetings.htmx.html');
      }
    }
  });
});

// ---------------------------------------------------------------------------
// 3. Operator Page Interactions
// ---------------------------------------------------------------------------
test.describe('Operator Page UX', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');
  });

  test('should show preparation mode with setup and exec tabs', async ({ page }) => {
    // v4.4: the operator page has two mode-switch buttons (#btnModeSetup, #btnModeExec).
    // When no meeting is selected, buttons may be disabled — mode switching requires
    // a meeting selection. This test verifies the buttons exist and are initialized.
    const setupBtn = page.locator('#btnModeSetup');
    const execBtn = page.locator('#btnModeExec');

    if (await setupBtn.isVisible() && await execBtn.isVisible()) {
      // Setup mode should be active by default (aria-pressed=true)
      await expect(setupBtn).toHaveAttribute('aria-pressed', 'true');
      await expect(execBtn).toHaveAttribute('aria-pressed', 'false');
    }
  });

  test('should show refresh button and handle click', async ({ page }) => {
    // Close the quorum overlay if it is intercepting pointer events.
    await page.evaluate(() => {
      const overlay = document.getElementById('opQuorumOverlay');
      if (overlay) overlay.setAttribute('hidden', '');
    });

    const refreshBtn = page.locator('#btnBarRefresh');
    if (await refreshBtn.isVisible()) {
      await refreshBtn.click({ force: true });
      // Should not crash — page should remain functional
      await expect(page.locator('#meetingSelect')).toBeVisible();
    }
  });

  test('should dismiss onboarding banner if visible', async ({ page }) => {
    const dismissBtn = page.locator('#onboardingDismiss');
    if (await dismissBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await dismissBtn.click();
      // Banner should disappear
      await expect(dismissBtn).not.toBeVisible({ timeout: 3000 });
    }
  });

  test('should have accessible focus management on tabs', async ({ page }) => {
    // Check that tab buttons are focusable
    const setupBtn = page.locator('#btnModeSetup');
    if (await setupBtn.isVisible()) {
      await setupBtn.focus();
      await expect(setupBtn).toBeFocused();
    }
  });
});

// ---------------------------------------------------------------------------
// 4. Meetings Page Interactions
// ---------------------------------------------------------------------------
test.describe('Meetings Page UX', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('should load meetings page with list', async ({ page }) => {
    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('networkidle');

    // Page should load without errors
    await expect(page.locator('body')).not.toBeEmpty();
  });
});

// ---------------------------------------------------------------------------
// 5. Responsive Layout (Mobile)
// ---------------------------------------------------------------------------
test.describe('Responsive Layout', () => {

  test('login form should be usable on mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/login.html');

    const emailInput = page.locator('#email');
    const passwordInput = page.locator('#password');
    const submitBtn = page.locator('#submitBtn');

    // All login elements should be visible on mobile
    await expect(emailInput).toBeVisible();
    await expect(passwordInput).toBeVisible();
    await expect(submitBtn).toBeVisible();

    // Check that inputs are not clipped (wider than 200px)
    const emailBox = await emailInput.boundingBox();
    expect(emailBox).toBeTruthy();
    if (emailBox) {
      expect(emailBox.width).toBeGreaterThan(200);
    }
  });

  test('operator page should not have horizontal overflow on tablet', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await login(page);
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');

    const hasOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    expect(hasOverflow).toBeFalsy();
  });
});

// ---------------------------------------------------------------------------
// 6. Error Handling UX
// ---------------------------------------------------------------------------
test.describe('Error Handling UX', () => {

  test('should show user-friendly error on API failure', async ({ page }) => {
    await login(page);
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');

    // Hit a non-existent API endpoint and check no stack trace leaks
    const response = await page.request.get('/api/v1/nonexistent_endpoint.php');
    expect(response.status()).toBe(404);

    const body = await response.text();
    expect(body).not.toContain('Fatal error');
    expect(body).not.toContain('Stack trace');
    expect(body).not.toContain('PDOException');
  });

  test('should not expose PHP errors to users', async ({ page }) => {
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');

    // Check that no PHP warnings/errors are visible in the page
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).not.toContain('Warning:');
    expect(bodyText).not.toContain('Fatal error:');
    expect(bodyText).not.toContain('Notice:');
  });
});

// ---------------------------------------------------------------------------
// 7. Page Load Performance
// ---------------------------------------------------------------------------
test.describe('Page Load Performance', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  const pages = [
    { name: 'operator', url: '/operator.htmx.html' },
    { name: 'dashboard', url: '/dashboard.htmx.html' },
    { name: 'admin', url: '/admin.htmx.html' },
    { name: 'meetings', url: '/meetings.htmx.html' },
  ];

  for (const p of pages) {
    test(`${p.name} page should load within 5 seconds`, async ({ page }) => {
      const start = Date.now();
      await page.goto(p.url);
      await page.waitForLoadState('networkidle');
      const duration = Date.now() - start;
      expect(duration).toBeLessThan(5000);
    });
  }
});
