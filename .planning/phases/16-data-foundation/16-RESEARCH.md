# Phase 16: Data Foundation - Research

**Researched:** 2026-03-16
**Domain:** PHP/PDO atomic transactions, frontend API contract, hub error-state replacement
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Persistence strategy**
- Atomic single-call: Extend `createMeeting()` to accept `members[]` and `resolutions[]` in the existing POST /api/v1/meetings payload
- Process all three (meeting + members + motions) in a single PDO transaction — rollback everything if any part fails
- Leverage existing `IdempotencyGuard` pattern for the expanded payload
- No multi-step API calls from the frontend; the wizard continues sending one payload

**Field mapping (wizard → backend)**
- Backend maps the mismatched field names — the frontend payload stays as-is
- `type` → `meeting_type`
- `date` + `time` → `scheduled_at` (combined)
- `place` → `location`
- `quorum` → mapped to quorum policy
- `defaultMaj` → mapped to vote policy
- Frontend `buildPayload()` is NOT modified for field names

**Member handling**
- Upsert behavior: If a member already exists (same email + tenant), reuse the existing member record — do not create duplicates
- Link existing/new members to the meeting via attendance records
- `voix` field defaults to 1 if not provided

**API response**
- Return `meeting_id` + counts: `{ meeting_id, title, members_created, members_linked, motions_created }`
- The wizard uses these counts for the success toast on redirect

**Hub error handling**
- Toast + retry: On API failure, show a red toast with error message and a retry button
- 1 automatic retry after 2 seconds, then manual retry button if still failing
- Remove DEMO_SESSION and DEMO_FILES entirely — hub shows only real data
- Invalid meeting_id: Redirect to dashboard with toast "Séance introuvable"

**Wizard → Hub redirect contract**
- Wizard waits for 201 response before redirecting to hub — no fire-and-forget
- On success: `clearDraft()` from localStorage, store counts in sessionStorage, redirect to `hub.htmx.html?id=X`
- Hub reads sessionStorage for toast: "Séance créée • 12 membres • 5 résolutions"
- On failure: Red toast with error detail, form stays filled, user can retry
- localStorage draft is only cleared after confirmed 201

**Backend validation**
- Email format validated at creation time — reject if any member email is invalid
- Required fields: Member → `nom` + `email`; Resolution → `title`
- Tout-ou-rien: If 1 member or 1 motion is invalid, rollback entire transaction, return 422 with detailed errors listing which items failed and why
- No quantity limits for members or resolutions per session
- Return error structure: `{ error: true, details: [{ index: 0, field: 'email', message: 'Format invalide' }] }`

### Claude's Discretion
- Exact PDO transaction implementation details
- ValidationSchemas extension approach
- Error message wording for edge cases
- Hub loading skeleton during API call

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| WIZ-01 | Le wizard crée une session en DB avec titre, type, lieu, date en une seule requête API | `createMeeting()` already creates meeting; extend to wrap in `api_transaction()` with full field mapping |
| WIZ-02 | Les membres sélectionnés à l'étape 2 du wizard sont persistés en transaction atomique avec la session | `MemberRepository::findByEmail()` + `MemberRepository::create()` + `AttendanceRepository::upsertMode()` all exist; wire inside the same `api_transaction()` block |
| WIZ-03 | Les résolutions saisies à l'étape 3 du wizard sont persistées en transaction atomique avec la session | `MotionRepository::create()` (via `MotionWriterTrait`) already exists; call inside the same transaction |
| HUB-01 | Le hub charge l'état réel de la session via l'API wizard_status (zéro donnée démo) | `loadData()` in hub.js already calls `wizard_status`; replace the demo fallback block (lines 421-430) with real error handling |
| HUB-02 | Le hub affiche un état d'erreur explicite quand le backend est indisponible | Add toast + retry logic; remove `DEMO_SESSION` and `DEMO_FILES` constants entirely from hub.js |
</phase_requirements>

---

## Summary

Phase 16 wires three things together: (1) the backend `createMeeting()` endpoint must absorb `members[]` and `resolutions[]` from the existing wizard payload inside a single PDO transaction, (2) the wizard JS must forward counts from the 201 response to sessionStorage before redirecting, and (3) hub.js must drop its demo-data fallback entirely and handle API failure explicitly.

All the infrastructure already exists. `api_transaction()` is a project-standard helper (used in BallotsController, MeetingWorkflowController, ImportController, etc.) that commits on success and rolls back on any exception. `MemberRepository::findByEmail()` and `MemberRepository::create()` exist; `AttendanceRepository::upsertMode()` handles the member-to-meeting linkage. `MotionRepository::create()` (via `MotionWriterTrait`) handles motion insertion. The wizard's `buildPayload()` already sends `members[]` and `resolutions[]`; the backend has simply been ignoring them.

The critical precision work in this phase is: the field-name mapping (wizard sends `type`/`date`+`time`/`place`; backend expects `meeting_type`/`scheduled_at`/`location`), the per-item 422 error structure, the `members_created` vs `members_linked` count distinction, and the hub's retry logic on failure.

**Primary recommendation:** Wrap the entire `createMeeting()` body inside `api_transaction()`, add member upsert + attendance link loop, add motion insert loop, return expanded response with counts. Then replace hub.js `loadData()` error path with toast + retry instead of demo fallback.

---

## Standard Stack

### Core — already in project, no new dependencies

| Component | Location | Purpose |
|-----------|----------|---------|
| `api_transaction()` | `app/api.php:249` | Wraps PDO beginTransaction/commit/rollBack — project standard for atomic operations |
| `MemberRepository::findByEmail()` | `app/Repository/MemberRepository.php:319` | Case-insensitive email lookup for upsert logic |
| `MemberRepository::create()` | `app/Repository/MemberRepository.php:238` | Insert new member row |
| `AttendanceRepository::upsertMode()` | `app/Repository/AttendanceRepository.php:271` | Insert or update attendance record for a member-meeting pair |
| `MotionRepository::create()` (via `MotionWriterTrait`) | `app/Repository/Traits/MotionWriterTrait.php:16` | Insert motion row |
| `ValidationSchemas` | `app/Core/Validation/Schemas/ValidationSchemas.php` | Has `member()` and `motion()` schemas already — reuse for per-item validation |
| `IdempotencyGuard` | `app/Core/Security/IdempotencyGuard.php` | Redis-backed dedup for POST — already wraps `createMeeting()` |
| `window.api()` | Frontend global | Project-standard AJAX helper used in wizard.js and hub.js |
| `Shared.showToast()` | Frontend global | Project-standard toast system |

**No new packages needed.** This phase is purely wiring existing components.

---

## Architecture Patterns

### Pattern 1: api_transaction() wrapping multi-repo writes

This is the established project pattern for any write that touches more than one table.

```php
// Source: app/Controller/MeetingWorkflowController.php:85
$txResult = api_transaction(function () use ($repo, $meetingId, $tenantId, ...) {
    // All repository calls inside here share one PDO transaction.
    // If any call throws (or calls api_fail()), PDO rolls back automatically.
    // api_fail() inside the closure triggers rollback via the ApiResponseException branch.
    $repo->doWrite1(...);
    $repo->doWrite2(...);
    return $someResult;
});
// Use $txResult after the closure — safe to read after commit.
```

**How api_transaction handles api_fail():** The function catches `ApiResponseException` (thrown by `api_fail()`); if the response code is >= 400, it calls `rollBack()` before re-throwing. This means calling `api_fail()` inside the transaction is safe and rolls back.

### Pattern 2: Member upsert — find-or-create by email

```php
// Pseudocode for member upsert inside the transaction
foreach ($members as $i => $m) {
    // validate required fields
    $email = strtolower(trim($m['email'] ?? ''));
    $nom   = trim($m['nom'] ?? '');
    if ($email === '' || $nom === '') {
        api_fail('invalid_member', 422, [
            'error' => true,
            'details' => [['index' => $i, 'field' => 'nom/email', 'message' => 'Champs obligatoires']],
        ]);
    }
    // Basic email format check
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        api_fail('invalid_member', 422, [
            'error' => true,
            'details' => [['index' => $i, 'field' => 'email', 'message' => 'Format invalide']],
        ]);
    }
    // Upsert
    $existing = $memberRepo->findByEmail($tenantId, $email);
    if ($existing) {
        $memberId = $existing['id'];
        $membersLinked++;
    } else {
        $memberId = $memberRepo->generateUuid();
        $memberRepo->create($memberId, $tenantId, $nom, $email, (float)($m['voix'] ?? 1), true);
        $membersCreated++;
    }
    // Link to meeting via attendance
    $attendanceRepo->upsertMode($meetingId, $memberId, 'present', $tenantId);
}
```

**Key note:** `MemberRepository::generateUuid()` calls PostgreSQL `gen_random_uuid()` — this requires a live DB connection, so UUID generation is inside the transaction closure.

### Pattern 3: Motion insert loop

```php
foreach ($resolutions as $i => $r) {
    $title = trim($r['title'] ?? '');
    if ($title === '') {
        api_fail('invalid_motion', 422, [
            'error' => true,
            'details' => [['index' => $i, 'field' => 'title', 'message' => 'Titre obligatoire']],
        ]);
    }
    $motionId = $motionRepo->generateUuid();
    $motionRepo->create(
        $motionId, $tenantId, $meetingId,
        null,        // agenda_id — not used by wizard
        $title,
        (string)($r['description'] ?? ''),
        false,       // secret — default
        null,        // vote_policy_id — default
        null,        // quorum_policy_id — default
    );
    $motionsCreated++;
}
```

**Note:** `MotionWriterTrait::create()` signature requires `agendaId`, `description`, `secret`, `votePolicyId`, `quorumPolicyId` — all can be null/false/empty.

### Pattern 4: Field mapping (wizard → backend) in createMeeting()

The wizard sends:
```json
{
  "title": "...",
  "type": "ag_ordinaire",
  "date": "2026-04-15",
  "time": "18:00",
  "place": "Salle des fêtes",
  "address": "12 rue...",
  "quorum": "uuid-or-empty",
  "defaultMaj": "uuid-or-empty",
  "members": [...],
  "resolutions": [...]
}
```

Backend mapping (all in the controller before the transaction):
```php
$meetingType = $data['type'] ?? 'ag_ordinaire';  // not $data['meeting_type']
$date        = $data['date'] ?? '';
$time        = $data['time'] ?? '00:00';
$scheduledAt = ($date !== '') ? $date . ' ' . $time . ':00' : null;
$location    = $data['place'] ?? null;            // not $data['location']
// quorum and defaultMaj are UUID strings for policy IDs (or empty)
$quorumPolicyId   = $data['quorum'] ?? null;
$votePolicyId     = $data['defaultMaj'] ?? null;
$members          = (array)($data['members'] ?? []);
$resolutions      = (array)($data['resolutions'] ?? []);
```

**IMPORTANT:** Do NOT use `ValidationSchemas::meeting()->validate($data)` with the raw wizard payload because the field names mismatch. Either map fields first, or validate individual mapped values.

### Pattern 5: Hub error state — replace demo fallback

Current `loadData()` structure in hub.js (lines 395–431):
```
try {
  api call → success path → return
} catch {
  console.warn → fall through
}
// DEMO fallback block (lines 421-430) ← DELETE THIS ENTIRE BLOCK
```

Replacement pattern:
```javascript
// No sessionId → redirect to dashboard immediately
if (!sessionId) {
  sessionStorage.setItem('ag-vote-toast', JSON.stringify({
    msg: 'Identifiant de séance manquant', type: 'error'
  }));
  window.location.href = '/dashboard.htmx.html';
  return;
}

// State: loading skeleton visible

var attempt = 0;
async function tryLoad() {
  attempt++;
  try {
    var res = await window.api('/api/v1/wizard_status?meeting_id=' + encodeURIComponent(sessionId));
    if (res && res.body && res.body.ok && res.body.data) {
      // success path (existing)
      hideError();
      applySessionToDOM(mapApiDataToSession(res.body.data));
      return;
    }
    if (res && res.body && res.body.error === 'meeting_not_found') {
      // Redirect with toast
      sessionStorage.setItem('ag-vote-toast', JSON.stringify({
        msg: 'Séance introuvable', type: 'error'
      }));
      window.location.href = '/dashboard.htmx.html';
      return;
    }
    throw new Error('invalid_response');
  } catch (e) {
    if (attempt === 1) {
      // Auto-retry after 2s
      setTimeout(tryLoad, 2000);
    } else {
      showError(e);  // Show toast + manual retry button
    }
  }
}
tryLoad();
```

### Pattern 6: Wizard success redirect with counts

Update `wizard.js` lines 702–715 (the `btnCreate` click handler's `.then()` block):
```javascript
.then(function(res) {
  if (!res.body || !res.body.ok) {
    throw new Error(res.body && res.body.error || 'creation_failed');
  }
  var d = res.body.data || {};
  var n = d.members_created + d.members_linked;
  var r = d.motions_created;
  clearDraft();  // Only after confirmed 201
  try {
    sessionStorage.setItem('ag-vote-toast', JSON.stringify({
      msg: 'Séance créée\u202f•\u202f' + n + ' membres\u202f•\u202f' + r + ' résolutions',
      type: 'success'
    }));
  } catch (e) {}
  window.location.href = '/hub.htmx.html?id=' + (d.meeting_id || '');
})
.catch(function(err) {
  btnCreate.disabled = false;
  btnCreate.innerHTML = '... Créer la séance';
  var msg = (err && err.message) ? err.message : 'Erreur lors de la création.';
  if (window.Shared && Shared.showToast) {
    Shared.showToast(msg, 'error');
  }
  // Draft is NOT cleared — form stays filled
});
```

### Anti-Patterns to Avoid

- **Running member/motion work outside the transaction:** Any insert that happens before `api_transaction()` begins will not roll back on failure. All three writes (meeting + members + motions) must be inside the single closure.
- **Calling `ValidationSchemas::meeting()->validate($data)` with raw wizard payload:** Field names mismatch. Map `type` → `meeting_type`, `date`+`time` → `scheduled_at`, `place` → `location` before validation, OR skip the schema for mapped fields and validate them manually.
- **Using `IdempotencyGuard` before mapping:** The current code calls `IdempotencyGuard::check()` at the top of `createMeeting()`. Keep this — but the cached response must now include the expanded fields (`members_created`, etc.).
- **Clearing localStorage draft on `.catch()`:** The decision is explicit: `clearDraft()` only after a confirmed 201. Do not call it in the error path.
- **Generating UUIDs outside the PDO connection context:** `generateUuid()` calls `SELECT gen_random_uuid()` — this is fine inside or outside a transaction, but must use the same PDO connection that is in the transaction.
- **Leaving DEMO_SESSION / DEMO_FILES as dead code:** They must be deleted, not just bypassed. Phase success criterion requires zero demo constants.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PDO transaction management | Manual `$pdo->beginTransaction()` calls in controller | `api_transaction()` helper | Already handles `ApiResponseException` commit/rollback distinction; project standard |
| Email format validation | Custom regex | `filter_var($email, FILTER_VALIDATE_EMAIL)` | Standard PHP; matches what `ValidationSchemas::member()` uses (which calls `InputValidator::email()`) |
| Member email lookup | Custom SQL in controller | `MemberRepository::findByEmail()` | Already exists, case-insensitive |
| Attendance insert/update | Custom INSERT in controller | `AttendanceRepository::upsertMode()` | Uses `ON CONFLICT (tenant_id, meeting_id, member_id) DO UPDATE` — handles duplicate calls safely |
| Motion insert | Custom SQL in controller | `MotionRepository::create()` (MotionWriterTrait) | Already handles nullable UUIDs with `NULLIF(...)::uuid` |
| UUID generation | `uuid_v4()` PHP impl | `$repo->generateUuid()` → `SELECT gen_random_uuid()` | Project-standard; PostgreSQL gen_random_uuid() is cryptographically secure |

---

## Common Pitfalls

### Pitfall 1: Field-name mismatch between wizard payload and backend schema

**What goes wrong:** `ValidationSchemas::meeting()->validate($data)` expects `meeting_type`, `scheduled_at`, `location`. The wizard sends `type`, `date`+`time`, `place`. If you validate before mapping, the validator rejects `meeting_type` as missing.

**Why it happens:** The wizard payload uses presentation-layer names; the DB schema uses canonical names. The decision locks this as a backend mapping responsibility.

**How to avoid:** Map first, validate second. OR: read raw fields from `$data` without schema validation for the wizard-specific fields, then pass the mapped values directly to `$repo->create()`.

**Warning signs:** `createMeeting()` returns 422 "meeting_type is required" even when `type` is set in the payload.

### Pitfall 2: Transaction isolation — meeting insert must also be inside api_transaction()

**What goes wrong:** The current `createMeeting()` does `$repo->create(...)` outside any transaction. If you add `api_transaction()` only around the member/motion loops but not the meeting insert, a partial failure (e.g., invalid member at index 5) leaves the meeting row orphaned in the DB.

**Why it happens:** It is tempting to "extend" by adding a transaction around only the new code.

**How to avoid:** Wrap the entire `createMeeting()` body — from `$repo->generateUuid()` through `api_ok($result, 201)` — inside a single `api_transaction()` closure.

**Warning signs:** Meeting appears in DB without members or motions after a 422 error.

### Pitfall 3: IdempotencyGuard cache invalidation — stale cached response

**What goes wrong:** `IdempotencyGuard::store()` is called at the end of `createMeeting()` with `['meeting_id' => $id, 'title' => $title]`. After expanding the response to include `members_created`, `members_linked`, `motions_created`, if a retry hits the old cached response, the wizard toast shows wrong counts.

**Why it happens:** The cache stores whatever was passed to `store()` at the time of first call.

**How to avoid:** Pass the full expanded result to `IdempotencyGuard::store($result)` — include all count fields in `$result` before storing.

### Pitfall 4: Hub shows blank screen when meeting_id is missing from URL

**What goes wrong:** If the wizard redirect somehow sends to `hub.htmx.html` without `?id=`, and the demo fallback is removed, the hub renders nothing.

**Why it happens:** The decision says "remove DEMO_SESSION and DEMO_FILES entirely" without a conditional.

**How to avoid:** In the `loadData()` early check: if `sessionId` is empty or null, redirect to dashboard immediately with an error toast before any API call.

### Pitfall 5: Race condition — members from wizard may reference email not yet in members table

**What goes wrong:** If two concurrent wizard completions create the same member email, the `findByEmail()` + `create()` pattern can race and both try to INSERT the same email, causing a DB unique constraint violation that uncaught will 500 the request.

**Why it happens:** PDO transaction isolation does not prevent two concurrent transactions from both reading "no row" before either inserts.

**How to avoid:** Add `ON CONFLICT (tenant_id, email) DO NOTHING RETURNING id` to the member INSERT, or use a `try/catch` around the create() call to handle constraint violations by doing a second `findByEmail()`. Alternatively: keep `findByEmail()` inside the transaction and use `SELECT ... FOR UPDATE` to lock the potential row. The simplest approach: use `INSERT ... ON CONFLICT (tenant_id, lower(email)) DO UPDATE SET updated_at = now() RETURNING id`. Check if the `members` table has a unique index on `(tenant_id, lower(email))` — if not, the upsert may not be needed for concurrent safety in the typical single-operator use case.

**Warning signs:** Occasional 500 errors on concurrent wizard submissions; PostgreSQL log shows unique constraint violation on `members.email`.

---

## Code Examples

### Full api_transaction() pattern for createMeeting()

```php
// Source: app/api.php:249 (api_transaction signature)
// Source: app/Controller/MeetingWorkflowController.php:85 (usage pattern)

public function createMeeting(): void {
    $cached = IdempotencyGuard::check();
    if ($cached !== null) {
        api_ok($cached, 201);
    }

    $data = api_request('POST');

    // Map wizard field names → backend field names (BEFORE any validation)
    $title      = trim((string)($data['title'] ?? ''));
    $type       = $data['type'] ?? 'ag_ordinaire';        // wizard sends 'type'
    $date       = $data['date'] ?? '';
    $time       = $data['time'] ?? '00:00';
    $scheduledAt = ($date !== '') ? $date . ' ' . $time . ':00' : null;
    $location   = ($data['place'] ?? '') ?: null;         // wizard sends 'place'
    $members    = (array)($data['members'] ?? []);
    $resolutions = (array)($data['resolutions'] ?? []);

    // Basic title validation before transaction
    if ($title === '' || strlen($title) < 3) {
        api_fail('validation_error', 422, ['details' => [['field' => 'title', 'message' => 'Titre obligatoire (min. 3 caractères)']]]);
    }

    $tenantId = api_current_tenant_id();
    $repo     = $this->repo();

    $result = api_transaction(function () use (
        $repo, $tenantId, $title, $type, $scheduledAt, $location,
        $members, $resolutions
    ) {
        $meetingId = $repo->meeting()->generateUuid();
        $repo->meeting()->create($meetingId, $tenantId, $title, null, $scheduledAt, $location, $type);

        $membersCreated = 0;
        $membersLinked  = 0;
        foreach ($members as $i => $m) {
            // validate + upsert logic (see Pattern 2)
            // ...
            $repo->attendance()->upsertMode($meetingId, $memberId, 'present', $tenantId);
        }

        $motionsCreated = 0;
        foreach ($resolutions as $i => $r) {
            // validate + insert logic (see Pattern 3)
            // ...
            $motionsCreated++;
        }

        return [
            'meeting_id'      => $meetingId,
            'title'           => $title,
            'members_created' => $membersCreated,
            'members_linked'  => $membersLinked,
            'motions_created' => $motionsCreated,
        ];
    });

    IdempotencyGuard::store($result);
    api_ok($result, 201);
}
```

### AttendanceRepository::upsertMode() — confirmed signature

```php
// Source: app/Repository/AttendanceRepository.php:271
public function upsertMode(string $meetingId, string $memberId, string $mode, string $tenantId): bool
// Returns true if inserted (new record), false if updated (existing)
// Uses ON CONFLICT (tenant_id, meeting_id, member_id) DO UPDATE SET mode = ...
```

### MemberRepository::findByEmail() — confirmed signature

```php
// Source: app/Repository/MemberRepository.php:319
public function findByEmail(string $tenantId, string $email): ?array
// Returns ['id' => '...'] or null
// Case-insensitive: uses LOWER(email) = :email
```

### MotionWriterTrait::create() — confirmed full signature

```php
// Source: app/Repository/Traits/MotionWriterTrait.php:16
public function create(
    string $id,
    string $tenantId,
    string $meetingId,
    ?string $agendaId,
    string $title,
    string $description,   // empty string is fine
    bool $secret,
    ?string $votePolicyId,
    ?string $quorumPolicyId,
): void
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| createMeeting() saves meeting only | createMeeting() saves meeting + members + motions in one transaction | Wizard completion is atomic — no partial state |
| hub.js falls back to DEMO_SESSION on API error | hub.js shows toast + retry button, redirects on missing ID | HUB-02 satisfied; zero demo constants |
| Wizard toast always says "Séance créée avec succès" | Wizard toast says "Séance créée • N membres • M résolutions" using counts from 201 response | User has confirmation that data was persisted |
| Draft cleared unconditionally | Draft cleared only after confirmed 201 | Prevents data loss on network failure |

---

## Open Questions

1. **Does the `members` table have a unique constraint on `(tenant_id, lower(email))`?**
   - What we know: `findByEmail()` does a SELECT with `LOWER(email)` — the logic assumes at most one row per email per tenant.
   - What's unclear: Whether there is a DB-level unique index that would make concurrent inserts safe or cause constraint violations.
   - Recommendation: Check `database/` migration files for the `members` table definition. If no unique constraint exists, the find-then-create pattern is safe in single-operator scenarios (low concurrency risk). Add a `try/catch` around `create()` to handle the rare race.

2. **What does `quorum` and `defaultMaj` actually contain in the wizard payload?**
   - What we know: The wizard has `getId('wizQuorum').value` and `getId('wizDefaultMaj').value`. They're sent as strings. The backend has `quorum_policy_id` and `vote_policy_id` UUID fields on the meetings table.
   - What's unclear: Whether these wizard fields contain actual UUID policy IDs, or human-readable labels like "1/3 des membres".
   - Recommendation: The CONTEXT.md says "mapped to quorum policy" / "mapped to vote policy" without specifying the mapping. Inspect the wizard HTML for `wizQuorum` and `wizDefaultMaj` to determine the values. If they are UUIDs, pass directly. If they are labels, a lookup table is needed — but this is discretionary (can default to null if mapping is ambiguous).

3. **Is there a loading skeleton or loading state in hub.js for the API call?**
   - What we know: `applySessionToDOM()` and `renderKpis()` exist. The CONTEXT.md mentions "Hub loading skeleton during API call" as discretionary.
   - What's unclear: Whether existing HTML has skeleton elements ready to toggle, or if DOM changes are needed.
   - Recommendation: This is discretionary. A simple CSS class on the hub container during load (e.g., `hub--loading`) is sufficient. Not required for success criteria.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | `/home/user/gestion-votes/phpunit.xml` |
| Quick run command | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` |
| Full suite command | `./vendor/bin/phpunit --no-coverage` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| WIZ-01 | createMeeting() returns 201 with meeting_id + counts | unit | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` | ✅ (extend existing) |
| WIZ-02 | members[] in payload are persisted and linked as attendance records | unit | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` | ✅ (add new test methods) |
| WIZ-03 | resolutions[] in payload are persisted as motions | unit | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` | ✅ (add new test methods) |
| WIZ-01/02/03 | Invalid member email causes 422 and full rollback | unit | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` | ✅ (add new test methods) |
| HUB-01 | hub.js loadData() calls wizard_status and renders real data | manual | n/a — frontend JS | ❌ manual smoke |
| HUB-02 | hub.js shows error state (not demo data) when API fails | manual | n/a — frontend JS | ❌ manual smoke |

### Sampling Rate

- **Per task commit:** `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage`
- **Per wave merge:** `./vendor/bin/phpunit --no-coverage`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] New test methods in `tests/Unit/MeetingsControllerTest.php` — covers WIZ-01 expanded response, WIZ-02 member upsert, WIZ-03 motion insert, 422 rollback behavior
- [ ] No new test files needed — existing `MeetingsControllerTest.php` is the correct location

*(Existing test infrastructure covers the framework. Only new test methods needed, not new files.)*

---

## Sources

### Primary (HIGH confidence)

All findings are from direct source code inspection of the repository.

- `app/api.php:249` — `api_transaction()` implementation and ApiResponseException handling
- `app/Controller/MeetingsController.php:367` — current `createMeeting()` implementation
- `app/Repository/MemberRepository.php:238,319` — `create()` and `findByEmail()` signatures
- `app/Repository/AttendanceRepository.php:271` — `upsertMode()` signature and SQL
- `app/Repository/Traits/MotionWriterTrait.php:16` — `create()` signature and SQL
- `app/Core/Validation/Schemas/ValidationSchemas.php` — existing `member()` and `motion()` schemas
- `app/Core/Security/IdempotencyGuard.php` — check/store pattern
- `app/Core/Providers/RepositoryFactory.php` — all available repos via `$this->repo()`
- `public/assets/js/pages/hub.js:301-431` — DEMO_SESSION, DEMO_FILES, loadData() structure
- `public/assets/js/pages/wizard.js:606,694-727` — buildPayload(), btnCreate handler
- `app/Controller/MeetingWorkflowController.php:85` — api_transaction() usage pattern
- `app/Controller/ImportController.php:106` — api_transaction() with member loops

### Secondary (MEDIUM confidence)

- `tests/Unit/MeetingsControllerTest.php` — confirmed test file exists and covers createMeeting

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components verified by direct code reading
- Architecture: HIGH — api_transaction() pattern confirmed in 10+ controller usages
- Pitfalls: HIGH — field-name mismatch and transaction scope issues verified by reading current createMeeting() and wizard buildPayload()
- Open Questions: MEDIUM — quorum/majority mapping needs wizard HTML inspection

**Research date:** 2026-03-16
**Valid until:** 2026-04-16 (stable codebase; valid longer unless Phase 16 implementation begins)
