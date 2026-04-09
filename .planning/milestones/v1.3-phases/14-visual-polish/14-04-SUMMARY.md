---
phase: 14-visual-polish
plan: "04"
subsystem: frontend-css
tags: [design-system, micro-interactions, accessibility, e2e-tests]
dependency_graph:
  requires: []
  provides: [POLISH-04]
  affects: [public/assets/css/design-system.css, tests/e2e/specs/ux-interactions.spec.js]
tech_stack:
  added: []
  patterns: [CSS custom properties, prefers-reduced-motion, Playwright computed-style assertion]
key_files:
  modified:
    - public/assets/css/design-system.css
    - tests/e2e/specs/ux-interactions.spec.js
decisions:
  - "Used login page for hover assertion — no auth required, idempotent"
  - "Added explicit .btn prefers-reduced-motion block alongside global * block for grep-verifiable contract"
  - ".btn-danger:focus-visible override added using --shadow-focus-danger token"
metrics:
  duration: 8m
  completed: "2026-04-09T06:59:40Z"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 2
---

# Phase 14 Plan 04: Micro-interactions Polish Summary

Closed POLISH-04 by adding missing `:active` states for `.btn-secondary` and `.btn-ghost`, a `.btn-danger:focus-visible` override using `--shadow-focus-danger`, an explicit `@media (prefers-reduced-motion: reduce)` block covering `.btn` transforms, and a Playwright `getComputedStyle` hover assertion in `ux-interactions.spec.js`.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Audit and patch .btn variant states | a4b268ab | public/assets/css/design-system.css |
| 2 | Extend ux-interactions.spec.js with hover assertion | 4b3e952a | tests/e2e/specs/ux-interactions.spec.js |

## Changes Made

### Task 1: design-system.css btn variant audit

**Pre-existing (no changes needed):**
- `.btn:focus-visible` — shared focus ring via `--shadow-focus`
- `.btn-primary:hover:not(:disabled)` — translateY + shadow-md
- `.btn-primary:active:not(:disabled)` — scale(.98)
- `.btn-secondary:hover:not(:disabled)` — bg-subtle + border-strong
- `.btn-success:hover/active` — full set
- `.btn-danger:hover:not(:disabled)` — danger-hover background
- `.btn-danger:active:not(:disabled)` — scale(.98)
- `.btn-warning:hover:not(:disabled)` — warning-hover
- `.btn-info:hover:not(:disabled)` — info-hover
- `.btn-ghost:hover:not(:disabled)` — bg-subtle

**Added:**
- `.btn-secondary:active:not(:disabled)` — `transform: scale(.98) translateY(0); box-shadow: var(--shadow-sm);`
- `.btn-ghost:active:not(:disabled)` — `transform: scale(.98) translateY(0); background: var(--color-neutral-subtle);`
- `.btn-danger:focus-visible` — `box-shadow: var(--shadow-focus-danger);`
- `@media (prefers-reduced-motion: reduce)` block covering `.btn, .btn:hover, .btn:active` with `transform: none !important; transition: none !important;`

### Task 2: ux-interactions.spec.js

Added `test.describe('POLISH-04: Button micro-interactions')` with one test:
- Navigates to `/login.html` (no auth, has `.btn-primary` submit button)
- Captures `getComputedStyle` transform + box-shadow before hover
- Dispatches `.hover()` + 250ms wait for transition
- Asserts at least one computed value changed

## Verification Results

- `grep -c '.btn-primary:hover'` → 1
- `grep -c '.btn-primary:active'` → 1
- `grep -c '.btn-secondary:hover'` → 1
- `grep -c '.btn-secondary:active'` → 1
- `grep -c '.btn-ghost:hover'` → 1
- `grep -c '.btn-ghost:active'` → 1
- `grep -c '.btn-danger:focus-visible'` → 1
- `grep -c '.btn:focus-visible'` → 1
- `grep -c 'prefers-reduced-motion'` → 4 (global + new .btn block)
- `node -c ux-interactions.spec.js` → SYNTAX OK
- No new CSS tokens declared (0 new `--color-*:` in `:root`)

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- `public/assets/css/design-system.css` modified with 24 insertions
- `tests/e2e/specs/ux-interactions.spec.js` modified with 38 insertions
- Commit a4b268ab exists (Task 1)
- Commit 4b3e952a exists (Task 2)
- All grep verification targets confirmed
