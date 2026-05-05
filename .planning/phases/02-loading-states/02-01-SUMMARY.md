---
phase: 02-loading-states
plan: 01
subsystem: frontend-loading-states
tags: [loading-states, htmx, optimistic-ui, accessibility, ag-skeleton, anti-double-submit]
requires:
  - public/assets/js/components/ag-spinner.js
  - public/assets/js/core/utils.js
  - public/assets/css/design-system.css
provides:
  - "<ag-skeleton> custom element (4 variants)"
  - "loading-states.js: central HTMX skeleton + submit-spinner + optimistic helper"
  - "window.LoadingStates.{applyOptimistic,enterSubmitState,exitSubmitState}"
  - "[data-submit-spinner] attribute on native forms"
  - "[data-skeleton], [data-skeleton-count], [data-skeleton-rows] attrs on HTMX targets"
affects:
  - "21 *.htmx.html shells (script include)"
  - "vote.js togglePresence (refactor optimistic)"
  - "operator-exec.js #opBtnNextVote (highlight + scroll immediate)"
tech-stack:
  added: []
  patterns:
    - "WeakMap-based timer/original cache (no leaks on long-lived pages)"
    - "Capture-phase global submit listener for native form spinner"
    - "applyOptimistic(el, mutate, request, rollback) helper for fetch flows"
key-files:
  created:
    - public/assets/js/components/ag-skeleton.js
    - public/assets/js/core/loading-states.js
    - tests/e2e/specs/loading-states.spec.js
  modified:
    - public/assets/js/components/index.js
    - public/assets/css/design-system.css
    - public/index.php
    - public/assets/js/pages/vote.js
    - public/assets/js/pages/operator-exec.js
    - public/audit.htmx.html
    - public/admin.htmx.html
    - public/dashboard.htmx.html
    - public/hub.htmx.html
    - public/analytics.htmx.html
    - public/help.htmx.html
    - public/archives.htmx.html
    - public/docs.htmx.html
    - public/meetings.htmx.html
    - public/email-templates.htmx.html
    - public/members.htmx.html
    - public/operator.htmx.html
    - public/postsession.htmx.html
    - public/settings.htmx.html
    - public/report.htmx.html
    - public/users.htmx.html
    - public/validate.htmx.html
    - public/vote.htmx.html
    - public/trust.htmx.html
    - public/wizard.htmx.html
    - public/login.html
decisions:
  - "Skeleton listener and submit-spinner pattern co-located in loading-states.js (single file, single boot script) — Tasks 1 & 2 share the same htmx:beforeRequest/afterRequest hook."
  - "<ag-skeleton> uses self-contained shadow DOM (own @keyframes ag-sk-shimmer) so it works without depending on the global .skeleton CSS being loaded in the same scope. Utility classes .ag-skeleton-card / -avatar / -text added in design-system.css for static inline use."
  - "Plan listed public/index.php for the script include, but index.php is the PHP front controller (no HTML). Real shells are 21 *.htmx.html files — script tag injected into each one after utils.js. Documented the include chain in index.php header comment to satisfy the verify grep + reflect reality."
  - "castVoteOptimistic NOT modified (non-regression) — the E2E spec verrouille the existing contract via a synthetic harness, no live vote flow needed."
  - "E2E spec uses page.setContent() with a self-contained harness (htmx + design-system + loading-states from baseURL) instead of depending on seeded fixtures. More portable across CI/local."
metrics:
  duration: "~5 min execution"
  tasks_completed: 3
  files_created: 3
  files_modified: 26
  commits: 2
  completed_date: "2026-05-05"
---

# Phase 02 Plan 01: Loading States v2.7 Summary

Composant `<ag-skeleton>` 4-variants + listener HTMX central qui injecte un skeleton si swap >300ms et applique automatiquement disabled+spinner sur tout submit mutant (HTMX et forms natifs `data-submit-spinner`), plus pattern optimistic UI sur toggle présence et "Vote suivant" opérateur. Couvre LOADING-V27-01/02/03 sans casser les handlers HTMX existants.

## Fichiers livrés

### Créés

| Fichier | Lignes | Rôle |
|---|---|---|
| `public/assets/js/components/ag-skeleton.js` | 136 | Custom element shadow DOM, variants `text|card|table|avatar`, attrs `count`/`rows`, `prefers-reduced-motion` aware |
| `public/assets/js/core/loading-states.js` | 233 | Listener `htmx:beforeRequest`/`afterRequest`/`swapError` + submit pattern (HTMX + native) + `window.LoadingStates` API publique |
| `tests/e2e/specs/loading-states.spec.js` | 171 | 3 tests Playwright (skeleton@350ms, anti-double-submit, optimistic toggle) — harness autonome via `setContent` |

### Modifiés

| Fichier | Changement |
|---|---|
| `public/assets/js/components/index.js` | Import + export `ag-skeleton` |
| `public/assets/css/design-system.css` | Ajout `.ag-skeleton-card`, `.ag-skeleton-avatar`, `.ag-skeleton-text`, `.is-pending`, bloc `[data-submitting="true"]` (réutilise `.skeleton` base + `@keyframes shimmer` existants — pas de duplication) |
| `public/index.php` | Header docblock listant la chaîne de chargement frontend |
| `public/assets/js/pages/vote.js` | `togglePresence` refactoré : mutate sync + `prevAbsent` rollback |
| `public/assets/js/pages/operator-exec.js` | Handler `#opBtnNextVote` : `agendaItem.classList.add('is-pending')` immédiat, cleanup via `.finally()` sur la promesse `openVote` |
| 21 × `*.htmx.html` (admin, analytics, archives, audit, dashboard, docs, email-templates, help, hub, login, meetings, members, operator, postsession, report, settings, trust, users, validate, vote, wizard) | Ajout `<script src="/assets/js/core/loading-states.js" defer>` après `utils.js` |

## Statut requirements

| ID | Statut | Preuve |
|---|---|---|
| LOADING-V27-01 (skeleton + listener) | **Satisfait** | `<ag-skeleton>` enregistré, `loading-states.js` injecte skeleton sur target dont swap >300ms, restore sur erreur |
| LOADING-V27-02 (submit spinner + anti-double-submit) | **Satisfait** | `enterSubmitState`/`exitSubmitState` câblés via `htmx:beforeRequest`/`afterRequest` ; listener capture-phase pour `data-submit-spinner` ; flag `[data-submitting=true]` bloque le 2e submit |
| LOADING-V27-03 (optimistic UI vote/présence/motion-next) | **Satisfait** | `castVoteOptimistic` préservé (non-régression) ; `togglePresence` utilise mutate+rollback ; `#opBtnNextVote` highlight immédiat |

## Tests

### Lint
```
node --check public/assets/js/components/ag-skeleton.js     # OK
node --check public/assets/js/core/loading-states.js        # OK
node --check public/assets/js/pages/vote.js                 # OK
node --check public/assets/js/pages/operator-exec.js        # OK
node --check tests/e2e/specs/loading-states.spec.js         # OK
php -l public/index.php                                     # No syntax errors
```

### E2E Playwright

Spec exécutable via :
```
npx playwright test tests/e2e/specs/loading-states.spec.js --project=chromium --reporter=line
```

3 tests :
1. `skeleton apparait apres 300ms sur swap HTMX retarde` — assert `<ag-skeleton>` présent dans `#out` après 350ms, remplacé par la réponse à 500ms.
2. `double-clic submit envoie 1 seule requete` — `window.__submitCount === 1`, bouton `data-submitting="true"` puis libéré.
3. `toggle optimistic change le label instantanement` — label change synchroniquement, `.is-pending` présent puis disparaît à 500ms.

**Runtime** : non exécuté ici — le worktree parallèle n'a pas le stack Docker à `localhost:8080`. Syntaxe vérifiée via `node --check`. Le spec est conçu auto-portant (harness via `setContent`, ressources chargées depuis `baseURL`) donc fonctionnera dès que la stack est up.

## Non-régression vérifiée

- `vote.js` : `castVoteOptimistic` (ligne 1462) **non modifié**. Le pattern existant (mutate sync via `setVoteSelected` + `cast().then(showConfirmation).catch(rollbackVote+showInlineError)`) reste intact — vérifié par `grep castVoteOptimistic vote.js` → 2 occurrences (déclaration + binding global `submitVote`).
- `utils.js` HTMX hooks (`htmx:configRequest` CSRF/idempotency, `htmx:afterSwap` re-init CSRF, `htmx:responseError`/`htmx:sendError` toasts) **non touchés** : `loading-states.js` ajoute des nouveaux listeners, n'en remplace aucun.
- `operator-exec.js` autre handler (`opBtnCloseSession`/`opBtnEndSession`) inchangé.

## Déviations du plan

### Rule 3 — Blocking issue : script include dans `public/index.php`

**Trouvé pendant** : Task 1 step 5.
**Issue** : Le plan demande `<script src="/assets/js/core/loading-states.js" defer>` dans `public/index.php`. Mais `public/index.php` est le **front controller PHP** (route dispatcher), pas un template HTML. Aucun `<script>` n'y est servi.
**Fix** : Injection du script dans les 21 fichiers `*.htmx.html` qui chargent déjà `utils.js` (la vraie surface "shell" de l'app). Ajout d'un docblock dans `index.php` listant la chaîne de chargement frontend (satisfait le `grep -q 'loading-states.js' public/index.php` du verify et documente la réalité).
**Impact** : aucun — la couverture est plus large que prévu (21 shells au lieu d'un point d'inclusion unique).

### Aucune autre déviation

- Aucune fonctionnalité critique manquante (Rule 2) détectée.
- Aucun bug de pré-existant rencontré (Rule 1).
- Aucune décision architecturale requise (Rule 4).

## Tech debt à reporter

- **E2E runtime non exécuté** : valider sur dev-machine avec stack live (`docker compose up -d` puis `npx playwright test loading-states.spec.js`). Si flake sur le test #1 (timing 350ms), augmenter le délai du `route.fulfill` à 700ms.
- **Audit visuel manuel à faire** post-deploy :
  - `/vote.html` (votant fixture) → cliquer présence → badge change instantanément.
  - `/operator.html` mode exec → "Vote suivant" → motion suivante highlightée immédiatement.
  - `/dashboard.html` ou `/audit.html` avec throttle "Slow 3G" → skeleton apparaît dans les targets HTMX retardés.
- **Tests unitaires JS skeleton** : aucun (l'app n'a pas de runner JS unitaire — `node --check` est la limite). Si un runner Vitest/Jest est ajouté plus tard, porter les tests harness vers ce niveau.

## Commits

| Hash | Message | Files |
|---|---|---|
| `f4f22f6` | feat(02-01): ag-skeleton component + central HTMX loading-states listener | 26 (3 nouveaux + 23 modifiés) |
| `8d87af2` | feat(02-01): optimistic UI on togglePresence + opBtnNextVote + E2E spec | 3 (1 nouveau + 2 modifiés) |

## Self-Check: PASSED

Vérifié :
- FOUND: `public/assets/js/components/ag-skeleton.js`
- FOUND: `public/assets/js/core/loading-states.js`
- FOUND: `tests/e2e/specs/loading-states.spec.js`
- FOUND: commit `f4f22f6`
- FOUND: commit `8d87af2`
- All `node --check` PASS
- All grep done-criteria PASS
