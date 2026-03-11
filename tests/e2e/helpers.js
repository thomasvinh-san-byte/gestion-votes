// @ts-check

/**
 * Shared E2E Test Helpers
 *
 * Centralized login helpers and test credentials.
 * Import in specs: const { loginAsOperator, ... } = require('../helpers');
 */

const OPERATOR_KEY = 'operator-key-2026-secret';
const ADMIN_KEY = 'admin-key-2026-secret';
const VOTER_KEY = 'votant-key-2026-secret';

const E2E_MEETING_ID = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
const E2E_MOTION_1 = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00301';
const E2E_MOTION_2 = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00302';
const E2E_MEMBER_1 = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00101';

async function loginAsOperator(page) {
  await page.goto('/login.html');
  await page.fill('input[type="password"], input[name="api_key"]', OPERATOR_KEY);
  await page.click('button[type="submit"]');
  await page.waitForURL(/meetings|operator/, { timeout: 10000 });
}

async function loginAsAdmin(page) {
  await page.goto('/login.html');
  await page.fill('input[type="password"], input[name="api_key"]', ADMIN_KEY);
  await page.click('button[type="submit"]');
  await page.waitForURL(/admin|meetings|operator/, { timeout: 10000 });
}

async function loginAsVoter(page) {
  await page.goto('/login.html');
  await page.fill('input[type="password"], input[name="api_key"]', VOTER_KEY);
  await page.click('button[type="submit"]');
  await page.waitForURL(/vote|meetings/, { timeout: 10000 });
}

module.exports = {
  OPERATOR_KEY,
  ADMIN_KEY,
  VOTER_KEY,
  E2E_MEETING_ID,
  E2E_MOTION_1,
  E2E_MOTION_2,
  E2E_MEMBER_1,
  loginAsOperator,
  loginAsAdmin,
  loginAsVoter,
};
