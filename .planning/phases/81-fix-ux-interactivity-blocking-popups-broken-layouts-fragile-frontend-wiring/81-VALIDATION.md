---
phase: 81
slug: fix-ux-interactivity-blocking-popups-broken-layouts-fragile-frontend-wiring
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-03
---

# Phase 81 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.x + Playwright |
| **Config file** | phpunit.xml.dist / playwright.config.ts |
| **Quick run command** | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Full suite command** | `timeout 120 php vendor/bin/phpunit --no-coverage` |
| **Estimated runtime** | ~30 seconds (unit), ~90 seconds (full) |

---

## Sampling Rate

- **After every task commit:** Run `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **After every plan wave:** Run `timeout 120 php vendor/bin/phpunit --no-coverage`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| TBD | TBD | TBD | UX fix | browser | Playwright visual check | TBD | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. PHPUnit and Playwright are already configured.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Modal backdrop click closes | D-02 | Visual interaction | Click outside modal, verify it closes |
| Wizard horizontal layout | D-04 | Visual layout | Open wizard, verify 2-3 column grid on desktop |
| Toast feedback on API calls | D-08 | Visual feedback | Trigger CRUD action, verify toast appears |
| SSE disconnect banner | D-11 | Network state | Disconnect SSE, verify warning banner appears |
| Page-level visual coherence | D-13 | Visual audit | Compare spacing, typography across pages |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
