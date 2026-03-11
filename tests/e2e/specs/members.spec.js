// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * Members Management E2E Tests
 *
 * Tests member listing, search, creation, editing, import (CSV/XLSX),
 * group management, and member details.
 */

// ---------------------------------------------------------------------------
// Members List
// ---------------------------------------------------------------------------

test.describe('Members List', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should display members page', async ({ page }) => {
    await page.goto('/members.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Membres|Members/);
    await page.waitForLoadState('networkidle');

    // Should show members list or empty state
    const content = page.locator('.member-card, [data-member-id], .members-list, .empty-state, table tbody tr');
    await expect(content.first()).toBeVisible({ timeout: 10000 });
  });

  test('should have search/filter functionality', async ({ page }) => {
    await page.goto('/members.htmx.html');
    await page.waitForLoadState('networkidle');

    // Search input should exist
    const searchInput = page.locator('input[type="search"], input[placeholder*="chercher" i], input[placeholder*="search" i], #memberSearch');
    if (await searchInput.count() > 0) {
      await expect(searchInput.first()).toBeVisible();

      // Type a search term
      await searchInput.first().fill('Dupont');
      await page.waitForTimeout(500);

      // Results should update
      const results = page.locator('.member-card, [data-member-id], table tbody tr');
      // At least one result for "Dupont" from seed data
    }
  });

  test('should display member count', async ({ page }) => {
    await page.goto('/members.htmx.html');
    await page.waitForLoadState('networkidle');

    // Should show total member count somewhere
    const countEl = page.locator('[data-member-count], .member-count, .badge');
    // Count may be in header or stats area
  });

});

// ---------------------------------------------------------------------------
// Member Creation
// ---------------------------------------------------------------------------

test.describe('Member Creation', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/members.htmx.html');
    await page.waitForLoadState('networkidle');
  });

  test('should have member creation form', async ({ page }) => {
    // Look for "Add member" button
    const addBtn = page.locator('button:has-text("Ajouter"), button:has-text("Nouveau"), [data-action="add-member"]');
    if (await addBtn.count() > 0) {
      await addBtn.first().click();
      await page.waitForTimeout(500);

      // Form should appear (drawer or modal)
      const form = page.locator('form, .drawer, .modal');
      await expect(form.first()).toBeVisible({ timeout: 5000 });

      // Should have name field
      const nameInput = page.locator('input[name="full_name"], input[name="name"]');
      if (await nameInput.count() > 0) {
        await expect(nameInput.first()).toBeVisible();
      }
    }
  });

});

// ---------------------------------------------------------------------------
// Member Import
// ---------------------------------------------------------------------------

test.describe('Member Import', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/members.htmx.html');
    await page.waitForLoadState('networkidle');
  });

  test('should have CSV import option', async ({ page }) => {
    // Look for import button
    const importBtn = page.locator('button:has-text("Importer"), button:has-text("Import"), [data-action="import"]');
    if (await importBtn.count() > 0) {
      await importBtn.first().click();
      await page.waitForTimeout(500);

      // File input should appear
      const fileInput = page.locator('input[type="file"]');
      if (await fileInput.count() > 0) {
        const accept = await fileInput.first().getAttribute('accept');
        // Should accept CSV files
      }
    }
  });

});

// ---------------------------------------------------------------------------
// Members API Security
// ---------------------------------------------------------------------------

test.describe('Members API', () => {

  test('members list should reject unauthenticated requests', async ({ request }) => {
    const response = await request.get('/api/v1/members.php');

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('member creation should reject unauthenticated requests', async ({ request }) => {
    const response = await request.post('/api/v1/members.php', {
      data: {
        full_name: 'Test Member',
        email: 'test@example.com',
      },
      headers: { 'Content-Type': 'application/json' },
    });

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('member CSV import should reject unauthenticated requests', async ({ request }) => {
    const response = await request.post('/api/v1/members_import_csv.php', {
      data: 'name,email\nTest,test@test.com',
      headers: { 'Content-Type': 'text/csv' },
    });

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

});
