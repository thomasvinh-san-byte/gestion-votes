// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsPresident } = require('../helpers');

/**
 * E2E-03: President critical path
 * login → /hub → assert hub hero → navigate to /operator → mode interaction
 *
 * Hybrid auth strategy: cookie injection. The president journey is mostly
 * read+navigate from the hub, with the actual presiding actions exercised
 * via the operator console (which the president can also access).
 *
 * Re-runnable: no DB writes; reads + navigation + non-destructive UI clicks.
 */

test.describe('E2E-03 President critical path', () => {

  test('president: hub hero → operator console → mode switch @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // ─── Step 1: Login (cookie injection) ───
    await loginAsPresident(page);

    const whoami = await page.request.get('/api/v1/whoami.php');
    expect(whoami.ok()).toBeTruthy();

    // ─── Step 2: Find an existing meeting to anchor the journey ───
    // The seed DB always has at least one meeting; we use it to feed
    // the hub and operator pages a meeting_id.
    const meetingsResp = await page.request.get('/api/v1/meetings');
    expect(meetingsResp.ok()).toBeTruthy();
    const meetingsBody = await meetingsResp.json();
    const meetings = meetingsBody?.data || meetingsBody || [];
    expect(Array.isArray(meetings) && meetings.length > 0,
      'expected at least one meeting in the seed DB').toBeTruthy();
    const meetingId = meetings[0].id || meetings[0].meeting_id;
    expect(meetingId).toBeTruthy();

    // ─── Step 3: Hub view ───
    await page.goto(`/hub.htmx.html?meeting_id=${meetingId}`, {
      waitUntil: 'domcontentloaded',
    });

    // Hub hero must render — title flips from "Chargement…" placeholder
    await expect(page.locator('#hubTitle')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#hubTitle')).not.toHaveText('Chargement…', { timeout: 15000 });

    // Status badge present (text varies by meeting state)
    await expect(page.locator('#hubStatusTag')).toBeVisible({ timeout: 5000 });

    // The operator button should be visible — proves the president can
    // access the console from the hub
    await expect(page.locator('#hubOperatorBtn')).toBeVisible({ timeout: 5000 });

    // ─── Step 4: Navigate to operator console ───
    // Use a direct goto rather than clicking the link, because the link
    // href is "/operator" without meeting_id and the operator page needs
    // the query param to bind to a meeting (Phase 7 lesson).
    await page.goto(`/operator.htmx.html?meeting_id=${meetingId}`, {
      waitUntil: 'domcontentloaded',
    });

    await expect(page.locator('#btnBarRefresh')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#btnModeSetup')).toBeVisible({ timeout: 10000 });

    // ─── Step 5: Mode switch interaction (presider modifying state) ───
    await page.locator('#btnModeExec').click();
    await expect(page.locator('#btnModeExec')).toHaveAttribute('aria-pressed', 'true', { timeout: 5000 });

    await page.locator('#btnModeSetup').click();
    await expect(page.locator('#btnModeSetup')).toHaveAttribute('aria-pressed', 'true', { timeout: 5000 });

    // ─── Step 6: Refresh action (non-destructive verification) ───
    await page.locator('#btnBarRefresh').click();
    await expect(page.locator('#btnBarRefresh')).toBeVisible({ timeout: 5000 });
  });

});
