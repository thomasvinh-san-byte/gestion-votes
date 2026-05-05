---
phase: 01-tests-heartbeat
plan: 01
subsystem: testing
tags: [phpunit, sse, heartbeat, refactor, dependency-injection, closure]

requires:
  - phase: v2.5 (HEARTBEAT-V25-03 debt note)
    provides: Free function buildHeartbeatPayload in events.php (untestable in isolation)
provides:
  - "AgVote\\SSE\\HeartbeatPayloadBuilder final class — testable builder for the meeting.heartbeat SSE payload"
  - "tests/Unit/Sse/HeartbeatPayloadTest.php — 8 tests / 29 assertions locking the payload contract"
  - "Closure-based dependency inversion pattern for final-class collaborators"
affects: [v2.6 phase-02 SSE refactors, future heartbeat field additions]

tech-stack:
  added: []
  patterns:
    - "Closure-injected dependency for unit-testing collaborators that are declared final"
    - "Static factory fromQuorumEngine() to keep production wiring concise"

key-files:
  created:
    - app/SSE/HeartbeatPayloadBuilder.php
    - tests/Unit/Sse/HeartbeatPayloadTest.php
  modified:
    - public/api/v1/events.php

key-decisions:
  - "Inject quorum lookup as Closure (not QuorumEngine) because QuorumEngine is final and cannot be mocked by PHPUnit 10.5"
  - "Provide static factory HeartbeatPayloadBuilder::fromQuorumEngine() to keep events.php wiring readable"
  - "Preserve payload shape byte-identical to the original free function — no field added, removed, or renamed"

patterns-established:
  - "Closure-based DI for final collaborators: when a final class blocks createMock(), wrap its method in a Closure parameter and provide a fromXxx() factory for production"

requirements-completed: [TEST-V26-01]

duration: ~12min
completed: 2026-05-05
---

# Phase 01 Plan 01: PHPUnit + extraction du heartbeat Summary

**Extraction de `buildHeartbeatPayload` libre vers la classe finale `AgVote\SSE\HeartbeatPayloadBuilder`, recâblage d'`events.php`, et test PHPUnit (8 tests / 29 assertions) verrouillant la forme du payload `meeting.heartbeat` (5 champs + 6 sous-clés quorum).**

## Performance

- **Duration:** ~12 min
- **Tasks:** 2
- **Files modified:** 3 (1 créé, 1 créé, 1 modifié)

## Accomplishments

- Classe finale `AgVote\SSE\HeartbeatPayloadBuilder` extraite du script SSE, instanciable hors HTTP bootstrap
- `events.php` recâblé via `HeartbeatPayloadBuilder::fromQuorumEngine($quorumEngine, $meetingRepo)` — fonction libre supprimée
- Test PHPUnit `HeartbeatPayloadTest` (8 tests, 29 assertions) verrouillant les 5 champs + 6 sous-clés quorum + 3 voies de dégradation gracieuse (MeetingRepo / quorum closure / Redis throw)
- Test runnable en isolation : `timeout 60 php vendor/bin/phpunit tests/Unit/Sse/HeartbeatPayloadTest.php --no-coverage` exits 0
- Forme du payload strictement préservée (byte-identical avec la fonction libre v2.5)

## Task Commits

1. **Task 1: Extract buildHeartbeatPayload into AgVote\\SSE\\HeartbeatPayloadBuilder** — `403478c` (refactor)
2. **Task 2: Write HeartbeatPayloadTest verifying the 5 required fields** — `0673e78` (test, includes builder closure refactor as Rule 3 deviation)

## Files Created/Modified

- `app/SSE/HeartbeatPayloadBuilder.php` (NEW) — classe finale, `namespace AgVote\SSE`, `declare(strict_types=1)`, méthode `build()` + factory `fromQuorumEngine()`
- `tests/Unit/Sse/HeartbeatPayloadTest.php` (NEW) — `namespace Tests\Unit\Sse`, 8 méthodes de test, mocks `MeetingRepository` + closures pour quorum/Redis
- `public/api/v1/events.php` (MODIFIED) — `use AgVote\SSE\HeartbeatPayloadBuilder`, instanciation via factory, suppression de la fonction libre `buildHeartbeatPayload` (54 lignes retirées)

## Decisions Made

- **Closure-based DI** : `HeartbeatPayloadBuilder` reçoit un `Closure(string, ?string): array` plutôt que `QuorumEngine` directement, car `QuorumEngine` est `final` (convention codebase) et PHPUnit 10.5 ne peut pas mocker une classe `final` sans extension externe (e.g., `dg/bypass-finals`). La factory statique `fromQuorumEngine()` enveloppe la closure pour le câblage de production, gardant `events.php` aussi lisible que la version naïve.
- **Préservation byte-identical** : aucun champ ajouté/retiré/renommé ; les `??` defaults d'origine ont été conservés ; l'ordre d'insertion dans `$payload` est identique à la fonction libre.
- **Test scope** : pas de tests d'intégration (la connexion Redis live n'est pas couverte) — le test verrouille uniquement la *forme* du payload, qui est le contrat HEARTBEAT-V25-03.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Refactored builder to inject quorum as Closure instead of QuorumEngine**

- **Found during:** Task 2 (premier run PHPUnit)
- **Issue:** Le plan spécifie `private readonly QuorumEngine $quorum` et `$this->createMock(QuorumEngine::class)` dans le test. Au premier `phpunit` run, les 8 tests échouent avec `PHPUnit\Framework\MockObject\Generator\ClassIsFinalException: Class "AgVote\Service\QuorumEngine" is declared "final" and cannot be doubled`. PHPUnit 10.5 ne peut pas créer un mock d'une classe `final` sans extension externe. La convention codebase rend `final` toutes les services — donc retirer `final` de QuorumEngine n'est pas envisageable (impact transverse). Construire un vrai `QuorumEngine` avec repos mockés ajoute beaucoup de complexité de setup (≥5 repos par test) sans valeur ajoutée pour le test de *forme du payload*.
- **Fix:** Refactor `HeartbeatPayloadBuilder` pour accepter un `Closure(string, ?string): array` au lieu de `QuorumEngine`. Ajout d'une factory statique `HeartbeatPayloadBuilder::fromQuorumEngine($quorum, $meetingRepo)` qui enveloppe l'appel à `computeForMeeting()` dans une closure. `events.php` utilise désormais la factory ; le test injecte des closures stubs (lambdas) qui retournent le payload canned ou throw selon le scénario. La forme du payload émis reste byte-identical.
- **Files modified:** `app/SSE/HeartbeatPayloadBuilder.php` (signature constructeur + ajout factory), `public/api/v1/events.php` (`new HeartbeatPayloadBuilder(...)` → `HeartbeatPayloadBuilder::fromQuorumEngine(...)`), `tests/Unit/Sse/HeartbeatPayloadTest.php` (utilise des closures plutôt que `createMock(QuorumEngine::class)`)
- **Verification:** `php -l` exits 0 sur les 3 fichiers, `timeout 60 php vendor/bin/phpunit tests/Unit/Sse/HeartbeatPayloadTest.php --no-coverage` exits 0 (8 tests, 29 assertions OK).
- **Committed in:** `0673e78` (commit Task 2)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Le refactor closure est une amélioration de testabilité (dependency inversion) qui ne change pas le contrat externe ni la forme du payload. Aucun scope creep — c'était la voie la plus surgicale pour débloquer le test sans ajouter de dépendance (ex. `dg/bypass-finals`) ou modifier `QuorumEngine` (transverse).

## Issues Encountered

- **vendor/ absent du worktree** : la première tentative de lancer PHPUnit a échoué avec `Could not open input file: vendor/bin/phpunit`. Résolu en exécutant `composer install --no-interaction --no-progress --prefer-dist` dans `/home/user/gestion-votes/` (le projet principal partagé par les worktrees). Le `tests/bootstrap.php` est déjà conçu pour résoudre vendor/autoload.php depuis la racine projet via fallback.
- **Plan acceptance criteria sur final class** : le plan inclut `createMock(QuorumEngine::class)` qui est mécaniquement bloqué par `final`. La déviation Rule 3 ci-dessus résout cela.

## Test Run Budget

Conformément à `CLAUDE.md` (max 3 exécutions PHPUnit par tâche) :
- Run 1 (Task 2) : 8/8 ERROR (ClassIsFinalException) — déclenche la déviation Rule 3
- Run 2 (Task 2 après refactor) : 8/8 OK (29 assertions)
- Run 3 : non utilisé

## Next Phase Readiness

- Builder testable et test verrouillant la forme — toute future modification du payload (ajout de champ, renommage) cassera `HeartbeatPayloadTest`, ce qui est exactement le contrat HEARTBEAT-V25-03 / TEST-V26-01.
- Pattern Closure-based DI réutilisable pour d'autres services nécessitant `QuorumEngine` ou autres classes `final` dans des tests unitaires.

## Self-Check: PASSED

- `app/SSE/HeartbeatPayloadBuilder.php` exists
- `tests/Unit/Sse/HeartbeatPayloadTest.php` exists
- Commit `403478c` (Task 1, refactor) present in git log
- Commit `0673e78` (Task 2, test + Rule 3 deviation) present in git log
- `timeout 60 php vendor/bin/phpunit tests/Unit/Sse/HeartbeatPayloadTest.php --no-coverage` → `OK (8 tests, 29 assertions)`
- `php -l` clean on all three modified files

---
*Phase: 01-tests-heartbeat*
*Completed: 2026-05-05*
