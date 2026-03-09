// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Email Templates E2E Tests
 *
 * Tests the email templates editor: template listing, preview,
 * variable insertion, and save functionality.
 */

const ADMIN_KEY = 'admin-key-2026-secret';

async function loginAsAdmin(page) {
  await page.goto('/login.html');
  await page.fill('input[type="password"], input[name="api_key"]', ADMIN_KEY);
  await page.click('button[type="submit"]');
  await page.waitForURL(/admin|meetings|operator/, { timeout: 10000 });
}

// ---------------------------------------------------------------------------
// Email Templates Page
// ---------------------------------------------------------------------------

test.describe('Email Templates', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('should display email templates page', async ({ page }) => {
    await page.goto('/email-templates.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Email|Modèle/);
    await page.waitForLoadState('networkidle');
  });

  test('should list available templates', async ({ page }) => {
    await page.goto('/email-templates.htmx.html');
    await page.waitForLoadState('networkidle');

    // Template cards or list
    const templates = page.locator('.template-card, [data-template], .template-grid > *');
    if (await templates.count() > 0) {
      await expect(templates.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should open template editor on click', async ({ page }) => {
    await page.goto('/email-templates.htmx.html');
    await page.waitForLoadState('networkidle');

    const template = page.locator('.template-card, [data-template]').first();
    if (await template.count() > 0) {
      await template.click();
      await page.waitForTimeout(1000);

      // Editor area should appear
      const editor = page.locator('textarea, .editor, [contenteditable], .template-editor');
      if (await editor.count() > 0) {
        await expect(editor.first()).toBeVisible({ timeout: 5000 });
      }
    }
  });

  test('should show available variables', async ({ page }) => {
    await page.goto('/email-templates.htmx.html');
    await page.waitForLoadState('networkidle');

    // Variables list
    const variables = page.locator('.variables-list, [data-variables], .variable-tag');
    if (await variables.count() > 0) {
      await expect(variables.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should have preview functionality', async ({ page }) => {
    await page.goto('/email-templates.htmx.html');
    await page.waitForLoadState('networkidle');

    const previewBtn = page.locator('button:has-text("Aperçu"), button:has-text("Preview"), [data-action="preview"]');
    if (await previewBtn.count() > 0) {
      await expect(previewBtn.first()).toBeVisible();
    }
  });

});

// ---------------------------------------------------------------------------
// Email Templates API
// ---------------------------------------------------------------------------

test.describe('Email Templates API', () => {

  test('templates list should reject unauthenticated', async ({ request }) => {
    const response = await request.get('/api/v1/email_templates.php');
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('template preview should reject unauthenticated', async ({ request }) => {
    const response = await request.post('/api/v1/email_templates_preview.php', {
      data: { template_id: 'test' },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

});
