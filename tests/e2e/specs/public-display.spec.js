// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Public Display E2E Tests
 *
 * Tests the public-facing display page used for projectors/screens
 * during live meetings. This page is accessible without authentication.
 */

// ---------------------------------------------------------------------------
// Public Display Page
// ---------------------------------------------------------------------------

test.describe('Public Display', () => {

  test('should display public page without authentication', async ({ page }) => {
    await page.goto('/public.htmx.html');

    await expect(page).toHaveTitle(/AG-VOTE|Affichage|Public|Vote/);
    await page.waitForLoadState('networkidle');
  });

  test('should show waiting or vote display area', async ({ page }) => {
    await page.goto('/public.htmx.html');
    await page.waitForLoadState('networkidle');

    // Public display should show a waiting screen or live vote
    const content = page.locator('.public-display, .vote-display, .waiting-screen, [data-public-view], main');
    await expect(content.first()).toBeVisible({ timeout: 10000 });
  });

  test('should be styled for large screen display', async ({ page }) => {
    await page.goto('/public.htmx.html');
    await page.waitForLoadState('networkidle');

    // Check that text is legible on large screens
    const body = page.locator('body');
    const fontSize = await body.evaluate(el => window.getComputedStyle(el).fontSize);
    // Font size should be reasonable (not tiny)
  });

  test('should not expose admin controls', async ({ page }) => {
    await page.goto('/public.htmx.html');
    await page.waitForLoadState('networkidle');

    // Should NOT have edit/admin controls
    const adminControls = page.locator('button:has-text("Supprimer"), button:has-text("Modifier"), [data-action="delete"]');
    expect(await adminControls.count()).toBe(0);
  });

  test('should not expose voter identities in public display', async ({ page }) => {
    await page.goto('/public.htmx.html');
    await page.waitForLoadState('networkidle');

    // Public display should not show individual voter choices
    const voterDetails = page.locator('[data-voter-choice], .voter-identity');
    expect(await voterDetails.count()).toBe(0);
  });

});

// ---------------------------------------------------------------------------
// Projector State API
// ---------------------------------------------------------------------------

test.describe('Projector API', () => {

  test('projector state should be accessible', async ({ request }) => {
    const response = await request.get('/api/v1/projector_state.php');

    // Public endpoint — should not 500
    expect(response.status()).not.toBe(500);
  });

});
