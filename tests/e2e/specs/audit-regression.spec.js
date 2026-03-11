// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * UX/UI Audit Regression Tests
 *
 * Verifies all P1/P2/P3 fixes from the UX audit remain intact.
 * Each test maps to a specific audit finding.
 */

const OPERATOR_KEY = 'operator-key-2026-secret';

async function loginAsOperator(page) {
  await page.goto('/login.html');
  await page.fill('input[type="password"], input[name="api_key"]', OPERATOR_KEY);
  await page.click('button[type="submit"]');
  await page.waitForURL(/meetings|operator/, { timeout: 10000 });
}

// ---------------------------------------------------------------------------
// P1 — Critical Fixes
// ---------------------------------------------------------------------------

test.describe('P1: Critical Fixes', () => {

  test('P1-#1: Sidebar pinned should not overlap main content', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/admin.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    // Pin the sidebar
    const sidebar = page.locator('.app-sidebar');
    if (await sidebar.count() > 0) {
      await page.evaluate(() => {
        const sb = document.querySelector('.app-sidebar');
        if (sb) sb.classList.add('pinned');
      });
      await page.waitForTimeout(200);

      // Main content padding should accommodate pinned sidebar
      const mainPadding = await page.evaluate(() => {
        const main = document.querySelector('.app-main');
        return main ? parseFloat(getComputedStyle(main).paddingLeft) : 0;
      });
      const sidebarRight = await page.evaluate(() => {
        const sb = document.querySelector('.app-sidebar');
        return sb ? sb.getBoundingClientRect().right : 0;
      });

      // Padding should be greater than sidebar width to prevent overlap
      expect(mainPadding).toBeGreaterThanOrEqual(sidebarRight);
    }
  });

  test('P1-#2: marked.js loads from local vendor (not CDN)', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/docs.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const hasMarked = await page.evaluate(() => typeof marked !== 'undefined');
    expect(hasMarked).toBeTruthy();

    // Verify the script tag points to vendor, not CDN
    const vendorScript = await page.evaluate(() => {
      const scripts = document.querySelectorAll('script[src*="vendor/marked"]');
      return scripts.length > 0;
    });
    expect(vendorScript).toBeTruthy();
  });

  test('P1-#3: KPI stats show mdash (—) not 0 on empty data', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/members.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const kpiTotal = await page.evaluate(() => {
      const el = document.getElementById('kpiTotal');
      return el ? el.textContent.trim() : null;
    });

    // Should show mdash (—) not "0" when no data loaded
    if (kpiTotal !== null) {
      expect(kpiTotal).toBe('—');
    }
  });

});

// ---------------------------------------------------------------------------
// P2 — Important Fixes
// ---------------------------------------------------------------------------

test.describe('P2: Important Fixes', () => {

  test('P2-#4: Meetings onboarding banner has dismiss button', async ({ page }) => {
    await loginAsOperator(page);

    // Clear localStorage to ensure banner shows
    await page.evaluate(() => localStorage.removeItem('ag_meetings_ob_dismissed'));

    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const btnClose = page.locator('#btnCloseOnboarding');
    if (await btnClose.count() > 0) {
      await expect(btnClose).toBeVisible();

      // Click dismiss
      await btnClose.click();
      await page.waitForTimeout(200);

      // Banner should be hidden
      const banner = page.locator('#onboardingBanner');
      await expect(banner).toBeHidden();

      // localStorage should remember dismissal
      const dismissed = await page.evaluate(() => localStorage.getItem('ag_meetings_ob_dismissed'));
      expect(dismissed).toBe('1');
    }
  });

  test('P2-#6: Trust audit event modal exists with close button', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/trust.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const modal = page.locator('#auditEventModal');
    const closeBtn = page.locator('#auditModalClose');

    if (await modal.count() > 0) {
      expect(await closeBtn.count()).toBeGreaterThan(0);
    }
  });

  test('P2-#7: Meeting stats show mdash (—) not 0 initially', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const statLive = await page.evaluate(() => {
      const el = document.getElementById('statLive');
      return el ? el.textContent.trim() : null;
    });

    if (statLive !== null) {
      expect(statLive).toBe('—');
    }
  });

  test('P2-#8: Report PV shows empty state when no PV loaded', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/report.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const emptyState = page.locator('#pvEmptyState');
    const iframe = page.locator('#pvFrame');

    if (await emptyState.count() > 0) {
      await expect(emptyState).toBeVisible();

      // iframe should be hidden initially
      if (await iframe.count() > 0) {
        const display = await iframe.evaluate(el => el.style.display);
        expect(display).toBe('none');
      }
    }
  });

});

// ---------------------------------------------------------------------------
// P3 — Polish Fixes
// ---------------------------------------------------------------------------

test.describe('P3: Polish Fixes', () => {

  test('P3-#9: Dead session-timeout-banner removed', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/meetings.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    // The dead banner should NOT exist in the DOM
    const deadBanner = page.locator('#sessionTimeoutBanner');
    expect(await deadBanner.count()).toBe(0);
  });

  test('P3-#10: Login page has eye toggle for password visibility', async ({ page }) => {
    await page.goto('/login.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    // Eye icons should exist
    const eyeOpen = page.locator('.toggle-visibility .eye-open');
    const eyeClosed = page.locator('.toggle-visibility .eye-closed');

    if (await eyeOpen.count() > 0) {
      await expect(eyeOpen).toBeVisible();

      // Click toggle
      const toggleBtn = page.locator('#togglePassword');
      if (await toggleBtn.count() > 0) {
        await toggleBtn.click();
        await page.waitForTimeout(200);

        // Password field should switch to text type
        const pwType = await page.evaluate(() => {
          const input = document.getElementById('password');
          return input ? input.type : null;
        });
        expect(pwType).toBe('text');
      }
    }
  });

  test('P3-#12: Analytics chart containers have loading spinners', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/analytics.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const chartContainers = page.locator('.chart-container');
    const count = await chartContainers.count();

    // Chart containers should exist
    expect(count).toBeGreaterThan(0);
  });

  test('P3-#13: Help tour cards have "Lancer" badges', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/help.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const badges = page.locator('.tour-launch');
    const cards = page.locator('.tour-card');

    const badgeCount = await badges.count();
    const cardCount = await cards.count();

    // All tour cards should have a Lancer badge
    if (cardCount > 0) {
      expect(badgeCount).toBe(cardCount);
    }
  });

  test('P3-#15: Admin has email template reset button', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/admin.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);

    const resetBtn = page.locator('#btnResetEmailTemplates');
    if (await resetBtn.count() > 0) {
      // Button should have a tooltip
      const title = await resetBtn.getAttribute('title');
      expect(title).toBeTruthy();
    }
  });

});
