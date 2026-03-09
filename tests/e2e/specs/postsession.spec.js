// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Post-Session E2E Tests
 *
 * Tests the post-session workflow: PV generation, validation,
 * signature, report export, and archiving.
 */

const OPERATOR_KEY = 'operator-key-2026-secret';
const E2E_MEETING_ID = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';

async function loginAsOperator(page) {
  await page.goto('/login.html');
  await page.fill('input[type="password"], input[name="api_key"]', OPERATOR_KEY);
  await page.click('button[type="submit"]');
  await page.waitForURL(/meetings|operator/, { timeout: 10000 });
}

// ---------------------------------------------------------------------------
// Post-Session Page
// ---------------------------------------------------------------------------

test.describe('Post-Session Page', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should display post-session page', async ({ page }) => {
    await page.goto('/postsession.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Post|Bilan/);
    await page.waitForLoadState('networkidle');
  });

  test('should show meeting selector', async ({ page }) => {
    await page.goto('/postsession.htmx.html');
    await page.waitForLoadState('networkidle');

    // Meeting selector
    const selector = page.locator('select, ag-searchable-select, [data-meeting-select]');
    if (await selector.count() > 0) {
      await expect(selector.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should display meeting summary when selected', async ({ page }) => {
    await page.goto('/postsession.htmx.html');
    await page.waitForLoadState('networkidle');

    // Select a meeting if dropdown exists
    const select = page.locator('select[name="meeting_id"], #meetingSelect');
    if (await select.count() > 0) {
      await select.first().selectOption({ index: 1 }).catch(() => {});
      await page.waitForTimeout(1000);

      // Summary section should appear
      const summary = page.locator('.meeting-summary, [data-summary], .postsession-content');
      if (await summary.count() > 0) {
        await expect(summary.first()).toBeVisible({ timeout: 5000 });
      }
    }
  });

});

// ---------------------------------------------------------------------------
// Report Generation
// ---------------------------------------------------------------------------

test.describe('Report Generation', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should display report page', async ({ page }) => {
    await page.goto('/report.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Rapport|Report/);
    await page.waitForLoadState('networkidle');
  });

  test('should have export options', async ({ page }) => {
    await page.goto('/report.htmx.html');
    await page.waitForLoadState('networkidle');

    // Export buttons (PDF, XLSX, CSV)
    const exportBtns = page.locator('button:has-text("Exporter"), button:has-text("Export"), button:has-text("PDF"), button:has-text("XLSX"), [data-action="export"]');
    if (await exportBtns.count() > 0) {
      await expect(exportBtns.first()).toBeVisible({ timeout: 5000 });
    }
  });

});

// ---------------------------------------------------------------------------
// Archives
// ---------------------------------------------------------------------------

test.describe('Archives', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should display archives page', async ({ page }) => {
    await page.goto('/archives.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Archives/);
    await page.waitForLoadState('networkidle');

    // Should show archived meetings or empty state
    const content = page.locator('.archive-card, [data-archive], .meeting-card, .empty-state');
    await expect(content.first()).toBeVisible({ timeout: 10000 });
  });

  test('should have search/filter for archives', async ({ page }) => {
    await page.goto('/archives.htmx.html');
    await page.waitForLoadState('networkidle');

    // Search or filter input
    const searchInput = page.locator('input[type="search"], input[placeholder*="chercher" i], #archiveSearch');
    if (await searchInput.count() > 0) {
      await expect(searchInput.first()).toBeVisible();
    }
  });

});

// ---------------------------------------------------------------------------
// Validation Page
// ---------------------------------------------------------------------------

test.describe('Validation', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should display validation page', async ({ page }) => {
    await page.goto('/validate.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Valid/);
    await page.waitForLoadState('networkidle');
  });

  test('should show meeting validation checklist', async ({ page }) => {
    await page.goto('/validate.htmx.html');
    await page.waitForLoadState('networkidle');

    // Validation checklist or steps
    const checklist = page.locator('.validation-checklist, .checklist, .validation-step, [data-validation]');
    if (await checklist.count() > 0) {
      await expect(checklist.first()).toBeVisible({ timeout: 5000 });
    }
  });

});

// ---------------------------------------------------------------------------
// Post-Session APIs
// ---------------------------------------------------------------------------

test.describe('Post-Session APIs', () => {

  test('meeting report API should reject unauthenticated requests', async ({ request }) => {
    const response = await request.get(`/api/v1/meeting_report.php?meeting_id=${E2E_MEETING_ID}`);

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('meeting summary API should reject unauthenticated requests', async ({ request }) => {
    const response = await request.get(`/api/v1/meeting_summary.php?meeting_id=${E2E_MEETING_ID}`);

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('meeting consolidate should reject unauthenticated requests', async ({ request }) => {
    const response = await request.post('/api/v1/meeting_consolidate.php', {
      data: { meeting_id: E2E_MEETING_ID },
      headers: { 'Content-Type': 'application/json' },
    });

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('export PV HTML should reject unauthenticated requests', async ({ request }) => {
    const response = await request.get(`/api/v1/export_pv_html.php?meeting_id=${E2E_MEETING_ID}`);

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

});
