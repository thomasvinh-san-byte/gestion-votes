# Phase 3: Test Infrastructure — Context

**Gathered:** 2026-05-04
**Status:** Ready for planning
**Milestone:** v2.4 Polish & Robustness

<domain>
## Phase Boundary

Toolchain sans friction — un nouveau contributeur passe d'un clone à `green tests run` en <30 min. Test infrastructure couvre :
- Helper `seedMeeting()` qui active les tests `@integration` skippés (notamment F-4 modal-focus-trap) via endpoint API dev
- Résolution du dual-install Playwright (root + tests/e2e)
- `gsd-code-reviewer` avec `--scope` + `--timeout-min` (50+ fichiers sans timeout)
- README e2e complet (install + auth-setup rate-limit + debug)
- `EXPLORE-PATTERNS.md` documentant le pattern anti-BEM-substring

**Hors scope** : refonte tests unit/integration PHP (déjà ok), nouveaux tests fonctionnels (couverts par autres phases), CI optimization (différé v2.5+).

</domain>

<decisions>
## Implementation Decisions

### TEST-V24-01 — seedMeeting helper (D-01..D-04)

- **D-01**: **Endpoint API dev** `/api/test/seed-meeting` créé, désactivé hors `APP_ENV in [development, test, demo]`. Fixture réaliste via stack PHP (PDO + repositories existants), respecte les validations métier (status workflow, RBAC, audit_log).
- **D-02**: **Signature** : `seedMeeting({tenantId, status, motionsCount}) → meetingId` côté JS. Côté PHP, le controller accepte ces 3 champs en JSON body, retourne `{meeting_id: string}`.
- **D-03**: **Sécurité** : middleware spécial `EnvGuardMiddleware` (nouveau ou pattern existant) qui retourne 404 en production. Pas d'auth requise (endpoint dev-only). Audit log explicite à chaque appel pour traçabilité.
- **D-04**: **F-4 test activé** : `tests/e2e/specs/modal-focus-trap.spec.js` test marqué `test.skip` ou `@integration` réactivé en utilisant `seedMeeting()`. Test passe sur dev machine après TEST-01 livré.

### TEST-V24-03 — Dual-install Playwright (D-05..D-07)

- **D-05**: **Root garde frontend deps + ESLint**, `tests/e2e/` devient SOT Playwright. Root `package.json` retire `@playwright/test` du `devDependencies` (garde `eslint` et frontend deps `chart.js`/`htmx.org`).
- **D-06**: **Scripts root mis à jour** : `test:e2e` et `test:e2e:chromium` deviennent `cd tests/e2e && npx playwright test ...` (proxy aux scripts e2e). Évite la duplication de version.
- **D-07**: **Rationale documenté** dans tests/e2e/README.md + commentaire dans root package.json (pourquoi root reste). Test gardien : `bin/check-deps.sh` (nouveau) verifie que `@playwright/test` n'est PAS dans root devDependencies.

### TEST-V24-02 — Code-reviewer scope+timeout (D-08..D-10)

- **D-08**: **Modifier `.claude/get-shit-done/agents/gsd-code-reviewer.md`** pour documenter les arguments :
  - `--scope=js|php|tests|all` (filtre via Glob patterns)
  - `--timeout-min=N` (défaut 60, max 120)
  - `--exclude=<glob>` optionnel
- **D-09**: **Pattern de chunking** documenté : si >50 fichiers ou taille >500KB total, l'agent split en 2-3 chunks séquentiels avec checkpoint inter-chunk. Évite le timeout déterministe.
- **D-10**: **Test de validation** : invoquer `/gsd:code-review` sur les 50+ fichiers de v2.4 P1+P2 (operator.htmx.html + ErrorDictionary.php + AdminErrorStatsController.php + ag-integrity-modal.js + sse-debounce.js + tests). Vérifier completion sans timeout. Documenté dans SUMMARY.

### TEST-V24-04 — README e2e (D-11..D-13)

- **D-11**: `tests/e2e/README.md` enrichi avec sections :
  1. **Prerequisites** : Node 18+, PostgreSQL local ou Docker, browsers téléchargés
  2. **First-time setup** : `cd tests/e2e && npm install && sudo npx playwright install --with-deps chromium`
  3. **Running tests** : commands chromium/firefox/webkit/mobile, `--ui` mode, `--debug` mode
  4. **Auth-setup rate-limit** : 5min cooldown assessor — comment l'éviter en dev (env var `TEST_BYPASS_RATELIMIT=1` ou wait time)
  5. **Common pitfalls** : Chromium CDN denied (sandbox), seedMeeting fixtures, fixture cleanup
  6. **Debug procedures** : `--reporter=list`, `--trace=on`, `playwright show-report`
- **D-12**: **Walkthrough fresh-clone** : objectif validation = ≤30 min depuis clone jusqu'au premier test vert. Documenté + checklist dans README.
- **D-13**: **Liens vers** seedMeeting helper, EXPLORE-PATTERNS.md, gsd-code-reviewer doc.

### TEST-V24-05 — EXPLORE-PATTERNS anti-BEM-substring (D-14..D-15)

- **D-14**: **`.planning/codebase/EXPLORE-PATTERNS.md`** créé avec :
  - Header explicite "Quand utiliser quoi pour scanner le codebase"
  - 3 anti-patterns concrets recensés (issus de v2.3 P3 Schoger S-8 où `shortcut-cards` matchait `shortcut-cards__title`)
  - Pattern correct fourni : utiliser `\\b` word boundary ou regex `pattern[^a-zA-Z]` pour éviter prefix substring match
  - Exemples : `grep -E "\\.shortcut-cards\\b"` vs `grep "shortcut-cards"` (mauvais)
  - 1-2 patterns avec test gardien (regex bash valides)
- **D-15**: **Audit BEM scan dans codebase v2.4** : appliquer le pattern à un cas concret (ex: scan de `.op-tab` pour vérifier qu'il ne match pas `.op-tab--current` accidentellement). 1 cas d'usage validation dans le doc.

### Granularité (D-16)

- **D-16**: **2 plans** :
  - **03.1 code/runtime** (TEST-V24-01 seed-meeting + TEST-V24-03 dual-install) — touche PHP backend (endpoint + middleware) + JS helper + package.json + bin script
  - **03.2 docs/agents** (TEST-V24-02 code-reviewer + TEST-V24-04 README + TEST-V24-05 EXPLORE-PATTERNS) — pure docs/agents, 0 code change

Parallel-safe : zones disjointes (code vs docs).

### Tests gardiens (D-17)

- **D-17**: 
  - **PHPUnit** : test endpoint `/api/test/seed-meeting` retourne 404 quand `APP_ENV=production` (`tests/Unit/Controller/TestSeedControllerTest.php` ou similaire)
  - **Bash** : `bin/check-deps.sh` (nouveau) vérifie absence de `@playwright/test` dans root package.json — exit 1 si présent
  - **Playwright** : F-4 test (modal-focus-trap.spec.js `@integration`) doit passer post-implementation
  - **Pas de test** pour docs (TEST-04, 05) — review humain suffit

### Branche & timing (D-18)

- **D-18**: Phase 3 sur branche `feat/v2.4-cockpit-polish` (réutilisée). Démarre **en parallèle Phase 2** (déjà en cours) ou **sequential post-Phase 2 done**. Zones disjointes garantissent 0 conflit.

### Claude's Discretion

- Format exact endpoint response (JSON shape)
- Naming `EnvGuardMiddleware` vs autre
- Format exact des sections README
- Stockage `bin/check-deps.sh` (nouveau bin/) vs intégré dans CI

</decisions>

<canonical_refs>
## Canonical References

### Test infra état actuel
- `package.json` (root) : `@playwright/test 1.59.1` + ESLint + chart.js + htmx.org
- `tests/e2e/package.json` : `@playwright/test 1.59.1` + axe-core (SOT cible)
- `tests/e2e/helpers/` : `axeAudit.js` + `waitForHtmxSettled.js` (pas de seedMeeting)
- `tests/e2e/specs/modal-focus-trap.spec.js` : F-4 `@integration` skippé pending seedMeeting

### Backend patterns à réutiliser
- `app/Application.php` : `APP_ENV` détection
- `app/Core/Middleware/` : pattern middleware
- `app/Core/Providers/EnvProvider::load()` : env vars
- `app/Repository/MeetingRepository.php` : insertion meetings (réutiliser pour seed)
- `app/routes.php` : pattern route déclaration

### GSD agent file
- `.claude/get-shit-done/agents/gsd-code-reviewer.md` (target TEST-02)
- `.planning/codebase/` : dossier existant pour EXPLORE-PATTERNS.md

### v2.3 P3 Schoger S-8 (source TEST-05)
- `.planning/phases/03-layouts-secondaires/03.2-SUMMARY.md` (v2.3) — count erroné scan initial dû à BEM substring match. Source du pattern à documenter.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`api_ok()` / `api_fail()`** : helpers JSON response réutilisables pour endpoint seed
- **`MeetingRepository::create()`** ou équivalent : pour insertion réelle
- **`audit_log()`** : helper global pour traçabilité endpoint dev
- **Pattern middleware** : `app/Core/Middleware/AuthMiddleware.php` (référence pour EnvGuardMiddleware)
- **`bin/console`** : pattern existant CLI commands

### Established Patterns
- **Constructor injection nullable** (CLAUDE.md) — applicable au TestSeedController
- **`HtmlView::render()`** non applicable (endpoint API JSON, pas HTML)
- **`final` classes** sur services et controllers (CLAUDE.md)

### Integration Points
- **modal-focus-trap.spec.js F-4** : test à activer post-seedMeeting
- **`bin/check-deps.sh`** (nouveau) potentiellement intégré dans `composer.json` ou CI hook (out of scope)

### Audit baseline
- Root `@playwright/test 1.59.1` : présent dans `devDependencies`
- `tests/e2e/@playwright/test 1.59.1` : présent (égal version)
- `seedMeeting` : 0 implémentation actuelle, 0 helpers/seed-*.js

</code_context>

<specifics>
## Specific Ideas

- **Endpoint dev-only** : appliquer la règle `404 si production` strictement. Le code de Test-Dev-Endpoints ne doit JAMAIS leak en prod (audit + middleware + assertion runtime).
- **`bin/check-deps.sh`** : 1 ligne shell, run en pre-commit ou CI — `grep -q '"@playwright/test"' package.json && exit 1 || exit 0`
- **Walkthrough ≤30 min** : objectif chronométré dans README — checklist concrète permettant à un dev de mesurer.
- **EXPLORE-PATTERNS.md** : 3 cas d'usage concrets (CSS BEM, JS module names, PHP namespaces), pas un tutoriel théorique.

</specifics>

<deferred>
## Deferred Ideas

- **Tests unit PHP supplémentaires** non liés aux 5 reqs — backlog v2.5+
- **CI optimization** (parallélisation, cache) — backlog v2.5+
- **Migration ESLint v9 flat config** — backlog v2.5+
- **Auto-provisioning DB Postgres pour fresh-clone** (Docker compose dev) — backlog v2.5+
- **Tests visual regression** Playwright — backlog v2.6+

</deferred>

---

*Phase: 03-test-infrastructure*
*Context gathered: 2026-05-04*
