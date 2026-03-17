---
phase: 18
slug: sse-infrastructure
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-16
---

# Phase 18 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit (backend) + grep verification (infrastructure config) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `vendor/bin/phpunit --testsuite unit` |
| **Full suite command** | `vendor/bin/phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Verify changed files with grep for key patterns + PHPUnit
- **After every plan wave:** Full grep verification of all SSE-01 through SSE-04 criteria
- **Before `/gsd:verify-work`:** All verification commands in plan pass
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 18-01-01 | 01 | 1 | SSE-01 | grep | `grep -c "sMembers\|sse:consumers" app/WebSocket/EventBroadcaster.php` ≥2 | ✅ | ⬜ pending |
| 18-01-02 | 01 | 1 | SSE-01 | grep | `grep -c "sAdd\|sRem\|sse:consumers" public/api/v1/events.php` ≥3 | ✅ | ⬜ pending |
| 18-01-03 | 01 | 1 | SSE-02 | grep | `grep -c "fastcgi_buffering off" deploy/nginx.conf` = 1 | ✅ | ⬜ pending |
| 18-01-04 | 01 | 1 | SSE-03 | grep | `grep -c "SSE" deploy/php-fpm.conf` ≥1 | ✅ | ⬜ pending |
| 18-01-05 | 01 | 1 | SSE-04 | grep | `grep -c "vote.cast" public/assets/js/pages/operator-realtime.js` ≥1 | ✅ | ⬜ pending |
| 18-01-06 | 01 | 1 | ALL | phpunit | `vendor/bin/phpunit --testsuite unit` passes | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing PHPUnit test infrastructure covers backend validation. Infrastructure config changes (nginx, PHP-FPM) are verified by grep pattern matching. No new test framework needed.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Two browser tabs receive same SSE events | SSE-01 | Requires running Redis + PHP-FPM | Open operator + voter tab for same meeting; trigger vote; verify both update |
| SSE events arrive without batch delay | SSE-02 | Requires running nginx | Open browser console; verify events arrive within 1-2s of trigger |
| Vote count updates in operator console | SSE-04 | Requires live session | Cast vote from voter tab; verify operator tally updates within 3s |

---

## Validation Sign-Off

- [x] All tasks have automated verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 15s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
