---
phase: 19
slug: operator-console-wiring
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-16
---

# Phase 19 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `vendor/bin/phpunit tests/Unit/AttendancesControllerTest.php tests/Unit/MotionsControllerTest.php` |
| **Full suite command** | `vendor/bin/phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `vendor/bin/phpunit tests/Unit/AttendancesControllerTest.php tests/Unit/MotionsControllerTest.php`
- **After every plan wave:** Run `vendor/bin/phpunit --testsuite Unit`
- **Before `/gsd:verify-work`:** Full suite must be green + manual browser verification of all 4 OPR requirements
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 19-01-01 | 01 | 1 | OPR-01 | manual | Browser: navigate to `/operator.htmx.html?meeting_id=UUID`, verify title loads | N/A — frontend JS | ⬜ pending |
| 19-01-02 | 01 | 1 | OPR-04 | manual | Browser devtools: open operator without meeting_id, confirm no SSE in Network tab | N/A — frontend JS | ⬜ pending |
| 19-01-03 | 01 | 1 | OPR-02 | unit (PHP) + manual | `vendor/bin/phpunit tests/Unit/AttendancesControllerTest.php` | ✅ exists | ⬜ pending |
| 19-01-04 | 01 | 1 | OPR-03 | unit (PHP) + manual | `vendor/bin/phpunit tests/Unit/MotionsControllerTest.php` | ✅ exists | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No new PHP files introduced — frontend JS changes require manual verification only.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Operator loads real meeting title/status via MeetingContext | OPR-01 | Frontend JS wiring — no PHPUnit coverage for browser behavior | Navigate to `/operator.htmx.html?meeting_id=UUID`, verify meeting title displays correctly with no hardcoded values |
| SSE connects only on MeetingContext:change | OPR-04 | Frontend JS event wiring — browser devtools verification | Open operator page without meeting_id, verify no SSE request in Network tab. Then select meeting, verify SSE connects |
| Attendance tab shows real participants with quorum | OPR-02 | JS rendering of API data — PHP API tested separately | Select meeting with known attendance, verify participant list matches API response, quorum % is correct |
| Motions tab lists real resolutions in order | OPR-03 | JS rendering of API data — PHP API tested separately | Select meeting with known motions, verify resolution list matches API response in correct order |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
