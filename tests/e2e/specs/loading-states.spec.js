// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

/**
 * Plan 02-01 — Loading states (LOADING-V27-01/02/03).
 *
 * Verrouille les 3 primitives de loading state percu :
 *   1. Skeleton injecte dans la cible HTMX si swap > 300 ms.
 *   2. Anti-double-submit : 2 clics rapides sur un bouton submit = 1 requete.
 *   3. Optimistic UI : DOM mute avant la fin de la requete (rollback si erreur).
 *
 * Strategie : on monte un harnais HTML autonome via page.setContent() qui charge
 * le bundle reel (ag-spinner, ag-skeleton, loading-states.js) depuis la racine
 * Docker. Cela evite les dependances aux fixtures votant/operator (qui peuvent
 * varier entre seeds) et garde le test focalise sur le comportement front pur.
 *
 * Les chemins JS/CSS sont resolus via baseURL (Docker stack expose /assets/...).
 */

const HARNESS_HTML = `<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title>Loading states harness</title>
  <link rel="stylesheet" href="/assets/css/design-system.css" />
  <script type="module" src="/assets/js/components/index.js"></script>
  <script src="https://unpkg.com/htmx.org@1.9.12" defer></script>
  <script src="/assets/js/core/loading-states.js" defer></script>
</head>
<body>
  <h1>Harness</h1>

  <!-- Skeleton zone : HTMX target with 500ms delayed response -->
  <button id="trigger" hx-get="/__test__/slow" hx-target="#out" hx-swap="innerHTML">
    Charger
  </button>
  <div id="out" data-skeleton="card" data-skeleton-count="2">
    contenu initial
  </div>

  <!-- Native form with data-submit-spinner -->
  <form id="natForm" method="post" action="/__test__/submit" data-submit-spinner>
    <button id="natSubmit" type="submit">Envoyer</button>
  </form>

  <!-- Optimistic UI presence-style toggle -->
  <button id="toggle" type="button" aria-pressed="true">
    <span class="label">Present</span>
  </button>
  <script>
    // Intercept native form submit so we can count POST attempts without
    // navigating away from the harness page.
    document.addEventListener('DOMContentLoaded', function () {
      var form = document.getElementById('natForm');
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        // Count the submit attempt for the test assertion.
        window.__submitCount = (window.__submitCount || 0) + 1;
        // Simulate a slow async response: hold the button busy 600 ms then release.
        setTimeout(function () {
          var btn = form.querySelector('button[type=submit]');
          if (window.LoadingStates && btn) window.LoadingStates.exitSubmitState(btn);
        }, 600);
      }, false);

      // Optimistic toggle handler using LoadingStates.applyOptimistic.
      var btn = document.getElementById('toggle');
      btn.addEventListener('click', function () {
        var label = btn.querySelector('.label');
        var prev = label.textContent;
        var next = prev === 'Present' ? 'Absent' : 'Present';
        window.LoadingStates.applyOptimistic(
          btn,
          function () { label.textContent = next; btn.setAttribute('aria-pressed', next === 'Present' ? 'true' : 'false'); },
          function () { return new Promise(function (res) { setTimeout(res, 400); }); },
          function () { label.textContent = prev; }
        );
      });
    });
  </script>
</body>
</html>`;

test.describe('Loading states (Plan 02-01)', () => {
  test.describe.configure({ mode: 'serial' });

  test('skeleton apparait apres 300ms sur swap HTMX retarde (LOADING-V27-01)', async ({ page }) => {
    // Stub the slow endpoint with a 500ms delayed response.
    await page.route('**/__test__/slow', async (route) => {
      await new Promise((r) => setTimeout(r, 500));
      await route.fulfill({
        status: 200,
        contentType: 'text/html; charset=utf-8',
        body: '<p id="loaded">contenu charge</p>',
      });
    });

    await page.setContent(HARNESS_HTML, { waitUntil: 'networkidle' });

    // Sanity: ag-skeleton custom element is registered.
    const registered = await page.evaluate(() => !!customElements.get('ag-skeleton'));
    expect(registered).toBe(true);

    // Trigger HTMX swap.
    await page.click('#trigger');

    // After 350ms the skeleton must be in the DOM (swap delay > 300ms).
    await page.waitForTimeout(350);
    const skeletonCount = await page.locator('#out ag-skeleton').count();
    expect(skeletonCount).toBeGreaterThanOrEqual(1);
    const ariaBusy = await page.locator('#out').getAttribute('aria-busy');
    expect(ariaBusy).toBe('true');

    // Wait for the response to land and assert the skeleton is replaced.
    await page.locator('#out #loaded').waitFor({ timeout: 2000 });
    const skeletonAfter = await page.locator('#out ag-skeleton').count();
    expect(skeletonAfter).toBe(0);
  });

  test('double-clic submit envoie 1 seule requete (LOADING-V27-02)', async ({ page }) => {
    await page.setContent(HARNESS_HTML, { waitUntil: 'networkidle' });

    // Wait for LoadingStates global to be ready.
    await page.waitForFunction(() => !!window.LoadingStates);

    const btn = page.locator('#natSubmit');
    // Double-click as fast as possible.
    await btn.click();
    await btn.click({ force: true });

    // Give the harness 100ms to register both attempts.
    await page.waitForTimeout(100);

    const count = await page.evaluate(() => window.__submitCount || 0);
    expect(count).toBe(1);

    // Bouton verrouille pendant la requete.
    expect(await btn.getAttribute('data-submitting')).toBe('true');
    expect(await btn.isDisabled()).toBe(true);

    // Apres la fin de la requete simulee (600ms), le bouton est reactif.
    await page.waitForTimeout(700);
    expect(await btn.isDisabled()).toBe(false);
    expect(await btn.getAttribute('data-submitting')).toBeNull();
  });

  test('toggle optimistic change le label instantanement (LOADING-V27-03)', async ({ page }) => {
    await page.setContent(HARNESS_HTML, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => !!window.LoadingStates);

    const label = page.locator('#toggle .label');
    expect(await label.textContent()).toBe('Present');

    // Click and IMMEDIATELY assert the label changed (no awaiting the 400ms request).
    await page.click('#toggle');
    // Synchronously read the label : optimistic mutation runs in the same tick.
    const after = await label.textContent();
    expect(after).toBe('Absent');

    // .is-pending classe presente pendant la requete.
    const pendingNow = await page.locator('#toggle.is-pending').count();
    expect(pendingNow).toBe(1);

    // Apres la fin du faux request (400ms), is-pending disparait.
    await page.waitForTimeout(500);
    const pendingAfter = await page.locator('#toggle.is-pending').count();
    expect(pendingAfter).toBe(0);
  });
});
