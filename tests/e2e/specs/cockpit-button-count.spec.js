// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * E2E v2.4 Phase 1 / COCKPIT-V24-01
 *
 * Garde permanente : ≤25 cliquables visibles dans la console opérateur en
 * mode exec sur viewport ≥1024px, sur les 3 états UI principaux (idle /
 * voting / proclaiming).
 *
 * Source décisions : .planning/phases/01-cockpit-polish-hygiene/01-CONTEXT.md
 *   - D-01 : compte strict, viewport ≥1024px, mode=exec
 *   - D-04 : contextual-only via #viewExec[data-vote-state]
 *   - D-06 : critical-path D-06 toujours visible (Lancer/Fermer/Proclaim)
 *   - D-10 : test gardien régression
 *
 * Budget CLAUDE.md : max 3 exécutions Playwright/plan, pas de retry aveugle.
 *
 * NOTE : ce spec est destiné à être exécuté sur la machine dev / CI où
 * Playwright + Chromium sont disponibles. Sandbox d'origine n'a pas le
 * runtime — voir 01.1-SUMMARY.md "Screenshot deferred".
 */

const VIEWPORT = { width: 1280, height: 720 };
const MAX_VISIBLE = 25;

// Sélecteur compte D-01 : tout cliquable visible dans le viewport.
// On exclut intentionnellement les inputs textuels (pas un cliquable au sens
// "action atteignable"), mais on inclut <select>, <button>, anchors btn,
// op-tab, agenda items, mode-switch, nav-group, nav-item, ainsi que le
// bouton sentinel ag-popover trigger via attribute slot.
const VISIBLE_CLICKABLE = [
  'button:visible',
  'a.btn:visible',
  'a.nav-item:visible',
  '.nav-group:visible',
  '.tab-btn:visible',
  '.op-tab:visible',
  '.op-agenda-item:visible',
  '.mode-switch-btn:visible',
  'select:visible',
  '[role="button"]:visible',
].join(', ');

async function gotoOperatorExecLive(page, opts) {
  opts = opts || {};
  await loginAsOperator(page);
  await page.setViewportSize(VIEWPORT);
  // Reset localStorage so sidebar default-collapse seed (D-05) takes effect
  await page.addInitScript(() => {
    try {
      // Clear only sidebar + disclosure keys; keep auth/session.
      localStorage.removeItem('ag-vote-sidebar-groups');
      Object.keys(localStorage).forEach(k => {
        if (k.startsWith('agOperatorDisclosure:')) localStorage.removeItem(k);
      });
    } catch (e) {}
  });
  await page.goto('/operator');

  // Force exec view + meeting-live state. We don't depend on a real seeded
  // motion — the test asserts on the structural count, not on backend data.
  await page.evaluate((voteState) => {
    var view = document.getElementById('viewExec');
    var setupView = document.getElementById('viewSetup');
    if (setupView) setupView.hidden = true;
    if (view) {
      view.hidden = false;
      view.setAttribute('data-vote-state', voteState);
    }

    // Show meeting bar actions row (normally toggled by meeting selection)
    var actions = document.getElementById('meetingBarActions');
    if (actions) actions.hidden = false;

    // Show sticky action bar (normally hidden until refreshExecView)
    var actionBar = document.getElementById('opActionBar');
    if (actionBar) actionBar.hidden = false;

    // Hide the prep tabsNav (operator-tabs.js setMode('exec') does this in prod)
    var tabsNav = document.getElementById('tabsNav');
    if (tabsNav) tabsNav.hidden = true;

    // Hide noMeetingState placeholder
    var noMeeting = document.getElementById('noMeetingState');
    if (noMeeting) noMeeting.hidden = true;

    // Stub a 4-motion agenda so the count is deterministic
    var list = document.getElementById('opAgendaList');
    var empty = document.getElementById('opAgendaEmpty');
    if (list && empty) {
      empty.hidden = true;
      list.hidden = false;
      list.innerHTML = '';
      for (var i = 1; i <= 4; i++) {
        var li = document.createElement('li');
        li.className = 'op-agenda-item' + (i === 1 ? ' current' : ' pending');
        li.setAttribute('role', 'button');
        li.setAttribute('tabindex', '0');
        li.setAttribute('data-motion-id', 'm' + i);
        li.textContent = 'Résolution ' + i;
        list.appendChild(li);
      }
    }

    window.O = window.O || {};
    window.O.currentMode = 'exec';
    window.O.currentMeetingStatus = 'live';
  }, opts.voteState || 'idle');

  // Allow any post-render disclosure init / mutation observer to run
  await page.waitForTimeout(150);
}

test.describe('@cockpit-v2.4 cockpit button count', () => {

  test('≤25 cliquables visibles — état idle', async ({ page }) => {
    await gotoOperatorExecLive(page, { voteState: 'idle' });
    const count = await page.locator(VISIBLE_CLICKABLE).count();
    // Diagnostic : enregistrer le compte exact pour audit-régression
    test.info().annotations.push({ type: 'count.idle', description: String(count) });
    expect(count, `idle state has ${count} clickables (cap ${MAX_VISIBLE})`).toBeLessThanOrEqual(MAX_VISIBLE);
  });

  test('≤25 cliquables visibles — état voting', async ({ page }) => {
    await gotoOperatorExecLive(page, { voteState: 'open' });
    const count = await page.locator(VISIBLE_CLICKABLE).count();
    test.info().annotations.push({ type: 'count.voting', description: String(count) });
    expect(count, `voting state has ${count} clickables (cap ${MAX_VISIBLE})`).toBeLessThanOrEqual(MAX_VISIBLE);
  });

  test('≤25 cliquables visibles — état proclaiming', async ({ page }) => {
    await gotoOperatorExecLive(page, { voteState: 'closed' });
    const count = await page.locator(VISIBLE_CLICKABLE).count();
    test.info().annotations.push({ type: 'count.proclaiming', description: String(count) });
    expect(count, `proclaiming state has ${count} clickables (cap ${MAX_VISIBLE})`).toBeLessThanOrEqual(MAX_VISIBLE);
  });

  test('critical-path D-06 toujours visible (sacred buttons)', async ({ page }) => {
    // Test critical-path sur les 3 états : Lancer/Fermer toujours, Clôturer
    // séance toujours, Proclaim visible quand vote-state=closed.
    for (const state of ['idle', 'open', 'closed']) {
      await gotoOperatorExecLive(page, { voteState: state });
      // Lancer / Fermer (toggle) toujours visible
      await expect(page.locator('#opBtnToggleVote'), `ToggleVote visible (${state})`).toBeVisible();
      // Clôturer la séance (header rouge) toujours visible
      await expect(page.locator('.op-exec-header-right #opBtnCloseSession'), `CloseSession visible (${state})`).toBeVisible();
      if (state === 'open') {
        await expect(page.locator('#execBtnCloseVote'), 'CloseVote visible during open').toBeVisible();
      }
      if (state === 'closed') {
        await expect(page.locator('#opBtnProclaim'), 'Proclaim visible during closed').toBeVisible();
      }
    }
  });

  test('disclosure persistence — open state survit au reload', async ({ page }) => {
    await gotoOperatorExecLive(page, { voteState: 'idle' });
    // Ouvrir le disclosure "Plus d'actions" du header
    await page.locator('#opHeaderActions > summary').click();
    await expect(page.locator('#opHeaderActions')).toHaveAttribute('open', '');
    // Reload — le state localStorage doit être restauré
    await page.reload();
    await page.evaluate(() => {
      var view = document.getElementById('viewExec');
      var setupView = document.getElementById('viewSetup');
      if (setupView) setupView.hidden = true;
      if (view) view.hidden = false;
    });
    await expect(page.locator('#opHeaderActions')).toHaveAttribute('open', '');
  });

});
