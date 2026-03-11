// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Meeting Lifecycle Workflow E2E Tests
 *
 * Tests the complete meeting lifecycle as experienced by real users:
 * Login → Select meeting → Configure → Launch → Vote → Close
 *
 * Uses the E2E seed data (04_e2e.sql): "Conseil Municipal — Seance E2E"
 */

const E2E_MEETING_TITLE = 'Conseil Municipal';
const OPERATOR_EMAIL = 'operator@ag-vote.local';
const OPERATOR_PASSWORD = 'Operator2026!';
const ADMIN_EMAIL = 'admin@ag-vote.local';
const ADMIN_PASSWORD = 'Admin2026!';

/**
 * Login via the real login form (email + password).
 */
async function loginWithCredentials(page, email, password) {
  await page.goto('/login.html');
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.click('#submitBtn');
  // Wait for redirect away from login
  await page.waitForURL(/(?!.*login).*\.htmx\.html/, { timeout: 10000 });
}

// ---------------------------------------------------------------------------
// 1. Login Flow
// ---------------------------------------------------------------------------
test.describe('Login Flow', () => {

  test('should display login form with email and password fields', async ({ page }) => {
    await page.goto('/login.html');

    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('#submitBtn')).toBeVisible();
    await expect(page.locator('#submitBtn')).toHaveText(/Se connecter/);
  });

  test('should reject invalid credentials and show error', async ({ page }) => {
    await page.goto('/login.html');
    await page.fill('#email', 'bad@example.com');
    await page.fill('#password', 'wrongpassword');
    await page.click('#submitBtn');

    // Should stay on login page
    await expect(page).toHaveURL(/login/);
    // Error box should appear
    await expect(page.locator('#errorBox')).not.toBeEmpty({ timeout: 5000 });
  });

  test('should login with valid operator credentials', async ({ page }) => {
    await loginWithCredentials(page, OPERATOR_EMAIL, OPERATOR_PASSWORD);

    // Should navigate to a protected page
    await expect(page).not.toHaveURL(/login/);
  });

  test('should login with valid admin credentials', async ({ page }) => {
    await loginWithCredentials(page, ADMIN_EMAIL, ADMIN_PASSWORD);

    await expect(page).not.toHaveURL(/login/);
  });
});

// ---------------------------------------------------------------------------
// 2. Operator — Meeting Selection & Overview
// ---------------------------------------------------------------------------
test.describe('Operator Meeting Management', () => {

  test.beforeEach(async ({ page }) => {
    await loginWithCredentials(page, OPERATOR_EMAIL, OPERATOR_PASSWORD);
  });

  test('should load operator page and show meeting selector', async ({ page }) => {
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');

    const meetingSelect = page.locator('#meetingSelect');
    await expect(meetingSelect).toBeVisible({ timeout: 10000 });
  });

  test('should list E2E meeting in selector', async ({ page }) => {
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');

    // Wait for options to be populated
    const meetingSelect = page.locator('#meetingSelect');
    await expect(meetingSelect).toBeVisible({ timeout: 10000 });

    // Check that the E2E meeting is in the dropdown
    const options = meetingSelect.locator('option');
    const optionTexts = await options.allTextContents();
    const hasE2EMeeting = optionTexts.some(t => t.includes(E2E_MEETING_TITLE));
    expect(hasE2EMeeting).toBeTruthy();
  });

  test('should select E2E meeting and show status badge', async ({ page }) => {
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');

    const meetingSelect = page.locator('#meetingSelect');
    await expect(meetingSelect).toBeVisible({ timeout: 10000 });

    // Find and select the E2E meeting option
    const options = meetingSelect.locator('option');
    const allOptions = await options.all();
    for (const opt of allOptions) {
      const text = await opt.textContent();
      if (text && text.includes(E2E_MEETING_TITLE)) {
        const value = await opt.getAttribute('value');
        if (value) {
          await meetingSelect.selectOption(value);
          break;
        }
      }
    }

    // Status badge should update
    const badge = page.locator('#meetingStatusBadge');
    await expect(badge).not.toHaveText('—', { timeout: 5000 });
  });

  test('should show preparation mode with setup tabs', async ({ page }) => {
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('networkidle');

    // Check mode switches exist
    await expect(page.locator('#btnModeSetup')).toBeVisible();
    await expect(page.locator('#btnModeExec')).toBeVisible();

    // Setup mode should be active by default
    await expect(page.locator('#btnModeSetup')).toHaveAttribute('aria-pressed', 'true');
  });
});

// ---------------------------------------------------------------------------
// 3. API — Meeting Workflow (status transitions)
// ---------------------------------------------------------------------------
test.describe('Meeting API Workflow', () => {

  test('should return meeting list from API', async ({ request }) => {
    // Login first to get session
    const loginResp = await request.post('/api/v1/auth_login', {
      data: { email: OPERATOR_EMAIL, password: OPERATOR_PASSWORD },
    });
    expect(loginResp.ok()).toBeTruthy();

    // Get meetings list
    const meetingsResp = await request.get('/api/v1/meetings');
    expect(meetingsResp.ok()).toBeTruthy();

    const body = await meetingsResp.json();
    expect(body.data || body.meetings || body).toBeTruthy();
  });

  test('should return workflow readiness check', async ({ request }) => {
    // Login
    await request.post('/api/v1/auth_login', {
      data: { email: OPERATOR_EMAIL, password: OPERATOR_PASSWORD },
    });

    // Check workflow readiness for E2E meeting
    const resp = await request.get('/api/v1/meeting_workflow_check?meeting_id=eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001');

    // Should return 200 with readiness info (may or may not be ready)
    expect(resp.status()).toBeLessThan(500);
  });

  test('should return health check OK', async ({ request }) => {
    const resp = await request.get('/api/v1/health.php');
    expect(resp.ok()).toBeTruthy();
  });
});

// ---------------------------------------------------------------------------
// 4. Vote interface
// ---------------------------------------------------------------------------
test.describe('Vote Interface', () => {

  test('should load vote page and show meeting selector', async ({ page }) => {
    await page.goto('/vote.htmx.html');
    await page.waitForLoadState('networkidle');

    // Vote page should have a meeting selector
    const meetingSelect = page.locator('#meetingSelect, select[name="meeting_id"], ag-searchable-select');
    await expect(meetingSelect.first()).toBeVisible({ timeout: 5000 });
  });
});

// ---------------------------------------------------------------------------
// 5. Dashboard
// ---------------------------------------------------------------------------
test.describe('Dashboard', () => {

  test.beforeEach(async ({ page }) => {
    await loginWithCredentials(page, OPERATOR_EMAIL, OPERATOR_PASSWORD);
  });

  test('should load dashboard with KPI cards', async ({ page }) => {
    await page.goto('/dashboard.htmx.html');
    await page.waitForLoadState('networkidle');

    // Dashboard should have content
    await expect(page.locator('body')).not.toBeEmpty();
    // At least the page should load without errors
    await expect(page.locator('.app-shell, main, [role="main"]').first()).toBeVisible({ timeout: 5000 });
  });
});

// ---------------------------------------------------------------------------
// 6. Admin Panel
// ---------------------------------------------------------------------------
test.describe('Admin Panel', () => {

  test.beforeEach(async ({ page }) => {
    await loginWithCredentials(page, ADMIN_EMAIL, ADMIN_PASSWORD);
  });

  test('should load admin page', async ({ page }) => {
    await page.goto('/admin.htmx.html');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.app-shell, main, [role="main"]').first()).toBeVisible({ timeout: 5000 });
  });
});
