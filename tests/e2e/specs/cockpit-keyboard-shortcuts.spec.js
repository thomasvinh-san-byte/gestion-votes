// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * E2E v2.3 / Phase 1 / COCKPIT-06
 *
 * Verifies operator keyboard shortcuts:
 *  - L triggers the launch-vote button (or its toggle fallback)
 *  - F triggers the close-vote button (or its toggle fallback)
 *  - ? toggles the shortcuts overlay
 *  - Escape closes the overlay
 *  - shortcuts are SUPPRESSED inside input/textarea/contenteditable focus
 *  - shortcuts are SUPPRESSED when meta/ctrl/alt is held
 *  - integration smoke: real seeded meeting + production button (F-4)
 *
 * Test budget: CLAUDE.md mandates max 3 Playwright executions per plan, and
 * "do NOT retry" blindly — if a case fails twice, STOP and surface it to the user.
 */

test.describe('@cockpit-v2.3 operator keyboard shortcuts', () => {

  async function gotoOperatorExec(page) {
    await loginAsOperator(page);
    await page.goto('/operator');
    await page.evaluate(() => {
      var v = document.getElementById('viewExec');
      if (v) v.hidden = false;
      // Inject a stub launch button if not present so the shortcut has a target.
      if (!document.getElementById('opBtnLaunchVote') && !document.getElementById('opBtnToggleVote')) {
        var b = document.createElement('button');
        b.id = 'opBtnLaunchVote';
        b.textContent = 'Lancer';
        document.body.appendChild(b);
      }
      // Stub close
      if (!document.getElementById('opBtnCloseVote') && !document.getElementById('opBtnToggleVote')) {
        var c = document.createElement('button');
        c.id = 'opBtnCloseVote';
        c.textContent = 'Fermer';
        document.body.appendChild(c);
      }
      // Force exec mode for the keybindings module
      window.O = window.O || {};
      window.O.currentMode = 'exec';
    });
  }

  test('? opens the shortcuts overlay; Escape closes it', async ({ page }) => {
    await gotoOperatorExec(page);
    await page.keyboard.press('?');
    const overlay = page.locator('#agShortcutsOverlay');
    await expect(overlay).toBeVisible();
    await expect(overlay).toContainText('Lancer le vote actif');
    await expect(overlay).toContainText('Fermer le scrutin actif');
    await expect(overlay).toContainText('Résolution suivante');
    await page.keyboard.press('Escape');
    await expect(overlay).toBeHidden();
  });

  test('L triggers the launch button', async ({ page }) => {
    await gotoOperatorExec(page);
    await page.evaluate(() => {
      var btn = document.getElementById('opBtnLaunchVote') || document.getElementById('opBtnToggleVote');
      btn.addEventListener('click', () => { window.__launchClicked = true; });
    });
    await page.keyboard.press('l');
    const clicked = await page.evaluate(() => !!window.__launchClicked);
    expect(clicked).toBe(true);
  });

  test('F triggers the close button', async ({ page }) => {
    await gotoOperatorExec(page);
    await page.evaluate(() => {
      var btn = document.getElementById('opBtnCloseVote') || document.getElementById('opBtnToggleVote');
      btn.addEventListener('click', () => { window.__closeClicked = true; });
    });
    await page.keyboard.press('f');
    const clicked = await page.evaluate(() => !!window.__closeClicked);
    expect(clicked).toBe(true);
  });

  test('shortcuts are SUPPRESSED while typing in an input', async ({ page }) => {
    await gotoOperatorExec(page);
    await page.evaluate(() => {
      var inp = document.createElement('input');
      inp.type = 'text';
      inp.id = 'testInput';
      document.body.appendChild(inp);
      window.__launchClicked = false;
      var btn = document.getElementById('opBtnLaunchVote') || document.getElementById('opBtnToggleVote');
      btn.addEventListener('click', () => { window.__launchClicked = true; });
    });
    await page.locator('#testInput').focus();
    await page.keyboard.press('l');
    const clicked = await page.evaluate(() => !!window.__launchClicked);
    expect(clicked).toBe(false);
    // The character is typed into the input instead
    const val = await page.locator('#testInput').inputValue();
    expect(val).toBe('l');
  });

  test('shortcuts are SUPPRESSED in contenteditable focus', async ({ page }) => {
    await gotoOperatorExec(page);
    await page.evaluate(() => {
      var div = document.createElement('div');
      div.contentEditable = 'true';
      div.id = 'testEditable';
      div.tabIndex = 0;
      document.body.appendChild(div);
      window.__launchClicked = false;
      var btn = document.getElementById('opBtnLaunchVote') || document.getElementById('opBtnToggleVote');
      btn.addEventListener('click', () => { window.__launchClicked = true; });
    });
    await page.locator('#testEditable').focus();
    await page.keyboard.press('l');
    const clicked = await page.evaluate(() => !!window.__launchClicked);
    expect(clicked).toBe(false);
  });

  test('shortcuts are SUPPRESSED when Ctrl/Cmd/Alt is held', async ({ page }) => {
    await gotoOperatorExec(page);
    await page.evaluate(() => {
      window.__launchClicked = false;
      var btn = document.getElementById('opBtnLaunchVote') || document.getElementById('opBtnToggleVote');
      btn.addEventListener('click', () => { window.__launchClicked = true; });
    });
    // browser may intercept Ctrl+L (focus address bar in real browser, but Playwright
    // dispatches the keypress to the page anyway). The assertion is: the keybindings
    // module must NOT treat Ctrl+L as a shortcut — i.e. the launch button must not be
    // clicked just because the L key was part of a modifier combo.
    await page.keyboard.press('Control+l');
    const clicked = await page.evaluate(() => !!window.__launchClicked);
    expect(clicked).toBe(false);
  });

  // F-4: integration smoke test against a REAL seeded meeting (no stub buttons).
  // The other tests use injected stubs for isolation; this one verifies the production
  // operator view actually wires `aria-keyshortcuts="L"` on the real toggle button and
  // that pressing L on the live page reaches a real button click.
  // STOP — do NOT retry blindly within this plan if it fails twice (CLAUDE.md test budget).
  //
  // Reactivated by Plan 03.1 (TEST-V24-01) — the seed-meeting helper now drives the
  // dev-only `/api/v1/test/seed-meeting` endpoint to materialise a fixture meeting.
  test('@integration L on real operator view reaches the production toggle button', async ({ page, request }) => {
    const { seedRunningMeeting } = require('../helpers/seed-meeting');

    await loginAsOperator(page);
    const meeting = await seedRunningMeeting(request, { motionsCount: 1 });
    await page.goto(`/operator?meeting=${meeting.id}`);
    // Real production button must exist with aria-keyshortcuts (Plan 01.3 Task 1 step 3 / B-2).
    const toggleBtn = page.locator('#opBtnToggleVote[aria-keyshortcuts="L"]');
    await expect(toggleBtn).toBeVisible();
    await page.evaluate(() => {
      var btn = document.getElementById('opBtnToggleVote');
      btn.addEventListener('click', () => { window.__realLaunchClicked = true; });
    });
    await page.keyboard.press('l');
    const clicked = await page.evaluate(() => !!window.__realLaunchClicked);
    expect(clicked).toBe(true);
  });

});
