// @ts-check
const { test, expect } = require('@playwright/test');
const { E2E_MOTION_1, loginAsVoter } = require('../helpers');
const { waitForHtmxSettled } = require('../helpers/waitForHtmxSettled');

/**
 * Vote E2E Tests
 *
 * Tests the voting interface and vote-specific security.
 */

// ---------------------------------------------------------------------------
// Voter Interface
// ---------------------------------------------------------------------------

test.describe('Voter Interface', () => {

  test('should show meeting selector', async ({ page }) => {
    // Vote page requires auth (redirects to login without a session or inv token).
    await loginAsVoter(page);

    await page.goto('/vote.htmx.html', { waitUntil: 'domcontentloaded' });
    await waitForHtmxSettled(page);

    const meetingSelect = page.locator('#meetingSelect, ag-searchable-select');
    await expect(meetingSelect.first()).toBeVisible({ timeout: 10000 });
  });

});

// ---------------------------------------------------------------------------
// Vote Security
// ---------------------------------------------------------------------------

test.describe('Vote Security', () => {

  test('should require CSRF token for vote submission', async ({ request }) => {
    const response = await request.post('/api/v1/ballots_cast.php', {
      data: {
        motion_id: E2E_MOTION_1,
        member_id: 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00201',
        value: 'for',
      },
      headers: {
        'Content-Type': 'application/json',
        // No CSRF token
      },
    });
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('should require idempotency key for ballot cast', async ({ request }) => {
    const response = await request.post('/api/v1/ballots_cast.php', {
      data: {
        motion_id: E2E_MOTION_1,
        member_id: 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00201',
        value: 'for',
      },
      headers: {
        'Content-Type': 'application/json',
        // No X-Idempotency-Key
      },
    });
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('should not expose vote details in error responses', async ({ request }) => {
    const response = await request.post('/api/v1/ballots_cast.php', {
      data: { motion_id: 'bad', value: 'for' },
      headers: { 'Content-Type': 'application/json' },
    });

    const body = await response.text();
    expect(body).not.toContain('stack trace');
    expect(body).not.toContain('PDO');
    expect(body).not.toContain('SQL');
  });

});
