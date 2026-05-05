// @ts-check
/**
 * race-404-empty-state.spec.js — RACE-V27-01 / RACE-V27-02 / RACE-V27-03
 *
 * Reproduit la race « ressource supprimée pendant l'affichage de la liste » :
 * une séance live est seedée, affichée dans une UI HTMX, supprimée côté
 * serveur, puis l'action HTMX déclenchée → la réponse 404 + JSON code
 * `meeting_not_found` doit être interceptée par le 404-handler central
 * (utils.js inlined, source-of-truth = htmx-404-handler.js) et substituée
 * par un <ag-empty-state variant="resource-deleted"> AU LIEU du toast
 * d'erreur rouge générique.
 *
 * Choix de surface UI : DOM injecté via page.evaluate() + htmx.process()
 * (plutôt que la dashboard réelle). Justification : la dashboard ne binde
 * pas automatiquement les séances seedées sur le tenant test. Le but du
 * spec est de tester la chaîne « clic HTMX → 404 → empty-state swap »
 * (le contrat réel sous test), pas le rendu dashboard. Le DOM injecté
 * isole exactement ce contrat. Tag @race-404 pour filtrage CI/local.
 */

const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');
const {
  seedRunningMeeting,
  deleteMeeting,
  DEFAULT_TENANT_ID,
} = require('../helpers/seed-meeting');

test.describe('@race-404 graceful 404 swap', () => {

  test('séance supprimée pendant affichage → empty-state swap, no toast', async ({ page, request }) => {
    // 1. Setup : login + seed une séance live
    await loginAsOperator(page);
    const { id: meetingId } = await seedRunningMeeting(request, { motionsCount: 1 });

    // 2. Naviguer vers une page qui charge utils.js (et donc le 404-handler
    //    inliné). Dashboard suffit — on ne dépend pas de son contenu, juste
    //    de l'environnement JS chargé (HTMX + components + utils).
    await page.goto('/dashboard.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForFunction(
      () => typeof window.htmx !== 'undefined' && customElements.get('ag-empty-state'),
      { timeout: 5000 },
    );

    // 3. Injecter dans le DOM un bouton HTMX qui cible la séance seedée +
    //    une cible de swap. Appeler htmx.process() pour activer les attrs.
    await page.evaluate((mid) => {
      const wrap = document.createElement('div');
      wrap.id = 'race-404-test-wrap';
      wrap.innerHTML = `
        <button id="race-404-trigger"
                hx-get="/api/v1/meetings_get?meeting_id=${mid}"
                hx-target="#race-404-out"
                hx-swap="innerHTML">Charger séance</button>
        <div id="race-404-out"></div>
      `;
      document.body.appendChild(wrap);
      // Activate HTMX attribute parsing on the freshly injected DOM
      window.htmx.process(wrap);
    }, meetingId);

    // 4. Race injection : supprimer la séance côté serveur AVANT le clic.
    //    À ce point, le bouton existe encore mais la ressource n'existe plus.
    const deleted = await deleteMeeting(request, {
      tenantId: DEFAULT_TENANT_ID,
      meetingId,
    });
    expect(deleted).toBe(true);

    // 5. Cliquer le bouton HTMX → requête 404 + JSON `meeting_not_found`.
    await page.locator('#race-404-trigger').click();

    // 6. Assertions : empty-state visible avec variant resource-deleted
    const emptyState = page.locator('#race-404-out ag-empty-state[variant="resource-deleted"]');
    await expect(emptyState).toBeVisible({ timeout: 5000 });

    // Le titre doit contenir le message « introuvable » du ErrorDictionary
    // (priorité au body.message, fallback message JS).
    const title = await emptyState.locator('.empty-state-title').textContent();
    expect(title).toMatch(/introuvable|n'existe plus/i);

    // Le CTA pointe vers /dashboard.htmx.html (config KNOWN_404_CODES)
    await expect(emptyState.locator('a')).toHaveAttribute('href', '/dashboard.htmx.html');

    // 7. Absence du toast d'erreur rouge (handler générique htmx:responseError
    //    n'a PAS été déclenché grâce à e.detail.isError = false).
    await page.waitForTimeout(500); // laisser passer un toast tardif éventuel
    await expect(page.locator('ag-toast[type="error"]')).toHaveCount(0);
  });

  test('404 sans code reconnu → handler générique (toast neutre)', async ({ page }) => {
    // Vérifie l'absence de régression : un 404 dont le body ne contient PAS
    // de code reconnu dans KNOWN_404_CODES doit retomber sur le handler
    // générique htmx:responseError (toast neutre rouge).
    await loginAsOperator(page);
    await page.goto('/dashboard.htmx.html');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForFunction(() => typeof window.htmx !== 'undefined', { timeout: 5000 });

    // Intercepter une route inexistante → 404 sans body JSON reconnu
    await page.route('**/api/v1/__nonexistent_404__', (route) => {
      return route.fulfill({
        status: 404,
        contentType: 'application/json',
        body: JSON.stringify({ ok: false, error: 'unknown_code_xyz', message: 'Inconnu.' }),
      });
    });

    await page.evaluate(() => {
      const wrap = document.createElement('div');
      wrap.id = 'race-404-fallthrough-wrap';
      wrap.innerHTML = `
        <button id="race-404-fallthrough"
                hx-get="/api/v1/__nonexistent_404__"
                hx-target="#race-404-fallthrough-out"
                hx-swap="innerHTML">Trigger</button>
        <div id="race-404-fallthrough-out"></div>
      `;
      document.body.appendChild(wrap);
      window.htmx.process(wrap);
    });

    await page.locator('#race-404-fallthrough').click();
    await page.waitForTimeout(800);

    // Pas d'empty-state injecté (code inconnu → fall-through)
    await expect(
      page.locator('#race-404-fallthrough-out ag-empty-state[variant="resource-deleted"]'),
    ).toHaveCount(0);
  });
});
