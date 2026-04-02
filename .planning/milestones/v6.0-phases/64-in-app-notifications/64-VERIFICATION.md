---
phase: 64-in-app-notifications
verified: 2026-04-01T07:30:00Z
status: human_needed
score: 7/8 must-haves verified
re_verification: false
human_verification:
  - test: "Bell badge shows correct unread count"
    expected: "Bell icon in header shows a numeric badge matching the server-side unread_count for the logged-in user"
    why_human: "Requires a live browser session with an authenticated user and real notification data in the database"
  - test: "Notification panel renders French labels"
    expected: "Clicking the bell opens a panel listing recent notifications with French labels (e.g., 'Séance démarrée', 'Vote ouvert') and human-readable timestamps"
    why_human: "Label rendering depends on real n.type values returned by the API — cannot verify mapping output without a running app"
  - test: "Mark-all-read clears badge to 0"
    expected: "Opening the panel triggers markNotificationsRead, the PUT to /api/v1/notifications_read.php succeeds, then fetchNotifications refreshes the badge to 0"
    why_human: "Requires database state (unread notifications) and a live API round-trip to verify the full flow"
  - test: "SSE toasts appear on operator and hub pages"
    expected: "Opening a vote in the operator console triggers an ag-toast 'Vote ouvert : [title]'; quorum reached triggers 'Quorum atteint'; session changes trigger appropriate toasts on both operator and public/hub pages"
    why_human: "Real-time SSE event behavior requires a live server, active session, and browser observation — not verifiable statically"
---

# Phase 64: In-App Notifications Verification Report

**Phase Goal:** Users receive real-time awareness of important events through a persistent notification bell and transient toast messages
**Verified:** 2026-04-01T07:30:00Z
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| #  | Truth                                                                                  | Status      | Evidence |
|----|----------------------------------------------------------------------------------------|-------------|----------|
| 1  | Bell badge shows correct unread_count from API response                                | ✓ VERIFIED  | shell.js L675-676: `data.notifications \|\| data.items` + `data.unread_count` used; badge capped at "9+" L681 |
| 2  | Notification panel renders human-readable French labels for each event type            | ✓ VERIFIED  | NOTIF_LABELS map L585-600 with 14 entries, all using proper accented French; used at L698 |
| 3  | Marking all as read sends `{all: true}` body and refreshes badge to 0                 | ✓ VERIFIED  | shell.js L707: `window.api('/api/v1/notifications_read.php', { all: true }, 'PUT')` then L708: `await fetchNotifications()` |
| 4  | NotificationsService passes all existing unit tests                                    | ✓ VERIFIED  | 22/22 tests pass in NotificationsServiceTest.php (verified live) |
| 5  | When a vote opens on the operator page, an ag-toast appears with the motion title      | ? UNCERTAIN | SSE forwarding wired in operator-realtime.js L128-130; SSE_TOAST_MAP handles motion.opened L603-606; runtime behavior needs human |
| 6  | When a vote opens on the hub page, an ag-toast appears with the motion title           | ? UNCERTAIN | SSE forwarding wired in public.js L535-537; same SSE_TOAST_MAP invoked; runtime behavior needs human |
| 7  | When quorum is reached, a success toast appears                                        | ? UNCERTAIN | SSE_TOAST_MAP quorum.updated handler L608-611 returns `{type: 'success', msg: 'Quorum atteint'}` when `data.quorum.met` is true; runtime needs human |
| 8  | When a session starts or closes, a toast appears                                       | ? UNCERTAIN | SSE_TOAST_MAP meeting.status_changed L612-615 handles 'live' and 'closed' states; runtime needs human |

**Score:** 4/4 automated truths verified; 4 truths require human/runtime verification

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Services/NotificationsService.php` | Service layer — emit, list, markRead, markAllRead, clear, emitReadinessTransitions | ✓ VERIFIED | Exists, 227 lines, all methods present, namespace `AgVote\Service`, 22 tests pass. Note: PLAN specified `app/Service/NotificationsService.php` (wrong path) — actual path is `app/Services/NotificationsService.php` matching the PSR-4 mapping in composer.json |
| `public/assets/js/core/shell.js` | NOTIF_LABELS, SSE_TOAST_MAP, fixed renderNotifications, fixed markNotificationsRead, window.Notifications.handleSseEvent | ✓ VERIFIED | All patterns present and substantive |
| `public/assets/js/pages/operator-realtime.js` | SSE event forwarding to Notifications.handleSseEvent | ✓ VERIFIED | L128-130: null-safe forwarding at end of handleSSEEvent() |
| `public/assets/js/pages/public.js` | SSE event forwarding to Notifications.handleSseEvent | ✓ VERIFIED | L535-537: null-safe forwarding inside onEvent callback |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `shell.js` fetchNotifications | `/api/v1/notifications.php` | `window.api()` call | ✓ WIRED | L666: `window.api('/api/v1/notifications.php')` |
| `shell.js` renderNotifications | `data.notifications` | data shape fix | ✓ WIRED | L675: `data.notifications \|\| data.items \|\| []` |
| `shell.js` markNotificationsRead | `/api/v1/notifications_read.php` | `{all: true}` body | ✓ WIRED | L707: `window.api(..., { all: true }, 'PUT')` then L708 badge refresh |
| `operator-realtime.js` handleSSEEvent | `window.Notifications.handleSseEvent` | end of switch | ✓ WIRED | L128-130: null-safe guard, called after all page-specific handling |
| `public.js` onEvent | `window.Notifications.handleSseEvent` | end of onEvent callback | ✓ WIRED | L535-537: null-safe guard inside onEvent |
| `shell.js` SSE_TOAST_MAP | `Shared.showToast` | handleSseEvent | ✓ WIRED | L720-722: `Shared.showToast(toast.msg, toast.type)` with `typeof Shared !== 'undefined'` guard |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| NOTIF-01 | 64-01 | Bell icon in header shows badge with unread notification count | ✓ SATISFIED | createNotifBell() wires badge, fetchNotifications reads unread_count, badge renders "9+" cap |
| NOTIF-02 | 64-01 | Clicking bell shows list of recent notifications with French labels | ✓ SATISFIED | renderNotifications uses NOTIF_LABELS map, panel markup renders n.type → French label, timestamps shown |
| NOTIF-03 | 64-02 | Toast appears in real-time via SSE when important event occurs | ✓ SATISFIED (code) | SSE_TOAST_MAP covers motion.opened/closed, quorum.updated, meeting.status_changed; forwarding wired in both operator-realtime.js and public.js |

All three requirement IDs declared across plans (NOTIF-01, NOTIF-02 in 64-01; NOTIF-03 in 64-02) are accounted for. No orphaned requirements found in REQUIREMENTS.md for Phase 64.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `public/assets/js/core/shell.js` | 760 | HTML `placeholder="Rechercher une page…"` | ℹ️ Info | Search input placeholder text — this is a legitimate HTML placeholder attribute, not a stub indicator |

No blocker anti-patterns. No TODO/FIXME/stub patterns in any phase-modified files.

### Key Deviation Noted

The PLAN artifacts specified `app/Service/NotificationsService.php` (singular) but the file lives at `app/Services/NotificationsService.php` (plural), matching composer.json PSR-4 mapping `"AgVote\\Service\\": "app/Services/"`. The namespace `AgVote\Service` is correct in both the file and all callers. This was a documentation error in the plan, not an implementation error.

Plan 02 also auto-corrected target files: plan specified `operator-exec.js` and `hub.js`, but SSE handling actually lives in `operator-realtime.js` and `public.js`. The executor applied the changes to the correct files.

### Human Verification Required

#### 1. Bell Badge Unread Count

**Test:** Log in as an operator. Observe the bell icon in the app header.
**Expected:** Bell shows a numeric badge (e.g., "3") matching the unread notification count from `/api/v1/notifications.php`. If no unread notifications, badge is hidden.
**Why human:** Requires authenticated session and real DB state.

#### 2. Notification Panel French Labels

**Test:** Click the bell icon to open the panel.
**Expected:** Panel lists recent notifications with French labels from NOTIF_LABELS (e.g., "Séance démarrée", "Vote ouvert", "Présences mises à jour") rather than raw event type codes. Timestamps are visible.
**Why human:** Label mapping requires real notification records with typed `n.type` fields.

#### 3. Mark-All-Read Clears Badge

**Test:** With unread notifications present, click the bell to open the panel. Close it and click again.
**Expected:** On first open, markNotificationsRead fires silently. On second open, badge shows 0 (or is hidden). The PUT to `/api/v1/notifications_read.php` with `{all: true}` should have fired.
**Why human:** Requires unread DB state and live API round-trip.

#### 4. SSE Toasts on Operator Page

**Test:** On the operator console with an active session, open a vote from the motions panel.
**Expected:** An ag-toast appears saying "Vote ouvert : [motion title]". Similarly, when quorum is reached, a success toast "Quorum atteint" appears. When session starts/closes, "Séance démarrée" or "Séance clôturée" appears.
**Why human:** Requires real SSE connection, live server, and observable browser behavior.

#### 5. SSE Toasts on Hub/Public Page

**Test:** Open the public/hub page for a live session. Trigger an event (vote open, quorum, status change) from the operator side.
**Expected:** The same toast messages appear on the hub page via public.js SSE forwarding.
**Why human:** Cross-page SSE behavior requires two browser tabs and a live server.

### Gaps Summary

No code-level gaps. All automated checks pass: NotificationsService exists and passes 22 unit tests, shell.js has all required patterns wired correctly (NOTIF_LABELS, SSE_TOAST_MAP, data shape fix, mark-all-read fix, handleSseEvent export), and both operator-realtime.js and public.js forward SSE events to the toast system.

The phase goal is fully implemented at the code level. Human verification (Task 3 from Plan 02) was deferred by the user during execution and remains outstanding. Recommend a spot-check in browser before declaring the phase fully signed off.

---

_Verified: 2026-04-01T07:30:00Z_
_Verifier: Claude (gsd-verifier)_
