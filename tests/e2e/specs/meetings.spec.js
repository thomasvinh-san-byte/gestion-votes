// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Meetings Management E2E Tests
 */
test.describe('Meetings', () => {

  // Login before each test
  test.beforeEach(async ({ page }) => {
    await page.goto('/login.html');
    await page.fill('input[type="password"], input[name="api_key"]', 'operator-key-2026-secret');
    await page.click('button[type="submit"]');
    await page.waitForURL(/meetings|operator/);
  });

  test('should display meetings list', async ({ page }) => {
    await page.goto('/meetings.htmx.html');

    await expect(page).toHaveTitle(/Séances|Meetings|AG-VOTE/);
    // Should have some meeting cards or empty state
    const content = page.locator('.meeting-card, .empty-state, [data-meeting-id]');
    await expect(content.first()).toBeVisible({ timeout: 10000 });
  });

  test('should navigate to operator console from meeting', async ({ page }) => {
    await page.goto('/meetings.htmx.html');

    // Click on first meeting card if available
    const meetingCard = page.locator('.meeting-card, [data-meeting-id]').first();
    if (await meetingCard.count() > 0) {
      await meetingCard.click();
      await page.waitForURL(/operator/);
      await expect(page).toHaveURL(/operator/);
    }
  });

  test('should show meeting creation form', async ({ page }) => {
    await page.goto('/meetings.htmx.html');

    // Look for "New meeting" button
    const newBtn = page.locator('button:has-text("Nouvelle"), button:has-text("Créer"), [data-action="create-meeting"]');
    if (await newBtn.count() > 0) {
      await newBtn.first().click();

      // Should show form/drawer
      const form = page.locator('.drawer, .modal, form[data-form="meeting"]');
      await expect(form.first()).toBeVisible({ timeout: 5000 });
    }
  });

});

test.describe('Operator Console', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/login.html');
    await page.fill('input[type="password"], input[name="api_key"]', 'operator-key-2026-secret');
    await page.click('button[type="submit"]');
    await page.waitForURL(/meetings|operator/);
  });

  test('should display operator tabs', async ({ page }) => {
    // Go to operator page with a meeting
    await page.goto('/meetings.htmx.html');
    const meetingCard = page.locator('.meeting-card, [data-meeting-id]').first();

    if (await meetingCard.count() > 0) {
      await meetingCard.click();
      await page.waitForURL(/operator/);

      // Check for main tabs
      const tabs = page.locator('.tab, [role="tab"], .admin-tab');
      await expect(tabs.first()).toBeVisible({ timeout: 5000 });
    }
  });

});
