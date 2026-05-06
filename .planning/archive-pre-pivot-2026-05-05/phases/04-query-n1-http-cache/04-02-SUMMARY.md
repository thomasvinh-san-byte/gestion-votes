---
phase: 04-query-n1-http-cache
plan: 02
subsystem: performance

tags: [http-cache, etag, 304, htmx, dashboard, audit, archives]

# Dependency graph
requires:
  - phase: 03-pre-v2.7
    provides: Stable JsonResponse + ApiResponseException pattern via api_ok()
provides:
  - app/Core/Http/HttpCache.php — reusable ETag/304 primitive (etagFor + sendOk)
  - JsonResponse::send() RFC-7232/7230 compliance for 304/204
  - ETag wiring on 3 GET HTMX hot endpoints (dashboard, audit timeline, archives)
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "HTTP cache primitive: HttpCache::sendOk(\\$payload) replaces api_ok(\\$payload) on idempotent GET endpoints"
    - "Deterministic ETag = '\"' . md5(json_encode(payload)) . '\"' (strong validator per RFC 7232 §2.3)"
    - "304 short-circuit: thrown via ApiResponseException(JsonResponse(304, [], headers)) with empty body"
    - "Cache-Control: private, must-revalidate (multi-tenant safe, forces revalidation per request)"
    - "Test pattern: capture response headers via try/catch ApiResponseException + getResponse()->getHeaders()"

key-files:
  created:
    - app/Core/Http/HttpCache.php
    - tests/Unit/Core/Http/HttpCacheTest.php
    - tests/Unit/Controller/EtagHotEndpointsTest.php
    - .planning/phases/04-query-n1-http-cache/deferred-items.md
  modified:
    - app/Core/Http/JsonResponse.php
    - app/Controller/DashboardController.php
    - app/Controller/AuditController.php
    - app/Controller/MeetingsController.php

key-decisions:
  - "Wired exactly 3 mandatory endpoints (dashboard, audit timeline, archives list) — skipped optional MembersController::index and AuditController::meetingEvents to keep scope minimal and align with the spec's '3-5' lower bound"
  - "Patched JsonResponse::send() to skip body for 304/204 (RFC 7232/7230 compliance) instead of accepting the '[]' fallback — keeps response strictly RFC-conform and exposes a getHeaders() accessor for tests"
  - "ETag computed via md5(json_encode(\\$payload)) — fast, deterministic; md5 is a cache key, not a security signature"
  - "Cache-Control 'private, must-revalidate' instead of 'public' — multi-tenant safety: never let intermediaries cache cross-tenant payloads"
  - "Pagination/search query params automatically affect ETag because the payload itself includes them (limit, offset, total) — no special key derivation needed"

patterns-established:
  - "Pattern: HttpCache::sendOk(\\$payload) is a drop-in replacement for api_ok(\\$payload) on GET idempotent endpoints"
  - "Pattern: tests capture response headers via custom callWithHeaders() helper that mirrors ControllerTestCase::callController() but exposes \\$response->getHeaders()"
  - "Pattern: round-trip 200 -> 304 verified by chaining two callWithHeaders() invocations with the same fixture and HTTP_IF_NONE_MATCH set to the captured ETag"

requirements-completed: [PERF-V27-03]

# Metrics
duration: ~25min
completed: 2026-05-05
---

# Phase 4 Plan 02: HTTP cache layer (ETag + 304) — Summary

**Primitive `HttpCache` (etagFor + sendOk) wirée sur 3 GET HTMX hot endpoints (dashboard, audit timeline, archives list) — round-trip 200→304 prouvé par 9 tests d'intégration + 7 tests unitaires de la primitive. JsonResponse::send() patché RFC-7232/7230 (skip body sur 304/204). PERF-V27-03 ✓.**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-05-05 (post-04-01 already on main)
- **Completed:** 2026-05-05
- **Tasks:** 2 (HttpCache primitive TDD + endpoints wiring TDD)
- **Files modified/created:** 8 (1 new primitive, 1 patched core, 3 controllers wired, 2 test files, 1 deferred-items log)

## Accomplishments

- **Primitive `HttpCache`** (`app/Core/Http/HttpCache.php`) :
  - `etagFor(array $payload): string` — déterministe, format `"<32 hex>"` (md5 quoted, RFC 7232 §2.3 strong validator).
  - `sendOk(array $payload): never` — drop-in replacement pour `api_ok($payload)` : émet 200+ETag normalement, ou court-circuite à 304 quand `If-None-Match` matche.
- **JsonResponse::send() RFC-conform** : skip body et Content-Type pour status 304/204 (RFC 7232 §4.1, RFC 7230 §3.3.2). `getHeaders()` accessor ajouté pour testabilité.
- **3 GET HTMX hot endpoints wirés** (PERF-V27-03 ✓) :
  - `DashboardController::index` → `GET /api/v1/dashboard` (commit `fb130f3`)
  - `AuditController::timeline` → `GET /api/v1/audit_log` (commit `c6af535`)
  - `MeetingsController::archivesList` → `GET /api/v1/meeting_archive_list` (commit `4758aea`)
- **16 nouveaux tests PHPUnit** (7 unitaires HttpCache + 9 intégration endpoints) prouvant :
  - ETag déterminisme (même payload → même ETag, format conforme).
  - Sensibilité au payload (mutation → ETag change).
  - 304 short-circuit (If-None-Match matche → 304 sans body).
  - 200+ETag header sur first call ou If-None-Match obsolète.
  - JsonResponse::send() n'émet pas de body pour 304/204.

## Task Commits

| # | Commit  | Type   | Description                                              |
| - | ------- | ------ | -------------------------------------------------------- |
| 1 | 25f2787 | test   | RED — failing HttpCache primitive tests (7 tests)        |
| 2 | 5f38963 | feat   | GREEN — HttpCache + JsonResponse::send() patch           |
| 3 | 554c002 | test   | RED — failing ETag tests for 3 endpoints (9 tests)       |
| 4 | fb130f3 | perf   | GREEN — wire HttpCache on /api/v1/dashboard              |
| 5 | c6af535 | perf   | GREEN — wire HttpCache on /api/v1/audit_log timeline     |
| 6 | 4758aea | perf   | GREEN — wire HttpCache on /api/v1/meeting_archive_list   |
| 7 | 4355a4e | test   | Tighten test fixture for dashboard payload-change case   |
| 8 | 1fbfe68 | docs   | Log pre-existing MeetingsController failures as deferred |

**Total :** 8 atomic commits.

## Endpoints wirés (3/3 mandatoires)

| Controller            | Method         | Route HTTP                          | Avant         | Après                     |
| --------------------- | -------------- | ----------------------------------- | ------------- | ------------------------- |
| `DashboardController` | `index`        | `GET /api/v1/dashboard`             | `api_ok($d)`  | `HttpCache::sendOk($d)`   |
| `AuditController`     | `timeline`     | `GET /api/v1/audit_log`             | `api_ok([…])` | `HttpCache::sendOk([…])`  |
| `MeetingsController`  | `archivesList` | `GET /api/v1/meeting_archive_list`  | `api_ok([…])` | `HttpCache::sendOk([…])`  |

## Décision : combien d'endpoints optionnels ?

**Aucun ajouté** (3 mandatoires uniquement, borne basse du `3-5` du plan).

Pourquoi :
- Le plan exige `≥3`, validé.
- Les optionnels (`AuditController::meetingEvents`, `MembersController::index`) suivraient le même pattern trivial — peu de valeur de scope additionnel pour ce milestone.
- Garder la surface minimale réduit le risque de régression et facilite un éventuel rollback isolé.
- Si l'audit production identifie d'autres hot paths cacheables (HTMX lazy loads du frontend), un follow-up plan court suffira (5 lignes par endpoint : 1 `use`, 1 `sendOk`, 3 tests).

## Round-trip 200→304 (preuve)

Les tests `EtagHotEndpointsTest::test*Returns304OnIfNoneMatchSameEtag` capturent l'ETag d'un premier appel, puis effectuent un second appel avec `$_SERVER['HTTP_IF_NONE_MATCH'] = $etag`. Assertion : status `304`, body vide, ETag header identique.

Équivalent curl manuel (informational, non-gate) :
```bash
# Premier appel : capture ETag
curl -i 'http://localhost:8080/api/v1/dashboard' \
  | grep -E '(HTTP|ETag|Cache-Control)'
# HTTP/1.1 200 OK
# ETag: "abc123…32hex…"
# Cache-Control: private, must-revalidate

# Second appel avec If-None-Match : 304
curl -i -H 'If-None-Match: "abc123…32hex…"' \
  'http://localhost:8080/api/v1/dashboard' \
  | grep -E '(HTTP|ETag|Cache-Control)'
# HTTP/1.1 304 Not Modified
# ETag: "abc123…32hex…"
# Cache-Control: private, must-revalidate
# (no body)
```

## Files Created/Modified

### Created
- `app/Core/Http/HttpCache.php` — Primitive (etagFor + sendOk).
- `tests/Unit/Core/Http/HttpCacheTest.php` — 7 tests unitaires (déterminisme, format, 304 logic, 200 logic, JsonResponse::send 304/204).
- `tests/Unit/Controller/EtagHotEndpointsTest.php` — 9 tests intégration (3 endpoints × 3 cases).
- `.planning/phases/04-query-n1-http-cache/deferred-items.md` — Log des 6 failures MeetingsControllerTest pré-existantes.

### Modified
- `app/Core/Http/JsonResponse.php` — Skip body pour 304/204, expose `getHeaders()`.
- `app/Controller/DashboardController.php` — `use HttpCache;` + remplace `api_ok($data)` par `HttpCache::sendOk($data)` (fin de `index()`).
- `app/Controller/AuditController.php` — `use HttpCache;` + remplace `api_ok([…])` par `HttpCache::sendOk([…])` (fin de `timeline()`).
- `app/Controller/MeetingsController.php` — `use HttpCache;` + remplace `api_ok([…])` par `HttpCache::sendOk([…])` (corps de `archivesList()`).

## Decisions Made

1. **Drop-in replacement pattern** : `HttpCache::sendOk($payload)` mirror exact d'`api_ok($payload)` (même signature, même throw-based contrat, même body shape `['ok' => true, 'data' => $payload]`). Aucune autre modification de controller nécessaire.
2. **JsonResponse patch RFC-conform** : choisi l'option (a) du plan (skip body pour 304/204) plutôt que (b) (accepter `'[]'`). HTTP intermediaries / HTMX se comportent mieux sur un 304 strictement vide.
3. **md5 pour l'ETag** : non-cryptographique mais c'est un cache key, pas une signature — collision risk négligeable sur des payloads applicatifs. `md5` est ~10× plus rapide que `sha256` (microbench PHP), pertinent sur un hot path.
4. **Cache-Control: private, must-revalidate** : `private` interdit aux proxies de cacher (multi-tenant) ; `must-revalidate` force le client à toujours revalider via `If-None-Match` (jamais servir une copie stale silencieusement).
5. **Tests d'intégration via custom helper `callWithHeaders()`** : mirror de `ControllerTestCase::callController()` mais expose `getResponse()->getHeaders()`. N'a pas modifié `ControllerTestCase` pour minimiser le blast radius sur les 200+ tests existants.

## Deviations from Plan

**Aucune significative.** Plan exécuté exactement comme écrit.

### Adaptations mineures (non-déviations)

1. **Test fixture dashboard `*WhenPayloadChanges`** : la version initiale du test injectait un meeting mutant mais sans configurer `findByIdForTenant` du mock pour le retourner — le controller passait à 404. Fix : enrichi `injectDashboardRepos()` pour configurer tous les mocks de manière cohérente avec la liste de meetings (commit `4355a4e`). C'est une amélioration de la robustesse du fixture, pas un changement de comportement de l'app.

2. **`AuditController::meetingEvents` & `MembersController::index` non-wirés** : optionnels du plan, scope volontairement contenu (cf. section "Décision" ci-dessus).

## Issues Encountered

### 6 failures pré-existantes dans MeetingsControllerTest (out-of-scope)

Détectées en exécutant les tests régression sur les 3 controllers touchés. Tous dans `update()` et `delete()` — méthodes non-touchées par 04-02. Vérifié pré-existantes via `git stash`. Loggées dans `.planning/phases/04-query-n1-http-cache/deferred-items.md`. Per CLAUDE.md SCOPE BOUNDARY rule : ne pas fixer hors scope.

Le test `archivesList` (le seul de `MeetingsControllerTest` impacté par 04-02) passe toujours (2/2 assertions OK).

## Soft Conflict Resolution (avec 04-01)

Le SUMMARY de 04-01 avait flaggé un soft conflict sur `DashboardController.php` :
- 04-01 a modifié les lignes 118-135 (foreach refactor → batch `countByMotionIds`).
- 04-02 modifie les lignes 1 (`use HttpCache;`) et ~144 (`api_ok($data)` → `HttpCache::sendOk($data)`).

**Résolution** : zéro conflit réel. Les éditions sont sur des lignes disjointes. 04-02 a été appliqué directement sur `main` post-merge de 04-01 sans intervention manuelle.

## User Setup Required

**None.** Aucune migration DB, aucune nouvelle dep Composer, aucune env var. Les changements sont rétro-compatibles : tout client HTMX qui n'envoie pas `If-None-Match` reçoit toujours `200 OK` avec body normal (juste 2 headers en plus : `ETag` + `Cache-Control`).

## Next Phase Readiness

- PERF-V27-03 entièrement complété : ≥3 GET HTMX hot endpoints servent un 304 sur revalidation.
- Primitive `HttpCache` réutilisable pour de futurs endpoints idempotents (signature stable, contrat équivalent à `api_ok`).
- Aucune dette technique introduite. Une seule patch micro-invasive sur `JsonResponse::send()` (RFC-conform, gain net).
- Le pattern test (`callWithHeaders` helper) est isolé dans un seul fichier de test — peut être promu vers `ControllerTestCase` si d'autres tests d'intégration ont besoin d'inspecter les headers de réponse.

---

## Self-Check: PASSED

Verified files exist:
- FOUND: app/Core/Http/HttpCache.php
- FOUND: tests/Unit/Core/Http/HttpCacheTest.php
- FOUND: tests/Unit/Controller/EtagHotEndpointsTest.php
- FOUND: .planning/phases/04-query-n1-http-cache/deferred-items.md

Verified commits exist:
- FOUND: 25f2787 (RED HttpCache tests)
- FOUND: 5f38963 (GREEN HttpCache primitive)
- FOUND: 554c002 (RED endpoint tests)
- FOUND: fb130f3 (GREEN dashboard wiring)
- FOUND: c6af535 (GREEN audit timeline wiring)
- FOUND: 4758aea (GREEN archivesList wiring)
- FOUND: 4355a4e (test fixture tightening)
- FOUND: 1fbfe68 (deferred items log)

Verified phase-level checks (from PLAN.md `<verification>`):
- `app/Core/Http/HttpCache.php` exists: PASS
- `etagFor` + `sendOk` static methods present: PASS (1 occurrence each)
- JsonResponse skips body for 304: PASS (`if ($this->statusCode === 304 || $this->statusCode === 204)` present)
- ≥3 controllers wired with `HttpCache::sendOk`: PASS (3 controllers)
- HttpCacheTest passes: PASS (7/7)
- EtagHotEndpointsTest passes: PASS (9/9)
- DashboardControllerTest + AuditControllerTest regression: PASS (48/48)
- MeetingsControllerTest::*ArchivesList* regression: PASS (2/2)
- 6 unrelated MeetingsControllerTest failures (update/delete): pre-existing, logged in deferred-items.md
- `php -l` clean on all 5 modified PHP files: PASS

---
*Phase: 04-query-n1-http-cache*
*Plan: 02 (HTTP cache layer)*
*Completed: 2026-05-05*
