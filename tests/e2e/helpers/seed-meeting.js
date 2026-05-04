// @ts-check

/**
 * Seed a fixture meeting (with optional motions) via the dev-only API endpoint
 * `/api/v1/test/seed-meeting`.
 *
 * Usage from a Playwright spec:
 *
 *   const { seedMeeting } = require('../helpers/seed-meeting');
 *
 *   test.beforeAll(async ({ request }) => {
 *     meetingId = await seedMeeting(request, {
 *       tenantId: 'aaaaaaaa-1111-2222-3333-444444444444',
 *       status: 'running',
 *       motionsCount: 3,
 *     });
 *   });
 *
 * Throws if the endpoint returns a non-2xx response — the helper is meant for
 * `@integration` setup paths where a missing fixture must fail loudly.
 *
 * The endpoint is gated by EnvGuardMiddleware and `guardProduction()` so this
 * helper will return 404 in production-like environments. That is intentional:
 * Playwright runs against dev/test stacks only.
 *
 * Source: TEST-V24-01 / D-02 — Plan 03.1 (Phase 3 v2.4).
 *
 * @param {import('@playwright/test').APIRequestContext} request - Playwright request fixture
 * @param {{ tenantId: string, status?: string, motionsCount?: number }} opts
 * @returns {Promise<string>} The seeded meeting's UUID.
 */
async function seedMeeting(request, { tenantId, status = 'setup', motionsCount = 0 } = {}) {
  if (!tenantId) {
    throw new Error('seedMeeting: tenantId is required');
  }

  const response = await request.post('/api/v1/test/seed-meeting', {
    data: { tenantId, status, motionsCount },
  });

  if (!response.ok()) {
    const body = await response.text();
    throw new Error(
      `seedMeeting failed: ${response.status()} ${response.statusText()} — ${body}`,
    );
  }

  const json = await response.json();
  const meetingId = json?.data?.meeting_id ?? json?.meeting_id;
  if (!meetingId) {
    throw new Error(`seedMeeting: response missing meeting_id (${JSON.stringify(json)})`);
  }
  return meetingId;
}

module.exports = { seedMeeting };
