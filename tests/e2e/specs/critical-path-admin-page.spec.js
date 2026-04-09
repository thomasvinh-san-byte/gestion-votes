// @ts-check
/**
 * critical-path-admin-page.spec.js
 *
 * Function gate for the admin.htmx.html PAGE interactions.
 * Tests KPI load, search, role filter, create form (password strength),
 * refresh button, and full-width layout verification.
 *
 * NOTE: This file is intentionally separate from critical-path-admin.spec.js
 * which tests the admin ROLE flow (login -> settings -> users -> audit -> logout).
 * This spec targets the admin PAGE itself at /admin.htmx.html.
 */
'use strict';

const { test, expect } = require('@playwright/test');
const { loginAsAdmin }  = require('../helpers');

test.describe('Admin page — function gate @critical-path', () => {

  test('admin page: KPI load + search + role filter + create form + user list', async ({ page }) => {
    test.setTimeout(120000);

    // ─── 0. Auth ────────────────────────────────────────────────────────────
    await loginAsAdmin(page);

    // ─── 1. Navigate ────────────────────────────────────────────────────────
    await page.goto('/admin.htmx.html', { waitUntil: 'domcontentloaded' });

    // Wait for user list to be visible (signals API data loaded)
    await page.locator('#usersListContainer').waitFor({ state: 'visible', timeout: 30000 });

    // ─── 2. KPI cards populate ──────────────────────────────────────────────
    // Wait briefly for async KPI fetch to complete
    await page.waitForTimeout(3000);

    const kpiMembers  = await page.locator('#adminKpiMembers').innerText();
    const kpiSessions = await page.locator('#adminKpiSessions').innerText();

    // KPIs should no longer show placeholder "-"
    expect(kpiMembers.trim()).not.toBe('-');
    expect(kpiSessions.trim()).not.toBe('-');

    // ─── 3. Users list loads ─────────────────────────────────────────────────
    const usersCountText = await page.locator('#usersCount').innerText();
    // Should contain a number — not just "- utilisateurs"
    expect(usersCountText).toMatch(/\d+/);

    // At least one .user-row element rendered
    const userRows = page.locator('#usersListContainer .user-row');
    await expect(userRows.first()).toBeVisible({ timeout: 10000 });

    // ─── 4. Search input — filters user list ─────────────────────────────────
    const noMatchTerm = `zzz-no-match-${Date.now()}`;
    await page.fill('#searchUser', noMatchTerm);
    await page.waitForTimeout(400); // debounce

    // User list should be empty (no rows, or shows empty-state element)
    const rowsAfterSearch = await page.locator('#usersListContainer .user-row').count();
    expect(rowsAfterSearch).toBe(0);

    // Clear the search
    await page.fill('#searchUser', '');
    await page.waitForTimeout(400);

    // User rows should reappear
    await expect(page.locator('#usersListContainer .user-row').first()).toBeVisible({ timeout: 10000 });

    // ─── 5. Role filter — observable change ──────────────────────────────────
    await page.selectOption('#filterRole', 'admin');
    await page.waitForTimeout(300);

    // Verify select is wired (value reflects selection)
    const filterVal = await page.locator('#filterRole').inputValue();
    expect(filterVal).toBe('admin');

    // Reset to all roles
    await page.selectOption('#filterRole', '');
    await page.waitForTimeout(300);

    // ─── 6. Create user form — field interaction + password strength ──────────
    await page.fill('#newName',  'Test User');
    await page.fill('#newEmail', 'test@example.com');
    await page.fill('#newPassword', 'TestPass123!');

    // Password strength indicator should become visible after typing
    await expect(page.locator('#passwordStrength')).toBeVisible({ timeout: 5000 });

    // Fill bar should carry a strength class (weak/fair/good/strong)
    const fillEl     = page.locator('#passwordStrengthFill');
    const fillClass  = await fillEl.getAttribute('class');
    expect(fillClass).toMatch(/\b(weak|fair|good|strong)\b/);

    // Clear fields — do NOT submit to avoid creating test users in DB
    await page.fill('#newName',     '');
    await page.fill('#newEmail',    '');
    await page.fill('#newPassword', '');

    // ─── 7. Refresh button — reloads user list ────────────────────────────────
    await page.click('#btnRefresh');
    // After refresh the container should still contain rows
    await expect(page.locator('#usersListContainer .user-row').first()).toBeVisible({ timeout: 15000 });

    // ─── 8. Width verification — no horizontal scroll at desktop viewport ─────
    const noHorizontalScroll = await page.evaluate(() => {
      return document.documentElement.scrollWidth <= document.documentElement.clientWidth;
    });
    expect(noHorizontalScroll).toBe(true);
  });

});
