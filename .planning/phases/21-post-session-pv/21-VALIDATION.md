---
phase: 21
slug: post-session-pv
status: validated
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-18
updated: 2026-03-18
---

# Phase 21 — Validation Strategy

> Per-phase validation contract. Phase 21 is a wiring-fix phase — backend logic is tested, JS wiring is manual-only.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x (backend), Playwright E2E (frontend) |
| **Config file** | `phpunit.xml` / `playwright.config.js` |
| **Quick run command** | `vendor/bin/phpunit tests/Unit/MeetingWorkflowControllerTest.php` |
| **Full suite command** | `vendor/bin/phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `vendor/bin/phpunit tests/Unit/MeetingWorkflowControllerTest.php`
- **After every plan wave:** Run `vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 21-01-01 | 01 | 1 | PST-01 | manual | Browser: postsession step 1 loads motion data | N/A | ✅ manual-only |
| 21-01-02 | 01 | 1 | PST-02 | manual | Browser: postsession step 2 consolidates then validates | N/A | ✅ manual-only |
| 21-01-03 | 01 | 1 | PST-03 | manual | Browser: postsession step 3 downloads PDF | N/A | ✅ manual-only |
| 21-01-04 | 01 | 1 | PST-04 | manual | Browser: postsession step 4 archives meeting | N/A | ✅ manual-only |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

None — no automated test stubs needed. All requirements verified manually + code-verified by integration checker.

---

## Manual-Only Requirements

| Requirement | Reason | Verification Method |
|-------------|--------|-------------------|
| PST-01 | JS endpoint wiring — no E2E spec for postsession stepper | Browse postsession page, verify step 1 loads motion results from `motions_for_meeting.php` |
| PST-02 | JS call sequence — consolidation before transition | Browse postsession page, verify step 2 calls consolidate then transition to validated |
| PST-03 | Vendor dependency — Dompdf installed, endpoint wired | Browse postsession page, verify step 3 generates PDF download |
| PST-04 | JS endpoint wiring — archive uses `meeting_transition.php` | Browse postsession page, verify step 4 archives meeting, no correspondance link |

**Code-level verification:** All 4 requirements were independently verified correct by the v3.0 integration checker (2026-03-18 re-audit). Backend logic is covered by `MeetingWorkflowControllerTest`, `OfficialResultsServiceTest`, and `MeetingWorkflowServiceTest`.

---

## Sign-Off

| Check | Status |
|-------|--------|
| All requirements have test mapping | ✅ (manual-only) |
| Backend logic tested | ✅ (PHPUnit) |
| JS wiring code-verified | ✅ (integration checker) |
| E2E automated tests | ❌ (not written — manual-only) |

---

## Validation Audit 2026-03-18

| Metric | Count |
|--------|-------|
| Gaps found | 4 |
| Resolved | 0 |
| Escalated to manual-only | 4 |
| Backend tests covering logic | 3 (MeetingWorkflowControllerTest, OfficialResultsServiceTest, MeetingWorkflowServiceTest) |

---

*Phase: 21-post-session-pv*
*Validation created: 2026-03-18 (retroactive, from artifacts)*
