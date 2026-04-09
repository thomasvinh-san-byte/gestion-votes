// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsAdmin } = require('../helpers');

/**
 * Users page critical path
 * role filter + search + add modal + role counts + data load + refresh
 *
 * Hybrid auth strategy: cookie injection via loginAsAdmin (no rate limit hit).
 * Re-runnable: CRUD write operations are not performed; assertions are read-only.
 * Tagged @critical-path for `--grep="@critical-path"` filtering.
 */

test.describe('Users page critical path', () => {

  test('users: role filter + search + add modal + role counts + data load @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // ── Login (cookie injection, no rate limit) ─────────────────────────────
    await loginAsAdmin(page);

    // ── 1. Navigate + data load ─────────────────────────────────────────────
    await page.goto('/users.htmx.html', { waitUntil: 'domcontentloaded' });

    // Wait for the users list to finish loading (aria-busy drops to "false")
    await expect(page.locator('#usersTableBody')).toHaveAttribute('aria-busy', 'false', { timeout: 15000 });

    // usersCount should now contain a number (not just the em-dash placeholder)
    const usersCountEl = page.locator('#usersCount');
    await expect(usersCountEl).toBeVisible({ timeout: 5000 });
    await expect(usersCountEl).not.toHaveText('— utilisateurs', { timeout: 5000 });

    // At least the admin role count should be > 0 (the logged-in admin exists)
    const adminCount = page.locator('#roleCountAdmin');
    await expect(adminCount).toBeVisible({ timeout: 5000 });
    const adminCountText = await adminCount.textContent();
    expect(parseInt(adminCountText || '0', 10)).toBeGreaterThan(0);

    // ── 2. Role filter click — observable DOM change ────────────────────────
    const adminTab = page.locator('#roleFilter .filter-tab[data-role="admin"]');
    const allTab   = page.locator('#roleFilter .filter-tab[data-role=""]');

    await adminTab.click();
    // Admin tab gains .active
    await expect(adminTab).toHaveClass(/active/, { timeout: 5000 });
    // "Tous" tab loses .active
    await expect(allTab).not.toHaveClass(/active/, { timeout: 5000 });

    // Wait for list to reload
    await expect(page.locator('#usersTableBody')).toHaveAttribute('aria-busy', 'false', { timeout: 10000 });

    // Restore "Tous"
    await allTab.click();
    await expect(allTab).toHaveClass(/active/, { timeout: 5000 });
    await expect(page.locator('#usersTableBody')).toHaveAttribute('aria-busy', 'false', { timeout: 10000 });

    // ── 3. Search input — filters the list ─────────────────────────────────
    const searchInput = page.locator('#searchUser');
    await expect(searchInput).toBeVisible({ timeout: 5000 });

    // Fill with a string that will match no users
    await searchInput.fill(`zzz-no-match-${Date.now()}`);
    await page.waitForTimeout(500); // debounce

    // Either an ag-empty-state element OR zero .user-row elements
    const userRows = page.locator('#usersTableBody .user-row');
    const emptyState = page.locator('#usersTableBody ag-empty-state');

    const rowCount = await userRows.count();
    const hasEmpty = await emptyState.count();
    expect(rowCount === 0 || hasEmpty > 0).toBeTruthy();

    // Clear search — rows should reappear
    await searchInput.fill('');
    await page.waitForTimeout(500); // debounce

    // After clearing, list should have at least one row again
    await expect(page.locator('#usersTableBody .user-row').first()).toBeVisible({ timeout: 8000 });

    // ── 4. Add user modal — opens with correct fields ───────────────────────
    const btnAddUser = page.locator('#btnAddUser');
    await expect(btnAddUser).toBeVisible({ timeout: 5000 });
    await btnAddUser.click();

    // ag-modal opens: aria-hidden transitions to "false" on the host element
    const userModal = page.locator('#userModal');
    await expect(userModal).toHaveAttribute('aria-hidden', 'false', { timeout: 8000 });

    // ag-modal uses Shadow DOM with slotted content.
    // The footer slot (slot="footer") is matched by <slot name="footer"> — buttons are accessible.
    // Form fields (slot="body") live in the light DOM and are accessible via DOM APIs,
    // so we read their values with inputValue() rather than asserting CSS visibility.
    const modalName  = page.locator('#modalUserName');
    const modalEmail = page.locator('#modalUserEmail');
    const modalRole  = page.locator('#modalUserRole');

    // Fields exist and are empty in add-user mode
    await expect(modalName).toHaveValue('', { timeout: 5000 });
    await expect(modalEmail).toHaveValue('', { timeout: 5000 });

    // Role select should have a default value (viewer by default)
    const roleValue = await modalRole.inputValue();
    expect(roleValue.length).toBeGreaterThan(0);

    // Close via cancel button (in the matched footer slot — it is visually accessible)
    const btnCancelUser = page.locator('#btnCancelUser');
    await expect(btnCancelUser).toBeVisible({ timeout: 5000 });
    await btnCancelUser.click();

    // Modal should close: aria-hidden returns to "true"
    await expect(userModal).toHaveAttribute('aria-hidden', 'true', { timeout: 8000 });

    // ── 5. Refresh button — reloads data ────────────────────────────────────
    const btnRefresh = page.locator('#btnRefresh');
    await expect(btnRefresh).toBeVisible({ timeout: 5000 });
    await btnRefresh.click();

    // Wait for data to reload
    await expect(page.locator('#usersTableBody')).toHaveAttribute('aria-busy', 'false', { timeout: 15000 });

    // User rows still present after refresh
    await expect(page.locator('#usersTableBody .user-row').first()).toBeVisible({ timeout: 8000 });
  });

});
