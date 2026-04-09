// @ts-check
// @critical-path
const { test, expect } = require('@playwright/test');
const { loginAsAdmin } = require('../helpers');

/**
 * E2E-EMAIL-TEMPLATES critical path spec.
 *
 * MUTATION SAFETY:
 *   - #btnSaveTemplate is NEVER clicked (would persist a template to DB)
 *   - #btnCreateDefaults is NEVER clicked (would create default rows in DB)
 *   - All tests are non-destructive and fully re-runnable.
 */
test.describe('E2E-EMAIL-TEMPLATES Email templates critical path', () => {
  test('email-templates: filter + new template opens editor + fields + cancel (non-destructive)', async ({ page }) => {
    test.setTimeout(90000);

    // --- Auth ---
    await loginAsAdmin(page);

    // --- 1. Page mount + toolbar visible ---
    await page.goto('/email-templates.htmx.html', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#filterType', { state: 'visible', timeout: 10000 });

    await expect(page.locator('#btnNewTemplate')).toBeVisible();
    await expect(page.locator('#btnCreateDefaults')).toBeVisible();

    // Grid OR empty state must be in DOM
    const gridVisible   = await page.locator('#templatesGrid').isVisible();
    const emptyVisible  = await page.locator('#emptyState').isVisible();
    expect(gridVisible || emptyVisible).toBe(true);

    // --- 2. Filter dropdown — changing value triggers grid re-render ---
    await page.selectOption('#filterType', 'invitation');
    await page.waitForTimeout(500);

    const filterVal = await page.locator('#filterType').inputValue();
    expect(filterVal).toBe('invitation');

    // Grid handler ran: #templatesGrid must still be in DOM
    await expect(page.locator('#templatesGrid')).toBeAttached();

    // Restore to "all" for the rest of the test
    await page.selectOption('#filterType', '');

    // --- 3. btnCreateDefaults — wired, NOT clicked ---
    await expect(page.locator('#btnCreateDefaults')).toBeVisible();
    await expect(page.locator('#btnCreateDefaults')).toBeEnabled();
    // Safety: no click — assertion is presence + enabled only.

    // --- 4. btnNewTemplate — click opens editor modal ---
    await page.click('#btnNewTemplate');
    await page.waitForFunction(() => {
      const el = document.getElementById('templateEditor');
      return el !== null && el.classList.contains('active');
    }, { timeout: 5000 });

    await expect(page.locator('#editorTitle')).toBeVisible();
    const editorTitle = await page.locator('#editorTitle').textContent();
    expect(editorTitle).toContain('Nouveau template');

    await expect(page.locator('#templateName')).toBeVisible();
    await expect(page.locator('#templateSubject')).toBeVisible();
    await expect(page.locator('#templateBody')).toBeVisible();

    // Hidden field must exist with empty value
    const templateIdVal = await page.locator('#templateId').inputValue();
    expect(templateIdVal).toBe('');

    // --- 5. Editor form — fill fields and verify values back ---
    await page.fill('#templateName', 'E2E test template (do not save)');
    await page.fill('#templateSubject', 'E2E test subject');
    await page.fill('#templateBody', '<p>E2E test body</p>');

    expect(await page.locator('#templateName').inputValue()).toBe('E2E test template (do not save)');
    expect(await page.locator('#templateSubject').inputValue()).toBe('E2E test subject');
    expect(await page.locator('#templateBody').inputValue()).toBe('<p>E2E test body</p>');

    // --- 6. templateType select changes ---
    await page.selectOption('#templateType', 'reminder');
    expect(await page.locator('#templateType').inputValue()).toBe('reminder');

    // --- 7. Preview iframe exists ---
    await expect(page.locator('#previewFrame')).toBeAttached();
    // Do NOT introspect iframe content (sandboxed, race-prone).

    // --- 8. btnRefreshPreview — click handler runs without crashing ---
    await page.click('#btnRefreshPreview');
    await page.waitForTimeout(300);
    // Page must still be on email-templates
    expect(page.url()).toMatch(/email-templates/);

    // --- 9. Cancel flow — btnCancelEdit closes editor ---
    await page.click('#btnCancelEdit');
    await page.waitForFunction(() => {
      const el = document.getElementById('templateEditor');
      return el !== null && !el.classList.contains('active');
    }, { timeout: 5000 });
    // Safety assertion: #btnSaveTemplate was NEVER clicked.

    // --- 10. btnCloseEditor — alternative close via × button ---
    await page.click('#btnNewTemplate');
    await page.waitForFunction(() => {
      const el = document.getElementById('templateEditor');
      return el !== null && el.classList.contains('active');
    }, { timeout: 5000 });

    await page.click('#btnCloseEditor');
    await page.waitForFunction(() => {
      const el = document.getElementById('templateEditor');
      return el !== null && !el.classList.contains('active');
    }, { timeout: 5000 });

    // --- 11. Width verification at 1920×1080 ---
    await page.setViewportSize({ width: 1920, height: 1080 });

    const hasHorizontalOverflow = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth + 1;
    });
    expect(hasHorizontalOverflow).toBe(false);

    // If the templates grid is visible (not empty state), its width should exceed 1200px
    // because the applicative clamp was removed.
    const isGridVisible = await page.locator('#templatesGrid').isVisible();
    if (isGridVisible) {
      const gridWidth = await page.locator('#templatesGrid').evaluate(
        el => el.getBoundingClientRect().width
      );
      // Grid must be able to use full viewport (applicative clamp removed)
      expect(gridWidth).toBeGreaterThan(1200);
    }
  });
});
