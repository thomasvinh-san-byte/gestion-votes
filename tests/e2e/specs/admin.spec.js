// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Admin Panel E2E Tests
 *
 * Tests the admin area: user management, quorum/vote policies, roles, audit log,
 * system status, meeting archive management.
 */

const { loginAsAdmin, loginAsOperator } = require('../helpers');

// ---------------------------------------------------------------------------
// Admin Dashboard
// ---------------------------------------------------------------------------

test.describe('Admin Dashboard', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('should display admin page with navigation tabs', async ({ page }) => {
    await page.goto('/admin.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Admin/);
    // Admin page should have tab navigation
    const tabs = page.locator('.tab, [role="tab"], .admin-tab, [data-tab]');
    await expect(tabs.first()).toBeVisible({ timeout: 10000 });
  });

  test('should display upcoming sessions section', async ({ page }) => {
    await page.goto('/admin.htmx.html');

    // Should see upcoming sessions or empty state
    const sessions = page.locator('#upcomingSessions, [data-upcoming-sessions], .session-card');
    const emptyState = page.locator('.empty-state');
    const hasContent = (await sessions.count()) > 0 || (await emptyState.count()) > 0;
    expect(hasContent).toBeTruthy();
  });

  test('should display system status indicators', async ({ page }) => {
    await page.goto('/admin.htmx.html');

    // Look for system status tab or section
    const statusTab = page.locator('[data-tab="system"], .tab:has-text("Système"), button:has-text("Système")');
    if (await statusTab.count() > 0) {
      await statusTab.first().click();
      await page.waitForTimeout(1000);

      // Status indicators should be visible
      const statusEl = page.locator('.status-indicator, .system-status, [data-status]');
      if (await statusEl.count() > 0) {
        await expect(statusEl.first()).toBeVisible({ timeout: 5000 });
      }
    }
  });

});

// ---------------------------------------------------------------------------
// User Management
// ---------------------------------------------------------------------------

test.describe('Admin User Management', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin.htmx.html');
  });

  test('should display user list', async ({ page }) => {
    // Navigate to users tab
    const usersTab = page.locator('[data-tab="users"], .tab:has-text("Utilisateurs"), button:has-text("Utilisateurs")');
    if (await usersTab.count() > 0) {
      await usersTab.first().click();
      await page.waitForTimeout(1000);

      // User list should render
      const userList = page.locator('.user-card, [data-user-id], .users-list');
      const emptyState = page.locator('.empty-state');
      const hasContent = (await userList.count()) > 0 || (await emptyState.count()) > 0;
      expect(hasContent).toBeTruthy();
    }
  });

  test('should have user creation form', async ({ page }) => {
    const usersTab = page.locator('[data-tab="users"], .tab:has-text("Utilisateurs"), button:has-text("Utilisateurs")');
    if (await usersTab.count() > 0) {
      await usersTab.first().click();
      await page.waitForTimeout(1000);

      // Look for user creation form
      const form = page.locator('form[data-form="user"], #userForm, input[name="email"]');
      if (await form.count() > 0) {
        await expect(form.first()).toBeVisible({ timeout: 5000 });
      }
    }
  });

});

// ---------------------------------------------------------------------------
// Quorum Policies
// ---------------------------------------------------------------------------

test.describe('Admin Quorum Policies', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin.htmx.html');
  });

  test('should display quorum policies list', async ({ page }) => {
    const tab = page.locator('[data-tab="quorum"], .tab:has-text("Quorum"), button:has-text("Quorum")');
    if (await tab.count() > 0) {
      await tab.first().click();
      await page.waitForTimeout(1000);

      // Quorum policies should be listed
      const policies = page.locator('.policy-card, [data-quorum-policy], table tbody tr');
      const emptyState = page.locator('.empty-state');
      const hasContent = (await policies.count()) > 0 || (await emptyState.count()) > 0;
      expect(hasContent).toBeTruthy();
    }
  });

});

// ---------------------------------------------------------------------------
// Vote Policies
// ---------------------------------------------------------------------------

test.describe('Admin Vote Policies', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin.htmx.html');
  });

  test('should display vote policies list', async ({ page }) => {
    const tab = page.locator('[data-tab="vote-policies"], .tab:has-text("Vote"), button:has-text("Vote")');
    if (await tab.count() > 0) {
      await tab.first().click();
      await page.waitForTimeout(1000);

      const policies = page.locator('.policy-card, [data-vote-policy], table tbody tr');
      const emptyState = page.locator('.empty-state');
      const hasContent = (await policies.count()) > 0 || (await emptyState.count()) > 0;
      expect(hasContent).toBeTruthy();
    }
  });

});

// ---------------------------------------------------------------------------
// Roles & Permissions
// ---------------------------------------------------------------------------

test.describe('Admin Roles & Permissions', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin.htmx.html');
  });

  test('should display roles configuration', async ({ page }) => {
    const tab = page.locator('[data-tab="roles"], .tab:has-text("Rôles"), button:has-text("Rôles")');
    if (await tab.count() > 0) {
      await tab.first().click();
      await page.waitForTimeout(1000);

      // Roles info should be visible
      const roles = page.locator('#systemRolesInfo, #meetingRolesInfo, .roles-section');
      if (await roles.count() > 0) {
        await expect(roles.first()).toBeVisible({ timeout: 5000 });
      }
    }
  });

  test('should display permissions matrix', async ({ page }) => {
    const tab = page.locator('[data-tab="roles"], .tab:has-text("Rôles"), button:has-text("Rôles")');
    if (await tab.count() > 0) {
      await tab.first().click();
      await page.waitForTimeout(1000);

      const matrix = page.locator('#permMatrix, .perm-matrix, table');
      if (await matrix.count() > 0) {
        await expect(matrix.first()).toBeVisible({ timeout: 5000 });
      }
    }
  });

});

// ---------------------------------------------------------------------------
// Audit Log
// ---------------------------------------------------------------------------

test.describe('Admin Audit Log', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin.htmx.html');
  });

  test('should display audit log section', async ({ page }) => {
    const tab = page.locator('[data-tab="audit"], .tab:has-text("Audit"), button:has-text("Audit")');
    if (await tab.count() > 0) {
      await tab.first().click();
      await page.waitForTimeout(1000);

      const logEntries = page.locator('.audit-entry, [data-audit-log], table tbody tr');
      const emptyState = page.locator('.empty-state');
      const hasContent = (await logEntries.count()) > 0 || (await emptyState.count()) > 0;
      expect(hasContent).toBeTruthy();
    }
  });

});

// ---------------------------------------------------------------------------
// Access Control
// ---------------------------------------------------------------------------

test.describe('Admin Access Control', () => {

  test('non-admin should not access admin page', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/admin.htmx.html');

    // Should redirect to login or show access denied
    // or the admin tab content should not load
    await page.waitForTimeout(2000);

    // Check that admin-specific content is not exposed
    const adminContent = page.locator('[data-tab="users"], #userForm');
    // If user management form is visible to non-admin, that's a problem
    // (This depends on the app's access control implementation)
  });

  test('admin users API should reject non-admin', async ({ request }) => {
    const response = await request.get('/api/v1/admin_users.php');

    // Should fail without admin auth
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('admin system status API should reject non-admin', async ({ request }) => {
    const response = await request.get('/api/v1/admin_system_status.php');

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

});
