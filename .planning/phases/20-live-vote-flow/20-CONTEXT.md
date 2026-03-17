# Phase 20: Live Vote Flow - Context

**Gathered:** 2026-03-17
**Status:** Ready for planning

<domain>
## Phase Boundary

The full vote cycle works end-to-end — operator opens a motion, voters cast ballots, results are calculated, and the operator sees the tally update in real-time. Meeting state machine transitions (draft → scheduled → frozen → live → closed → validated) execute without errors and are reflected in the hub checklist.

**Requirements:** VOT-01, VOT-02, VOT-03, VOT-04

**Success Criteria:**
1. An operator opens a motion and the voter view immediately shows the motion as active without a page reload
2. A voter submits a ballot and receives a confirmation; the ballot is recorded in the database and cannot be submitted a second time
3. An operator closes a motion and the vote counts are calculated and displayed in the operator console
4. The full meeting state machine transitions execute without errors and each transition is reflected in the hub checklist status

</domain>

<decisions>
## Implementation Decisions

### Voter ballot experience
- **Auto-switch to ballot**: When SSE `motion.opened` event fires, the voter view automatically transitions to show the active ballot card — no manual navigation required
- **Ballot options**: 4 choices — Pour / Contre / Abstention / NSP (ne se prononce pas). Backend already validates all four values in `BallotsService::castBallot()`
- **Confirmation before submit**: After tapping a ballot option, show a confirmation modal ("Etes-vous sur de voter Pour ?") before submitting. Required because votes are irrevocable
- **One shot, no re-vote**: Once submitted, the vote is final and cannot be changed. Matches traditional assembly voting norms and legal expectations. `BallotsService` already enforces duplicate rejection
- **Post-submission state**: Claude decides the exact post-vote screen (confirmation card with disabled buttons vs. toast + waiting screen) based on existing vote.js patterns
- **No results for voters**: After `motion.closed`, voter sees "Vote termine" but NOT the breakdown. Results are visible only on operator console and projection screen
- **Proxy voting deferred**: Only direct voting in Phase 20. Proxy ballot handling is a separate future phase

### Live tally & results
- **Count only during open vote**: Operator sees "12/25 votes recus" in real-time via SSE `vote.cast` events. Pour/contre breakdown is NOT shown while vote is open (prevents operator bias)
- **Full breakdown after close**: After closing a motion, operator sees full results: Pour, Contre, Abstention, NSP counts + percentages. Claude decides format (table or bar chart) based on operator console UI patterns
- **Auto-computed verdict + operator override**: System computes "Adopte" or "Rejete" based on the motion's majority rule (simple, absolute, 2/3). Operator/president can manually override the verdict if needed (procedural reasons, tie-breaking)
- **Who hasn't voted**: Claude decides whether to show the list of non-voters or just the anonymous count, based on the app's existing privacy model and `BallotsService` data structure

### State machine wiring
- **Auto-transition with confirmation on first vote**: When operator clicks "Ouvrir le vote" and meeting is still in 'frozen', the confirmation modal says "Cela demarrera la seance. Continuer?" — one click transitions to 'live' AND opens the vote. Operator retains control (can cancel) but skips the separate "start meeting" step
- **All manual transitions**: Meeting state transitions (frozen→live, live→closed) are operator-triggered. Closing the last motion does NOT auto-close the meeting — operator clicks "Cloturer la seance" explicitly
- **One motion at a time**: Only one motion can be open for voting simultaneously. Closing one unlocks the next. Matches physical assembly norms and the existing `operator-exec.js` pattern (`currentOpenMotion` is singular)
- **Any order for motions**: Claude decides whether to enforce sequential order or allow any-order voting based on existing `operator-exec.js` next-motion logic

### Projection screen sync
- **During open vote**: Show motion title + "Vote en cours" + live progress bar (X/Y votes recus). Audience sees activity without knowing the breakdown
- **After close — auto-reveal**: Full results + verdict (Adopte/Rejete) appear automatically on projection when the motion closes. No extra operator click needed — the "close vote" confirmation is the control point
- **Between votes**: Show meeting info — title, date, current agenda position ("Resolution 3/7"). Keeps the audience oriented

### Claude's Discretion
- Voter authentication method (existing session auth vs. vote tokens — based on existing vote.js auth patterns)
- Post-vote screen design (confirmation card vs. toast + waiting)
- Results format on operator console (table vs. bar chart)
- Non-voter list visibility decision
- Motion ordering enforcement (sequential vs. any order)
- Loading skeleton designs for all views
- Exact progress bar implementation on projection screen
- Error handling for failed ballot submissions

</decisions>

<specifics>
## Specific Ideas

- French language throughout: "Vote en cours", "Vote termine", "Adopte", "Rejete", "Etes-vous sur de voter Pour ?", "Cela demarrera la seance. Continuer?", "Resolution 3/7"
- Between votes, projection shows meeting info + agenda position — keeps audience oriented during assembly proceedings
- The auto-transition on first vote open is a UX optimization: fewer clicks for the operator while maintaining explicit control via confirmation modal
- SSE events used: `motion.opened` (ballot appears for voters), `vote.cast` (tally count updates), `motion.closed` (results displayed), `meeting.status_changed` (hub checklist updates)
- Phase 18 fan-out pattern ensures operator + voters + projection all receive every event simultaneously

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `BallotsService::castBallot()` (`app/Services/BallotsService.php:48`): Complete ballot submission with duplicate detection, meeting status check, motion status check
- `BallotRepository::castBallot()` (`app/Repository/BallotRepository.php:118`): PDO insert for ballot records
- `MeetingWorkflowService` (`app/Services/MeetingWorkflowService.php`): State machine with pre-condition checks, transition validation, readiness summary
- `OperatorController::openVote` (`app/Controller/OperatorController.php`): Backend handler for opening a motion, routed at `/api/v1/operator_open_vote`
- `EventBroadcaster::toMeeting()` (`app/WebSocket/EventBroadcaster.php`): SSE event publishing with fan-out to per-consumer queues (Phase 18)
- `PermissionChecker::canTransition()` (`app/Core/Security/PermissionChecker.php:75`): Validates state transitions against allowed rules
- `Permissions::TRANSITIONS` (`app/Core/Security/Permissions.php:101`): Defines the full state machine graph

### Established Patterns
- `operator-motions.js:openVote()` (line 739): Already has confirmation modal with ag-confirm component
- `operator-motions.js:closeVote()` (line 812): Already has close confirmation modal
- `operator-exec.js`: Exec view with `O.currentOpenMotion` tracking, live dot indicator, next-motion auto-advance
- `operator-realtime.js`: SSE event handler for `motion.opened` (line 65) and `motion.closed` (line 80)
- `vote.js`: ~1100+ line voter view with motion display, SSE handling, ballot UI
- `public.js`: Room display with SSE handlers for `motion.opened`/`motion.closed` (line 516)
- `event-stream.js`: EventSource wrapper with auto-reconnect, polling fallback
- OpS bridge pattern: Cross-module state via `window.OpS` with `OpS.fn` function registry
- IIFE + var + 'use strict' for all page JS modules

### Integration Points
- `operator-motions.js:openVote()` → calls `/api/v1/operator_open_vote` → EventBroadcaster publishes `motion.opened`
- `vote.js` SSE handler → receives `motion.opened` → auto-switches to ballot view
- `vote.js` ballot submit → calls `BallotsService::castBallot()` → EventBroadcaster publishes `vote.cast`
- `operator-realtime.js` → receives `vote.cast` → updates tally count display
- `operator-motions.js:closeVote()` → calls close endpoint → EventBroadcaster publishes `motion.closed`
- `public.js` → receives `motion.opened`/`motion.closed`/`vote.cast` → updates projection display
- `MeetingWorkflowController::transition()` at route `/api/v1/meeting_transition` → state machine changes
- Hub checklist → receives `meeting.status_changed` SSE → updates status display

</code_context>

<deferred>
## Deferred Ideas

- Proxy voting (procurations) — separate future phase, not wired in Phase 20
- Operator-triggered result reveal on projection — rejected in favor of auto-reveal on close (simpler UX)

</deferred>

---

*Phase: 20-live-vote-flow*
*Context gathered: 2026-03-17*
