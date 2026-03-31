# Phase 60: Session, Import, and Auth Edge Cases - Research

**Researched:** 2026-03-31
**Domain:** PHP session state machine hardening, CSV encoding, authentication rate limiting
**Confidence:** HIGH

## Summary

Phase 60 is a targeted hardening phase across three independent subsystems. The codebase is already well-structured: state machine logic lives in `Permissions::TRANSITIONS` and `AuthMiddleware::requireTransition()`, import logic is centralized in `ImportService`, and authentication flows through `AuthController` with an existing `RateLimiter` infrastructure. Every required behavior in this phase is a surgical addition to existing code — there is nothing to build from scratch.

The three subsystems are genuinely independent. Session state machine work touches `MeetingWorkflowController::transition()` and `MeetingsController::deleteMeeting()` to improve error messages. CSV import hardening adds encoding detection and a pre-insert duplicate email scan to `ImportService::readCsvFile()` and `ImportController`. Auth hardening adds session-expiry differentiation in `AuthMiddleware::authenticate()` and wires the existing `RateLimiter` into `AuthController::login()` with an audit log call.

All six requirements have clear insertion points. No new classes or infrastructure are needed. Tests follow the established `ControllerTestCase` pattern for controllers and direct `PHPUnit\Framework\TestCase` for service/static method tests. The existing `ImportServiceTest`, `AuthControllerTest`, and `MeetingWorkflowControllerTest` files are the natural homes for new tests.

**Primary recommendation:** Add behavior incrementally — improve error messages first (SESS-01, SESS-02), then add encoding detection to `ImportService::readCsvFile()` and duplicate check to `ImportController::membersCsv()` (IMP-01, IMP-02), then differentiate expiry from unauthenticated in `AuthMiddleware` and add rate-limit audit call in `AuthController::login()` (AUTH-01, AUTH-02).

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Session State Machine Enforcement**
- Invalid transitions return 422 with specific `from_status` and `to_status` in the error response: `"Transition '{from}' → '{to}' non autorisée"`
- Delete-live-session rejection includes hint: `"Fermez d'abord la séance avant de la supprimer"` — existing code already returns 409 for non-draft
- Invalid transition attempts are NOT audit-logged — these are normal user errors, not anomalies

**CSV Import Hardening**
- Detect non-UTF-8 encodings with `mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true)` then `mb_convert_encoding()` — applied at CSV read step before parsing
- Duplicate email detection happens in ImportService/ImportController during validation before insert — collect all emails, detect duplicates, return error listing them
- Duplicate error lists ALL duplicate emails in the response — user fixes the CSV once instead of iterating
- Empty/missing email rows are skipped during duplicate check — only flag actual duplicated email values

**Auth and Session Edge Cases**
- Expired session redirect shows French flash message: `"Votre session a expiré. Veuillez vous reconnecter."` — set as query param before redirect to `/login`
- Rate limiting uses existing `RateLimiter` with configurable `maxAttempts` (default: 5) and `windowSeconds` (default: 300) from `APP_LOGIN_MAX_ATTEMPTS`/`APP_LOGIN_WINDOW` env vars
- Rate limit blocking produces audit entry: `audit_log('auth_rate_limited', 'security', null, {ip, attempt_count, window})` — essential for security monitoring
- Rate limit response includes `Retry-After` header (already in RateLimiter) and French message: `"Trop de tentatives. Réessayez dans X minutes."`

### Claude's Discretion
- Internal code organization and helper structure
- Test structure and naming conventions
- Specific encoding detection order or fallback strategies

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SESS-01 | Invalid state transitions are rejected with explicit message naming from/to status | `AuthMiddleware::requireTransition()` already raises `invalid_transition` (422) — needs enriched message `"Transition '{from}' → '{to}' non autorisée"` |
| SESS-02 | Deletion of a live session is forbidden | `MeetingsController::deleteMeeting()` already rejects non-draft with 409 — needs live-specific hint message |
| IMP-01 | CSV with Windows-1252 or ISO-8859-1 encoding is detected and converted correctly | `ImportService::readCsvFile()` reads raw file bytes — encoding detection with `mb_detect_encoding` before `fgetcsv` is the insertion point |
| IMP-02 | Duplicate emails are detected and reported, no silent duplicates | `ImportController::membersCsv()` processes rows one-by-one with no pre-scan — a pre-insert email deduplication pass is needed |
| AUTH-01 | Expired session redirects to /login with clear message, no blank page | `AuthMiddleware::authenticate()` at line 298-307 destroys session silently and returns null — needs to set a session flag or query param before destroying |
| AUTH-02 | Brute force attempts are blocked and IP is logged in audit trail | Route already has `rate_limit: ['auth_login', 10, 300]` in `routes.php` but no audit log call on block — `AuthController::login()` needs explicit rate-check with audit log before credential validation |
</phase_requirements>

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP mbstring extension | built-in | `mb_detect_encoding()` and `mb_convert_encoding()` | Only reliable way to detect Windows-1252/ISO-8859-1 in PHP without external dependencies |
| PHPUnit | 10.5 (per phpunit.xml schema) | Unit tests | Already project standard |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Redis (via RedisProvider) | project-standard | RateLimiter backend | Production; falls back to file automatically |
| PHP `session_*` functions | built-in | Session management | Already used in AuthMiddleware/SessionHelper |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `mb_detect_encoding` | chardet-based library | No external dep needed; mb_detect_encoding works for the 3 target encodings |
| Session query param for expired message | Server-side flash in $_SESSION | Query param is simpler — no risk of session data surviving after destroy |

**Installation:**
No new packages required. All dependencies are already present.

---

## Architecture Patterns

### Recommended Change Structure

```
app/Core/Security/AuthMiddleware.php   — differentiate session_expired from unauthenticated
app/Controller/AuthController.php     — add explicit rate-limit check with audit before credentials
app/Controller/MeetingsController.php — improve error message for live-session delete (line 557-560)
app/Services/ImportService.php        — add encoding detection to readCsvFile()
app/Controller/ImportController.php   — add duplicate email pre-scan in membersCsv()
```

### Pattern 1: Encoding Detection at Read Time (IMP-01)

**What:** Detect file encoding before `fgetcsv` processes bytes. `fgetcsv` is not encoding-aware — it works on raw bytes. If the file is Windows-1252, multibyte accented chars corrupt silently unless converted to UTF-8 first.

**When to use:** Applied in `ImportService::readCsvFile()` immediately after reading the raw file content, before any `fgetcsv` call.

**Example:**
```php
// In ImportService::readCsvFile() — after fopen, before fgetcsv
$content = file_get_contents($filePath);
if ($content === false) { /* error */ }

$encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
if ($encoding !== false && $encoding !== 'UTF-8') {
    $content = mb_convert_encoding($content, 'UTF-8', $encoding);
}

// Write converted content to a temp file, then use fgetcsv on that
$tmpPath = tempnam(sys_get_temp_dir(), 'csv_enc_');
file_put_contents($tmpPath, $content);
$handle = fopen($tmpPath, 'r');
// ... fgetcsv logic ...
@unlink($tmpPath);
```

**Alternative approach** — stream-based: read file into string, convert, write back to temp, parse. This avoids changing the method signature.

### Pattern 2: Duplicate Email Detection Before Insert (IMP-02)

**What:** Collect all email values from all rows first, find duplicates, return a 422 with all duplicate email addresses listed. Do this before the `api_transaction` block so no rows are inserted.

**When to use:** In `ImportController::membersCsv()` and `ImportController::membersXlsx()`, after column mapping and before `processMemberRows`.

**Example:**
```php
// After $rows and $colIndex are known, before api_transaction:
if (isset($colIndex['email'])) {
    $emailIdx = $colIndex['email'];
    $seen = [];
    $duplicates = [];
    foreach ($rows as $row) {
        $email = strtolower(trim($row[$emailIdx] ?? ''));
        if ($email === '') continue; // skip blank emails per decision
        if (isset($seen[$email])) {
            $duplicates[] = $email;
        }
        $seen[$email] = true;
    }
    $duplicates = array_unique($duplicates);
    if (!empty($duplicates)) {
        api_fail('duplicate_emails', 422, [
            'detail' => 'Le fichier contient des adresses email en double.',
            'duplicates' => $duplicates,
        ]);
    }
}
```

### Pattern 3: Session Expiry Differentiation (AUTH-01)

**What:** When `AuthMiddleware::authenticate()` detects an expired session at lines 298-307, instead of silently returning null, set a temporary session variable (or pass expiry reason via a PHP session re-start before destroy) that the JS can detect via a 401 response with `session_expired` error code.

**The actual mechanism:** The app is an SPA with API calls — authenticated pages call `/api/v1/whoami` on load. If the session is expired, the API returns 401. The JS in `auth-ui.js` already handles 401 by redirecting to `/login.html?redirect=...`. The gap is: the redirect currently has no expiry message.

**Decision says:** set as query param before redirect to `/login`. This is a JS-side concern — the backend needs to return a distinguishable error code so the frontend can append `?expired=1` or `?msg=session_expired`.

**Backend change:** In `AuthMiddleware::authenticate()`, when session is expired, set a marker before destroying so the API can return `'session_expired'` instead of the generic `'authentication_required'`:

```php
if ($lastActivity > 0 && ($now - $lastActivity) > self::SESSION_TIMEOUT) {
    // Store reason before destroying
    $_SESSION['_session_expiry_reason'] = 'timeout';
    $_SESSION = [];
    session_destroy();
    // Signal to requireRole() that this was expiry, not "never logged in"
    // Use a static flag since session is gone
    self::$sessionExpired = true;
    return null;
}
```

Then in `deny()`, check the flag and emit `session_expired` error code instead of `authentication_required`.

**Frontend change in `auth-ui.js`:** Handle `session_expired` error code specifically — redirect to `/login.html?expired=1&redirect=...`. The login page reads `?expired=1` and shows the French flash message.

### Pattern 4: Rate Limit with Audit (AUTH-02)

**What:** The route already has `rate_limit: ['auth_login', 10, 300]` applied by `RateLimitGuard`, which calls `api_rate_limit()` → `RateLimiter::check()`. This blocks at 10 attempts per IP. The configurable threshold (from `APP_LOGIN_MAX_ATTEMPTS`/`APP_LOGIN_WINDOW`) and the audit log on block are missing.

**Current state:** `RateLimitGuard` uses hardcoded values from routes.php (`10, 300`). The block throws a 429 via `denyWithRetryAfter()` with no audit log.

**Required change:** Move rate-limit enforcement into `AuthController::login()` itself (before credential check), reading env vars for thresholds, and calling `audit_log` on block:

```php
// In AuthController::login(), before credential extraction:
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$maxAttempts = (int) (getenv('APP_LOGIN_MAX_ATTEMPTS') ?: 5);
$windowSeconds = (int) (getenv('APP_LOGIN_WINDOW') ?: 300);

$limited = RateLimiter::isLimited('auth_login', $ip, $maxAttempts, $windowSeconds);
if ($limited) {
    $attemptCount = /* get from RateLimiter */ $maxAttempts;
    audit_log('auth_rate_limited', 'security', null, [
        'ip' => $ip,
        'attempt_count' => $attemptCount,
        'window' => $windowSeconds,
    ]);
    $retryMinutes = (int) ceil($windowSeconds / 60);
    api_fail('rate_limit_exceeded', 429, [
        'detail' => "Trop de tentatives. Réessayez dans {$retryMinutes} minutes.",
        'retry_after' => $windowSeconds,
    ]);
}
RateLimiter::check('auth_login', $ip, $maxAttempts, $windowSeconds);
```

Note: `RateLimiter` does not currently expose an attempt-count getter for a specific key without incrementing. Use `isLimited()` for the check and then `check()` to increment. Or check first with a peek method — `isLimited` already exists for this.

### Pattern 5: Invalid Transition Error Message (SESS-01)

**What:** `AuthMiddleware::requireTransition()` currently returns `invalid_transition` with `from`, `to`, `allowed` in debug only. The decision requires the detail message `"Transition '{from}' → '{to}' non autorisée"` in the 422 body.

**Current code (line 514):**
```php
self::deny('invalid_transition', 422, [
    'from' => $fromStatus,
    'to' => $toStatus,
    'allowed' => array_keys($allowed),
]);
```

The `deny()` method only includes `$extra` in debug mode (`self::$debug`). Change: add the human-readable `detail` to the main error body (not debug-only):

```php
// Option A: modify deny() to pass detail in main body
// Option B: throw ApiResponseException directly with full body
api_fail('invalid_transition', 422, [
    'detail' => "Transition '{$fromStatus}' → '{$toStatus}' non autorisée.",
    'from_status' => $fromStatus,
    'to_status' => $toStatus,
    'allowed' => array_keys($allowed),
]);
```

Since `requireTransition` is in `AuthMiddleware` and uses `deny()` not `api_fail()`, the cleanest change is to add a `detail` key to the response body unconditionally (not behind debug flag).

### Pattern 6: Live Session Delete Message (SESS-02)

**Current code (MeetingsController line 557):**
```php
if ((string) $current['status'] !== 'draft') {
    api_fail('meeting_not_draft', 409, [
        'detail' => 'Seules les séances en brouillon peuvent être supprimées.',
    ]);
}
```

**Required change:** Add a live-specific branch with the hint message:
```php
if ((string) $current['status'] === 'live') {
    api_fail('meeting_live_cannot_delete', 409, [
        'detail' => 'Fermez d\'abord la séance avant de la supprimer.',
        'status' => $current['status'],
    ]);
}
if ((string) $current['status'] !== 'draft') {
    api_fail('meeting_not_draft', 409, [
        'detail' => 'Seules les séances en brouillon peuvent être supprimées.',
        'status' => $current['status'],
    ]);
}
```

### Anti-Patterns to Avoid

- **Encoding conversion in a loop per-row:** Apply encoding conversion once on the full file content, not row-by-row. Row-by-row `mb_convert_encoding()` on strings from `fgetcsv` is unreliable because `fgetcsv` already split bytes before conversion.
- **Duplicate check after insert attempt:** Never attempt DB insert and rollback on duplicate email. Do the pre-scan before opening any transaction.
- **Audit-log every failed login attempt:** Only audit-log rate-limit blocks (`auth_rate_limited`). Individual login failures are already logged by `userRepo->logAuthFailure()` — do not add a second audit entry for each failure.
- **Hardcoding the session expiry flag in the response body of a JSON API:** The frontend SPA reads API error codes, not HTML messages. Use a distinct error code (`session_expired`) so the JS can route the message correctly.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Encoding detection | Custom byte-scanning heuristic | `mb_detect_encoding()` | mbstring handles BOM, encoding order, strict mode properly |
| Rate limiting storage | Custom Redis/file counter | Existing `RateLimiter::check()` / `isLimited()` | Already handles Redis + file fallback, locking, sliding window |
| State machine transition map | Copy-paste of allowed transitions | `Permissions::TRANSITIONS` | Single source of truth — already used by `requireTransition()` |
| Audit logging | Custom DB insert | `audit_log()` global function | Established pattern, consistent schema, chaining already set up |

**Key insight:** All infrastructure exists — this phase is adding missing enforcement and error quality, not building new subsystems.

---

## Common Pitfalls

### Pitfall 1: mb_detect_encoding False Positives
**What goes wrong:** `mb_detect_encoding($str, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true)` can return `'Windows-1252'` for ASCII-only UTF-8 content (since ASCII is a subset of both). This triggers an unnecessary but harmless `mb_convert_encoding()` — ASCII UTF-8 converted from Windows-1252 to UTF-8 is identical.
**Why it happens:** The encoding detection is probabilistic for short strings.
**How to avoid:** Always convert when detection says non-UTF-8. The result is correct even for false positives on ASCII content. Log detection result at DEBUG level if helpful.
**Warning signs:** Headers-only CSV (column names are ASCII) detected as Windows-1252 — safe, conversion is a no-op for ASCII.

### Pitfall 2: fgetcsv and Encoding
**What goes wrong:** PHP's `fgetcsv()` operates on raw bytes. If a Windows-1252 file has a byte sequence like `0xE9` (é in Windows-1252), `fgetcsv` returns it as-is. If you then do `mb_convert_encoding($cell, 'UTF-8', 'Windows-1252')` on each cell after fgetcsv, multibyte sequences from cells with embedded quotes may be split wrong.
**How to avoid:** Convert the entire file content to UTF-8 BEFORE `fgetcsv` touches it. Write converted bytes to a temp file, then `fgetcsv` that temp file. Clean up temp file in a `finally` block.

### Pitfall 3: Rate Limit Identifier for Login
**What goes wrong:** `api_rate_limit()` uses `api_current_user_id()` as identifier when authenticated. At login time, the user is not yet authenticated, so it falls back to IP. This is correct behavior. But `APP_LOGIN_MAX_ATTEMPTS` must be read at call time, not at route registration time. Route-level `rate_limit: ['auth_login', 10, 300]` is hardcoded. To make it configurable, the env var must be read inside `AuthController::login()`.
**How to avoid:** Add an explicit `RateLimiter::check()` call inside `login()` using env vars, and do NOT remove the route-level guard (it provides defense-in-depth).

### Pitfall 4: Session Expiry State in Static PHP
**What goes wrong:** After `$_SESSION = []; session_destroy();`, there is no session to store the expiry reason. A static property on `AuthMiddleware` works for a single request, but must be reset in `AuthMiddleware::reset()` (used in tests) to avoid state leakage between tests.
**How to avoid:** Add `private static bool $sessionExpired = false;` and reset it in `reset()`.

### Pitfall 5: Duplicate Email Check Case Sensitivity
**What goes wrong:** `jean@dupont.fr` and `Jean@Dupont.Fr` are the same email — if the duplicate check is case-sensitive, it misses this.
**How to avoid:** Normalize emails to lowercase before the duplicate scan: `strtolower(trim($email))`.

### Pitfall 6: RateLimiter::isLimited Does Not Increment
**What goes wrong:** `isLimited()` reads the current count without incrementing. Calling `isLimited()` followed by `check()` means two operations: the block check is based on the pre-increment count, then `check()` increments. If you call `isLimited()` to get the block decision, you must also call `check()` to register the attempt.
**How to avoid:** Use `RateLimiter::check($context, $ip, $max, $window, false)` (strict=false) to increment and get the boolean result without throwing. Then handle the block explicitly with audit log and `api_fail()`.

---

## Code Examples

### Encoding Detection in readCsvFile

```php
// Source: PHP manual — mb_detect_encoding, mb_convert_encoding
public static function readCsvFile(string $filePath): array {
    $content = @file_get_contents($filePath);
    if ($content === false) {
        return ['headers' => [], 'rows' => [], 'separator' => ',', 'error' => 'Impossible d\'ouvrir le fichier.'];
    }

    // Detect and normalize encoding before fgetcsv
    $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
    if ($encoding !== false && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }

    // Write converted content to temp, parse with fgetcsv
    $tmpPath = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tmpPath, $content);
    $handle = @fopen($tmpPath, 'r');
    if (!$handle) {
        @unlink($tmpPath);
        return ['headers' => [], 'rows' => [], 'separator' => ',', 'error' => 'Impossible d\'ouvrir le fichier.'];
    }

    try {
        // ... existing separator detection and fgetcsv logic ...
    } finally {
        fclose($handle);
        @unlink($tmpPath);
    }
}
```

### Duplicate Email Pre-Scan

```php
// Source: project pattern — validate before transaction
// Placement: ImportController::membersCsv(), after $colIndex is known, before api_transaction
if (isset($colIndex['email'])) {
    $emailIdx = $colIndex['email'];
    $seen = [];
    $duplicates = [];
    foreach ($rows as $row) {
        $raw = strtolower(trim((string) ($row[$emailIdx] ?? '')));
        if ($raw === '') continue;
        if (isset($seen[$raw])) {
            $duplicates[] = $raw;
        } else {
            $seen[$raw] = true;
        }
    }
    $duplicates = array_values(array_unique($duplicates));
    if (!empty($duplicates)) {
        api_fail('duplicate_emails', 422, [
            'detail' => 'Le fichier contient des adresses email en double.',
            'duplicate_emails' => $duplicates,
        ]);
    }
}
```

### Rate Limit with Audit in AuthController::login()

```php
// Source: project pattern — RateLimiter::check() with strict=false
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$maxAttempts = (int) (getenv('APP_LOGIN_MAX_ATTEMPTS') ?: 5);
$windowSeconds = (int) (getenv('APP_LOGIN_WINDOW') ?: 300);

$allowed = RateLimiter::check('auth_login', $ip, $maxAttempts, $windowSeconds, false);
if (!$allowed) {
    try {
        audit_log('auth_rate_limited', 'security', null, [
            'ip' => $ip,
            'attempt_count' => $maxAttempts,
            'window' => $windowSeconds,
        ]);
    } catch (Throwable) { /* best effort */ }
    $retryMinutes = (int) ceil($windowSeconds / 60);
    api_fail('rate_limit_exceeded', 429, [
        'detail' => "Trop de tentatives. Réessayez dans {$retryMinutes} minutes.",
        'retry_after' => $windowSeconds,
    ], ['Retry-After' => (string) $windowSeconds]);
}
```

### Session Expiry Static Flag in AuthMiddleware

```php
// Add to AuthMiddleware state section:
private static bool $sessionExpired = false;

// In authenticate(), expired block:
if ($lastActivity > 0 && ($now - $lastActivity) > self::SESSION_TIMEOUT) {
    error_log(sprintf('SESSION_EXPIRED | user_id=%s | idle=%ds', ...));
    $_SESSION = [];
    session_destroy();
    self::$sessionExpired = true;  // mark expiry for this request
    return null;
}

// In deny(), when code is 'authentication_required' and $sessionExpired is true:
// use 'session_expired' error code instead
// Or: add a dedicated denyExpired() that emits session_expired

// In reset():
self::$sessionExpired = false;
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `fgetcsv` directly on file bytes | `file_get_contents` + encoding detection + temp file + `fgetcsv` | Phase 60 | Correct handling of accented names in Windows-1252 CSV exports |
| Generic `authentication_required` on expiry | Distinguishable `session_expired` + French message query param | Phase 60 | Users see informative message instead of silent redirect |
| Route-level fixed rate limit only | Explicit controller-level check reading env vars + audit log | Phase 60 | Configurable threshold + security audit trail |
| Generic non-draft delete message | Live-specific message with actionable hint | Phase 60 | Operator understands what to do |
| Invalid transition message debug-only | `detail` in main body always | Phase 60 | Frontend can display user-friendly message |

---

## Open Questions

1. **Session expiry detection — API vs HTML redirect**
   - What we know: This is an SPA. Pages make API calls. `auth-ui.js` already redirects to `/login.html?redirect=...` on 401. The decision says "set as query param before redirect to `/login`".
   - What's unclear: Should the backend emit a distinct JSON error code (`session_expired`) for the JS to detect, or does the backend issue an actual HTTP redirect? For a JSON API, a JSON error code is correct — the JS handles the redirect.
   - Recommendation: Backend emits `{'ok': false, 'error': 'session_expired'}` with 401. Frontend JS intercepts `session_expired` and redirects to `/login.html?expired=1&redirect=...`. Login page reads `?expired=1` and shows the French message.

2. **Where to expose attempt count for audit log**
   - What we know: `RateLimiter::isLimited()` checks count against max but doesn't return the count. The `check()` method (strict=false) returns bool. Neither returns the raw attempt count for the audit payload.
   - What's unclear: Is it sufficient to log `attempt_count: $maxAttempts` (i.e., "blocked after N attempts") rather than the actual current count?
   - Recommendation: Log `$maxAttempts` as `attempt_count` — this documents the threshold that was exceeded, which is the operationally useful fact for security monitoring. Exact count is not required.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | `phpunit.xml` (project root) |
| Quick run command | `./vendor/bin/phpunit --testsuite Unit --filter "ImportServiceTest|MeetingWorkflowControllerTest|AuthControllerTest|MeetingsControllerTest" --no-coverage` |
| Full suite command | `./vendor/bin/phpunit --testsuite Unit --no-coverage` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SESS-01 | Invalid transition returns 422 with `from_status`/`to_status` in body | unit | `./vendor/bin/phpunit tests/Unit/MeetingWorkflowControllerTest.php --filter testInvalidTransition --no-coverage` | Partial (file exists, new test needed) |
| SESS-02 | Live session delete returns 409 with hint message | unit | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --filter testDeleteLiveMeeting --no-coverage` | Partial (file exists, new test needed) |
| IMP-01 | Windows-1252 CSV produces correct UTF-8 import | unit | `./vendor/bin/phpunit tests/Unit/ImportServiceTest.php --filter testReadCsvFileWindows1252 --no-coverage` | Partial (file exists, new test needed) |
| IMP-02 | Duplicate email CSV returns 422 listing duplicates | unit | `./vendor/bin/phpunit tests/Unit/ImportControllerTest.php --filter testMembersCsvDuplicateEmails --no-coverage` | Partial (file exists, new test needed) |
| AUTH-01 | Expired session returns `session_expired` error code | unit | `./vendor/bin/phpunit tests/Unit/AuthMiddlewareTest.php --filter testExpiredSessionReturnsSessionExpiredCode --no-coverage` | Partial (file exists, new test needed) |
| AUTH-02 | Rate-limit block writes audit entry | unit | `./vendor/bin/phpunit tests/Unit/AuthControllerTest.php --filter testLoginRateLimitAudits --no-coverage` | Partial (file exists, new test needed) |

### Sampling Rate
- **Per task commit:** `./vendor/bin/phpunit tests/Unit/ImportServiceTest.php tests/Unit/AuthControllerTest.php tests/Unit/MeetingWorkflowControllerTest.php tests/Unit/MeetingsControllerTest.php --no-coverage`
- **Per wave merge:** `./vendor/bin/phpunit --testsuite Unit --no-coverage`
- **Phase gate:** Full unit suite green before `/gsd:verify-work`

### Wave 0 Gaps
None — all test files already exist. New test methods go into existing files following established patterns.

---

## Sources

### Primary (HIGH confidence)
- Direct source inspection: `app/Core/Security/AuthMiddleware.php` — full session expiry logic, requireTransition, static state
- Direct source inspection: `app/Services/ImportService.php` — readCsvFile() implementation
- Direct source inspection: `app/Controller/ImportController.php` — membersCsv() flow
- Direct source inspection: `app/Controller/AuthController.php` — login() flow
- Direct source inspection: `app/Core/Security/RateLimiter.php` — check(), isLimited() implementations
- Direct source inspection: `app/Core/Security/Permissions.php` — TRANSITIONS map
- Direct source inspection: `app/routes.php` — existing rate_limit config on auth_login
- Direct source inspection: `tests/Unit/ImportServiceTest.php` — test patterns and temp file approach

### Secondary (MEDIUM confidence)
- PHP manual knowledge (training data, HIGH confidence for stable built-ins): `mb_detect_encoding`, `mb_convert_encoding`, `fgetcsv` byte-level behavior

### Tertiary (LOW confidence)
None.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages, all based on direct code inspection
- Architecture: HIGH — all insertion points verified in source files
- Pitfalls: HIGH — derived from actual code behavior observed in source (fgetcsv byte-level, RateLimiter::isLimited semantics, static flag lifecycle)
- Test approach: HIGH — existing test files and patterns verified

**Research date:** 2026-03-31
**Valid until:** 2026-04-30 (stable PHP codebase, no fast-moving dependencies)
