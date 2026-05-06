---
phase: 3
slug: feedback-et-etats-vides
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-21
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 + Playwright E2E |
| **Config file** | `phpunit.xml` / `tests/e2e/playwright.config.ts` |
| **Quick run command** | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Full suite command** | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php -l` on modified PHP files (syntax check)
- **After every plan wave:** Run full unit test suite
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | Status |
|---------|------|------|-------------|-----------|-------------------|--------|
| 3-01-01 | 01 | 1 | FEED-02 | grep | `grep -q voteConfirmedTimestamp public/vote.htmx.html` | ⬜ pending |
| 3-01-02 | 01 | 1 | FEED-04 | grep | `grep -q loading-label public/meetings.htmx.html` | ⬜ pending |
| 3-02-01 | 02 | 1 | FEED-01 | grep | `grep -q ag-empty-state public/email-templates.htmx.html` | ⬜ pending |
| 3-02-02 | 02 | 1 | FEED-03 | grep | `grep -q reset-filters public/assets/js/pages/meetings.js` | ⬜ pending |

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. ag-empty-state component already built. Skeleton and loading CSS already present.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Vote confirmation stays visible | FEED-02 | Runtime behavior | Cast a vote, verify confirmation persists with timestamp |
| Empty state shows on empty list | FEED-01 | Requires empty database state | Clear meetings list, verify ag-empty-state appears |
| Filter reset clears results | FEED-03 | Interactive behavior | Apply filter with no results, click Reinitialiser |
| Loading text visible during load | FEED-04 | Timing-dependent | Navigate to meetings, observe "Chargement..." during load |

---

## Validation Sign-Off

- [ ] All tasks have automated verify
- [ ] Sampling continuity maintained
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
