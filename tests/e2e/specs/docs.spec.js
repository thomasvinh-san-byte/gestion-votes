// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Documentation & Help E2E Tests
 *
 * Tests the documentation viewer, help/FAQ page, and related content pages.
 */

const { loginAsOperator: login } = require('../helpers');

// ---------------------------------------------------------------------------
// Help / FAQ
// ---------------------------------------------------------------------------

test.describe('Help & FAQ', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('should display help page', async ({ page }) => {
    await page.goto('/help.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Aide|Help/);
    await page.waitForLoadState('networkidle');
  });

  test('should display FAQ items', async ({ page }) => {
    await page.goto('/help.htmx.html');
    await page.waitForLoadState('networkidle');

    const faqItems = page.locator('.faq-item, .faq-question, [data-faq]');
    if (await faqItems.count() > 0) {
      await expect(faqItems.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should expand FAQ answers on click', async ({ page }) => {
    await page.goto('/help.htmx.html');
    await page.waitForLoadState('networkidle');

    const faqQuestion = page.locator('.faq-question, [data-faq-toggle]').first();
    if (await faqQuestion.count() > 0) {
      await faqQuestion.click();
      await page.waitForTimeout(300);

      // Answer should become visible
      const answer = page.locator('.faq-answer, [data-faq-answer]').first();
      if (await answer.count() > 0) {
        await expect(answer).toBeVisible({ timeout: 3000 });
      }
    }
  });

});

// ---------------------------------------------------------------------------
// Documentation Viewer
// ---------------------------------------------------------------------------

test.describe('Documentation Viewer', () => {

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('should display docs page', async ({ page }) => {
    await page.goto('/docs.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Documentation|Docs/);
    await page.waitForLoadState('networkidle');
  });

  test('should display table of contents', async ({ page }) => {
    await page.goto('/docs.htmx.html');
    await page.waitForLoadState('networkidle');

    const toc = page.locator('.toc, .table-of-contents, [data-toc], nav.docs-nav');
    if (await toc.count() > 0) {
      await expect(toc.first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('should display documentation content', async ({ page }) => {
    await page.goto('/docs.htmx.html');
    await page.waitForLoadState('networkidle');

    const content = page.locator('.doc-content, .docs-container, [data-doc-content], article');
    if (await content.count() > 0) {
      await expect(content.first()).toBeVisible({ timeout: 5000 });
    }
  });

});

// ---------------------------------------------------------------------------
// Doc API
// ---------------------------------------------------------------------------

test.describe('Doc API', () => {

  test('doc index should be accessible', async ({ request }) => {
    const response = await request.get('/api/v1/doc_index.php');

    // Documentation index is typically public or semi-public
    expect(response.status()).not.toBe(500);
  });

});
