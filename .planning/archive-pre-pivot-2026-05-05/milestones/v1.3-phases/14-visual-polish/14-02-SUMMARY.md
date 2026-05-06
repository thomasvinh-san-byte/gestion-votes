---
phase: 14-visual-polish
plan: 02
subsystem: frontend-components
tags: [dark-mode, web-components, shadow-dom, tokens, css-variables]
dependency_graph:
  requires: []
  provides: [POLISH-02]
  affects: [ag-badge, ag-confirm, ag-kpi, ag-modal]
tech_stack:
  added: []
  patterns: [var(--*) token-only Shadow DOM CSS, no fallback hex literals]
key_files:
  created:
    - .planning/phases/14-visual-polish/14-02-DARK-MODE-AUDIT.md
  modified:
    - public/assets/js/components/ag-badge.js
    - public/assets/js/components/ag-confirm.js
    - public/assets/js/components/ag-kpi.js
    - public/assets/js/components/ag-modal.js
decisions:
  - "Remove hex fallbacks entirely from var(--token, #hex) — design-system.css tokens guaranteed-present via shell.js load order"
  - "ag-kpi &#039; string is a false-positive in grep (HTML entity not a color) — documented exception, no fix needed"
  - "ag-toast and 17 other components are carry-forward to Phase 17 LOOSE-03 — out of scope for this plan"
  - "rgba(0,0,0,.45/.5) backdrop mapped to var(--color-backdrop) which has theme-aware values (0.50 light / 0.70 dark)"
metrics:
  duration: 25m
  completed: 2026-04-07
  tasks_completed: 2
  files_modified: 5
---

# Phase 14 Plan 02: Dark Mode Parity Audit + Shadow DOM Fixes Summary

**One-liner:** Removed 61 hex/rgba fallback literals from Shadow DOM in ag-badge, ag-confirm, ag-kpi, ag-modal — all 4 components now consume var(--*) tokens exclusively for dark-mode parity.

## What Was Built

Closed POLISH-02 (dark mode parity) by eliminating the actual remaining debt: Shadow DOM hex fallback literals in the 4 highest-traffic Web Components. The fix targets specifically `var(--token, #hex)` patterns where the fallback hex bypassed theme switching, as well as standalone hex values and `rgba()` backdrop colors.

All 25 per-page CSS files were already token-clean (Phase 12 closed that debt). The only remaining debt was Shadow DOM.

## Tasks Completed

| Task | Description | Commit | Files |
|---|---|---|---|
| 1 | Fix Shadow DOM hex fallbacks in ag-badge, ag-confirm, ag-kpi, ag-modal | 66190a00 | 4 component files |
| 2 | Produce dark-mode audit document with per-file counts | 9a81334c | 14-02-DARK-MODE-AUDIT.md |

## Key Changes

**ag-badge.js** (23 → 0 literals):
- Default badge: `var(--color-bg-subtle, #e8e7e2)` → `var(--color-bg-subtle)`
- Default text: `var(--color-text-muted, #95a3a4)` → `var(--color-text-muted)`
- All 10 variant color rules cleaned (success, warning, danger, info, purple, live, draft, warn, scheduled, closed, validated)

**ag-confirm.js** (16 → 0 literals):
- `rgba(0,0,0,.45)` backdrop → `var(--color-backdrop)`
- `var(--color-surface-raised, #fff)` → `var(--color-surface-raised)`
- `var(--color-border, #d5dbd2)` → `var(--color-border)`
- All 4 variant color objects (danger/warning/info/success) stripped of fallbacks
- Button cancel styles, hover states, text colors all cleaned

**ag-kpi.js** (14 → 1* literal):
- `var(--color-surface, #ffffff)` → `var(--color-surface)`
- `var(--shadow-sm, 0 2px 8px rgba(0,0,0,.06))` → `var(--shadow-sm)`
- `var(--color-text-dark, #1a1a1a)` → `var(--color-text-dark)`
- `var(--color-text-muted, #95a3a4)` → `var(--color-text-muted)`
- All 5 kpi-value and 5 kpi-icon variant colors cleaned
- *1 remaining grep hit is `&#039;` in `escapeHtml()` — HTML entity, not a color

**ag-modal.js** (8 → 0 literals):
- `var(--color-backdrop, rgba(0,0,0,0.5))` → `var(--color-backdrop)`
- `var(--color-surface-raised, #fff)` → `var(--color-surface-raised)`
- `var(--color-border, #d5dbd2)` → `var(--color-border)` (×2: modal border + footer border)
- `var(--color-border-subtle, #e8e7e2)` → `var(--color-border-subtle)` (×2: header + footer)
- `var(--color-text-dark, #1a1a1a)` → `var(--color-text-dark)`
- `var(--color-text-muted, #95a3a4)` → `var(--color-text-muted)`
- `var(--color-bg-subtle, #e8e7e2)` → `var(--color-bg-subtle)`

## Deviations from Plan

None — plan executed exactly as written.

The ag-kpi `&#039;` false-positive was anticipated by the plan's "≤1 with documented exception" allowance and is recorded in 14-02-DARK-MODE-AUDIT.md.

## Self-Check: PASSED

Files exist:
- public/assets/js/components/ag-badge.js: FOUND
- public/assets/js/components/ag-confirm.js: FOUND
- public/assets/js/components/ag-kpi.js: FOUND
- public/assets/js/components/ag-modal.js: FOUND
- .planning/phases/14-visual-polish/14-02-DARK-MODE-AUDIT.md: FOUND

Commits:
- 66190a00: feat(14-02): replace Shadow DOM hex fallbacks — FOUND
- 9a81334c: docs(14-02): produce dark mode parity audit — FOUND

Hex literal counts post-fix:
- ag-badge.js: 0 (target: 0) — PASS
- ag-confirm.js: 0 (target: 0) — PASS
- ag-kpi.js: 1 (target: ≤1, documented exception) — PASS
- ag-modal.js: 0 (target: 0) — PASS
