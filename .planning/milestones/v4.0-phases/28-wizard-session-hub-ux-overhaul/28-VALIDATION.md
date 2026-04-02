---
phase: 28
slug: wizard-session-hub-ux-overhaul
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-18
---

# Phase 28 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x + Playwright 1.50 |
| **Config file** | `phpunit.xml` / `playwright.config.ts` |
| **Quick run command** | `grep -rn "wiz-step-label\|STATUS_CTA\|ag-quorum-bar" public/` |
| **Full suite command** | `php vendor/bin/phpunit && npx playwright test` |
| **Estimated runtime** | ~5 seconds (grep) + ~90 seconds (full) |

---

## Sampling Rate

- **After every task commit:** Run structural grep checks
- **After every plan wave:** Run `php vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 5 seconds

---

## Per-Task Verification Map

Populated during planning.

---

## Wave 0 Requirements

*Existing test infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Wizard step fade transition (150ms) | WIZ-01 | CSS animation timing | Navigate between steps, verify smooth fade |
| Review card layout and "Modifier" links | WIZ-03 | Visual layout | Complete steps 1-3, verify step 4 shows all data with working back links |
| Quorum bar threshold tick animation | WIZ-07 | CSS animation | Load hub with partial attendance, verify amber→green transition |
| Notion-like visual quality | WIZ-01 | Subjective design | Compare wizard before/after screenshots |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 5s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
