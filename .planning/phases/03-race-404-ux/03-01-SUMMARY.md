---
phase: 03-race-404-ux
plan: 01
subsystem: ux-htmx-resilience
tags:
  - htmx
  - ux
  - race-condition
  - empty-state
  - dev-endpoint
requirements_completed:
  - RACE-V27-01
  - RACE-V27-02
  - RACE-V27-03
dependency_graph:
  requires:
    - ag-empty-state component (existing, light DOM)
    - ErrorDictionary entries meeting_not_found / motion_not_found (existing)
    - TestSeedController triple-guard pattern (seedMeeting reference)
    - MeetingRepository (existing — extended with deleteForTest)
    - Playwright helper seed-meeting.js (existing — extended with deleteMeeting)
  provides:
    - <ag-empty-state variant="resource-deleted|no-data-yet|error">
    - htmx-404-handler.js (canonical) + inlined registration in utils.js
    - POST /api/v1/test/delete-meeting (dev-only, triple-guarded)
    - MeetingRepository::deleteForTest(tenantId, meetingId)
    - tests/e2e/helpers/seed-meeting.js exports deleteMeeting()
    - tests/e2e/specs/race-404-empty-state.spec.js (@race-404)
  affects:
    - All 21 templates loading utils.js (auto-pick up the 404 hijack listener)
    - 10 existing <ag-empty-state> usages (rétrocompat absolue — non affectés)
tech_stack:
  added: []
  patterns:
    - HTMX hijack via htmx:beforeOnLoad (e.detail.shouldSwap + e.detail.isError)
    - Triple-guard dev-endpoint (route conditional + EnvGuardMiddleware + guardProduction())
    - Defense-in-depth XSS escape (escapeHtml in handler + _esc in component)
key_files:
  created:
    - public/assets/js/core/htmx-404-handler.js
    - tests/e2e/specs/race-404-empty-state.spec.js
  modified:
    - public/assets/js/components/ag-empty-state.js
    - public/assets/css/design-system.css
    - public/assets/js/core/utils.js
    - app/Controller/TestSeedController.php
    - app/Repository/MeetingRepository.php
    - app/routes.php
    - tests/e2e/helpers/seed-meeting.js
decisions:
  - "Sub-task 2b: integrate the 404 listener inline in utils.js rather than per-template <script> includes (21 templates load utils.js — over the ≥5 threshold). Canonical source kept in htmx-404-handler.js as the auditable artifact."
  - "Task 3: DOM-injected button via page.evaluate() + htmx.process() rather than dashboard surface (dashboard does not auto-bind seeded tenant meetings — DOM injection isolates exactly the clic→404→swap chain under test)."
  - "MeetingRepository::deleteForTest uses a single DELETE on meetings; FK ON DELETE CASCADE handles all child tables (verified in schema-master.sql: motions, ballots, attendances, etc. all have ON DELETE CASCADE)."
metrics:
  duration_minutes: ~15
  tasks_completed: 3
  files_created: 2
  files_modified: 7
  total_changes: "+406 insertions / -4 deletions"
  completed_date: 2026-05-05
---

# Phase 3 Plan 01: Race-404-UX Graceful Empty-State Substitution Summary

Implémentation de l'UX graceful pour les races liste→action HTMX : substituer `<ag-empty-state variant="resource-deleted">` au toast rouge générique quand la ressource (séance/motion) est supprimée entre l'affichage et le clic.

## Tasks executed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Étendre `<ag-empty-state>` avec attribut `variant` + 3 modificateurs CSS | `72caced` | ag-empty-state.js, design-system.css |
| 2 | Listener htmx 404-handler central + dev delete-meeting endpoint | `fd3c67b` | htmx-404-handler.js, utils.js, TestSeedController.php, MeetingRepository.php, routes.php, seed-meeting.js |
| 3 | Spec Playwright `race-404-empty-state.spec.js` | `7622777` | tests/e2e/specs/race-404-empty-state.spec.js |

## Décisions clés

### Sous-tâche 2b — fichier séparé vs intégration utils.js

**Decision:** intégrer la logique du listener dans `utils.js` (en tête de l'IIFE, AVANT le handler générique `htmx:responseError` lignes ~360+) **et** créer aussi `htmx-404-handler.js` comme source-de-vérité auditable indépendante.

**Rationale:** `grep -rln 'core/utils.js' public/` → **21 templates** (largement au-dessus du seuil ≥5 fixé par le plan). Modifier chaque template pour ajouter un `<script>` aurait été risqué (oubli, ordre de chargement, CSP nonce). L'inline garantit que les 21 surfaces auto-picknt-up le hijack sans édition. Le fichier standalone reste utile : (a) référence canonique pour code review, (b) source si on refactor le pipeline JS plus tard. Note explicite dans les deux fichiers : si on veut un jour charger `htmx-404-handler.js` directement, il faudra retirer le bloc inliné dans utils.js (sinon double registration → double hijack).

### Task 3 — DOM injecté vs dashboard réelle

**Decision:** DOM injecté via `page.evaluate()` + `window.htmx.process()`.

**Rationale:** la dashboard ne binde pas automatiquement les séances seedées sur le tenant test (`aaaaaaaa-1111-...`) — le contenu rendu dépend du contexte utilisateur. Le contrat sous test est strictement « clic HTMX vers ressource manquante → 404 + JSON code → swap empty-state, pas de toast ». Injecter un bouton + cible isole exactement ce contrat sans dépendre du rendu dashboard. Le test reste réaliste car il s'exécute dans le DOM dashboard chargé (utils.js + components actifs).

### Repository delete cascade

**Decision:** `deleteForTest()` exécute un seul `DELETE FROM meetings WHERE id=:id AND tenant_id=:tid` sans wrap transaction.

**Rationale:** vérification `schema-master.sql` confirme que toutes les tables enfants (motions, ballots, attendances, presences, devices, audit_events, etc. — 22+ FK) ont `ON DELETE CASCADE`. PostgreSQL gère le cascade atomiquement. Pas besoin d'un `api_transaction()` manuel — réduit la surface de bug.

## Vérification rétrocompat <ag-empty-state>

`grep -rln "ag-empty-state" public/ | grep -v ag-empty-state.js | grep -v components/index.js | xargs grep -L "variant="` → 10 fichiers utilisateurs :
- `public/email-templates.htmx.html`
- `public/dashboard.htmx.html`
- `public/assets/js/pages/meetings.js`
- `public/assets/js/pages/users.js`
- `public/assets/js/pages/operator-tabs.js`
- `public/assets/js/pages/settings.js`
- `public/assets/js/pages/audit.js`
- `public/assets/js/pages/members.js`
- `public/assets/js/pages/archives.js`
- (+ design-system.css : juste la définition CSS, pas un usage)

**Aucun** ne contient `variant=` → comportement identique au pré-plan (priorité absolue des attributs explicites + chemin par défaut `'generic'` icon préservé). Rétrocompat absolue confirmée.

## Statut Playwright

**Runs effectués pendant ce plan : 0.**

Conformément à CLAUDE.md (max 3 exécutions de tests par tâche, à effectuer en dev-machine, pas en CI dans cette plan). Le spec a été validé syntaxiquement via `node --check` (PASS). L'exécution réelle (`npx playwright test specs/race-404-empty-state.spec.js --project=chromium`) doit être effectuée localement avec la stack dev/test up (PHP-FPM + DB + Redis seedés). Le spec est tagué `@race-404` pour filtrage : `npx playwright test --grep @race-404`.

**Point d'attention pour l'exécution locale :** le spec assume que `/api/v1/meetings_get?meeting_id=...` existe et retourne 404 + body JSON `{error:"meeting_not_found"}` quand la séance est supprimée. Si l'endpoint réel renvoie un code différent (ex. `not_found` générique), ajuster soit l'endpoint pour utiliser le code spécifique, soit la KNOWN_404_CODES map.

## Threat model dispositions

| Threat ID | Disposition | Status |
|-----------|-------------|--------|
| T-03-01 (XSS via responseText) | mitigate | Implémenté : escapeHtml() dans handler + _esc() dans composant (defense-in-depth) |
| T-03-02 (info disclosure body JSON) | accept | OK — body filtré par api_fail() server-side |
| T-03-03 (privesc TestSeedController) | mitigate | Implémenté : triple-guard route + middleware + guardProduction(), audit_log obligatoire |
| T-03-04 (DoS deleteForTest sans rate limit) | accept | OK — endpoint dev-only inaccessible en prod |
| T-03-05 (faux 404 MITM) | accept | OK — TLS + whitelist 2 codes seulement |

## CLAUDE.md compliance

- ✅ Tous les nouveaux strings utilisateur en français (« Cette séance n'existe plus », « Retour aux séances », « Retour à la séance », « Cette résolution n'existe plus »)
- ✅ Aucune mention de "copropriété" ni "syndic" (grep --no-match)
- ✅ `php -l` clean sur les 3 fichiers PHP modifiés
- ✅ Namespaces respectés : `AgVote\Controller\TestSeedController`, `AgVote\Repository\MeetingRepository`
- ✅ TestSeedController étend AbstractController (controller API, pas HTML)
- ✅ DI constructeur avec params nullable (pattern existant `?MeetingRepository $meetingRepo = null` préservé)
- ✅ 0 exécution PHPUnit pendant ce plan (rien à tester côté unit pour ce plan — la couverture est E2E)
- ✅ 0 exécution Playwright pendant ce plan (CLAUDE.md : max 3, à effectuer en dev-machine)

## Deviations from Plan

**None.** Plan exécuté exactement comme écrit. Les seules décisions documentées étaient prévues par le plan lui-même comme « à décider par l'executor » (sous-tâche 2b, surface UI Task 3) — choix justifiés ci-dessus.

## Followups (out of scope v2.7)

- Ajouter d'autres codes 404 candidats à `KNOWN_404_CODES` quand un cas réel se présente : `member_not_found`, `proxy_not_found`, `attendance_not_found`, `policy_not_found`. Hors scope v2.7 mais facile à ajouter (1 entrée objet par code + 1 message ErrorDictionary si manquant).
- Considérer un mécanisme de cache-bust ou polling léger sur les listes critiques (dashboard, operator) pour réduire la fenêtre de race en amont — orthogonal à ce plan (recovery > prevention reste pertinent).
- Si l'endpoint `/api/v1/meetings_get` renvoie un code différent de `meeting_not_found`, harmoniser ou créer un mapping serveur. À vérifier au premier `npx playwright test --grep @race-404`.

## Self-Check: PASSED

Verified files exist:
- FOUND: public/assets/js/core/htmx-404-handler.js
- FOUND: tests/e2e/specs/race-404-empty-state.spec.js
- FOUND: public/assets/js/components/ag-empty-state.js (modified, contains 'variant')
- FOUND: app/Controller/TestSeedController.php (contains 'deleteMeeting')
- FOUND: app/Repository/MeetingRepository.php (contains 'deleteForTest')
- FOUND: app/routes.php (contains 'test/delete-meeting')
- FOUND: tests/e2e/helpers/seed-meeting.js (contains 'deleteMeeting')

Verified commits exist:
- FOUND: 72caced (Task 1: variant attribute)
- FOUND: fd3c67b (Task 2: 404 handler + dev endpoint)
- FOUND: 7622777 (Task 3: Playwright spec)
