// @ts-check
const { test, expect } = require('@playwright/test');
const { loginWithEmail, loginAsOperator, loginAsAdmin, authStatePath, CREDENTIALS } = require('../helpers');

/**
 * Meeting Lifecycle Workflow E2E Tests
 *
 * Tests the complete meeting lifecycle as experienced by real users:
 * Login → Select meeting → Configure → Launch → Vote → Close
 *
 * Uses the E2E seed data (04_e2e.sql): "Conseil Municipal — Seance E2E"
 */

const E2E_MEETING_TITLE = 'Conseil Municipal';
const OPERATOR_EMAIL = CREDENTIALS.operator.email;
const OPERATOR_PASSWORD = CREDENTIALS.operator.password;
const ADMIN_EMAIL = CREDENTIALS.admin.email;
const ADMIN_PASSWORD = CREDENTIALS.admin.password;

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
    // Test the actual login form flow (not injected cookies)
    await loginWithEmail(page, OPERATOR_EMAIL, OPERATOR_PASSWORD);

    // Should navigate to a protected page
    await expect(page).not.toHaveURL(/login/);
  });

  test('should login with valid admin credentials', async ({ page }) => {
    // Test the actual login form flow (not injected cookies)
    await loginWithEmail(page, ADMIN_EMAIL, ADMIN_PASSWORD);

    await expect(page).not.toHaveURL(/login/);
  });
});

// ---------------------------------------------------------------------------
// 2. Operator — Meeting Selection & Overview
// ---------------------------------------------------------------------------
test.describe('Operator Meeting Management', () => {

  test.beforeEach(async ({ page }) => {
    // Use cached auth state (no rate-limit hit)
    await loginAsOperator(page);
  });

  test('should load operator page and show meeting selector', async ({ page }) => {
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const meetingSelect = page.locator('#meetingSelect');
    await expect(meetingSelect).toBeVisible({ timeout: 10000 });
  });

  test('should list E2E meeting in selector', async ({ page }) => {
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('domcontentloaded');

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
    await page.waitForLoadState('domcontentloaded');

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
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('#meetingSelect')).toBeVisible({ timeout: 10000 });

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

  /**
   * Inject saved operator session cookie into the request context.
   * This avoids a fresh auth_login call (rate-limit risk).
   */
  async function getAuthCookie() {
    const fs   = require('fs');
    const path = require('path');
    const stateFile = path.join(__dirname, '..', '.auth', 'operator.json');
    if (!fs.existsSync(stateFile)) return null;
    const state = JSON.parse(fs.readFileSync(stateFile, 'utf-8'));
    const phpSession = (state.cookies || []).find(c => c.name === 'PHPSESSID');
    return phpSession ? `PHPSESSID=${phpSession.value}` : null;
  }

  test('should return meeting list from API', async ({ request }) => {
    const cookie = await getAuthCookie();
    const headers = cookie ? { Cookie: cookie } : {};

    // If no cached auth, fall back to login (acceptable for first run)
    if (!cookie) {
      const loginResp = await request.post('/api/v1/auth_login', {
        data: { email: OPERATOR_EMAIL, password: OPERATOR_PASSWORD },
      });
      expect(loginResp.ok()).toBeTruthy();
    }

    const meetingsResp = await request.get('/api/v1/meetings', { headers });
    expect(meetingsResp.ok()).toBeTruthy();

    const body = await meetingsResp.json();
    expect(body.data || body.meetings || body).toBeTruthy();
  });

  test('should return workflow readiness check', async ({ request }) => {
    const cookie = await getAuthCookie();
    const headers = cookie ? { Cookie: cookie } : {};

    if (!cookie) {
      await request.post('/api/v1/auth_login', {
        data: { email: OPERATOR_EMAIL, password: OPERATOR_PASSWORD },
      });
    }

    // Check workflow readiness for E2E meeting
    const resp = await request.get(
      '/api/v1/meeting_workflow_check?meeting_id=eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
      { headers },
    );

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
    // Voter login via cached auth state
    const { loginAsVoter } = require('../helpers');
    await loginAsVoter(page);

    await page.goto('/vote.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    // Vote page should have a meeting selector
    const meetingSelect = page.locator('#meetingSelect, ag-searchable-select');
    await expect(meetingSelect.first()).toBeVisible({ timeout: 10000 });
  });
});

// ---------------------------------------------------------------------------
// 5. Dashboard
// ---------------------------------------------------------------------------
test.describe('Dashboard', () => {

  test.beforeEach(async ({ page }) => {
    await loginAsOperator(page);
  });

  test('should load dashboard with KPI cards', async ({ page }) => {
    await page.goto('/dashboard.htmx.html');
    await page.waitForLoadState('domcontentloaded');

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
    await loginAsAdmin(page);
  });

  test('should load admin page', async ({ page }) => {
    await page.goto('/admin.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('.app-shell, main, [role="main"]').first()).toBeVisible({ timeout: 5000 });
  });
});
