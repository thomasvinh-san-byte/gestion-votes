// @ts-check
// @critical-path
const { test, expect } = require('@playwright/test');

/**
 * Critical-path E2E: Public projection screen (/public.htmx.html).
 *
 * This page is PUBLIC — no authentication required.
 *
 * SSE WARNING: public.htmx.html subscribes to an SSE stream on mount which
 * keeps the network BUSY INDEFINITELY. Never use waitUntil:'networkidle' —
 * it will never resolve. Use { waitUntil: 'domcontentloaded' } + explicit
 * waitForSelector for specific elements.
 */

test.describe('E2E-PUBLIC Public projection critical path', () => {

  test('@critical-path public: mount + theme toggle + fullscreen wiring + no horizontal overflow (no auth, SSE-aware)', async ({ page }) => {
    test.setTimeout(60000);

    // Collect page-level JS errors throughout the test
    const pageErrors = [];
    page.on('pageerror', e => pageErrors.push(e.message));

    // ──────────────────────────────────────────────────────────────
    // 1. Page mounts without auth + header visible
    // ──────────────────────────────────────────────────────────────
    // MUST use domcontentloaded — SSE keeps network busy indefinitely
    await page.goto('/public.htmx.html', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#badge', { timeout: 10000 });

    await expect(page.locator('#meeting_title')).toBeVisible();
    await expect(page.locator('#clock')).toBeVisible();
    await expect(page.locator('#btnThemeToggle')).toBeVisible();
    await expect(page.locator('#btnFullscreen')).toBeVisible();

    // ──────────────────────────────────────────────────────────────
    // 2. Dark theme is forced on mount (DISP-01)
    // ──────────────────────────────────────────────────────────────
    const themeBefore = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    expect(themeBefore).toBe('dark');

    // ──────────────────────────────────────────────────────────────
    // 3. Theme toggle — click flips data-theme attribute
    // ──────────────────────────────────────────────────────────────
    await page.click('#btnThemeToggle');
    await page.waitForTimeout(200);

    const themeAfterFirstClick = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    expect(themeAfterFirstClick).toBe('light');

    // Toggle back to dark
    await page.click('#btnThemeToggle');
    await page.waitForTimeout(200);

    const themeRestoredToDark = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    expect(themeRestoredToDark).toBe('dark');

    // ──────────────────────────────────────────────────────────────
    // 4. Fullscreen button — click is handled (must not throw)
    //    Playwright cannot trigger real fullscreen (security restriction)
    //    so we verify the element is wired (has aria-label attribute).
    // ──────────────────────────────────────────────────────────────
    const hasFullscreenWiring = await page.evaluate(() => {
      const el = document.getElementById('btnFullscreen');
      return !!el && el.getAttribute('aria-label') !== null;
    });
    expect(hasFullscreenWiring).toBeTruthy();

    // Click the button via evaluate — if the listener throws it will
    // surface as a pageError which we check at the end
    await page.evaluate(() => {
      try { document.getElementById('btnFullscreen').click(); } catch (_) { /* fullscreen may be blocked */ }
    });

    // ──────────────────────────────────────────────────────────────
    // 5. Status badge reflects meeting state (non-empty)
    // ──────────────────────────────────────────────────────────────
    const badgeText = (await page.locator('#badge').textContent()).trim();
    expect(badgeText.length).toBeGreaterThan(0);

    // ──────────────────────────────────────────────────────────────
    // 6. Waiting state / motion state is visible (exclusive)
    // ──────────────────────────────────────────────────────────────
    const hasVisibleState = await page.evaluate(() => {
      const waiting = document.getElementById('waiting_state');
      const motion  = document.getElementById('motion_title');
      const picker  = document.getElementById('meeting_picker');
      return (waiting && !waiting.hidden && waiting.offsetParent !== null)
          || (motion && motion.textContent && motion.textContent.trim() !== '—')
          || (picker && !picker.hidden && picker.offsetParent !== null);
    });
    expect(hasVisibleState).toBe(true);

    // ──────────────────────────────────────────────────────────────
    // 7. SSE connect does not crash the page
    //    Wait 2s for SSE subscription to settle, then check errors.
    //    Filter out expected fullscreen-related browser messages.
    // ──────────────────────────────────────────────────────────────
    await page.waitForTimeout(2000);
    const criticalErrors = pageErrors.filter(e => !e.toLowerCase().includes('fullscreen'));
    expect(criticalErrors).toEqual([]);

    // ──────────────────────────────────────────────────────────────
    // 8. Width verification — no horizontal overflow on 1920×1080
    // ──────────────────────────────────────────────────────────────
    await page.setViewportSize({ width: 1920, height: 1080 });
    const overflow = await page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    expect(overflow).toBeLessThanOrEqual(1);
  });

});
