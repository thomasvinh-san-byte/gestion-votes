# Phase 16: Accessibility Deep Audit — Research

**Researched:** 2026-04-09
**Domain:** Web accessibility (WCAG 2.1 AA) via axe-core + Playwright on HTMX pages
**Confidence:** HIGH (all findings verified in the local codebase or against canonical axe/Playwright docs)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01** — Paramétriser `accessibility.spec.js` via `PAGES = [{ path, loginFn, requiredLocator }]` et générer un test par entrée. Couvre les 21 pages sans duplication.
- **D-02** — Les 7 pages déjà couvertes (login, dashboard, meetings, members, operator, settings, audit) restent dans le même fichier paramétrisé — pas de régression.
- **D-03** — Ajouter 14 pages : admin, analytics, archives, docs, email-templates, help, hub, postsession, public, report, trust, users, validate, vote, wizard.
- **D-04** — `axeAudit.js` garde `disableRules: ['color-contrast']` pour le runner structurel.
- **D-05** — Audit contraste dédié via `tests/e2e/specs/contrast-audit.spec.js` (one-shot, active color-contrast, produit un JSON), exécuté manuellement, pas en CI.
- **D-06** — Résultats contraste documentés dans `v1.3-A11Y-REPORT.md` section "Contrast audit".
- **D-07** — Fix par type de violation (pas page-par-page) : batch par rule-id.
- **D-08** — Les WIP déjà diagnostiqués (`SettingsController.php`, `operator.htmx.html`, `settings.css`, `settings.js`, `axeAudit.js`, `accessibility.spec.js`) servent de seed pour le plan 16-01 — à committer en début d'exécution.
- **D-09** — Waivers inline dans les specs : `// A11Y-WAIVER: <rule-id> — <raison> — expires YYYY-MM-DD`.
- **D-10** — `axeAudit.js` accepte un paramètre optionnel `extraDisabledRules` pour waivers par page.
- **D-11** — Tous les waivers listés dans `v1.3-A11Y-REPORT.md` section "Waivers".
- **D-12** — Nouveau `tests/e2e/specs/keyboard-nav.spec.js` teste : skip-link, ordre Tab header/nav/main, focus trap modales (Tab boucle, Escape ferme), focus restauré après fermeture.
- **D-13** — Audit manuel ponctuel des flows critiques (login → vote → logout) documenté dans le rapport, pas de test automatisé exhaustif.
- **D-14** — `v1.3-A11Y-REPORT.md` structure en 7 sections : Scope & méthodo / Résultats par page / Contraste / Keyboard & focus / Waivers / Conformance statement / Annexe commandes.

### Claude's Discretion

- Choix précis de la regex/sélecteur pour détecter les layouts shells dans `keyboard-nav.spec.js`.
- Format exact du JSON produit par `contrast-audit.spec.js`.
- Template markdown du rapport (structure D-14 respectée).
- Ordre des batches de fix dans le plan d'exécution (probablement par rule-id count décroissant).

### Deferred Ideas (OUT OF SCOPE)

- Screen reader manual testing (NVDA / VoiceOver / JAWS).
- Internationalisation (i18n).
- Conformance AAA (contraste 7:1, etc.).
- Audit a11y des emails HTML transactionnels.
- Refonte keyboard shortcuts (no-keyboard-shortcuts scope limit).

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| A11Y-01 | axe-core scan complet sur les 21 pages applicatives | Page inventory + auth mapping ci-dessous ; pattern de paramétrisation Playwright (section "Parametrized Axe Tests"). |
| A11Y-02 | Fix toutes les violations critical + serious détectées | Taxonomie batch-by-rule (section "Expected Violation Families") + WIP seed déjà diagnostiqué. |
| A11Y-03 | Conformance WCAG 2.1 AA documentée (aria-labels, color contrast, keyboard nav, focus management) | Keyboard nav spec (skip-link + focus trap `ag-modal`), contrast-audit one-shot, report template. |

</phase_requirements>

## Summary

Le terrain est déjà bien préparé : les 21 pages HTMX ont **toutes** un skip-link (vérifié par grep), le composant modal partagé `public/assets/js/components/ag-modal.js` implémente déjà `role="dialog" aria-modal="true"`, focus trap Tab/Shift-Tab, Escape pour fermer, restauration du focus précédent. Les helpers de login (`loginAsOperator/Admin/Voter/President`) reposent sur une injection de cookies via `.auth/{role}.json` — pas de risque de rate-limit sur 21+ tests parallèles. Le runner `axeAudit.js` existant utilise `@axe-core/playwright@4.10.2` (≥ 4.x) avec tags `wcag2a/wcag2aa` et désactive déjà `color-contrast` ; les messages d'erreur WIP listent déjà les 5 premiers noeuds fautifs.

Ce qui manque : (1) paramétrisation de la liste `PAGES` pour exécuter le même assert sur 21 pages avec `loginFn` variable, (2) `extraDisabledRules` en paramètre d'`axeAudit()` pour waivers ciblés, (3) `keyboard-nav.spec.js` pour skip-link + focus trap sur le custom element `ag-modal` (attention : shadow DOM), (4) `contrast-audit.spec.js` one-shot, (5) le rapport markdown final.

**Primary recommendation:** suivre D-07 (batch by rule-id) dans l'ordre observé après le run initial ; le WIP déjà diagnostiqué (`role="status"`, `aria-label` sur boutons icon, `[hidden]` fix) couvre probablement déjà 3-4 règles serveuses. Commiter le WIP en plan 16-01, puis lancer le scan complet pour établir la baseline réelle.

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `@axe-core/playwright` | 4.10.2 (déjà installé) | Runner axe intégré à Playwright | Maintenu par Deque, API stable `AxeBuilder`, support `withTags`/`disableRules`/`include`/`exclude`. |
| `@playwright/test` | 1.59.1 (déjà installé) | Framework e2e | Déjà le standard projet, supporte génération de tests par boucle `for` sur array. |
| `axe-core` (transitive) | ~4.10 | Moteur de règles WCAG | Bundled avec `@axe-core/playwright`, implémente WCAG 2.0/2.1 A/AA/AAA. |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| (aucun ajout requis) | — | — | Le parsing des résultats se fait en JS natif ; pour le rapport markdown on produit le MD à la main ou via un petit script `fs.writeFileSync` en fin de spec. |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `@axe-core/playwright` | `playwright-lighthouse` | Lighthouse couvre perf+a11y mais rapport moins ciblé, plus lent. Out-of-scope per REQUIREMENTS v1.3. |
| Runner structural + one-shot contrast | Exécuter color-contrast dans le runner principal | Faux-positifs bloquants car tokens tunés à part (D-04). Rejeté. |
| Pa11y / pa11y-ci | — | Stack Node alternative, mais doublon avec axe ; pas de bénéfice. |

**Installation :** aucune install requise — toutes les deps sont déjà dans `tests/e2e/package.json`.

## Architecture Patterns

### Recommended file layout

```
tests/e2e/
├── helpers/
│   └── axeAudit.js              # extend: accept extraDisabledRules param
├── specs/
│   ├── accessibility.spec.js    # parametrized — 21 pages via PAGES array
│   ├── contrast-audit.spec.js   # NEW — one-shot color-contrast, JSON out
│   └── keyboard-nav.spec.js     # NEW — skip-link + ag-modal focus trap
└── .auth/
    ├── operator.json            # already populated by global setup
    ├── admin.json
    ├── voter.json
    └── president.json
.planning/
└── v1.3-A11Y-REPORT.md          # final deliverable (D-14 structure)
```

### Pattern 1: Parametrized Axe Tests (D-01)

**What:** Une seule liste `PAGES`, un test généré par entrée via boucle top-level dans le `describe`. Playwright autorise `test(...)` appelé dans une boucle `for` — chaque appel crée un test distinct avec son propre title.

**When to use:** toutes les pages qui partagent la même logique (goto → wait → `axeAudit`). Pour les pages qui ont besoin d'interactions pré-audit (wizard multi-step, vote choisi), préférer un test dédié à côté de la boucle.

**Example:**
```js
// tests/e2e/specs/accessibility.spec.js
const { test, expect } = require('@playwright/test');
const { loginAsOperator, loginAsAdmin, loginAsVoter } = require('../helpers');
const { axeAudit } = require('../helpers/axeAudit');

/**
 * Axe audit matrix — 21 pages HTMX (A11Y-01).
 * loginFn: null means anonymous (public/login pages).
 * requiredLocator: selector that must be visible before running axe
 *                  (HTMX hydration safety — see helpers/waitForHtmxSettled).
 * extraDisabled: per-page waivers (see D-09/D-10). Keep empty unless justified.
 */
const PAGES = [
  // already covered (D-02)
  { path: '/login.html',            loginFn: null,           requiredLocator: '#email' },
  { path: '/dashboard.htmx.html',   loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/meetings.htmx.html',    loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/members.htmx.html',     loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/operator.htmx.html',    loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/settings.htmx.html',    loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
  { path: '/audit.htmx.html',       loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
  // new (D-03)
  { path: '/admin.htmx.html',       loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
  { path: '/analytics.htmx.html',   loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/archives.htmx.html',    loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/docs.htmx.html',        loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/email-templates.htmx.html', loginFn: loginAsAdmin, requiredLocator: 'main, [data-page]' },
  { path: '/help.htmx.html',        loginFn: null,            requiredLocator: 'h1' },
  { path: '/hub.htmx.html',         loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/postsession.htmx.html', loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/public.htmx.html',      loginFn: null,            requiredLocator: '.projection-header' },
  { path: '/report.htmx.html',      loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/trust.htmx.html',       loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' }, // operator peut voir trust en dev; sinon loginAsAdmin
  { path: '/users.htmx.html',       loginFn: loginAsAdmin,    requiredLocator: 'main, [data-page]' },
  { path: '/validate.htmx.html',    loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
  { path: '/vote.htmx.html',        loginFn: loginAsVoter,    requiredLocator: '#meetingSelect, [data-page]' },
  { path: '/wizard.htmx.html',      loginFn: loginAsOperator, requiredLocator: 'main, [data-page]' },
];

test.describe('Axe audits — 21 pages', () => {
  for (const p of PAGES) {
    test(`${p.path} has no critical/serious axe violations`, async ({ page }) => {
      if (p.loginFn) await p.loginFn(page);
      await page.goto(p.path, { waitUntil: 'domcontentloaded' });
      await expect(page.locator(p.requiredLocator).first()).toBeVisible({ timeout: 10000 });
      await axeAudit(page, p.path, { extraDisabledRules: p.extraDisabled || [] });
    });
  }
});
```

**Key references observed in repo:**
- `public.htmx.html` is anonymous (projection screen) — no login.
- `help.htmx.html` is `data-page-role="viewer"` but is in practice anonymous-accessible (no login wall).
- `vote.htmx.html` needs `loginAsVoter` and exposes `<ag-searchable-select id="meetingSelect">` — wait on that selector, not generic `main`.
- `trust.htmx.html` has `data-page-role="auditor,assessor"` — in test fixtures, an operator account may not have rights; if axe fails with "page blocked" use `loginAsAdmin` instead (test will show the real landing). **Open question — validate in plan 16-01 run.**

### Pattern 2: axeAudit with `extraDisabledRules` (D-10)

```js
// tests/e2e/helpers/axeAudit.js — extended signature
async function axeAudit(page, pageName, options = {}) {
  const { extraDisabledRules = [] } = options;
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa'])
    .disableRules(['color-contrast', ...extraDisabledRules])
    .analyze();
  // ...existing blocker assertion...
}
```

Backward compatible : appel sans `options` fonctionne comme avant. Les sites d'appel existants ne changent pas.

### Pattern 3: Keyboard Navigation Spec (D-12)

`keyboard-nav.spec.js` testera **un layout shell par rôle** (pas les 21 pages) car les shells sont mutualisés :

| Shell | Representative page | Login |
|-------|---------------------|-------|
| `.app-shell` + sidebar (admin/operator) | `/operator.htmx.html` | operator |
| Login 2-panel | `/login.html` | — |
| Voter tablet | `/vote.htmx.html` | voter |
| Projection (public) | `/public.htmx.html` | — |

**Tests par shell :**
1. Skip-link présent, focusable, saute au `#main-content` :
   ```js
   await page.goto('/operator.htmx.html');
   await page.keyboard.press('Tab');
   const focused = page.locator(':focus');
   await expect(focused).toHaveClass(/skip-link/);
   await focused.press('Enter');
   // After activation, focus moves to main landmark
   const hash = await page.evaluate(() => location.hash);
   expect(hash).toBe('#main-content');
   ```
2. Tab order cohérent : skip → nav → main interactive → footer (assertion souple : `:focus` doit appartenir à `header, nav, main` dans cet ordre).

**Focus trap `ag-modal` — attention au Shadow DOM :** le composant définit son Shadow root et ses boutons internes (`.modal-close`) y vivent. Playwright pierces automatically le shadow DOM pour la plupart des locators, mais `:focus` sur shadow-hosted element nécessite `page.evaluate(() => document.activeElement.shadowRoot?.activeElement)`. Pattern recommandé :
```js
// Open a modal via a known trigger page (e.g. members page has a confirm modal)
await loginAsOperator(page);
await page.goto('/members.htmx.html');
await page.locator('[data-action="delete"]').first().click();
// Verify Escape closes
await page.keyboard.press('Escape');
await expect(page.locator('ag-modal[aria-hidden="false"]')).toHaveCount(0);
// Verify focus restored to trigger
const tag = await page.evaluate(() => document.activeElement.tagName);
expect(tag).toBe('BUTTON');
```

**Alternative plus simple (recommandée) :** tester directement le composant via une page de fixture ou la page existante qui l'utilise le plus (members / trust / operator). Ne pas réécrire le trap, juste vérifier son comportement.

### Pattern 4: Contrast Audit One-Shot (D-05)

```js
// tests/e2e/specs/contrast-audit.spec.js
const { test } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const fs = require('fs');
const path = require('path');

// Skipped in default runs — executed manually via:
//   PROJECT=chromium npx playwright test specs/contrast-audit.spec.js
test.describe.configure({ mode: 'serial' });
test.describe('Contrast audit (manual)', () => {
  test('collect color-contrast violations on all pages', async ({ page }) => {
    const PAGES = [ /* reuse the 21-page list, import from accessibility.spec or dup */ ];
    const report = { generatedAt: new Date().toISOString(), pages: [] };

    for (const p of PAGES) {
      if (p.loginFn) await p.loginFn(page);
      await page.goto(p.path, { waitUntil: 'domcontentloaded' });
      const results = await new AxeBuilder({ page })
        .withTags(['wcag2aa'])
        .withRules(['color-contrast'])
        .analyze();

      report.pages.push({
        path: p.path,
        violations: results.violations.map(v => ({
          id: v.id,
          impact: v.impact,
          nodes: v.nodes.slice(0, 20).map(n => ({
            target: n.target,
            html: n.html.slice(0, 120),
            ratio: (n.any?.[0]?.data?.contrastRatio) ?? null,
            fg: n.any?.[0]?.data?.fgColor,
            bg: n.any?.[0]?.data?.bgColor,
          })),
        })),
      });
    }

    const out = path.resolve(__dirname, '../../../.planning/v1.3-CONTRAST-AUDIT.json');
    fs.writeFileSync(out, JSON.stringify(report, null, 2));
    console.log(`Contrast report written: ${out}`);
  });
});
```

**Key:** utiliser `.withRules(['color-contrast'])` = whitelist (désactive toutes les autres règles), garantit un scan ciblé rapide. Les propriétés `contrastRatio`, `fgColor`, `bgColor` sont dans `node.any[0].data` (API axe-core stable depuis 4.x).

**Exécution manuelle, hors CI** : ne pas ajouter au `playwright.config.js` sans grep pattern — ou ajouter un `testIgnore: ['**/contrast-audit.spec.js']` si besoin d'éviter qu'il tourne par défaut. Alternative : marquer `test.skip(!process.env.CONTRAST_AUDIT, 'manual only')` en tête.

### Anti-Patterns to Avoid

- **Un test par page écrit à la main** : duplication à 21× — violé D-01, fragilise la maintenance.
- **Lancer color-contrast dans le runner structurel** : bloque la CI sur des faux-positifs dépendant du thème → D-04.
- **Waivers sans date d'expiration** : deviennent de la dette invisible. Toujours `expires YYYY-MM-DD` (D-09).
- **Désactiver des règles globalement dans `axeAudit.js`** pour masquer un bug : les waivers sont **par page** via `extraDisabledRules`, avec commentaire justificatif au site d'appel.
- **Réécrire le focus trap** : `ag-modal` en a déjà un qui marche (Tab loop + Escape + focus restore). Tester son comportement, pas le reconstruire.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Detect WCAG violations | Custom DOM traversal | `@axe-core/playwright` (already installed) | 90+ règles maintenues, couverture WCAG 2.1 AA officielle. |
| Modal focus trap | New trap in keyboard-nav spec | Existing `ag-modal._trapFocus` | Déjà implémenté + testé visuellement. |
| Skip-link CSS | Page-specific rules | Global `.skip-link` in `design-system.css` | Déjà présent et utilisé par les 21 pages. |
| Contrast ratio math | Custom pixel sampler | axe `color-contrast` rule (`node.any[0].data.contrastRatio`) | Gère transparence, gradients, text-shadow, images de fond. |
| Cookie-based login per test | Fresh `/login.html` form | `loginAs*` helpers (cookie injection from `.auth/{role}.json`) | Évite le rate-limit `auth_login` (10/300s) sur run parallèle 21+ tests. |
| Markdown report builder | NPM `markdown-table` / templating lib | Plain `fs.writeFileSync` with template literals | Rapport statique, taille modeste, zéro dep. |

**Key insight :** toute l'infrastructure est déjà là. Cette phase est **de l'assemblage et du fix**, pas de la construction.

## Expected Violation Families (What the Audit Will Likely Find)

Basé sur des runs axe typiques sur apps HTMX + custom elements, classes de violations à attendre (grossièrement triées par probabilité d'apparition dans CE codebase) :

| Rule ID | Impact | Fix location | Notes spécifiques AG-VOTE |
|---------|--------|--------------|---------------------------|
| `button-name` | critical | HTML `public/*.htmx.html`, JS générateurs | Boutons icon (svg only) sans `aria-label`. **WIP seed 16-01 traite déjà settings.js.** Chercher les autres : `operator.js`, `members.js`, `validate.js`. |
| `link-name` | serious | HTML | Liens avec icône uniquement (sidebar role filter, theme toggle). |
| `landmark-one-main` | moderate→serious | HTML shells | Pages sans `<main>` ou avec plusieurs `<main>`. Vérifier shells `app-shell`. |
| `region` | moderate | HTML | Contenu hors landmark (ex : toasts root, blocked overlay dans vote.htmx.html). |
| `aria-valid-attr-value` | serious | JS dynamique | HTMX qui set un `aria-*` invalide. |
| `aria-required-children` / `aria-required-parent` | serious | Custom components | `ag-searchable-select`, `ag-modal` → vérifier ARIA roles. |
| `label` | critical | HTML forms | Inputs sans `<label for>` ou `aria-label`. Wizard multi-step à haut risque. |
| `duplicate-id`, `duplicate-id-aria` | serious | HTML partials inclus dynamiquement | Risque élevé avec sidebar injection HTMX sur 21 pages. |
| `heading-order` | moderate | HTML | Sauts h1→h3. Probable sur analytics/report. |
| `dialog-name` / `aria-dialog-name` | serious | `ag-modal.js` shadow template | Le template met `aria-label="${title}"` mais si `title=""` → violation. **Action : fallback dans `ag-modal.js`.** |
| `role="status"` / `role="progressbar"` | (positive) | — | Déjà ajouté en WIP sur `operator.htmx.html`. |
| `[hidden]` display override | (positive) | — | Déjà corrigé en WIP dans `settings.css`. |

**Probabilité faible mais à surveiller :**
- `color-contrast` : désactivé dans runner structurel (D-04), audité séparément.
- `video-caption`, `audio-caption` : pas de média dans l'app → N/A.
- `html-has-lang`, `html-lang-valid` : déjà `<html lang="fr">` sur les 21 pages (vérifié via grep `data-page-role`).

**Stratégie batch D-07 :** après le premier run, grouper les violations par `rule-id`, trier par nombre de noeuds affectés, fixer en commençant par la plus fréquente. Pattern attendu : 3-5 batches pour couvrir 80% des violations.

## Page Inventory — Login Role Matrix

| # | Page | `data-page-role` | Auth in tests | Wait locator |
|---|------|------------------|---------------|--------------|
| 1 | `/login.html` | — | none | `#email` |
| 2 | `/dashboard.htmx.html` | viewer | operator | `main, [data-page]` |
| 3 | `/meetings.htmx.html` | viewer | operator | `main, [data-page]` |
| 4 | `/members.htmx.html` | operator,admin | operator | `main, [data-page]` |
| 5 | `/operator.htmx.html` | operator | operator | `main, [data-page]` |
| 6 | `/settings.htmx.html` | admin | admin | `main, [data-page]` |
| 7 | `/audit.htmx.html` | operator,admin | admin | `main, [data-page]` |
| 8 | `/admin.htmx.html` | admin | admin | `main, [data-page]` |
| 9 | `/analytics.htmx.html` | operator | operator | `main, [data-page]` |
| 10 | `/archives.htmx.html` | viewer | operator | `main, [data-page]` |
| 11 | `/docs.htmx.html` | viewer | operator | `main, [data-page]` |
| 12 | `/email-templates.htmx.html` | admin | admin | `main, [data-page]` |
| 13 | `/help.htmx.html` | viewer | none (anonymous) | `h1` |
| 14 | `/hub.htmx.html` | operator,admin | operator | `main, [data-page]` |
| 15 | `/postsession.htmx.html` | operator,president,admin | operator | `main, [data-page]` |
| 16 | `/public.htmx.html` | public | none | `.projection-header` |
| 17 | `/report.htmx.html` | operator,president,auditor | operator | `main, [data-page]` |
| 18 | `/trust.htmx.html` | auditor,assessor | admin (fallback if operator blocked) | `main, [data-page]` |
| 19 | `/users.htmx.html` | admin | admin | `main, [data-page]` |
| 20 | `/validate.htmx.html` | operator | operator | `main, [data-page]` |
| 21 | `/vote.htmx.html` | voter | voter | `#meetingSelect, [data-page]` |
| + | `/wizard.htmx.html` | operator,admin | operator | `main, [data-page]` |

(22 lignes car login + 21 pages `.htmx.html`. A11Y-01 vise "21 pages applicatives" = `*.htmx.html` — login est bonus couvert depuis Phase 7.)

**Edge cases :**
- `vote.htmx.html` : l'interface tablette affiche un `<ag-searchable-select id="meetingSelect">` au chargement. Si le voter n'a pas de meeting assignée, l'overlay `#blockedOverlay` peut apparaître → l'audit run sur cet état. À valider en plan 16-01.
- `wizard.htmx.html` : multi-étapes, l'audit ne couvre que l'étape 1 par défaut. Pour couvrir toutes les étapes, ajouter 3 tests supplémentaires (étape 2/3/4) hors de la boucle `PAGES` si nécessaire — **décision à prendre par le planner**. Pragma : scope D-03 dit "wizard" unique, donc étape 1 suffit pour Phase 16.
- `public.htmx.html` : force dark theme au chargement (D-04 contexte). L'audit color-contrast devra explicitement tester ce mode.

## Waivers — Expected Candidates

Liste pré-identifiée pour anticipation (à confirmer après le run initial) :

| Zone | Rule | Reason | Waiver scope |
|------|------|--------|--------------|
| PDF preview iframe (trust / postsession) | `frame-title` ou `region` | dompdf génère un PDF, iframe src = blob opaque | `extraDisabledRules: ['frame-title']` sur trust/postsession uniquement |
| FilePond widget (wizard) | variable | Lib tierce, pas sous notre contrôle | documenter + `extraDisabledRules` sur wizard si nécessaire |
| Google Fonts `<link>` | N/A | pas de règle axe | — |

**Règle** (D-09) : chaque waiver doit avoir `// A11Y-WAIVER: <rule-id> — <raison> — expires YYYY-MM-DD`. Expiration suggérée : 6 mois (2026-10-09).

## Common Pitfalls

### Pitfall 1: Shadow DOM and axe-core
**What:** axe-core traverse le shadow DOM ouvert par défaut depuis 4.0, mais certains sélecteurs Playwright ne fonctionnent pas sur les `::part()` ou slots non-assignés.
**Why:** `ag-modal` met son contenu dans un shadow root et le template du header (close button) y vit.
**How to avoid:** ne pas tester le close button via `page.locator('.modal-close')` directement — axe trouve les violations dans le shadow, mais pour vérifier le focus on doit `page.evaluate(() => document.activeElement.shadowRoot?.activeElement)`.
**Warning signs:** un test keyboard-nav vert alors que visuellement le focus trap ne marche pas → probablement le locator n'a pas pierced le shadow.

### Pitfall 2: HTMX hydration timing
**What:** axe run avant que HTMX n'ait remplacé les placeholders = rapport sur un DOM incomplet.
**Why:** `domcontentloaded` ne signifie pas "HTMX settled".
**How to avoid:** toujours `await expect(requiredLocator).toBeVisible({ timeout: 10000 })` avant `axeAudit`. Utiliser `tests/e2e/helpers/waitForHtmxSettled.js` si besoin plus fin.
**Warning signs:** test flaky — passe localement, fail en CI. Ajouter le wait explicite.

### Pitfall 3: `duplicate-id` from sidebar partial
**What:** la sidebar est injectée via `data-include-sidebar data-page="X"` sur **toutes** les pages. Si elle contient des `id=`, ils seront uniques par page — pas de problème. **Mais** si deux composants inline partagent un id, axe hurlera 21 fois.
**How to avoid:** quand fix arrive, preferer `data-*` ou `aria-labelledby` avec un id unique par composant. Batch fix `duplicate-id` touchera probablement un template partagé → fix une fois, 21 pages gagnent.

### Pitfall 4: Waiver that drifts
**What:** un waiver ajouté "temporairement" devient permanent.
**How to avoid:** D-09 `expires YYYY-MM-DD` obligatoire ; grep pre-commit : `grep -rn "A11Y-WAIVER" tests/e2e/specs/` en annexe du rapport. Une section "Waivers actifs" dans `v1.3-A11Y-REPORT.md` avec date de revue.

### Pitfall 5: Roles mismatch dans les tests
**What:** `trust.htmx.html` requiert `auditor,assessor` mais le cookie opérateur ne donne peut-être pas accès.
**How to avoid:** premier run en plan 16-01, si `trust.htmx.html` renvoie un 403 ou redirect vers login, fallback sur `loginAsAdmin`. **Ne pas assumer — mesurer.**

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright `@playwright/test` 1.59.1 + `@axe-core/playwright` 4.10.2 |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `cd tests/e2e && npx playwright test specs/accessibility.spec.js --project=chromium` |
| Full suite command | `bin/test-e2e.sh` (all specs all projects from Phase 15) or `cd tests/e2e && npx playwright test --project=chromium specs/accessibility.spec.js specs/keyboard-nav.spec.js` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| A11Y-01 | axe scan sur 21 pages, 0 critical/serious | e2e (Playwright) | `cd tests/e2e && npx playwright test specs/accessibility.spec.js --project=chromium` | ⚠️ Extend (baseline Phase 7 = 7 tests) |
| A11Y-02 | Violations critical/serious fixées | e2e (même runner) | idem A11Y-01 | ⚠️ Extend |
| A11Y-03 — skip-link | Skip link focusable par Tab, active au click, cible `#main-content` | e2e | `cd tests/e2e && npx playwright test specs/keyboard-nav.spec.js --project=chromium` | ❌ New Wave 0 |
| A11Y-03 — focus trap | Modale piège le focus Tab/Shift-Tab, Escape ferme, focus restauré | e2e | idem keyboard-nav | ❌ New Wave 0 |
| A11Y-03 — contraste | Ratios WCAG AA pour les 21 pages (one-shot) | e2e manuel | `cd tests/e2e && CONTRAST_AUDIT=1 npx playwright test specs/contrast-audit.spec.js --project=chromium` | ❌ New Wave 0 |
| A11Y-03 — conformance statement | Rapport final `.planning/v1.3-A11Y-REPORT.md` | manual-only | `ls .planning/v1.3-A11Y-REPORT.md` | ❌ New deliverable |

### Sampling Rate

- **Per task commit:** `cd tests/e2e && npx playwright test specs/accessibility.spec.js --project=chromium --grep "<page>"` (run ciblé sur la page touchée).
- **Per wave merge:** `cd tests/e2e && npx playwright test specs/accessibility.spec.js specs/keyboard-nav.spec.js --project=chromium` (suite a11y complète, un seul browser).
- **Phase gate:** Full suite green on chromium AVANT `/gsd:verify-work`. Multi-browser a11y = facultatif (Phase 15 valide déjà la parité).

### Wave 0 Gaps

- [ ] `tests/e2e/specs/keyboard-nav.spec.js` — couvre A11Y-03 skip-link + focus trap. **NEW.**
- [ ] `tests/e2e/specs/contrast-audit.spec.js` — one-shot color-contrast, génère `.planning/v1.3-CONTRAST-AUDIT.json`. **NEW.**
- [ ] Extension `tests/e2e/helpers/axeAudit.js` — signature `axeAudit(page, pageName, { extraDisabledRules })`. **Edit existing.**
- [ ] Paramétrisation `tests/e2e/specs/accessibility.spec.js` → `PAGES` array + loop. **Edit existing.**
- [ ] `.planning/v1.3-A11Y-REPORT.md` — deliverable final, 7 sections D-14. **NEW.**
- [ ] Commit WIP seed (D-08) : `SettingsController.php`, `operator.htmx.html`, `settings.css`, `settings.js`, `axeAudit.js` debug, `accessibility.spec.js .first()`. **Plan 16-01 premier acte.**

*(No framework install needed — all deps already in `tests/e2e/package.json`.)*

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `axe-core` standalone via CDN | `@axe-core/playwright` AxeBuilder | axe-core 4.0 (2020) | API fluent, auto-injection dans page, synchronisation Playwright. |
| Tests a11y écrits à la main un par un | Data-driven via array + boucle `for` dans `describe` | Playwright stable | D-01 recommandation. |
| `jest-axe` (React/Vitest land) | `@axe-core/playwright` (e2e) | — | jest-axe est pour JSDOM, inutile ici (pas de SSR React). |

**Deprecated/outdated :**
- `pa11y-ci` : viable mais pas le standard axe, redondant ici.
- axe-core `runOptions.runOnly` avec chaîne : préférer `withRules(['color-contrast'])` API fluent.

## Open Questions

1. **Rôle effectif pour `/trust.htmx.html` en tests**
   - What we know: `data-page-role="auditor,assessor"`, nos fixtures ont operator/admin/voter/president, pas auditor.
   - What's unclear: est-ce que l'operator est autorisé côté backend ou redirigé ?
   - Recommendation: démarrer avec `loginAsAdmin` (le plus large), mesurer au plan 16-01 ; fallback documenté.

2. **Wizard multi-step coverage**
   - What we know: D-03 liste "wizard" (singulier). L'étape 1 est la landing par défaut.
   - What's unclear: faut-il auditer les étapes 2/3/4 ?
   - Recommendation: scope Phase 16 = étape 1 uniquement. Ajouter des tests spécifiques étapes 2-4 en Phase 17+ si retour terrain. Documenter la limite dans le rapport.

3. **Exécution de `contrast-audit.spec.js` en CI**
   - What we know: D-05 dit "manuel, pas en CI".
   - What's unclear: faut-il l'exclure via `testIgnore` dans `playwright.config.js` ou via `test.skip(!process.env.CONTRAST_AUDIT)` en tête du spec ?
   - Recommendation: `test.skip(!process.env.CONTRAST_AUDIT, 'manual only')` — plus explicite, découvrable via `test:list`.

4. **Validation du WIP seed réellement nécessaire**
   - What we know: D-08 dit que le WIP adresse de vraies violations (status/progressbar, aria-label boutons icon, `[hidden]` fix, debug axeAudit).
   - What's unclear: est-ce que tous ces fixes correspondent à des violations axe observées, ou certains sont "anticipatifs" ?
   - Recommendation: plan 16-01 = committer le WIP (pas de régression possible), puis run baseline pour mesurer ce qui reste. Pas de re-validation individuelle avant commit.

5. **Audit manuel flows critiques (D-13)**
   - What we know: login → vote → logout en manuel.
   - What's unclear: format de documentation (checklist ? screenshots ? markdown inline dans rapport ?).
   - Recommendation: checklist markdown dans section 4 du rapport (keyboard nav & focus management), sans screenshots (out of scope).

## Code Examples (Verified patterns)

### Parametrized tests — Playwright idiom
```js
// Playwright officially supports this pattern; each iteration creates a unique test.
// Source: Playwright docs "Parameterize tests" — verified stable since 1.20+.
for (const { name, input, expected } of cases) {
  test(`${name}`, async ({ page }) => { /* ... */ });
}
```

### axe-core `.withRules` whitelist
```js
// Source: @axe-core/playwright README, deque-systems/axe-core-npm
const results = await new AxeBuilder({ page })
  .withTags(['wcag2aa'])
  .withRules(['color-contrast'])  // ONLY this rule
  .analyze();
```

### axe-core violation node shape
```js
// node.any[0].data contains rule-specific extras:
// for color-contrast: { contrastRatio, fgColor, bgColor, expectedContrastRatio }
results.violations[0].nodes[0].any[0].data
```

## Sources

### Primary (HIGH confidence) — verified in repo
- `/home/user/gestion_votes_php/tests/e2e/helpers/axeAudit.js` — current runner, extensible.
- `/home/user/gestion_votes_php/tests/e2e/helpers.js` — `loginAs*` helpers, cookie injection strategy.
- `/home/user/gestion_votes_php/tests/e2e/specs/accessibility.spec.js` — 7-page baseline.
- `/home/user/gestion_votes_php/tests/e2e/playwright.config.js` — projects matrix, `tests/e2e` cwd.
- `/home/user/gestion_votes_php/tests/e2e/package.json` — `@playwright/test 1.59.1`, `@axe-core/playwright 4.10.2`.
- `/home/user/gestion_votes_php/public/assets/js/components/ag-modal.js` — focus trap, Escape, restore, `role="dialog" aria-modal="true"`.
- `/home/user/gestion_votes_php/public/assets/css/design-system.css` — global `.skip-link`.
- `/home/user/gestion_votes_php/public/*.htmx.html` — 21 pages, all have `<a class="skip-link">`, `<html lang="fr">`, `data-page-role` attribute mapped above.
- `.planning/phases/16-accessibility-deep-audit/16-CONTEXT.md` — locked decisions D-01 → D-14.

### Secondary (HIGH confidence) — canonical docs
- Deque `@axe-core/playwright` README (API: `withTags`, `withRules`, `disableRules`, `include`, `exclude`, `.analyze()`).
- Playwright docs "Parameterize tests" (for-loop idiom in describe block).
- WCAG 2.1 AA quickref (https://www.w3.org/WAI/WCAG21/quickref/).
- Dequeuniversity axe rules reference (https://dequeuniversity.com/rules/axe/4.10).

### Tertiary (training data — flagged LOW where used)
- Common axe violation frequency ranking (used in "Expected Violation Families" table) — LOW confidence on exact ordering; **the plan must validate by running the audit first and re-ordering batches by observed count (D-07)**.

## Metadata

**Confidence breakdown:**
- Standard stack : HIGH — all libs already installed, versions verified in `package.json`.
- Architecture / patterns : HIGH — existing files already demonstrate the target patterns (login, axeAudit, modal focus trap, skip-link).
- Pitfalls : HIGH for repo-specific (shadow DOM, HTMX timing, trust role); MEDIUM for generic axe pitfalls.
- Expected violation families : LOW-MEDIUM — order/frequency is hypothesis; baseline run in plan 16-01 must override this.
- Report structure : HIGH — D-14 locked.

**Research date :** 2026-04-09
**Valid until :** 2026-07-09 (stack stable ~3 months ; axe-core rule updates possible before then)
