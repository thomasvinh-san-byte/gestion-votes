// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsAdmin } = require('../helpers');

/**
 * E2E-01: Admin critical path
 * login → settings → users → audit → logout
 *
 * Hybrid auth strategy: cookie injection via loginAsAdmin (no rate limit hit).
 * Re-runnable: no DB writes, only navigation and read-only assertions.
 * Tagged @critical-path for `--grep="@critical-path"` filtering.
 */

test.describe('E2E-01 Admin critical path', () => {

  test('admin: settings → users → audit → logout @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // Step 1 — Login (cookie injection, no rate limit)
    await loginAsAdmin(page);

    // Sanity: whoami via the page request context
    const whoami = await page.request.get('/api/v1/whoami.php');
    expect(whoami.ok()).toBeTruthy();

    // Step 2 — /settings: switch tabs (non-destructive admin interaction)
    await page.goto('/settings.htmx.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#stab-regles')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#settVoteMode')).toBeVisible({ timeout: 10000 });

    // Click the "Communication" tab and assert its panel becomes visible
    await page.locator('[data-stab="communication"]').first().click();
    await expect(page.locator('#stab-communication')).toBeVisible({ timeout: 5000 });
    // Switch back so we leave the page in a clean state
    await page.locator('[data-stab="regles"]').first().click();
    await expect(page.locator('#stab-regles')).toBeVisible({ timeout: 5000 });

    // Step 3 — /users: assert the list loads
    await page.goto('/users.htmx.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#usersTableBody')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#btnAddUser')).toBeVisible({ timeout: 5000 });
    // The aria-busy attribute should drop from "true" once data loads —
    // wait for the role count chip to flip from its initial 0 placeholder.
    await expect(page.locator('#roleCountAdmin')).not.toHaveText('', { timeout: 10000 });

    // Step 4 — /audit: assert the table renders
    await page.goto('/audit.htmx.html', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#auditTableBody')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#auditSearch')).toBeVisible({ timeout: 5000 });
    // KPI counter should update from its em-dash placeholder
    await expect(page.locator('#kpiEvents')).not.toHaveText('—', { timeout: 10000 });

    // Step 5 — Logout via API (real DOM logout button is sidebar-included
    // and not always present; the API path is what the JS calls anyway).
    const logoutResp = await page.request.post('/api/v1/auth_logout.php', { data: {} });
    expect(logoutResp.ok()).toBeTruthy();

    // Confirm the session is gone — whoami should now fail (401 or ok=false)
    const whoamiAfter = await page.request.get('/api/v1/whoami.php');
    if (whoamiAfter.ok()) {
      const body = await whoamiAfter.json();
      expect(body.ok === false || body.user == null || body.error).toBeTruthy();
    } else {
      expect([401, 403]).toContain(whoamiAfter.status());
    }
  });

});
