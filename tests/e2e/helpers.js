// @ts-check
const path = require('path');
const fs   = require('fs');

/**
 * Shared E2E Test Helpers
 *
 * Centralized login helpers and test credentials.
 * Import in specs: const { loginAsOperator, ... } = require('../helpers');
 *
 * Auth strategy: the global setup (setup/auth.setup.js) logs in once per role
 * and saves the browser storageState (cookies) to .auth/{role}.json.
 * loginAs* helpers inject those saved cookies — no fresh HTTP login needed,
 * which prevents hitting the auth_login rate limit (10 req / 300 s) during
 * a full parallel test run.
 *
 * If the saved auth file doesn't exist (first run before globalSetup), the
 * helpers fall back to a direct login form navigation.
 */

// Legacy API keys (still supported as fallback auth)
const OPERATOR_KEY = 'operator-key-2026-secret';
const ADMIN_KEY = 'admin-key-2026-secret';
const VOTER_KEY = 'votant-key-2026-secret';

// Email/password credentials (primary auth method)
const CREDENTIALS = {
  operator:  { email: 'operator@ag-vote.local',  password: 'Operator2026!'  },
  admin:     { email: 'admin@ag-vote.local',      password: 'Admin2026!'     },
  voter:     { email: 'votant@ag-vote.local',     password: 'Votant2026!'    },
  president: { email: 'president@ag-vote.local',  password: 'President2026!' },
  auditor:   { email: 'auditor@ag-vote.local',       password: 'Auditor2026!'   },
  assessor:  { email: 'assessor-e2e@ag-vote.local',  password: 'Assessor2026!'  },
};

const E2E_MEETING_ID = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
const E2E_MOTION_1   = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00301';
const E2E_MOTION_2   = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00302';
const E2E_MEMBER_1   = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00101';

const AUTH_DIR = path.join(__dirname, '.auth');

/**
 * Resolve path to saved storageState for a role.
 */
function authStatePath(role) {
  return path.join(AUTH_DIR, `${role}.json`);
}

/**
 * Add cached cookies for `role` to the current page context.
 * Falls back to fresh login if no cached state exists.
 *
 * Strategy: navigate to the base URL first so cookies for the domain are
 * accepted, then inject the saved PHPSESSID via addCookies.
 * This avoids the auth_login rate limit (10 req / 300 s) on parallel runs.
 */
async function injectAuth(page, role, email, password) {
  const stateFile = authStatePath(role);

  if (fs.existsSync(stateFile)) {
    const state = JSON.parse(fs.readFileSync(stateFile, 'utf-8'));
    const cookies = state.cookies || [];

    if (cookies.length > 0) {
      // Navigate to the base URL first so the domain context is established,
      // then add cookies. Without an initial navigation, addCookies may fail
      // for localhost due to port/domain matching in Playwright internals.
      const baseURL = page.context()._options && page.context()._options.baseURL
        ? page.context()._options.baseURL
        : 'http://localhost:8080';

      // Use a lightweight endpoint to establish the domain before injecting cookies.
      try {
        await page.goto('/login.html', { waitUntil: 'commit' });
      } catch (e) {
        // ignore navigation errors — we just need to establish the domain
      }

      await page.context().addCookies(cookies);
      return;
    }
  }

  // Fallback: full login form (slow, counts against rate limit)
  await loginWithEmail(page, email, password);
}

/**
 * Login via the real login form using email + password.
 * Use only when cached auth state is unavailable (rate-limit risk!).
 */
async function loginWithEmail(page, email, password) {
  await page.goto('/login.html');
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.click('#submitBtn');
  await page.waitForURL(/(?!.*login).*/, { timeout: 15000 });
}

async function loginAsOperator(page) {
  await injectAuth(page, 'operator', CREDENTIALS.operator.email, CREDENTIALS.operator.password);
}

async function loginAsAdmin(page) {
  await injectAuth(page, 'admin', CREDENTIALS.admin.email, CREDENTIALS.admin.password);
}

async function loginAsVoter(page) {
  await injectAuth(page, 'voter', CREDENTIALS.voter.email, CREDENTIALS.voter.password);
}

async function loginAsPresident(page) {
  await injectAuth(page, 'president', CREDENTIALS.president.email, CREDENTIALS.president.password);
}

async function loginAsAuditor(page) {
  await injectAuth(page, 'auditor', CREDENTIALS.auditor.email, CREDENTIALS.auditor.password);
}

async function loginAsAssessor(page) {
  await injectAuth(page, 'assessor', CREDENTIALS.assessor.email, CREDENTIALS.assessor.password);
}

module.exports = {
  OPERATOR_KEY,
  ADMIN_KEY,
  VOTER_KEY,
  CREDENTIALS,
  E2E_MEETING_ID,
  E2E_MOTION_1,
  E2E_MOTION_2,
  E2E_MEMBER_1,
  authStatePath,
  loginWithEmail,
  loginAsOperator,
  loginAsAdmin,
  loginAsVoter,
  loginAsPresident,
  loginAsAuditor,
  loginAsAssessor,
};
