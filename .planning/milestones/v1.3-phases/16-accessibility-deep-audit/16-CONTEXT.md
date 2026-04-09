# Phase 16: Accessibility Deep Audit - Context

**Gathered:** 2026-04-09
**Status:** Ready for planning
**Mode:** --auto (Claude picked recommended defaults; review before planning if needed)

<domain>
## Phase Boundary

Deep axe-core audit on **les 21 pages applicatives HTMX** + conformance documentée WCAG 2.1 AA.
Livrable : aucune violation critical/serious, rapport `.planning/v1.3-A11Y-REPORT.md`, tests Playwright a11y intégrés aux critical-path specs.

**In scope:**
- Extension de `tests/e2e/specs/accessibility.spec.js` aux 14 pages non encore couvertes
- Fix de toutes les violations critical + serious détectées (ARIA, landmarks, labels, focus)
- Audit contraste couleur (séparé, via design tokens + spot-check axe)
- Keyboard navigation + focus management (skip-to-content, focus trap modales)
- Rapport WCAG 2.1 AA dans `.planning/v1.3-A11Y-REPORT.md`
- Waivers documentés pour violations justifiables (fournisseurs tiers, PDF dompdf, etc.)

**Out of scope (other phases):**
- Redesign visuel ou contraste ajusté par ajustement de tokens (Phase 14 déjà shipped)
- Traduction / i18n (hors milestone)
- Screen reader UX testing manuel systématique (spot-check uniquement)
- Conformance AAA (AA est la cible)

</domain>

<decisions>
## Implementation Decisions

### Audit scope & coverage strategy
- **D-01:** Parametrize `accessibility.spec.js` via une liste `PAGES = [{ path, loginFn, requiredLocator }]` et générer un test par entrée avec `test.describe.parallel` ou boucle. Couvre les 21 pages sans duplication. *(Auto: recommended — DRY, permet skip ciblé, scale)*
- **D-02:** Les 7 pages déjà couvertes (login, dashboard, meetings, members, operator, settings, audit) restent dans le même fichier paramétrisé — pas de régression sur l'existant.
- **D-03:** Les 14 pages à ajouter : admin, analytics, archives, docs, email-templates, help, hub, postsession, public, report, trust, users, validate, vote, wizard.

### Color contrast policy
- **D-04:** `axeAudit.js` garde `disableRules: ['color-contrast']` pour les tests structurels (rapides, déterministes). Raison : les tokens de couleur ont été ajustés en Phase 14 et le contraste dépend du thème (light/dark). *(Auto: recommended — évite faux positifs bloquants)*
- **D-05:** Audit contraste dédié via un script one-shot `tests/e2e/specs/contrast-audit.spec.js` qui active color-contrast et produit un JSON → intégré au rapport. Exécuté manuellement, pas en CI.
- **D-06:** Résultats contraste documentés dans `v1.3-A11Y-REPORT.md` section "Contrast audit" avec ratios mesurés.

### Violation resolution strategy
- **D-07:** Fix **par type de violation** (pas page-par-page). Exemple : un batch pour tous les `button-name` missing, un batch pour tous les `landmark-unique`, etc. Raison : enforce consistent patterns, plus rapide. *(Auto: recommended)*
- **D-08:** Les WIP déjà en cours (SettingsController `api_request('GET','POST')`/`api_ok` unwrapped, operator.htmx.html `role="status"`/`role="progressbar"`, settings.css `[hidden]`, settings.js `aria-label` sur boutons icon, axeAudit.js debug amélioré, accessibility.spec.js `.first()`) servent de **seed** pour le plan 16-01 — à committer en début d'exécution.

### Waiver mechanism
- **D-09:** Waivers inline dans les specs via commentaire standardisé : `// A11Y-WAIVER: <rule-id> — <raison> — expires YYYY-MM-DD`. *(Auto: recommended — traceable, expirable)*
- **D-10:** `axeAudit.js` accepte optionnellement un paramètre `extraDisabledRules` pour waivers par page (ex: iframe dompdf preview).
- **D-11:** Tous les waivers listés dans `v1.3-A11Y-REPORT.md` section "Waivers" avec justification et date de revue.

### Keyboard navigation & focus management
- **D-12:** Nouveau fichier `tests/e2e/specs/keyboard-nav.spec.js` qui teste pour chaque layout shell (admin, operator, vote, public) :
  1. Skip-to-content link présent et fonctionnel
  2. Ordre Tab sur header/nav/main cohérent
  3. Focus trap dans les modales (`.modal`, `dialog[open]`) — Tab boucle, Escape ferme
  4. Focus restauré sur l'élément déclencheur après fermeture modale
- **D-13:** Audit manuel ponctuel des flows critiques (login → vote → logout) documenté dans le rapport, pas de test automatisé exhaustif.

### Report format
- **D-14:** `.planning/v1.3-A11Y-REPORT.md` structure :
  1. Scope & méthodologie (outils, standards, pages)
  2. Résultats par page (violations résolues, waivers)
  3. Audit contraste (ratios, écarts)
  4. Keyboard navigation & focus management (résultats manuels + auto)
  5. Waivers actifs
  6. WCAG 2.1 AA conformance statement
  7. Annexe : commandes de reproduction

### Claude's Discretion
- Choix précis de la regex/selecteur pour détecter les layouts shells dans keyboard-nav.spec.js
- Format exact du JSON produit par contrast-audit.spec.js
- Template markdown du rapport (structure D-14 respectée)
- Ordre des batches de fix dans le plan d'exécution (par rule-id count décroissant probablement)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Standards
- [WCAG 2.1 AA](https://www.w3.org/WAI/WCAG21/quickref/?currentsidebar=%23col_overview&versions=2.1&levels=aa) — niveau cible
- [axe-core rules](https://dequeuniversity.com/rules/axe/4.10) — règles utilisées par le runner

### Existing code
- `tests/e2e/helpers/axeAudit.js` — runner existant, WCAG 2.0 A/AA, color-contrast disabled
- `tests/e2e/specs/accessibility.spec.js` — 7 pages déjà couvertes (baseline Phase 7)
- `public/*.htmx.html` — 21 pages à auditer
- `.planning/phases/14-visual-polish/14-CONTEXT.md` — décisions design system (dark mode, toasts, sidebar filter) qui informent les patterns a11y

### Prior phase artifacts
- `.planning/phases/14-visual-polish/14-04-SUMMARY.md` — micro-interactions / focus states (base pour focus ring)
- `.planning/phases/15-multi-browser-tests/15-CROSS-BROWSER-REPORT.md` — contexte multi-browser (webkit/mobile ont des écarts)

### Requirements
- `.planning/REQUIREMENTS.md` §A11Y-01/02/03

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable assets
- **`tests/e2e/helpers/axeAudit.js`** : runner axe prêt, à étendre (extraDisabledRules param). Debug messages déjà améliorés dans le WIP.
- **`tests/e2e/specs/accessibility.spec.js`** : 7 tests axe existants à paramétriser en liste.
- **`tests/e2e/helpers/index.js`** : helpers `loginAsOperator`, `loginAsAdmin`, etc. déjà disponibles.
- **Design tokens (Phase 14)** : variables CSS pour colors déjà unifiées — base pour audit contraste.

### Established patterns
- **Login helpers par rôle** : `loginAsOperator(page)`, `loginAsAdmin(page)`, `loginAsPresident(page)`, `loginAsVoter(page)` — utilisés consistently.
- **Playwright test.describe** : groupement par feature area, parallel par défaut.
- **HTMX progressive enhancement** : le JS n'est pas toujours chargé au `domcontentloaded`, d'où les `.first()` + timeout 10s.
- **Francisation** : tous les aria-labels et textes visibles doivent être en français (jamais copropriété/syndic).

### Integration points
- `bin/test-e2e.sh` avec `PROJECT=` env var (Phase 15) → pour runs multi-browser des nouveaux tests a11y
- `tests/e2e/playwright.config.js` → ajouter le nouveau spec `keyboard-nav.spec.js` et `contrast-audit.spec.js`
- Plan d'exécution Phase 16 doit générer le rapport à la fin (pas pendant les itérations)

### WIP déjà diagnostiqué (seed pour plan 16-01)
Fichiers modifiés non committés :
- `app/Controller/SettingsController.php` : fix `api_request('GET','POST')` + `api_ok($data)` (déwrap)
- `public/assets/css/settings.css` : `.settings-panel[hidden]{display:none}` override flex
- `public/assets/js/pages/settings.js` : `aria-label` sur boutons icon edit/delete quorum
- `public/operator.htmx.html` : `role="status"` sur live dots, `role="progressbar"` sur barre résolution
- `tests/e2e/helpers/axeAudit.js` : debug message avec liste des nœuds fautifs (max 5)
- `tests/e2e/specs/accessibility.spec.js` : `.first()` pour éviter strict mode violations

Ces changements doivent être committés en tant que premier acte du plan 16-01, pas jetés.

</code_context>

<specifics>
## Specific Ideas

- **Priorité WCAG 2.1 AA, pas AAA** — l'objectif est la conformité légale/pro standard.
- **Pas de "false positives as waivers"** — un waiver doit avoir une justification documentée (tiers, iframe opaque, bibliothèque externe).
- **PDF dompdf (procurations)** : probablement non auditable directement par axe — spot-check manuel + waiver si nécessaire.
- **Mode demo/public** : la page `public.htmx.html` (vote public) a la priorité — utilisateurs non techniques.
- **Dark mode** : Phase 14 a passé l'audit parity — l'audit a11y doit fonctionner dans les deux modes.

</specifics>

<deferred>
## Deferred Ideas

- **Screen reader manual testing** (NVDA / VoiceOver / JAWS) — utile mais coûteux, à prévoir après milestone v1.3 en phase dédiée si retour terrain.
- **Internationalisation (i18n)** — hors scope milestone, pas de plan actif.
- **Conformance AAA** (contraste 7:1, etc.) — dépasse la cible AA, à documenter comme "not targeted".
- **Audit a11y des emails HTML** (templates transactionnels) — hors scope Phase 16, pourrait devenir une mini-phase si pertinent.
- **Refonte keyboard shortcuts** — backlog a confirmé no-keyboard-shortcuts scope limit (feedback memory).

</deferred>

---

*Phase: 16-accessibility-deep-audit*
*Context gathered: 2026-04-09*
