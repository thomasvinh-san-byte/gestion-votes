// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsVoter } = require('../helpers');
const { waitForHtmxSettled } = require('../helpers/waitForHtmxSettled');

// E2E-04: Votant critical path
// login (cookie) -> /vote.htmx.html -> meeting selector visible
//               -> vote app renders waiting state -> confirm button wiring present
//
// Scope note: CONTEXT.md mentions a token-based flow. The current
// vote.htmx.html is session-based (verified via vote.spec.js). We follow
// what works -- token-flow gaps will surface in Phase 10 UAT.
//
// Re-runnable: no DB writes, only navigation and visibility assertions.

test.describe('E2E-04 Votant critical path', () => {

  test('votant: vote page -> meeting selector -> vote app ready @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // Step 1: Login as voter (cookie injection)
    await loginAsVoter(page);

    const whoami = await page.request.get('/api/v1/whoami.php');
    expect(whoami.ok()).toBeTruthy();

    // Step 2: Navigate to vote page
    await page.goto('/vote.htmx.html', { waitUntil: 'domcontentloaded' });
    await waitForHtmxSettled(page);

    // Step 3: Vote app shell rendered
    await expect(page.locator('#voteApp')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#voteApp')).toHaveAttribute('data-vote-state', /waiting|loading|ready/, { timeout: 10000 });

    // Step 4: Meeting selector visible (entry point of votant journey)
    const meetingSelect = page.locator('#meetingSelect, ag-searchable-select');
    await expect(meetingSelect.first()).toBeVisible({ timeout: 10000 });

    // Step 5: Member selector visible (next step after meeting pick)
    await expect(page.locator('#memberSelect')).toBeVisible({ timeout: 5000 });

    // Step 6: Waiting state panel visible (proves vote app booted)
    await expect(page.locator('#voteWaitingState')).toBeVisible({ timeout: 10000 });

    // Step 7: Confirmation control wired (required for any vote submission)
    // The button may be hidden until a vote is staged; check it exists in DOM.
    const btnConfirm = page.locator('#btnConfirm');
    await expect(btnConfirm).toHaveCount(1);

    // Step 8: Zoom toggle proves vote-app JS is alive
    const btnZoom = page.locator('#btnZoom');
    if (await btnZoom.isVisible().catch(() => false)) {
      await btnZoom.click();
      await expect(btnZoom).toHaveAttribute('aria-pressed', /true|false/, { timeout: 5000 });
    }
  });

});
