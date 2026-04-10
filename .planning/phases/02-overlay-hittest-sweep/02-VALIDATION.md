---
phase: 02
slug: overlay-hittest-sweep
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-10
---

# Phase 02 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright (via Docker) |
| **Config file** | `tests/e2e/playwright.config.ts` |
| **Quick run command** | `docker exec agvote-playwright npx playwright test hidden-attr.spec.js --project=chromium` |
| **Full suite command** | `docker exec agvote-playwright npx playwright test --project=chromium` |
| **Estimated runtime** | ~30 seconds (new spec), ~120 seconds (full suite) |

---

## Sampling Rate

- **After every task commit:** Run hidden-attr spec
- **After every plan wave:** Run full Playwright chromium suite
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | OVERLAY-01 | integration | `grep ':where(\[hidden\])' public/assets/css/design-system.css` | ❌ W0 | ⬜ pending |
| 02-01-02 | 01 | 1 | OVERLAY-02 | audit | `test -f .planning/v1.4-overlay-hittest.md` | ❌ W0 | ⬜ pending |
| 02-01-03 | 01 | 2 | OVERLAY-01 | e2e | `docker exec agvote-playwright npx playwright test hidden-attr.spec.js` | ❌ W0 | ⬜ pending |
| 02-01-04 | 01 | 2 | OVERLAY-03 | regression | `docker exec agvote-playwright npx playwright test keyboard-nav.spec.js page-interactions.spec.js` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/e2e/hidden-attr.spec.js` — stubs for OVERLAY-01 hidden attribute enforcement
- [ ] Existing `keyboard-nav.spec.js` and `page-interactions.spec.js` cover OVERLAY-03 regression

*Existing infrastructure covers regression requirements. New spec needed only for hidden attribute enforcement.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Visual check that previously-hidden overlays don't flash on page load | OVERLAY-01 | Timing-dependent, hard to assert via Playwright | Navigate to operator, settings, vote pages; confirm no overlay flash |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
