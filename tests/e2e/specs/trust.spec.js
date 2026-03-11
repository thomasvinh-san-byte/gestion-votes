// @ts-check
const { test, expect } = require('@playwright/test');
const { E2E_MEETING_ID } = require('../helpers');

/**
 * Trust API E2E Tests
 */
test.describe('Trust APIs', () => {

  test('trust anomalies should reject unauthenticated', async ({ request }) => {
    const response = await request.get(`/api/v1/trust_anomalies.php?meeting_id=${E2E_MEETING_ID}`);
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

});
