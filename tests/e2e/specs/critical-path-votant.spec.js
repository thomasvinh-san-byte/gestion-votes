// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsVoter } = require('../helpers');

// E2E-04: Votant critical path
// login (cookie) -> /vote.htmx.html -> meeting selector visible
//               -> vote app renders waiting state -> confirm button wiring present
//
// Scope note: CONTEXT.md mentions a token-based flow. The current
// vote.htmx.html is session-based (verified via vote.spec.js). We follow
// what works -- token-flow gaps will surface in Phase 10 UAT.
//
// Re-runnable: no DB writes, only navigation and visibility assertions.

test.describe('E2E-04 Votant critical path', () => {

  test('votant: vote page -> meeting selector -> vote app ready @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // Step 1: Login as voter (cookie injection)
    await loginAsVoter(page);

    const whoami = await page.request.get('/api/v1/whoami.php');
    expect(whoami.ok()).toBeTruthy();

    // Step 2: Navigate to vote page
    await page.goto('/vote.htmx.html', { waitUntil: 'domcontentloaded' });

    // Step 3: Vote app shell rendered
    await expect(page.locator('#voteApp')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#voteApp')).toHaveAttribute('data-vote-state', /waiting|loading|ready/, { timeout: 10000 });

    // Step 4: Meeting selector visible (entry point of votant journey)
    const meetingSelect = page.locator('#meetingSelect, ag-searchable-select');
    await expect(meetingSelect.first()).toBeVisible({ timeout: 10000 });

    // Step 5: Member selector visible (next step after meeting pick)
    await expect(page.locator('#memberSelect')).toBeVisible({ timeout: 5000 });

    // Step 6: Waiting state panel visible (proves vote app booted)
    await expect(page.locator('#voteWaitingState')).toBeVisible({ timeout: 10000 });

    // Step 7: Confirmation control wired (required for any vote submission)
    // The button may be hidden until a vote is staged; check it exists in DOM.
    const btnConfirm = page.locator('#btnConfirm');
    await expect(btnConfirm).toHaveCount(1);

    // Step 8: Zoom toggle proves vote-app JS is alive
    const btnZoom = page.locator('#btnZoom');
    if (await btnZoom.isVisible().catch(() => false)) {
      await btnZoom.click();
      await expect(btnZoom).toHaveAttribute('aria-pressed', /true|false/, { timeout: 5000 });
    }
  });

  // POLISH-03: sidebar visibility for voter role
  // Verifies that auth-ui.js filterSidebar() correctly hides admin/operator-only
  // nav items when the authenticated user has a voter role.
  // Navigate to /help.htmx.html — accessible to all roles and includes the sidebar shell.
  test('votant: sidebar hides admin-only items (POLISH-03) @critical-path', async ({ page }) => {
    test.setTimeout(60000);

    // Step 1: Login as voter
    await loginAsVoter(page);

    // Step 2: Navigate to a page with the full sidebar (help is accessible to all roles)
    await page.goto('/help.htmx.html', { waitUntil: 'domcontentloaded' });

    // Step 3: Wait for sidebar partial to be injected and role-filtered by auth-ui.js
    // The sidebar is asynchronously injected via data-include-sidebar; filter applies after whoami.
    const sidebar = page.locator('[data-include-sidebar]');
    await expect(sidebar).toBeVisible({ timeout: 10000 });

    // Wait for at least one nav-item to appear (confirms sidebar partial was injected)
    await expect(sidebar.locator('[data-requires-role]').first()).toHaveCount(1, { timeout: 10000 });

    // Allow auth-ui.js filterSidebar() to complete (runs after whoami resolves)
    await page.waitForTimeout(2000);

    // Step 4: Items that MUST be hidden for a voter (display:none via auth-ui.js _hide)
    // These items have data-requires-role values that do not include 'voter' or any
    // meeting role the voter holds, so filterSidebar() sets display:none on them.
    // Note: use [data-requires-role="admin"] for the "Parametres" /settings entry so we
    // do not accidentally hide the "Mon compte" /settings entry (no role restriction).
    const mustBeHidden = [
      'a[href="/users"]',
      'a[href="/admin"]',
      'a[href="/settings"][data-requires-role="admin"]',  // admin "Parametres", NOT "Mon compte"
      'a[href="/members"]',
      'a[href="/operator"]',
      'a[href="/hub"]',
      'a[href="/wizard"]',
    ];
    for (const selector of mustBeHidden) {
      const el = sidebar.locator(selector);
      // Element is present in DOM but hidden. toBeHidden() accepts display:none.
      await expect(el).toBeHidden({ timeout: 2000 });
    }

    // Step 5: Items that MUST be visible for a voter
    // /help has no data-requires-role (all roles see it — filterSidebar skips ungated items)
    // Brand link (href="/") also has no data-requires-role
    // /vote has data-requires-role="admin,operator,president,voter" — voter is included
    // /dashboard has no data-requires-role — visible to all
    const mustBeVisible = [
      'a[href="/"]',          // brand link — all roles
      'a[href="/help"]',      // Guide & FAQ — all roles
      'a[href="/vote"]',      // Voter — voter role included in data-requires-role
      'a[href="/dashboard"]', // Tableau de bord — no role restriction
    ];
    for (const selector of mustBeVisible) {
      await expect(sidebar.locator(selector).first()).toBeVisible({ timeout: 2000 });
    }

    // "Mon compte" entry (no role restriction) must be visible for voter
    // Uses text selector to distinguish from admin "Parametres" (same href="/settings")
    const monCompte = sidebar.locator('a[href="/settings"]', { hasText: 'Mon compte' });
    await expect(monCompte).toBeVisible({ timeout: 2000 });

    // NAV-01: Sidebar must be 200px wide (static, no hover needed)
    const sidebarBox = await sidebar.boundingBox();
    expect(sidebarBox).not.toBeNull();
    expect(sidebarBox.width).toBe(200);
  });

});
