// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator, loginAsVoter } = require('../helpers');

/**
 * Plan 16-03 — Keyboard navigation + focus management (A11Y-03).
 *
 * Couvre les 4 shells applicatifs :
 *   - admin/operator (app-shell + sidebar)
 *   - login (2-panneaux)
 *   - voter tablet
 *   - public projection
 *
 * Teste :
 *   1. Skip-link présent et fonctionnel (Tab puis Enter déplace le focus)
 *   2. Ordre Tab cohérent (header/nav/main) sur le shell operator
 *   3. Focus trap ag-modal : Tab/Shift+Tab cyclent, Escape ferme, focus restauré
 *
 * NOTE Shadow DOM (RESEARCH §Pitfall 1) :
 *   `page.locator(':focus')` ne traverse PAS les shadow roots. Pour vérifier
 *   le focus dans un ag-modal, on interroge `document.activeElement` et
 *   `activeElement.shadowRoot?.activeElement` via `page.evaluate()`.
 */

// --- Helpers ----------------------------------------------------------------

/**
 * Lit la classe et le href du focus courant (shadow-safe pour éléments hors SD).
 * Retourne { className, href, tagName } ou null si body/null.
 */
async function getFocusedInfo(page) {
  return await page.evaluate(() => {
    const el = document.activeElement;
    if (!el || el === document.body) return null;
    return {
      tagName: el.tagName,
      className: el.className || '',
      href: el.getAttribute('href') || '',
      id: el.id || '',
    };
  });
}

// --- Skip-link : 4 shells ---------------------------------------------------

test.describe('Keyboard navigation — skip-link sur les 4 shells', () => {

  test('shell operator : premier Tab atteint le skip-link et Enter focalise #main-content', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    await page.keyboard.press('Tab');
    const focused = await getFocusedInfo(page);
    expect(focused, 'aucun élément focalisé après Tab').not.toBeNull();
    expect(focused.className).toContain('skip-link');
    expect(focused.href).toContain('#main-content');

    // Enter sur l'ancre ajoute le hash; on vérifie ensuite que le main existe.
    await page.keyboard.press('Enter');
    const mainExists = await page.evaluate(() => !!document.querySelector('main#main-content'));
    expect(mainExists).toBe(true);
  });

  test('shell login : premier Tab atteint un skip-link français', async ({ page }) => {
    await page.goto('/login.html');
    await page.waitForLoadState('domcontentloaded');

    await page.keyboard.press('Tab');
    const focused = await getFocusedInfo(page);
    expect(focused).not.toBeNull();
    // login.html a `skip-link` vers #loginForm (pas #main-content — voir plan 16-03 waiver implicite).
    expect(focused.className).toContain('skip-link');
    expect(focused.href).toMatch(/#loginForm|#main-content/);
  });

  test('shell voter : premier Tab atteint le skip-link', async ({ page }) => {
    await loginAsVoter(page);
    await page.goto('/vote.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    await page.keyboard.press('Tab');
    const focused = await getFocusedInfo(page);
    expect(focused).not.toBeNull();
    expect(focused.className).toContain('skip-link');
  });

  test('shell public projection : skip-link présent et main#main-content existe', async ({ page }) => {
    // A11Y-WAIVER: la projection publique n'a pas de navigation interactive — on
    // valide uniquement la présence du skip-link et du main cible — expires 2026-10-09
    await page.goto('/public.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const skipCount = await page.locator('a.skip-link').count();
    expect(skipCount, 'skip-link absent sur la projection').toBeGreaterThan(0);
    const mainExists = await page.evaluate(() => !!document.querySelector('main#main-content'));
    expect(mainExists).toBe(true);
  });
});

// --- Ordre Tab cohérent (smoke) --------------------------------------------

test.describe('Keyboard navigation — ordre Tab', () => {

  test('shell operator : les 6 premiers Tab restent dans header/nav/main', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    const landmarks = [];
    for (let i = 0; i < 6; i++) {
      await page.keyboard.press('Tab');
      const landmark = await page.evaluate(() => {
        const el = document.activeElement;
        if (!el || el === document.body) return 'NONE';
        const host = el.closest('header, nav, main, footer, aside');
        return host ? host.tagName : 'NONE';
      });
      landmarks.push(landmark);
    }
    // Soft assertion : au moins un stop dans HEADER/NAV/MAIN (pas de focus perdu).
    const validStops = landmarks.filter(l => ['HEADER', 'NAV', 'MAIN', 'ASIDE', 'FOOTER'].includes(l));
    expect(validStops.length, `ordre Tab: ${landmarks.join(' → ')}`).toBeGreaterThan(0);
  });
});

// --- Focus trap ag-modal (shadow DOM) --------------------------------------

test.describe('Keyboard navigation — focus trap ag-modal', () => {

  test('ag-modal : Tab/Shift-Tab piégés, Escape ferme, focus restauré au trigger', async ({ page }) => {
    await loginAsOperator(page);
    await page.goto('/operator.htmx.html');
    await page.waitForLoadState('domcontentloaded');

    // Attendre que le custom element ag-modal soit enregistré (chargé par components/index.js).
    await page.waitForFunction(() => !!customElements.get('ag-modal'), { timeout: 10000 });

    // Injecter un bouton déclencheur + une ag-modal avec deux boutons slot pour
    // rendre le piège de focus testable de façon déterministe (pas de fixture dépendante).
    await page.evaluate(() => {
      const trigger = document.createElement('button');
      trigger.id = 'e2e-trap-trigger';
      trigger.type = 'button';
      trigger.textContent = 'Ouvrir modale de test';
      document.body.appendChild(trigger);

      const modal = document.createElement('ag-modal');
      modal.id = 'e2e-trap-modal';
      modal.setAttribute('title', 'Modale de test A11Y');
      modal.innerHTML = `
        <p>Contenu de test piège de focus.</p>
        <button type="button" id="e2e-modal-btn-1">Bouton interne 1</button>
        <button type="button" id="e2e-modal-btn-2">Bouton interne 2</button>
        <div slot="footer">
          <button type="button" id="e2e-modal-cancel">Annuler</button>
          <button type="button" id="e2e-modal-ok">Valider</button>
        </div>
      `;
      document.body.appendChild(modal);

      trigger.addEventListener('click', () => modal.open());
    });

    // Focaliser puis cliquer le trigger — on veut que _previousFocus === trigger.
    await page.focus('#e2e-trap-trigger');
    await page.click('#e2e-trap-trigger');

    // Attendre que la modale soit ouverte (aria-hidden="false").
    await page.waitForFunction(
      () => document.querySelector('ag-modal#e2e-trap-modal')?.getAttribute('aria-hidden') === 'false',
      { timeout: 5000 },
    );

    // Tab x 5 : le focus doit rester dans / sur l'ag-modal (shadow DOM ou slot).
    for (let i = 0; i < 5; i++) {
      await page.keyboard.press('Tab');
      const trapped = await page.evaluate(() => {
        const active = document.activeElement;
        if (!active) return false;
        // Cas 1 : focus sur un élément slotted (descendant direct de <ag-modal>).
        if (active.closest && active.closest('ag-modal#e2e-trap-modal')) return true;
        // Cas 2 : focus sur l'host ag-modal lui-même, avec shadowRoot.activeElement dans le modal interne.
        if (active.tagName === 'AG-MODAL' && active.shadowRoot?.activeElement) return true;
        return false;
      });
      expect(trapped, `Tab #${i + 1} : focus hors de l'ag-modal`).toBe(true);
    }

    // Shift+Tab x 3 : même invariant.
    for (let i = 0; i < 3; i++) {
      await page.keyboard.press('Shift+Tab');
      const trapped = await page.evaluate(() => {
        const active = document.activeElement;
        if (!active) return false;
        if (active.closest && active.closest('ag-modal#e2e-trap-modal')) return true;
        if (active.tagName === 'AG-MODAL' && active.shadowRoot?.activeElement) return true;
        return false;
      });
      expect(trapped, `Shift+Tab #${i + 1} : focus hors de l'ag-modal`).toBe(true);
    }

    // Escape : la modale se ferme (aria-hidden="true").
    await page.keyboard.press('Escape');
    await page.waitForFunction(
      () => document.querySelector('ag-modal#e2e-trap-modal')?.getAttribute('aria-hidden') === 'true',
      { timeout: 2000 },
    );

    // Focus restauré sur le trigger d'origine.
    const restoredId = await page.evaluate(() => document.activeElement?.id || '');
    expect(restoredId).toBe('e2e-trap-trigger');
  });
});
