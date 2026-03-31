---
phase: 61
slug: dead-code-cleanup
status: draft
nyquist_compliant: false
wave_0_complete: false
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
| 61-01-01 | 01 | 1 | CLEAN-01 | grep | `grep -rn "not.implemented\|stub\|placeholder" app/Controller/ --include="*.php"` | N/A | ⬜ pending |
| 61-01-02 | 01 | 1 | CLEAN-02 | grep | `grep -rni "copropri\|syndic" SETUP.md docs/ scripts/ public/api/` | N/A | ⬜ pending |
| 61-01-03 | 01 | 1 | CLEAN-03 | grep | `grep -rn "WebSocket\|websocket" phpunit.xml` | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements.

---

## Manual-Only Verifications

All phase behaviors have automated verification (grep-based).

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 2s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
