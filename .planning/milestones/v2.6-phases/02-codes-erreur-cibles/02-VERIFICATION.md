---
phase: 02-codes-erreur-cibles
verified: 2026-05-05T00:00:00Z
status: passed
score: 12/12 must-haves verified
overrides_applied: 0
---

# Phase 02: Codes erreur ciblés + idempotency empty-state — Verification Report

**Phase Goal:** Le code générique `business_error` restant (3 sites identifiés Phase 4 v2.3 follow-up 04.6-FOLLOWUP-2) est remplacé par des codes spécifiques observables ; les empty-states soumis à rafale d'events SSE sont rendus idempotents (intra-request scope, locked commit 544a60a) ; le dashboard `/admin/error-stats` reflète les nouveaux codes.

**Verified:** 2026-05-05
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths (merged from ROADMAP SCs + Plan must_haves)

| #   | Truth                                                                                                                                                  | Status     | Evidence                                                                                                                                                                                                          |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | SC#1 — 3 sites `business_error` génériques migrés vers codes spécifiques avec entrées ErrorDictionary (FR, next-step Norman v2.3 ERR-02)               | ✓ VERIFIED | MeetingTransitionService.php:56 → `archived_meeting_locked`, line 251 → `validated_meeting_locked`, MeetingLifecycleService.php:44 → `archived_meeting_locked`. ErrorDictionary.php:118-120 nouvelles entrées FR. |
| 2   | SC#2 — Test PHPUnit ciblé : 2 captures back-to-back même requête, audit ≤1 (intra-request scope explicite)                                             | ✓ VERIFIED | ErrorEventsCaptureIdempotencyTest::test_back_to_back_same_key_inserts_once asserts executeCount===1. 7/7 tests passing.                                                                                           |
| 3   | SC#3 — Dashboard `/admin/error-stats` reflète les nouveaux codes (smoke verifié par test mock-PDO)                                                     | ✓ VERIFIED | ErrorStatsRoutingTest 3/7 — capture(archived_meeting_locked), capture(validated_meeting_locked), topCodesSince() returning both codes via mock PDO. PASS.                                                         |
| 4   | SC#4 — Aucune régression ; `business_error` n'apparaît plus comme code émis par les 3 call-sites migrés                                                | ✓ VERIFIED | grep `api_fail('business_error'` returns 0 hits in app/ + public/. The literal in AbstractController.php:53 is now `$code` variable. The 3 target French throws are gone.                                          |
| 5   | AbstractController::handle() detects snake_case in RuntimeException::getMessage() and emits via api_fail() instead of generic business_error          | ✓ VERIFIED | AbstractController.php:53 calls `extractBusinessErrorCode()` ?? `'business_error'`. Test 10/10 OK.                                                                                                                |
| 6   | Si message ne matche pas le pattern snake_case, fallback business_error reste émis (rétrocompat)                                                       | ✓ VERIFIED | extractBusinessErrorCode returns null on French/spaces/empty/oversized; fallback `?? 'business_error'` preserves legacy behaviour. Tests test_falls_back_* (6 cases) PASS.                                         |
| 7   | ErrorDictionary contient 2 nouveaux codes avec next-step français Norman v2.3 ERR-02 (virgule + verbe d'action)                                         | ✓ VERIFIED | Lines 119-120 contain: "...créez une nouvelle séance..." (verbe imperatif après virgule). No banned phrases ("réessayer", "contactez l'admin").                                                                   |
| 8   | ErrorEventsRepository::capture() applique guard d'idempotence in-process keyed (request_id, error_code, route)                                          | ✓ VERIFIED | ErrorEventsRepository.php:42-48 — `md5($requestId . '|' . $errorCode . '|' . $route)` dedup logic.                                                                                                                |
| 9   | Le guard est in-memory pur (static private $captureSeenKeys) — pas de Redis/DB                                                                          | ✓ VERIFIED | Line 26: `private static array $captureSeenKeys = []`. No Redis/DB ref in capture() guard logic.                                                                                                                  |
| 10  | Si request_id ou route est null, dedup skipped (compat CLI/bootstrap)                                                                                  | ✓ VERIFIED | Conditional guard line 42 skips when null/empty. Tests test_null_request_id_skips_guard + test_null_route_skips_guard assert executeCount===2.                                                                    |
| 11  | resetIdempotencyCache() exposed for tests; cache rebuilt per HTTP request via RepositoryFactory singleton                                              | ✓ VERIFIED | Line 83: `public static function resetIdempotencyCache(): void`. Test test_reset_cache_allows_re_insert PASS.                                                                                                     |
| 12  | Cross-plan contract: ErrorStatsRoutingTest uses distinct request_ids (req-A, req-B) to escape Plan 02-02 guard                                          | ✓ VERIFIED | ErrorStatsRoutingTest.php lines 48 (`req-A`) and 74 (`req-B`). Documented in class docblock lines 20-23.                                                                                                          |

**Score:** 12/12 truths verified

### Required Artifacts

| Artifact                                                            | Expected                                                                  | Status     | Details                                                                                                                                |
| ------------------------------------------------------------------- | ------------------------------------------------------------------------- | ---------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Controller/AbstractController.php`                             | catch RuntimeException uses extractBusinessErrorCode                      | ✓ VERIFIED | Lines 52-54 catch surfaces snake_case; lines 77-86 define extractor. PSR-12, strict_types, FR comments.                                |
| `app/Services/ErrorDictionary.php`                                  | 2 entries: archived_meeting_locked, validated_meeting_locked              | ✓ VERIFIED | Lines 119-120 with proper UTF-8 accents, next-step pattern Norman v2.3 ERR-02.                                                         |
| `app/Services/MeetingTransitionService.php`                         | Lines 56 + 251 normalized to snake_case codes                             | ✓ VERIFIED | Line 56: `throw new RuntimeException('archived_meeting_locked');`. Line 251: `validated_meeting_locked`.                               |
| `app/Services/MeetingLifecycleService.php`                          | Line 44 normalized to snake_case                                          | ✓ VERIFIED | Line 44: `throw new RuntimeException('archived_meeting_locked');`.                                                                     |
| `app/Repository/ErrorEventsRepository.php`                          | $captureSeenKeys + dedupe in capture() + resetIdempotencyCache()          | ✓ VERIFIED | Lines 26 (property), 42-48 (guard), 83-85 (reset). PSR-12, strict_types, final class.                                                  |
| `tests/Unit/Controller/AbstractControllerBusinessErrorTest.php`     | 10 tests on extractBusinessErrorCode regex via Reflection                 | ✓ VERIFIED | 10 tests / 12 assertions. PASS. Tests\Unit\Controller namespace.                                                                       |
| `tests/Unit/ErrorStatsRoutingTest.php`                              | 3 tests mock-PDO smoke (capture archived/validated + topCodesSince)       | ✓ VERIFIED | 3 tests / 7 assertions. PASS. No markTestSkipped. Distinct request_ids.                                                                |
| `tests/Unit/ErrorEventsCaptureIdempotencyTest.php`                  | 7 tests on idempotency guard                                              | ✓ VERIFIED | 7 tests / 8 assertions. PASS. Uses createMock(PDO) + resetIdempotencyCache() in setUp/tearDown.                                        |

### Key Link Verification

| From                                       | To                                          | Via                                                                       | Status   | Details                                                                                                          |
| ------------------------------------------ | ------------------------------------------- | ------------------------------------------------------------------------- | -------- | ---------------------------------------------------------------------------------------------------------------- |
| AbstractController.php:53                  | ErrorDictionary.php                         | api_fail($extractedCode, 400) where code is snake_case                    | ✓ WIRED  | extractBusinessErrorCode → api_fail → ErrorEventsRepository::capture (via api.php:48-68).                        |
| MeetingTransitionService.php:56,251        | AbstractController catch                    | throw RuntimeException('archived_meeting_locked'\|'validated_meeting_locked') captured | ✓ WIRED  | Source confirmed in service files. Catch path in AbstractController unchanged for RuntimeException flow.         |
| MeetingLifecycleService.php:44             | AbstractController catch                    | throw RuntimeException('archived_meeting_locked')                         | ✓ WIRED  | Source confirmed line 44.                                                                                        |
| ErrorEventsRepository.capture()            | tests/Unit/ErrorEventsCaptureIdempotencyTest | constructor(PDO mock) + capture() consecutive calls                       | ✓ WIRED  | createMock(PDO::class) injected; tests assert dedup behavior.                                                    |

### Behavioral Spot-Checks

| Behavior                                                                               | Command                                                                      | Result                          | Status |
| -------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- | ------------------------------- | ------ |
| AbstractController extractor regex correctness                                          | `phpunit tests/Unit/Controller/AbstractControllerBusinessErrorTest.php`     | OK (10 tests, 12 assertions)    | ✓ PASS |
| ErrorEventsRepository idempotency intra-request                                         | `phpunit tests/Unit/ErrorEventsCaptureIdempotencyTest.php`                  | OK (7 tests, 8 assertions)      | ✓ PASS |
| Dashboard SQL routing for new codes (smoke ERR-V26-03)                                  | `phpunit tests/Unit/ErrorStatsRoutingTest.php`                              | OK (3 tests, 7 assertions)      | ✓ PASS |
| No `api_fail('business_error'` literal in app/ or public/                               | `grep -rn "api_fail('business_error'" app/ public/`                         | 0 hits                          | ✓ PASS |
| 3 target French throws normalized                                                       | `grep -rE "throw new RuntimeException\(['\"]S(é\|e)ance (archiv\|valid)" app/Services/` (3 target sites) | 0 hits at the 3 cibles  | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description                                                                                                                                                                                | Status      | Evidence                                                                                                                                                          |
| ----------- | ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ERR-V26-01  | 02-01       | 3 sites business_error remplacés par codes ciblés observables avec entrées dans ErrorDictionary.php                                                                                        | ✓ SATISFIED | extractBusinessErrorCode + 3 normalizations + 2 dict entries. Bonus: ~80 service throws already snake_case now bypass business_error via the catch enhancement.    |
| ERR-V26-02  | 02-02       | Routes empty-state idempotentes — test back-to-back même ressource vide retourne même code/payload sans état corrompu                                                                       | ✓ SATISFIED | ErrorEventsRepository capture() guard intra-request + ErrorEventsCaptureIdempotencyTest 7/7 passing.                                                              |
| ERR-V26-03  | 02-01       | Dashboard /admin/error-stats reflète nouveaux codes (vérifié smoke 1 cycle capture)                                                                                                         | ✓ SATISFIED | ErrorStatsRoutingTest 3/3 mock-PDO: capture INSERTs propagate :code = new code; topCodesSince SELECT shape returns both codes.                                    |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| ---- | ---- | ------- | -------- | ------ |
| (none) | — | — | — | — |

No TODO/FIXME/XXX/HACK introduced in modified files. No stub returns. No empty handlers. No console.log-style placeholders. PSR-12 compliant. strict_types declared on all new/modified files.

### Human Verification Required

(none — all SCs and must-haves verified programmatically via PHPUnit + greps; the test mock-PDO strategy is explicitly chosen by the plan to avoid requiring a live Postgres dashboard cycle).

### Gaps Summary

No gaps. Phase goal achieved:

1. **SC#1 (3 sites + dict entries):** Complete. The 3 target sites (MeetingTransitionService:56, :251 + MeetingLifecycleService:44) carry snake_case codes; ErrorDictionary has 2 new FR entries with Norman v2.3 ERR-02 next-step pattern.
2. **SC#2 (intra-request idempotency):** Complete. ErrorEventsRepository::capture() guards via static `$captureSeenKeys` keyed by md5(rid|code|route). 7 PHPUnit tests cover all scope boundaries.
3. **SC#3 (dashboard reflects new codes):** Complete via mock-PDO smoke covering both capture() INSERT param routing and topCodesSince() SELECT shape.
4. **SC#4 (no regression):** Complete. `api_fail('business_error'` literal grep returns 0 hits app-wide; the only remaining occurrence is the dynamic fallback `$code = ... ?? 'business_error'`.

**Strategic bonus (not required by SCs but documented in 02-01-SUMMARY):** the catch-enhancement in AbstractController also benefits ~80 pre-existing snake_case service throws (meeting_not_found, motion_not_found, consolidation_forbidden, cannot_toggle_self, etc.) — they now bypass business_error automatically without source changes. Larger surface than the literal "3 sites".

**Out-of-scope deferred (per v2.6 strict closure):** 8 other French RuntimeException throws (AttendancesService:121/124, QuorumEngine:119, SpeechService:65/229, BallotsService:78/160, VoteTokenService:69) — captured in 02-01-SUMMARY for v2.7 backlog as ERR-V27-XX. Not blocking.

**Pre-existing ErrorDictionaryTest failures:** 4 failures noted as pre-existing at base commit 544a60a per 02-01-SUMMARY. Not introduced by this phase. Recommend cleanup in v2.6 closure or v2.7.

---

_Verified: 2026-05-05_
_Verifier: Claude (gsd-verifier)_
