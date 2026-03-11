// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Members API E2E Tests
 */
test.describe('Members API', () => {

  test('member CSV import should reject unauthenticated requests', async ({ request }) => {
    const response = await request.post('/api/v1/members_import_csv.php', {
      data: 'name,email\nTest,test@test.com',
      headers: { 'Content-Type': 'text/csv' },
    });
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

});
