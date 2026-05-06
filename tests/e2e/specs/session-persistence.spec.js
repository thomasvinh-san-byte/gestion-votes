// @ts-check
const { test, expect } = require('@playwright/test');
const { execSync } = require('node:child_process');
const { loginAsOperator } = require('../helpers');

/**
 * Session persistence E2E.
 *
 * Proves that PHP sessions stored in Redis DB1 (CLEANUP-SESSIONS-01)
 * survive a `docker compose restart app`. Before this work, sessions
 * lived on tmpfs /tmp inside the app container — every restart wiped
 * them and forced re-login.
 *
 * Marked slow because the container restart + healthcheck wait adds
 * ~15s. CI fast lane may skip via `--grep-invert @slow`.
 *
 * Requires Docker compose running on the test host. Skipped in
 * environments where `docker compose` is unavailable (e.g. agent
 * sandboxes).
 *
 * Refs: M-INFRA-CLEANUP / CLEANUP-SESSIONS-03.
 */

const HEALTH_TIMEOUT_MS = 30_000;
const HEALTH_POLL_INTERVAL_MS = 1_000;

function dockerComposeAvailable() {
  try {
    execSync('docker compose version', { stdio: 'ignore' });
    return true;
  } catch (_e) {
    return false;
  }
}

async function waitForAppHealth(request, baseURL) {
  const deadline = Date.now() + HEALTH_TIMEOUT_MS;
  while (Date.now() < deadline) {
    try {
      const res = await request.get(`${baseURL}/api/v1/health.php`);
      if (res.ok()) return;
    } catch (_e) {
      // ignore — container still booting
    }
    await new Promise((r) => setTimeout(r, HEALTH_POLL_INTERVAL_MS));
  }
  throw new Error('app container did not return healthy after restart');
}

test.describe('@slow Session persistence', () => {
  test.skip(!dockerComposeAvailable(), 'docker compose not available on host');
  test.slow();

  test('session survives docker compose restart app', async ({ page, request, baseURL }) => {
    await loginAsOperator(page);
    await page.goto('/cockpit.html');
    await expect(page).not.toHaveURL(/login/);

    // Capture the session cookie so we can assert it stays valid post-restart.
    const cookiesBefore = await page.context().cookies();
    const sessionBefore = cookiesBefore.find((c) => /PHPSESSID|sess/i.test(c.name));
    expect(sessionBefore, 'session cookie must exist before restart').toBeTruthy();

    // Restart only the app service — Redis must stay up so session data persists.
    execSync('docker compose restart app', { stdio: 'ignore' });
    await waitForAppHealth(request, baseURL ?? 'http://localhost:8080');

    // Same browser context (cookie still set), same session id, same user.
    await page.goto('/cockpit.html');
    await expect(page).not.toHaveURL(/login/);

    const cookiesAfter = await page.context().cookies();
    const sessionAfter = cookiesAfter.find((c) => c.name === sessionBefore.name);
    expect(sessionAfter?.value).toBe(sessionBefore.value);
  });
});
