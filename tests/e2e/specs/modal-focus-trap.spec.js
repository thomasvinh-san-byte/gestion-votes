// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAsOperator } = require('../helpers');

/**
 * E2E v2.3 / Phase 4 / MODAL-02
 *
 * Filet permanent contre la régression du focus trap sur `<ag-modal>`.
 *
 * Contrat vérifié :
 *  1. Tab cycle reste à l'intérieur de la modale (focus piégé)
 *  2. Shift+Tab cycle inverse reste à l'intérieur
 *  3. Escape ferme la modale (aria-hidden bascule à "true")
 *  4. Le focus est restauré sur l'élément déclencheur après fermeture
 *
 * Stratégie de test :
 *  - Un test synthétique (modale injectée à la volée) garantit le contrat
 *    de base de `<ag-modal>` indépendamment de tout fixtures applicative.
 *  - Un test d'intégration sur une vraie modale migrée par Plan 04.2
 *    (`exportsModal` sur archives.htmx.html, déclenchée par `#btnExportsModal`)
 *    couvre le bout-en-bout production : trigger réel → composant réel → focus
 *    restoration sur le bouton réel.
 *
 * NOTE Shadow DOM : `<ag-modal>` utilise un shadow DOM. `document.activeElement`
 * retourne le host `<ag-modal>` quand le focus est dans le shadow root — il
 * faut suivre la chaîne `shadowRoot.activeElement` pour vérifier le contenu réel.
 *
 * Test budget : CLAUDE.md mandate max 3 exécutions Playwright par plan.
 * Si un cas échoue 2 fois de suite, STOP et remonter à l'utilisateur.
 */

/**
 * Vérifie que le focus est piégé "dans" un `<ag-modal>` donné (id),
 * en couvrant les 3 cas du shadow DOM :
 *   - élément slotted (descendant direct de l'host)
 *   - host `<ag-modal>` avec `shadowRoot.activeElement` non vide (close button)
 *   - chaîne shadowRoot.activeElement.shadowRoot.activeElement (web components nested)
 */
async function isFocusTrapped(page, modalId) {
  return await page.evaluate((id) => {
    const modal = document.getElementById(id);
    if (!modal) return false;
    let active = document.activeElement;
    if (!active) return false;
    // Cas 1 : focus sur un élément slotted (descendant direct de <ag-modal>).
    if (active.closest && active.closest(`#${id}`)) return true;
    // Cas 2 : focus sur l'host ag-modal avec shadowRoot.activeElement dans le modal interne.
    if (active === modal && active.shadowRoot && active.shadowRoot.activeElement) return true;
    // Cas 3 : descendre la chaîne shadow pour les composants imbriqués.
    while (active && active.shadowRoot && active.shadowRoot.activeElement) {
      active = active.shadowRoot.activeElement;
      if (active.closest && active.closest(`#${id}`)) return true;
    }
    return false;
  }, modalId);
}

test.describe('@a11y-v2.3 @modal-02 focus trap <ag-modal>', () => {

  test.describe('contract synthétique (modale injectée)', () => {

    /**
     * Setup commun : ouvre operator.htmx.html (qui charge ag-modal.js via
     * components/index.js), puis injecte un trigger + une <ag-modal> de test
     * avec plusieurs éléments focusables pour rendre le piège déterministe.
     */
    async function setupSyntheticModal(page) {
      await loginAsOperator(page);
      await page.goto('/operator.htmx.html');
      await page.waitForLoadState('domcontentloaded');
      await page.waitForFunction(() => !!customElements.get('ag-modal'), { timeout: 10000 });

      await page.evaluate(() => {
        const trigger = document.createElement('button');
        trigger.id = 'mft-trigger';
        trigger.type = 'button';
        trigger.textContent = 'Ouvrir modale focus-trap';
        document.body.appendChild(trigger);

        const modal = document.createElement('ag-modal');
        modal.id = 'mft-modal';
        modal.setAttribute('title', 'Modale focus-trap');
        modal.innerHTML = `
          <p>Contenu de test pour le piège de focus.</p>
          <button type="button" id="mft-btn-1">Bouton 1</button>
          <input type="text" id="mft-input" />
          <button type="button" id="mft-btn-2">Bouton 2</button>
          <div slot="footer">
            <button type="button" id="mft-cancel">Annuler</button>
            <button type="button" id="mft-confirm">Confirmer</button>
          </div>
        `;
        document.body.appendChild(modal);

        trigger.addEventListener('click', () => modal.open());
      });

      await page.focus('#mft-trigger');
      await page.click('#mft-trigger');

      // Attendre que la modale soit ouverte (aria-hidden="false").
      await page.waitForFunction(
        () => document.getElementById('mft-modal')?.getAttribute('aria-hidden') === 'false',
        { timeout: 5000 },
      );
    }

    test('Tab cycle reste à l\'intérieur de la modale', async ({ page }) => {
      await setupSyntheticModal(page);

      // Tab x 8 : le focus doit rester dans / sur l'ag-modal après chaque Tab.
      // 8 itérations > nombre d'éléments focusables (5 slot + 1 close shadow) pour
      // forcer au moins un cycle complet et exposer une fuite éventuelle.
      for (let i = 0; i < 8; i++) {
        await page.keyboard.press('Tab');
        const trapped = await isFocusTrapped(page, 'mft-modal');
        expect(trapped, `Tab #${i + 1} : le focus est sorti de la modale`).toBe(true);
      }

      // Cleanup pour éviter de polluer les autres tests.
      await page.keyboard.press('Escape');
    });

    test('Shift+Tab cycle inverse reste à l\'intérieur de la modale', async ({ page }) => {
      await setupSyntheticModal(page);

      // Shift+Tab x 8 : même invariant en sens inverse.
      for (let i = 0; i < 8; i++) {
        await page.keyboard.press('Shift+Tab');
        const trapped = await isFocusTrapped(page, 'mft-modal');
        expect(trapped, `Shift+Tab #${i + 1} : le focus est sorti de la modale`).toBe(true);
      }

      await page.keyboard.press('Escape');
    });

    test('Escape ferme la modale', async ({ page }) => {
      await setupSyntheticModal(page);

      // Pré-condition : modale ouverte.
      const beforeHidden = await page.evaluate(
        () => document.getElementById('mft-modal')?.getAttribute('aria-hidden'),
      );
      expect(beforeHidden).toBe('false');

      // Action : Escape.
      await page.keyboard.press('Escape');

      // Post-condition : aria-hidden bascule à "true".
      await page.waitForFunction(
        () => document.getElementById('mft-modal')?.getAttribute('aria-hidden') === 'true',
        { timeout: 2000 },
      );
      const afterHidden = await page.evaluate(
        () => document.getElementById('mft-modal')?.getAttribute('aria-hidden'),
      );
      expect(afterHidden).toBe('true');
    });

    test('focus restauré sur le trigger après fermeture par Escape', async ({ page }) => {
      await setupSyntheticModal(page);

      await page.keyboard.press('Escape');
      await page.waitForFunction(
        () => document.getElementById('mft-modal')?.getAttribute('aria-hidden') === 'true',
        { timeout: 2000 },
      );

      // Le focus doit être de retour sur le trigger d'origine.
      const restoredId = await page.evaluate(() => document.activeElement?.id || '');
      expect(restoredId).toBe('mft-trigger');
    });

    test('focus restauré sur le trigger après clic sur le bouton de fermeture (X)', async ({ page }) => {
      await setupSyntheticModal(page);

      // Clic sur le bouton X dans le shadow root (rendu par <ag-modal>).
      const closed = await page.evaluate(() => {
        const modal = document.getElementById('mft-modal');
        if (!modal || !modal.shadowRoot) return false;
        const closeBtn = modal.shadowRoot.querySelector('.modal-close');
        if (!closeBtn) return false;
        closeBtn.click();
        return true;
      });
      expect(closed, 'bouton de fermeture introuvable dans le shadow root').toBe(true);

      await page.waitForFunction(
        () => document.getElementById('mft-modal')?.getAttribute('aria-hidden') === 'true',
        { timeout: 2000 },
      );

      const restoredId = await page.evaluate(() => document.activeElement?.id || '');
      expect(restoredId).toBe('mft-trigger');
    });
  });

  test.describe('intégration sur une modale réelle migrée (Plan 04.2)', () => {

    /**
     * exportsModal (archives.htmx.html) : trigger statique `#btnExportsModal`,
     * pas de fixture applicative requise (l'ouverture du modal n'attend pas de
     * payload réseau spécifique). C'est la modale la plus déterministe à tester
     * en bout-en-bout parmi les 7 modales migrées par Plan 04.2.
     */
    test('exportsModal (archives) — Escape ferme + focus restauré sur #btnExportsModal', async ({ page }) => {
      await loginAsOperator(page);
      await page.goto('/archives.htmx.html');
      await page.waitForLoadState('domcontentloaded');
      await page.waitForFunction(() => !!customElements.get('ag-modal'), { timeout: 10000 });

      // Vérifier que le trigger porte bien aria-haspopup="dialog" (MODAL-03 / Plan 04.2).
      const trigger = page.locator('#btnExportsModal');
      await expect(trigger).toBeVisible();
      await expect(trigger).toHaveAttribute('aria-haspopup', 'dialog');

      // Vérifier que la modale réelle est bien une <ag-modal> (Plan 04.2 migration).
      const modalIsAgModal = await page.evaluate(() => {
        const m = document.getElementById('exportsModal');
        return !!m && m.tagName.toLowerCase() === 'ag-modal';
      });
      expect(modalIsAgModal, 'exportsModal n\'est pas une <ag-modal>').toBe(true);

      // Mémoriser le focus initial sur le trigger pour vérifier la restauration.
      await trigger.focus();
      await trigger.click();

      // Attendre l'ouverture (aria-hidden="false").
      await page.waitForFunction(
        () => document.getElementById('exportsModal')?.getAttribute('aria-hidden') === 'false',
        { timeout: 5000 },
      );

      // Le focus doit être passé à l'intérieur de la modale (focus initial trap).
      const trappedAfterOpen = await isFocusTrapped(page, 'exportsModal');
      expect(trappedAfterOpen, 'focus pas piégé dans exportsModal après ouverture').toBe(true);

      // Quelques Tab pour vérifier que ça reste piégé sur la modale réelle.
      for (let i = 0; i < 4; i++) {
        await page.keyboard.press('Tab');
        const trapped = await isFocusTrapped(page, 'exportsModal');
        expect(trapped, `Tab #${i + 1} : focus sorti de exportsModal`).toBe(true);
      }

      // Escape ferme.
      await page.keyboard.press('Escape');
      await page.waitForFunction(
        () => document.getElementById('exportsModal')?.getAttribute('aria-hidden') === 'true',
        { timeout: 2000 },
      );

      // Focus restauré sur le trigger d'origine (#btnExportsModal).
      const restoredId = await page.evaluate(() => document.activeElement?.id || '');
      expect(restoredId).toBe('btnExportsModal');
    });
  });
});
