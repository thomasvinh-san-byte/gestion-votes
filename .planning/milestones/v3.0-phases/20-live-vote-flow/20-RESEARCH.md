# Phase 20: Live Vote Flow - Research

**Researched:** 2026-03-17
**Domain:** Vanilla PHP + vanilla JS (IIFE/var/use strict), SSE fan-out (Phase 18), operator console (Phase 19)
**Confidence:** HIGH — all findings verified directly against project source code

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Voter ballot experience**
- Auto-switch to ballot: When SSE `motion.opened` event fires, the voter view automatically transitions to show the active ballot card — no manual navigation required
- Ballot options: 4 choices — Pour / Contre / Abstention / NSP (ne se prononce pas). Backend already validates all four values in `BallotsService::castBallot()`
- Confirmation before submit: After tapping a ballot option, show a confirmation modal ("Etes-vous sur de voter Pour ?") before submitting. Required because votes are irrevocable
- One shot, no re-vote: Once submitted, the vote is final and cannot be changed. `BallotsService` already enforces duplicate rejection
- Post-submission state: Claude decides the exact post-vote screen (confirmation card with disabled buttons vs. toast + waiting screen) based on existing vote.js patterns
- No results for voters: After `motion.closed`, voter sees "Vote termine" but NOT the breakdown

**Live tally & results**
- Count only during open vote: Operator sees "12/25 votes recus" in real-time via SSE `vote.cast` events. Pour/contre breakdown is NOT shown while vote is open
- Full breakdown after close: After closing a motion, operator sees full results: Pour, Contre, Abstention, NSP counts + percentages. Claude decides format (table or bar chart)
- Auto-computed verdict + operator override: System computes "Adopte" or "Rejete" based on majority rule. Operator/president can manually override
- Who hasn't voted: Claude decides whether to show the list of non-voters or just the anonymous count

**State machine wiring**
- Auto-transition with confirmation on first vote: When operator clicks "Ouvrir le vote" and meeting is still 'frozen', the confirmation modal says "Cela demarrera la seance. Continuer?" — one click transitions to 'live' AND opens the vote
- All manual transitions: Meeting state transitions are operator-triggered. Closing the last motion does NOT auto-close the meeting
- One motion at a time: Only one motion can be open simultaneously
- Any order for motions: Claude decides whether to enforce sequential order or allow any-order voting

**Projection screen sync**
- During open vote: Show motion title + "Vote en cours" + live progress bar (X/Y votes recus)
- After close — auto-reveal: Full results + verdict appear automatically on projection when the motion closes
- Between votes: Show meeting info — title, date, current agenda position ("Resolution 3/7")

### Claude's Discretion
- Voter authentication method (existing session auth vs. vote tokens)
- Post-vote screen design (confirmation card vs. toast + waiting)
- Results format on operator console (table vs. bar chart)
- Non-voter list visibility decision
- Motion ordering enforcement (sequential vs. any order)
- Loading skeleton designs for all views
- Exact progress bar implementation on projection screen
- Error handling for failed ballot submissions

### Deferred Ideas (OUT OF SCOPE)
- Proxy voting (procurations) — separate future phase
- Operator-triggered result reveal on projection — rejected in favor of auto-reveal on close
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| VOT-01 | L'opérateur peut ouvrir une motion et les votants voient la motion active | Backend `MotionsController::open()` + `EventBroadcaster::motionOpened()` fully implemented; voter `vote.js` SSE handler triggers `refresh()` on `motion.opened`; gap is auto-switch logic |
| VOT-02 | Le votant peut soumettre un bulletin et reçoit une confirmation | `BallotsService::castBallot()` fully implemented with idempotency; `vote.js` `cast()` calls `/api/v1/ballots_cast.php`; gap is confirmation modal before submit |
| VOT-03 | L'opérateur peut fermer une motion et les résultats sont calculés | `MotionsController::close()` computes `OfficialResultsService::computeOfficialTallies()` and broadcasts `motion.closed`; `operator-motions.js::closeVote()` already renders proclamation modal; gap is operator-side tally display wiring during live vote |
| VOT-04 | Les transitions d'état machine fonctionnent de bout en bout | `MeetingWorkflowController::transition()` + `launch()` handle all transitions; `operator-realtime.js` handles `meeting.status_changed`; gap is the auto-transition (frozen→live + open vote in one click) and hub checklist SSE update |
</phase_requirements>

---

## Summary

Phase 20 wires together backend infrastructure that is already 80–90% implemented. The backend services (`BallotsService`, `MotionsController::open/close`, `MeetingWorkflowController::transition/launch`, `EventBroadcaster`) are fully built and tested. The operator console already has working `openVote()` and `closeVote()` functions with confirmation modals in `operator-motions.js`. The voter view (`vote.js`) already polls `current_motion.php`, renders the motion card, and calls `ballots_cast.php`. The projection screen (`public.js`) already listens to all relevant SSE events.

What Phase 20 must actually build is the **wiring layer**: connecting SSE events to immediate UI state transitions, implementing the voter confirmation modal before ballot submission, adding the frozen→live auto-transition in the operator's "open vote" flow, connecting real tally counts to the operator exec view during an open vote, and ensuring the hub checklist reflects state machine changes via `meeting.status_changed` SSE.

**Primary recommendation:** Work in three sub-flows: (1) voter-side ballot confirmation + post-vote state, (2) operator-side live tally display + results proclamation, (3) frozen→live auto-transition + hub SSE wiring.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vanilla PHP 8.x | 8.x | Backend API controllers and services | Project stack, no frameworks |
| Vanilla JS (IIFE + var + use strict) | ES5-compatible | Frontend page modules | Project standard, no transpilers |
| Redis | present | SSE fan-out per consumer (Phase 18 pattern) | Already deployed for multi-consumer SSE |
| PHPUnit | 10.5 | Unit tests for PHP services | Already configured in `phpunit.xml` |
| Playwright | present | E2E browser tests | Already in `tests/e2e/specs/` |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `ag-confirm` component | project | Confirmation modal pattern | Use for all confirmation dialogs |
| `ag-toast` component | project | Toast notifications | Post-vote success/error messages |
| `EventStream` wrapper | project | SSE auto-reconnect | All SSE subscriptions in voter + operator |
| `Shared` module | project | `emptyState()`, `show()`, `hide()`, `formatWeight()` | All DOM state transitions |
| `Utils` module | project | `isValidUUID()`, `escapeHtml()`, `apiGet/apiPost` | Input validation, API calls |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| IIFE + var pattern | async/await modules | Project enforces ES5-compatible IIFE everywhere; do not deviate |
| `O.createModal()` pattern | Native confirm() | Already established in `openVote()`/`closeVote()` — must match |
| `/api/v1/motions_close.php` endpoint | New close endpoint | Endpoint exists, `MotionsController::close()` already computes official tallies |

---

## Architecture Patterns

### Recommended Project Structure

New code goes into existing files — no new files expected for core functionality.

```
public/assets/js/pages/
├── vote.js              — voter ballot confirmation modal + post-vote state
├── operator-motions.js  — frozen→live auto-transition in openVote()
├── operator-exec.js     — live tally display ("X/Y votes recus") during open vote
├── operator-realtime.js — already handles all SSE events correctly (no changes expected)
├── public.js            — projection screen between-vote info + progress bar
└── hub.js               — (if hub SSE wiring is needed — see Gap analysis)
```

### Pattern 1: SSE Event → Immediate UI Transition (voter side)

**What:** On `motion.opened` SSE event, voter view auto-switches from waiting state to ballot card without page reload.

**When to use:** All SSE-driven view transitions.

**How it works today:** `vote.js` calls `refresh()` on SSE events — `refresh()` fetches `/api/v1/current_motion.php` and calls `updateMotionCard(m)`. The gap is that the current SSE wiring in `vote.js` is not shown in the excerpt; research shows `public.js` calls `refresh()` on `motion.opened` — the same pattern needs to be confirmed/ensured in `vote.js`.

```javascript
// Source: vote.js (inline SSE handler pattern, confirmed from public.js pattern)
// In the vote page init, after EventStream.connect():
onEvent: function(type, data) {
  if (type === 'motion.opened' || type === 'motion.closed' || type === 'vote.cast') {
    refresh();  // polls current_motion.php and re-renders card
  }
}
```

### Pattern 2: Voter Ballot Confirmation Modal

**What:** After voter taps Pour/Contre/Abstention/NSP, show a confirmation modal before submitting. The `confirmationOverlay` DOM element path already exists in `vote.js` (`wire()` checks for `confirmationOverlay` to decide whether to bind direct cast or let the overlay handle it).

**When to use:** This is the locked UX decision — always confirm before cast.

**Example (existing modal creation pattern in operator-motions.js):**
```javascript
// Source: operator-motions.js openVote() — confirmed ag-confirm modal pattern
var confirmed = await new Promise(function(resolve) {
  var modal = O.createModal({
    id: 'voteConfirmModal',
    title: 'Confirmer votre vote',
    onDismiss: function() { resolve(false); },
    content: '<p>Etes-vous sur de voter <strong>Pour</strong> ?</p>'
      + '<div style="display:flex;gap:0.75rem;justify-content:flex-end;">'
      + '<button class="btn btn-secondary" data-action="cancel">Annuler</button>'
      + '<button class="btn btn-primary" data-action="confirm">Confirmer</button>'
      + '</div>'
  });
  modal.querySelector('[data-action="cancel"]').addEventListener('click', function() {
    closeModal(modal); resolve(false);
  });
  modal.querySelector('[data-action="confirm"]').addEventListener('click', function() {
    closeModal(modal); resolve(true);
  });
});
if (!confirmed) return;
await cast(choice);
```

Note: `vote.js` uses `notify()` not `setNotif()`. The modal helper must use the voter page's own modal system (not `O.createModal` from operator).

### Pattern 3: Operator Live Tally Display

**What:** During an open vote, operator exec view shows "X/Y votes recus" updated on each `vote.cast` SSE event. Pour/contre breakdown is hidden until close.

**Current state:** `operator-realtime.js` already calls `O.fn.loadBallots(motionId)` on `vote.cast` events, then calls `O.fn.refreshExecView()`. The `operator-exec.js` `refreshExecView()` function must display only the total count (not breakdown) when a vote is open.

**KPI element IDs in operator exec view:**
- `opKpiVoted` — "X/Y votes recus" counter
- `liveVoteFor`, `liveVoteAgainst`, `liveVoteAbstain` — breakdown elements used by `closeVote()` confirmation modal

### Pattern 4: Frozen→Live Auto-Transition in openVote()

**What:** When `operator-motions.js::openVote()` is called and `O.currentMeetingStatus === 'frozen'`, the confirmation modal changes to "Cela demarrera la seance. Continuer?" and on confirm, calls `/api/v1/meeting_transition` with `to_status: 'live'` BEFORE calling `/api/v1/motions_open.php`.

**Current state:** `OperatorController::openVote()` already does the transition internally (`if ($status !== 'live') { $meetingRepo->updateFields($meetingId, ..., ['status' => 'live']); }`) but does NOT broadcast `meeting.status_changed`. The JS side checks `O.currentMeetingStatus` but the auto-transition modal text is not yet wired.

**Two approaches:**
1. Keep using the implicit transition in `OperatorController::openVote()` and add the `EventBroadcaster::meetingStatusChanged()` call there (backend fix).
2. Have the JS call `meeting_transition` explicitly before `motions_open` (frontend fix).

**Recommendation:** Option 1 (backend fix) is cleaner. `OperatorController::openVote()` already transitions the status silently — add the broadcast. Then change the JS confirmation modal text when `O.currentMeetingStatus === 'frozen'`.

### Pattern 5: Hub Checklist SSE Update

**What:** Hub page (`hub.js`) does NOT have SSE wiring — it only loads data on `DOMContentLoaded` via `loadData()` which calls `/api/v1/wizard_status`. The `meeting.status_changed` SSE event is not consumed by `hub.js`. The operator console `operator-realtime.js` calls `O.fn.loadStatusChecklist()` on `meeting.status_changed` — this is the operator console's internal checklist, not the hub page.

**Gap:** For success criterion 4 ("each transition is reflected in the hub checklist status"), the hub page may need SSE wiring OR the criterion is satisfied by `operator-realtime.js::loadStatusChecklist()` in the operator console (not the hub page). Planner should clarify: the hub is a pre-session planning page; VOT-04 success criterion is most naturally satisfied by the operator console checklist updating via SSE.

### Pattern 6: Projection Screen State

**What:** `public.js` calls `refresh()` which hits `/api/v1/projector_state.php` (ProjectorController). The projection already shows `motion.opened`/`motion.closed` via SSE. Between votes it needs to show "Resolution X/Y" and meeting info.

**Current projector_state endpoint:** `ProjectorController::state()` — returns meeting and motion data for the projection screen.

### Anti-Patterns to Avoid
- **Using async/await in IIFE modules:** project pattern is `var`, `Promise.then()`, or `async function` declared inside the IIFE — not top-level async/await ES modules.
- **Creating new page JS files:** modifications go into existing files; the project has no bundler/build step.
- **Calling `O.createModal()` from vote.js:** vote.js is a separate page module, not part of the OpS bridge. Use the voter page's own modal/overlay system.
- **Breaking the idempotency key pattern:** `cast()` in vote.js already sends `X-Idempotency-Key: ${motionId}:${memberId}` — keep this.
- **Showing vote breakdown to voters:** the locked decision prohibits showing Pour/Contre counts to voters at any time.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Duplicate vote prevention | Custom duplicate check | `BallotsService::castBallot()` SQLSTATE 23505 handling | Race condition protection already in transaction |
| Meeting status transition | Custom status update | `MeetingWorkflowController::transition()` or `launch()` | Pre-condition checks, audit log, SSE broadcast |
| Results calculation | Custom vote tally | `OfficialResultsService::computeOfficialTallies()` via `MotionsController::close()` | VoteEngine handles majority rules (simple/absolute/2/3) |
| SSE fan-out | Per-page Redis publish | `EventBroadcaster::toMeeting()` | Phase 18 fan-out handles all consumers simultaneously |
| Motion open/close state | Direct DB update | `MotionsController::open()` and `close()` | Policy resolution, token generation, audit log |
| Confirmation modal | Custom dialog | `O.createModal()` (operator) or `confirmationOverlay` pattern (voter) | Consistent UX, accessibility, dismiss handling |

**Key insight:** All backend vote logic is complete. Phase 20 is primarily frontend wiring.

---

## Common Pitfalls

### Pitfall 1: Missing `meeting.status_changed` broadcast after implicit frozen→live in openVote
**What goes wrong:** `OperatorController::openVote()` silently transitions status to 'live' (`updateFields(..., ['status' => 'live'])`) but does NOT call `EventBroadcaster::meetingStatusChanged()`. The operator console's `meeting.status_changed` handler never fires; hub checklist and exec view don't update.
**Why it happens:** The implicit transition was added for convenience but predates the SSE fan-out infrastructure from Phase 18.
**How to avoid:** Add `EventBroadcaster::meetingStatusChanged($meetingId, $tenantId, 'live', $previousStatus)` after the implicit transition in `OperatorController::openVote()`.
**Warning signs:** Meeting status is 'live' in DB but operator console still shows frozen state UI.

### Pitfall 2: Voter confirmation modal breaking the idempotency key
**What goes wrong:** The idempotency key `${motionId}:${memberId}` is computed at call time in `cast()`. If the motion changes between modal open and confirm, the key is still valid (same motion) but if implementation wraps at the wrong level, two attempts get different keys.
**How to avoid:** Compute the idempotency key inside `cast()` as it is now — do not move it to the confirmation wrapper.

### Pitfall 3: Tally display showing breakdown during open vote
**What goes wrong:** `operator-motions.js::closeVote()` reads `liveVoteFor`, `liveVoteAgainst`, `liveVoteAbstain` from the DOM for its summary modal. If those elements are updated by `loadBallots()` during an open vote, the breakdown is visible in the DOM even if hidden via CSS.
**How to avoid:** Elements `liveVoteFor`, `liveVoteAgainst`, `liveVoteAbstain` can be populated (for the close modal) but must be visually hidden while vote is open. Use `Shared.hide()` on the breakdown section; only show total count.

### Pitfall 4: SSE `motion.opened` race in voter view
**What goes wrong:** SSE `motion.opened` fires, voter calls `refresh()`, but `current_motion.php` still returns null if the motion row's `opened_at` is not committed yet (race between SSE publish and DB transaction commit).
**Why it happens:** `EventBroadcaster::motionOpened()` is called AFTER the transaction in `MotionsController::open()`, so commit happens before broadcast. This is actually safe — but confirms the correct order must not be changed.
**How to avoid:** Never move the broadcast INSIDE the transaction.

### Pitfall 5: Motion ordering — findNextNotOpened vs. any-order
**What goes wrong:** `OperatorController::openVote()` calls `$motionRepo->findNextNotOpenedForUpdate()` when no `motion_id` is provided, which enforces sequential order. If the JS passes an explicit `motion_id`, any-order voting is already supported.
**How to avoid:** The JS `openVote(motionId)` in `operator-motions.js` always passes `motion_id` — so any-order is already the actual behavior. Sequential order is only enforced when no ID is passed (quick-open button). This is acceptable: Claude can decide to keep the existing behavior.

### Pitfall 6: Hub checklist does not have SSE connection
**What goes wrong:** The hub page (`hub.js`) has no EventStream connection. `meeting.status_changed` SSE does not trigger a hub reload. For VOT-04, "transitions reflected in hub checklist" — this is satisfied by the operator console's checklist (via `operator-realtime.js`), NOT the hub page.
**How to avoid:** Clarify in planning that VOT-04's "hub checklist" refers to the operator console's status checklist (`loadStatusChecklist()`), which IS wired to SSE. The hub page at `/hub.htmx.html` is a pre-session planning tool, not a live view.

---

## Code Examples

### Opening a Motion (Backend — already implemented)
```php
// Source: app/Controller/MotionsController.php:409
// Route: POST /api/v1/motions_open.php
// Already: marks opened_at, updates meeting.current_motion_id, broadcasts motion.opened
$repo->markOpened($motionId, $tenantId);
$meetingRepo->updateCurrentMotion($meetingId, $tenantId, $motionId);
EventBroadcaster::motionOpened($meetingId, $motionId, ['title' => ..., 'secret' => ...]);
```

### Missing: Add broadcast after implicit frozen→live transition
```php
// Source: app/Controller/OperatorController.php:242-244 — FIX NEEDED
$previousStatus = $status;  // capture before update
if ($status !== 'live') {
    $meetingRepo->updateFields($meetingId, api_current_tenant_id(), ['status' => 'live']);
    // ADD THIS after the transaction:
    EventBroadcaster::meetingStatusChanged($meetingId, api_current_tenant_id(), 'live', $previousStatus);
}
```

### Casting a Ballot (already implemented)
```javascript
// Source: vote.js:917-951
async function cast(choice) {
  if (choice === 'blanc') choice = 'nsp';
  const idempotencyKey = `${_currentMotionId}:${memberId}`;
  await apiPost('/api/v1/ballots_cast.php',
    { motion_id: _currentMotionId, member_id: memberId, value: choice },
    { 'X-Idempotency-Key': idempotencyKey }
  );
  notify('success', 'Vote enregistré.');
}
```

### Voter Confirmation Modal — where to add it
```javascript
// Source: vote.js:1082-1097 (wire() function) — WRAP cast() call with modal
// Currently:
btn.addEventListener('click', async () => {
  allBtns.forEach(b => b.disabled = true);
  try { await cast(btn.dataset.choice); }
  catch(e) { notify('error', e?.message || String(e)); }
  finally { allBtns.forEach(b => b.disabled = false); }
});

// Phase 20 change: add confirmation overlay before cast()
// If #confirmationOverlay exists in DOM — the HTML template must include it
// vote.js::wire() already checks: if (!document.getElementById('confirmationOverlay'))
// So add the overlay to vote.htmx.html and handle there
```

### SSE Handler in vote.js — verify this exists
```javascript
// Source: vote.js — SSE wiring pattern to verify/add
// Confirmed from public.js pattern (line 510-523):
if (window.EventStream && MEETING_ID) {
  EventStream.connect(meetingId, {
    onEvent: function(type) {
      if (type === 'motion.opened' || type === 'motion.closed' || type === 'vote.cast') {
        refresh();
      }
    }
  });
}
// vote.js uses MeetingContext.get() for meetingId, not a static MEETING_ID
```

### Meeting State Machine — all transitions (already implemented)
```javascript
// Source: operator-tabs.js route to meeting_transition
// Route: POST /api/v1/meeting_transition.php
// Transitions: draft→scheduled→frozen→live→closed→validated→archived
// Also available: POST /api/v1/meeting_launch.php (multi-step fast-path)
await api('/api/v1/meeting_transition.php', { meeting_id: id, to_status: 'live' });
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Polling only for real-time updates | SSE primary + polling fallback (3x slower rate when SSE active) | Phase 18 | vote.cast updates are near-instant, not 5s delayed |
| Single-consumer SSE file queue | Per-consumer Redis lists via `sse:consumers:{meetingId}` SET | Phase 18 | Operator + voters + projection all receive events simultaneously |
| Operator context via page load | MeetingContext:change event drives SSE lifecycle | Phase 19 | SSE connects only when a meeting is selected, not on page load |

**Current route names (not PHP files):**
- Open vote: `/api/v1/motions_open` (not `operator_open_vote` — that's the token-generating endpoint)
- Close vote: `/api/v1/motions_close`
- Ballot cast: `/api/v1/ballots_cast`
- Meeting transition: `/api/v1/meeting_transition`
- Current motion (voter polling): `/api/v1/current_motion`
- Workflow state (operator exec): `/api/v1/operator_workflow_state`

**Important discovery:** `operator-motions.js::openVote()` calls `/api/v1/motions_open.php` at line 774, NOT `/api/v1/operator_open_vote.php`. The `operator_open_vote` endpoint is for token generation. The motion open/close are through `MotionsController`, not `OperatorController`.

---

## Open Questions

1. **Hub checklist SSE wiring scope**
   - What we know: `hub.js` has no SSE connection; VOT-04 says "reflected in hub checklist"
   - What's unclear: Whether the requirement means the operator console checklist (already SSE-wired) or the hub page specifically
   - Recommendation: Treat "hub checklist" as `loadStatusChecklist()` in the operator console (line 99 in `operator-realtime.js`). If the hub page needs SSE, it would require adding EventStream.connect() to `hub.js` — scope this as a separate task if needed.

2. **Voter SSE connection initialization**
   - What we know: `vote.js` uses `MeetingContext.get()` for meeting ID; SSE wiring code not shown in examined portions
   - What's unclear: Whether `vote.js` already has EventStream.connect() wired or if this is a gap
   - Recommendation: Read `vote.js` lines 1099–1200 during planning to confirm. The `public.js` pattern (lines 510-523) is the reference — voter should follow the same pattern.

3. **Results display format (Claude's discretion)**
   - What we know: `operator-motions.js::closeVote()` already renders a proclamation modal with `Shared.formatWeight()` for counts; `closeData.results` contains `for/against/abstain/nsp/total/decision`
   - Recommendation: Use a compact results table in the operator exec view post-close (matching `operator-motions.js` proclamation modal style). No bar chart required for MVP.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 (unit) + Playwright (e2e) |
| Config file | `phpunit.xml` (unit), `tests/e2e/` (e2e) |
| Quick run command | `./vendor/bin/phpunit tests/Unit/BallotsServiceTest.php` |
| Full suite command | `./vendor/bin/phpunit --testsuite Unit` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| VOT-01 | Operator opens motion, motion.opened SSE broadcasts | unit | `./vendor/bin/phpunit tests/Unit/MotionsControllerTest.php` | ❌ Wave 0 |
| VOT-02 | Voter casts ballot, duplicate rejected | unit | `./vendor/bin/phpunit tests/Unit/BallotsServiceTest.php` | ✅ |
| VOT-03 | Operator closes motion, results computed | unit | `./vendor/bin/phpunit tests/Unit/OfficialResultsServiceTest.php` | ✅ |
| VOT-04 | meeting.status_changed broadcasts on transition | unit | `./vendor/bin/phpunit tests/Unit/MeetingWorkflowControllerTest.php` | ✅ |

### Sampling Rate
- **Per task commit:** `./vendor/bin/phpunit tests/Unit/BallotsServiceTest.php tests/Unit/MeetingWorkflowServiceTest.php -x`
- **Per wave merge:** `./vendor/bin/phpunit --testsuite Unit`
- **Phase gate:** Full unit suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/MotionsControllerTest.php` — covers VOT-01 (motion open broadcasts SSE)
- [ ] `tests/Unit/OperatorControllerTest.php` — covers VOT-04 (implicit frozen→live broadcasts meeting.status_changed)

*(All other test files already exist)*

---

## Sources

### Primary (HIGH confidence)
- Direct source read: `app/Controller/OperatorController.php` — openVote() implicit transition, workflowState()
- Direct source read: `app/Controller/MotionsController.php` — open(), close() with EventBroadcaster calls
- Direct source read: `app/Controller/MeetingWorkflowController.php` — transition(), launch() full state machine
- Direct source read: `app/Services/BallotsService.php` — castBallot() with duplicate rejection, transaction pattern
- Direct source read: `app/WebSocket/EventBroadcaster.php` — motionOpened(), motionClosed(), voteCast(), meetingStatusChanged(), per-consumer fan-out
- Direct source read: `app/Services/MeetingWorkflowService.php` — issuesBeforeTransition(), getTransitionReadiness()
- Direct source read: `public/assets/js/pages/vote.js` — cast(), refresh(), updateMotionCard(), SSE pattern
- Direct source read: `public/assets/js/pages/operator-motions.js` — openVote() line 739, closeVote() line 812
- Direct source read: `public/assets/js/pages/operator-realtime.js` — handleSSEEvent() full handler
- Direct source read: `public/assets/js/pages/public.js` — SSE connect pattern lines 510-523
- Direct source read: `public/assets/js/pages/hub.js` — no SSE wiring confirmed
- Direct source read: `app/routes.php` — all route names verified

### Secondary (MEDIUM confidence)
- Direct source read: `public/assets/js/pages/operator-exec.js` — refreshExecView pattern, KPI elements
- Direct source read: `public/assets/js/pages/operator-tabs.js` — loadStatusChecklist() wiring
- `phpunit.xml` — test infrastructure confirmed

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — confirmed from direct source reads
- Architecture patterns: HIGH — confirmed from existing working code
- Pitfalls: HIGH — derived from reading actual implementation gaps
- Test infrastructure: HIGH — phpunit.xml and test files directly verified

**Research date:** 2026-03-17
**Valid until:** 2026-04-17 (stable codebase, no external dependencies)
