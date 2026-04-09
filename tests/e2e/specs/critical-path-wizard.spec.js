// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * Critical-path E2E spec — Wizard 4-step flow.
 *
 * Asserts the complete session creation workflow:
 *   Step 0 — General info (validation + field fill + advance)
 *   Step 1 — Member addition (inline form + count assertion)
 *   Step 2 — Resolution creation + secret vote chip toggle
 *   Step 3 — Review recap + final API create call
 *
 * Tag: @critical-path
 * Timeout: 120 s (DOM transitions + real API call can be slow)
 */

test.describe('Wizard — critical path @critical-path', () => {
  test('wizard: 4-step flow — fill + navigate + create session', async ({ page }) => {
    test.setTimeout(120000);

    await loginAsOperator(page);

    // -------------------------------------------------------------------------
    // Pre-flight: validation fires on empty required field BEFORE filling
    // -------------------------------------------------------------------------
    await page.goto('/wizard.htmx.html', { waitUntil: 'domcontentloaded' });

    // Step 0 must be visible initially
    const step0 = page.locator('#step0');
    await expect(step0).toBeVisible({ timeout: 10000 });

    // Click Next without a title — expect an error banner or field error
    await page.click('#btnNext0');
    const errBanner = page.locator('#errBannerStep0');
    const errTitle  = page.locator('#errWizTitle');
    // One of the two must be visible (step stays on 0, validation wired)
    const validationFired = await errBanner.isVisible()
      .then(v => v || errTitle.isVisible());
    expect(validationFired).toBe(true);

    // -------------------------------------------------------------------------
    // Step 0 — General info: fill required fields + advance
    // -------------------------------------------------------------------------
    const sessionTitle = `E2E-Session-${Date.now()}`;

    // Tomorrow's date as YYYY-MM-DD
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const yyyy = tomorrow.getFullYear();
    const mm   = String(tomorrow.getMonth() + 1).padStart(2, '0');
    const dd   = String(tomorrow.getDate()).padStart(2, '0');
    const tomorrowStr = `${yyyy}-${mm}-${dd}`;

    await page.fill('#wizTitle', sessionTitle);
    await page.fill('#wizDate', tomorrowStr);
    await page.fill('#wizTime', '18:00');

    await page.click('#btnNext0');

    // Step 1 must become visible; step 0 must disappear
    const step1 = page.locator('#step1');
    await expect(step1).toBeVisible({ timeout: 10000 });
    await expect(step0).not.toBeVisible();

    // -------------------------------------------------------------------------
    // Step 1 — Add a member inline
    // -------------------------------------------------------------------------
    await page.fill('#wizMemberName', 'Membre E2E');
    await page.fill('#wizMemberEmail', 'membre-e2e@test.local');
    await page.click('#btnAddMemberInline');

    // Member count must change from "0" to "1"
    const memberCount = page.locator('#wizMemberCount');
    await expect(memberCount).not.toHaveText('0', { timeout: 8000 });
    const countText = await memberCount.textContent();
    expect(Number(countText?.trim())).toBeGreaterThanOrEqual(1);

    // Member list must contain at least one row
    await expect(page.locator('#wizMembersList .member-row').first()).toBeVisible({ timeout: 5000 });

    // -------------------------------------------------------------------------
    // Step 1 → Step 2 navigation
    // -------------------------------------------------------------------------
    await page.click('#btnNext1');
    const step2 = page.locator('#step2');
    await expect(step2).toBeVisible({ timeout: 10000 });

    // -------------------------------------------------------------------------
    // Step 2 — Add a resolution
    // -------------------------------------------------------------------------
    await page.click('#btnShowResoPanel');

    // Fill the resolution title
    await page.fill('#resoTitle', 'Resolution E2E Test');

    // Select the first non-default majority option
    await page.locator('#resoMaj').selectOption({ index: 1 });

    await page.click('#btnAddReso');

    // Resolution list must contain at least one row
    await expect(page.locator('#wizResoList .reso-row').first()).toBeVisible({ timeout: 8000 });

    // -------------------------------------------------------------------------
    // Step 2 — Secret vote chip toggle
    // -------------------------------------------------------------------------
    await page.click('#chipSecret');
    await expect(page.locator('#chipSecret')).toHaveClass(/active/);
    await expect(page.locator('#chipNonSecret')).not.toHaveClass(/active/);

    // -------------------------------------------------------------------------
    // Step 2 → Step 3 (review)
    // -------------------------------------------------------------------------
    await page.click('#btnNext2');
    const step3 = page.locator('#step3');
    await expect(step3).toBeVisible({ timeout: 10000 });

    // Review recap must show the session title from step 0
    const recap = page.locator('#wizRecap');
    await expect(recap).toContainText(sessionTitle, { timeout: 5000 });

    // -------------------------------------------------------------------------
    // Step 3 — Create session (real API call)
    // -------------------------------------------------------------------------
    const responsePromise = page.waitForResponse(
      r => r.url().includes('meeting') || r.url().includes('session') || r.url().includes('wizard'),
      { timeout: 30000 },
    );

    await page.click('#btnCreate');

    const response = await responsePromise;
    expect(response.status()).toBeGreaterThanOrEqual(200);
    expect(response.status()).toBeLessThan(300);

    // After creation: either page navigates away from /wizard, or a success
    // feedback element appears. Either assertion proves the create was handled.
    const navigatedAway = !page.url().includes('wizard');
    if (!navigatedAway) {
      // Stay on page with success feedback
      const successEl = page.locator('[data-wiz-success], .alert-success, #wizSuccess, .notice-success');
      await expect(successEl.first()).toBeVisible({ timeout: 10000 });
    }
  });
});
