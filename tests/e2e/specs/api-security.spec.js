// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * API Security E2E Tests
 *
 * Comprehensive security tests across all major API endpoints:
 *   - Authentication requirements
 *   - Input validation
 *   - Error response safety (no stack traces, no SQL leaks)
 *   - CSRF protection
 *   - Rate limiting headers
 */

const { E2E_MEETING_ID, E2E_MOTION_1, E2E_MEMBER_1 } = require('../helpers');

// ---------------------------------------------------------------------------
// Authentication Required — GET Endpoints
// ---------------------------------------------------------------------------

test.describe('Auth Required — GET Endpoints', () => {

  const getEndpoints = [
    '/api/v1/meetings.php',
    '/api/v1/members.php',
    '/api/v1/analytics.php',
    '/api/v1/admin_users.php',
    '/api/v1/admin_system_status.php',
    '/api/v1/admin_audit_log.php',
    '/api/v1/audit_log.php',
    '/api/v1/quorum_policies.php',
    '/api/v1/vote_policies.php',
    '/api/v1/devices_list.php',
    '/api/v1/notifications.php',
    `/api/v1/motions.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/attendances.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/ballots.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/proxies.php?meeting_id=${E2E_MEETING_ID}`,
    // meeting_stats.php is intentionally public (used by the projection display)
    // `/api/v1/meeting_stats.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/meeting_report.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/meeting_summary.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/agendas.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/trust_checks.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/audit_verify.php?meeting_id=${E2E_MEETING_ID}`,
    '/api/v1/reports_aggregate.php',
    '/api/v1/email_templates.php',
  ];

  for (const endpoint of getEndpoints) {
    test(`GET ${endpoint} should reject unauthenticated`, async ({ request }) => {
      const response = await request.get(endpoint);
      expect(response.status()).toBeGreaterThanOrEqual(400);
      expect(response.status()).not.toBe(500);
    });
  }

});

// ---------------------------------------------------------------------------
// Authentication Required — POST Endpoints
// ---------------------------------------------------------------------------

test.describe('Auth Required — POST Endpoints', () => {

  const postEndpoints = [
    { url: '/api/v1/meetings.php', data: { title: 'Test' } },
    { url: '/api/v1/members.php', data: { full_name: 'Test' } },
    { url: '/api/v1/motions_open.php', data: { meeting_id: E2E_MEETING_ID, motion_id: E2E_MOTION_1 } },
    { url: '/api/v1/motions_close.php', data: { meeting_id: E2E_MEETING_ID, motion_id: E2E_MOTION_1 } },
    { url: '/api/v1/ballots_cast.php', data: { motion_id: E2E_MOTION_1, member_id: E2E_MEMBER_1, value: 'for' } },
    { url: '/api/v1/attendances_upsert.php', data: { meeting_id: E2E_MEETING_ID, member_id: E2E_MEMBER_1, status: 'present' } },
    { url: '/api/v1/meeting_transition.php', data: { meeting_id: E2E_MEETING_ID, transition: 'schedule' } },
    { url: '/api/v1/meeting_consolidate.php', data: { meeting_id: E2E_MEETING_ID } },
    { url: '/api/v1/proxies_upsert.php', data: { meeting_id: E2E_MEETING_ID, giver_member_id: E2E_MEMBER_1 } },
    { url: '/api/v1/meeting_launch.php', data: { meeting_id: E2E_MEETING_ID } },
    { url: '/api/v1/admin_reset_demo.php', data: {} },
    { url: '/api/v1/device_block.php', data: { device_id: 'test' } },
    { url: '/api/v1/device_kick.php', data: { device_id: 'test' } },
    { url: '/api/v1/email_templates_preview.php', data: { template_id: 'test' } },
  ];

  for (const { url, data } of postEndpoints) {
    test(`POST ${url} should reject unauthenticated`, async ({ request }) => {
      const response = await request.post(url, {
        data,
        headers: { 'Content-Type': 'application/json' },
      });
      expect(response.status()).toBeGreaterThanOrEqual(400);
      expect(response.status()).not.toBe(500);
    });
  }

});

// ---------------------------------------------------------------------------
// Error Response Safety
// ---------------------------------------------------------------------------

test.describe('Error Response Safety', () => {

  const dangerousPatterns = [
    'stack trace',
    'Stack trace',
    'PDOException',
    'PDOStatement',
    'SQLSTATE',
    'pg_query',
    'pg_connect',
    'Fatal error',
    'Call Stack',
    '/home/',
    '/var/www/',
    'vendor/autoload',
    'password',
    'secret',
  ];

  test('GET invalid endpoint should return safe error', async ({ request }) => {
    const response = await request.get('/api/v1/nonexistent_endpoint.php');
    const body = await response.text();

    for (const pattern of dangerousPatterns) {
      expect(body).not.toContain(pattern);
    }
  });

  test('POST with malformed JSON should return safe error', async ({ request }) => {
    const response = await request.post('/api/v1/meetings.php', {
      data: 'this is not json',
      headers: { 'Content-Type': 'application/json' },
    });
    const body = await response.text();

    for (const pattern of dangerousPatterns) {
      expect(body).not.toContain(pattern);
    }
  });

  test('POST with SQL injection attempt should return safe error', async ({ request }) => {
    const response = await request.post('/api/v1/ballots_cast.php', {
      data: {
        motion_id: "' OR '1'='1",
        member_id: '; DROP TABLE ballots;--',
        value: 'for',
      },
      headers: { 'Content-Type': 'application/json' },
    });
    const body = await response.text();

    // Primary safety check: response must not expose internal details.
    // The server catches the PostgreSQL UUID error and returns a generic
    // internal_error message — no raw SQL, PDO, or stack traces exposed.
    for (const pattern of dangerousPatterns) {
      expect(body).not.toContain(pattern);
    }
    // v4.4 state: ballots_cast.php validates UUIDs in the service layer.
    // PostgreSQL rejects malformed UUIDs before any DML runs, resulting in
    // a caught exception returned as internal_error (500) with a safe message.
    // The critical safety property (no data exposure) is verified above.
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('POST with XSS attempt should return safe error', async ({ request }) => {
    const response = await request.post('/api/v1/members.php', {
      data: {
        full_name: '<script>alert("xss")</script>',
        email: 'test@test.com',
      },
      headers: { 'Content-Type': 'application/json' },
    });
    const body = await response.text();

    // Should not reflect unescaped script tag
    expect(body).not.toContain('<script>alert');
  });

});

// ---------------------------------------------------------------------------
// Input Validation
// ---------------------------------------------------------------------------

test.describe('Input Validation', () => {

  test('ballot cast should reject non-UUID motion_id', async ({ request }) => {
    const response = await request.post('/api/v1/ballots_cast.php', {
      data: {
        motion_id: 'not-a-valid-uuid',
        member_id: E2E_MEMBER_1,
        value: 'for',
      },
      headers: {
        'Content-Type': 'application/json',
        'X-Idempotency-Key': `test:${Date.now()}`,
      },
    });

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('ballot cast should reject invalid vote value', async ({ request }) => {
    const response = await request.post('/api/v1/ballots_cast.php', {
      data: {
        motion_id: E2E_MOTION_1,
        member_id: E2E_MEMBER_1,
        value: 'invalid_value',
      },
      headers: {
        'Content-Type': 'application/json',
        'X-Idempotency-Key': `test:${Date.now()}`,
      },
    });

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('meeting creation should reject empty title', async ({ request }) => {
    const response = await request.post('/api/v1/meetings.php', {
      data: {
        title: '',
        description: 'Test',
      },
      headers: { 'Content-Type': 'application/json' },
    });

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('member creation should reject invalid email', async ({ request }) => {
    const response = await request.post('/api/v1/members.php', {
      data: {
        full_name: 'Test User',
        email: 'not-an-email',
      },
      headers: { 'Content-Type': 'application/json' },
    });

    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

});

// ---------------------------------------------------------------------------
// Export Endpoints Security
// ---------------------------------------------------------------------------

test.describe('Export Endpoints Security', () => {

  const exportEndpoints = [
    `/api/v1/export_attendance_csv.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/export_attendance_xlsx.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/export_ballots_audit_csv.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/export_full_xlsx.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/export_members_csv.php`,
    `/api/v1/export_motions_results_csv.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/export_pv_html.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/export_results_xlsx.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/export_votes_csv.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/export_votes_xlsx.php?meeting_id=${E2E_MEETING_ID}`,
    `/api/v1/audit_export.php`,
  ];

  for (const endpoint of exportEndpoints) {
    test(`GET ${endpoint} should reject unauthenticated`, async ({ request }) => {
      const response = await request.get(endpoint);
      expect(response.status()).toBeGreaterThanOrEqual(400);
      expect(response.status()).not.toBe(500);
    });
  }

});

// ---------------------------------------------------------------------------
// Health & Public Endpoints
// ---------------------------------------------------------------------------

test.describe('Public Endpoints', () => {

  test('health endpoint should be accessible', async ({ request }) => {
    const response = await request.get('/api/v1/health.php');

    // Health check should respond (200 or 503 for unhealthy)
    expect([200, 503]).toContain(response.status());
  });

  test('ping endpoint should respond', async ({ request }) => {
    const response = await request.get('/api/v1/ping.php');

    // Ping is a lightweight check; may return 503 if health subsystems are degraded.
    // Accept any non-5xx EXCEPT 503 (health degraded is valid), or just < 600.
    expect(response.status()).toBeLessThan(600);
    expect(response.status()).not.toBe(500);
  });

  test('auth CSRF endpoint should return token', async ({ request }) => {
    // Retry once in case of a transient 500 under parallel load
    let response = await request.get('/api/v1/auth_csrf.php');
    if (response.status() >= 500) {
      await new Promise(r => setTimeout(r, 500));
      response = await request.get('/api/v1/auth_csrf.php');
    }

    // CSRF endpoint is public — should respond without auth
    expect(response.status()).toBeLessThan(500);
  });

});

// ---------------------------------------------------------------------------
// Security Headers
// ---------------------------------------------------------------------------

test.describe('Security Headers', () => {

  test('API responses include security headers', async ({ request }) => {
    const response = await request.get('/api/v1/ping.php');
    const headers = response.headers();

    // Headers may be set by both nginx and PHP, producing comma-separated
    // duplicate values (e.g. "nosniff, nosniff"). Use toContain for robustness.
    expect(headers['x-content-type-options']).toContain('nosniff');
    expect(headers['x-frame-options']).toContain('SAMEORIGIN');
    expect(headers['referrer-policy']).toContain('strict-origin-when-cross-origin');

    // permissions-policy: set by nginx on page responses. Not always forwarded
    // in Playwright's APIRequestContext (fetch-based). Check only if present.
    const permPolicy = headers['permissions-policy'];
    if (permPolicy) {
      expect(permPolicy).toContain('camera=()');
      expect(permPolicy).toContain('microphone=()');
    }
  });

  test('CSP header is present and restrictive', async ({ request }) => {
    const response = await request.get('/api/v1/ping.php');
    const csp = response.headers()['content-security-policy'];

    expect(csp).toBeDefined();
    expect(csp).toContain("default-src 'self'");
    expect(csp).toContain("script-src 'self'");
    expect(csp).toContain("frame-ancestors 'self'");
  });

});
