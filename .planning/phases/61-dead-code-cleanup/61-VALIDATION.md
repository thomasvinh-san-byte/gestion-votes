---
phase: 61
slug: dead-code-cleanup
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-31
---

# Phase 61 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 |
| **Config file** | phpunit.xml |
| **Quick run command** | `php vendor/bin/phpunit` |
| **Full suite command** | `php vendor/bin/phpunit` |
| **Estimated runtime** | ~2 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php vendor/bin/phpunit`
- **After every plan wave:** Run `php vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 2 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 61-01-01 | 01 | 1 | CLEAN-02, CLEAN-03 | grep | `grep -rni "copropri\|syndic" SETUP.md docs/ && echo FAIL \|\| echo PASS; grep -n "WebSocket" phpunit.xml && echo FAIL \|\| echo PASS` | N/A | ⬜ pending |
| 61-01-02 | 01 | 1 | CLEAN-01 | grep + phpunit | `grep -rn "not.implemented\|stub\|placeholder" app/Controller/ --include="*.php"; vendor/bin/phpunit --no-coverage 2>&1 \| tail -3` | N/A | ⬜ pending |
| 61-01-03 | 01 | 1 | CLEAN-03 | grep | `grep -l "CLI tool" app/Command/*.php \| wc -l` (expect 4) | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No new test files needed.

---

## Manual-Only Verifications

All phase behaviors have automated verification (grep-based).

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 2s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved
