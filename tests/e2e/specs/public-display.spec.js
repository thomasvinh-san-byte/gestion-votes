// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Public Display E2E Tests
 *
 * Tests the public-facing display page used for projectors/screens
 * during live meetings. This page is accessible without authentication.
 *
 * NOTE: public.htmx.html subscribes to SSE and keeps the network busy
 * indefinitely — waitForLoadState('networkidle') can NEVER resolve here.
 * Strategy C is used: goto with domcontentloaded + waitForSelector.
 */

test.describe('Public Display', () => {

  test('should not expose admin controls', async ({ page }) => {
    await page.goto('/public.htmx.html', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('main, #content, body', { timeout: 10000 });

    const adminControls = page.locator('button:has-text("Supprimer"), button:has-text("Modifier"), [data-action="delete"]');
    expect(await adminControls.count()).toBe(0);
  });

  test('should not expose voter identities in public display', async ({ page }) => {
    await page.goto('/public.htmx.html', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('main, #content, body', { timeout: 10000 });

    const voterDetails = page.locator('[data-voter-choice], .voter-identity');
    expect(await voterDetails.count()).toBe(0);
  });

});

test.describe('Projector API', () => {

  test('projector state should be accessible', async ({ request }) => {
    const response = await request.get('/api/v1/projector_state.php');
    expect(response.status()).not.toBe(500);
  });

});
