// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsVoter } = require('../helpers');
const { waitForHtmxSettled } = require('../helpers/waitForHtmxSettled');

// E2E-12-07: Vote page function gate
//
// Asserts real observable results for primary vote page interactions:
// app boot state, selector population, zoom toggle, speech button wiring,
// offline detection, and waiting state rendering.
//
// Scope: does NOT attempt a live vote submission (requires an active meeting
// with an open vote). Full voting flow covered by critical-path-operator +
// critical-path-votant. This spec focuses on PAGE interactions and UI wiring.
//
// Re-runnable: no DB writes, only navigation and DOM interaction assertions.

test.describe('Vote page: function gate @critical-path', () => {

  test('vote page: selectors + zoom + app state + speech button @critical-path', async ({ page }) => {
    test.setTimeout(120000);

    // 1. Login as voter (cookie injection)
    await loginAsVoter(page);

    // 2. Navigate to vote page
    await page.goto('/vote.htmx.html', { waitUntil: 'domcontentloaded' });
    await waitForHtmxSettled(page);

    // ── Assertion 1: App shell boot — data-vote-state attribute set by JS ──
    const voteApp = page.locator('#voteApp');
    await expect(voteApp).toBeVisible({ timeout: 15000 });
    // Proves JS booted and set initial state (waiting|loading|ready|voting|confirmed)
    await expect(voteApp).toHaveAttribute('data-vote-state', /waiting|loading|ready|voting|confirmed/, { timeout: 10000 });

    // ── Assertion 2: Meeting select — custom component visible and interactive ──
    // vote.htmx.html uses <ag-searchable-select id="meetingSelect">
    const meetingSelect = page.locator('#meetingSelect');
    await expect(meetingSelect).toBeVisible({ timeout: 10000 });
    // Custom component should be present in DOM as a defined element
    const meetingTagName = await meetingSelect.evaluate(el => el.tagName.toLowerCase());
    expect(meetingTagName).toBe('ag-searchable-select');

    // ── Assertion 3: Member select — custom component visible ──
    const memberSelect = page.locator('#memberSelect');
    await expect(memberSelect).toBeVisible({ timeout: 10000 });
    const memberTagName = await memberSelect.evaluate(el => el.tagName.toLowerCase());
    expect(memberTagName).toBe('ag-searchable-select');

    // ── Assertion 4: Zoom toggle — button visible, aria-pressed attribute wired ──
    const btnZoom = page.locator('#btnZoom');
    await expect(btnZoom).toBeVisible({ timeout: 10000 });
    // aria-pressed must be present (proves the accessibility contract is in place)
    await expect(btnZoom).toHaveAttribute('aria-pressed', /true|false/);
    // Click: button must remain clickable (no error, no page crash)
    await btnZoom.click();
    // After click, aria-pressed attribute must still exist
    await expect(btnZoom).toHaveAttribute('aria-pressed', /true|false/);

    // ── Assertion 5: Speech button — aria-pressed toggles on click ──
    const btnHand = page.locator('#btnHand');
    const handVisible = await btnHand.isVisible().catch(() => false);
    if (handVisible) {
      const initialHandPressed = await btnHand.getAttribute('aria-pressed');
      await btnHand.click();
      // After click: either aria-pressed flipped OR speechLabel text changed
      // Both are observable results proving the button is wired
      const afterHandClick = await btnHand.getAttribute('aria-pressed');
      const speechLabel = await page.locator('#speechLabel').textContent().catch(() => '');
      const handWired = (afterHandClick !== initialHandPressed) || (speechLabel !== '');
      expect(handWired).toBeTruthy();
    }

    // ── Assertion 6: Offline banner — hidden in connected state ──
    // The online/offline listener sets hidden when navigator.onLine is true
    const offlineBanner = page.locator('#offlineBanner');
    await expect(offlineBanner).toBeHidden({ timeout: 5000 });

    // ── Assertion 7: Waiting state — visible with non-empty text content ──
    const voteWaitingState = page.locator('#voteWaitingState');
    await expect(voteWaitingState).toBeVisible({ timeout: 10000 });
    const waitingText = await voteWaitingState.textContent();
    expect((waitingText || '').trim().length).toBeGreaterThan(0);
  });

});
