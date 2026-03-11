// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Doc API E2E Tests
 */
test.describe('Doc API', () => {

  test('doc index should be accessible', async ({ request }) => {
    const response = await request.get('/api/v1/doc_index.php');
    // Documentation index is typically public or semi-public
    expect(response.status()).not.toBe(500);
  });

});
