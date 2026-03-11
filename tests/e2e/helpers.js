// @ts-check

/**
 * Shared E2E Test Helpers
 *
 * Centralized login helpers and test credentials.
 * Import in specs: const { loginAsOperator, ... } = require('../helpers');
 */

// Legacy API keys (still supported as fallback auth)
const OPERATOR_KEY = 'operator-key-2026-secret';
const ADMIN_KEY = 'admin-key-2026-secret';
const VOTER_KEY = 'votant-key-2026-secret';

// Email/password credentials (primary auth method)
const CREDENTIALS = {
  operator: { email: 'operator@ag-vote.local', password: 'Operator2026!' },
  admin: { email: 'admin@ag-vote.local', password: 'Admin2026!' },
  voter: { email: 'votant@ag-vote.local', password: 'Votant2026!' },
  president: { email: 'president@ag-vote.local', password: 'President2026!' },
};

const E2E_MEETING_ID = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
const E2E_MOTION_1 = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00301';
const E2E_MOTION_2 = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00302';
const E2E_MEMBER_1 = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00101';

/**
 * Login via the real login form using email + password.
 */
async function loginWithEmail(page, email, password) {
  await page.goto('/login.html');
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.click('#submitBtn');
  await page.waitForURL(/(?!.*login).*/, { timeout: 10000 });
}

async function loginAsOperator(page) {
  await loginWithEmail(page, CREDENTIALS.operator.email, CREDENTIALS.operator.password);
}

async function loginAsAdmin(page) {
  await loginWithEmail(page, CREDENTIALS.admin.email, CREDENTIALS.admin.password);
}

async function loginAsVoter(page) {
  await loginWithEmail(page, CREDENTIALS.voter.email, CREDENTIALS.voter.password);
}

async function loginAsPresident(page) {
  await loginWithEmail(page, CREDENTIALS.president.email, CREDENTIALS.president.password);
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
  loginWithEmail,
  loginAsOperator,
  loginAsAdmin,
  loginAsVoter,
  loginAsPresident,
};
