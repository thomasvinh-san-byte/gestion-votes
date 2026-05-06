---
phase: 01-checklist-operateur
plan: 01
subsystem: operator-ui
tags: [html, css, checklist, exec-view, accessibility]
requirements: [CHECK-01, CHECK-03, CHECK-04, CHECK-05]

dependency_graph:
  requires: []
  provides: [op-checklist-panel HTML structure, op-checklist CSS rules, op-exec-body wrapper]
  affects: [public/operator.htmx.html, public/assets/css/operator.css]

tech_stack:
  added: []
  patterns: [flex row wrapper, CSS custom properties, prefers-reduced-motion, @keyframes pulse]

key_files:
  created: []
  modified:
    - public/operator.htmx.html
    - public/assets/css/operator.css

decisions:
  - Used icon-chevron-right for collapse toggle (icon confirmed present in sprite)
  - Checklist panel inserted as aside sibling to .op-exec-main inside new .op-exec-body wrapper
  - SSE banner uses hidden attribute (not display:none class) for JS toggling via removeAttribute
  - checklistPulse animation wrapped in prefers-reduced-motion: no-preference per CHECK-05

metrics:
  duration: "~8 minutes"
  completed: "2026-04-21T12:23:00Z"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 2
---

# Phase 1 Plan 01: Checklist Panel Structure Summary

**One-liner:** Static checklist sidebar HTML + CSS added to operator exec view — 4 indicator rows (SSE, quorum, votes, online), SSE disconnect banner, collapse toggle, alert pulse animation respecting prefers-reduced-motion.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add checklist panel HTML and wrapper | e5d639d1 | public/operator.htmx.html |
| 2 | Add checklist CSS rules and alert animation | 6fc518b4 | public/assets/css/operator.css |

## What Was Built

### Task 1 — HTML Structure (operator.htmx.html)

- Wrapped existing `.op-exec-main` inside new `.op-exec-body` flex container
- Added `<aside class="op-checklist-panel">` as sibling to `.op-exec-main`
- SSE disconnect banner: `role="alert" aria-live="assertive"`, `hidden` by default
- Panel header: title "CONTRÔLE SÉANCE" + collapse toggle button (`aria-expanded`, `aria-controls`)
- 4 indicator rows with `data-row` attributes: `sse`, `quorum`, `votes`, `online`
- `aria-live="polite"` on SSE, votes, and online value spans for screen reader updates
- `role="complementary"` on the aside panel

### Task 2 — CSS Rules (operator.css)

- `.op-exec-body`: `display: flex; flex-direction: row` wrapper (flex: 1, overflow: hidden)
- `.op-checklist-panel`: 240px fixed width, flex column, `border-left`, `background: var(--color-surface)`
- `.op-checklist-panel--collapsed`: collapses to 32px strip, hides rows/header/banner
- `.op-checklist-sse-banner`: danger-subtle background, border-bottom danger, 13px semibold
- `.op-checklist-header/title/toggle`: header layout, uppercase 12px title, 32×32 toggle button
- `.op-checklist-row`: 44px min-height touch target, 3px transparent left border
- `.op-checklist-row--alert`: danger-subtle bg + danger border-left + danger icon color
- `.op-checklist-row--ok`: success icon color
- `.op-checklist-value`: `font-family: var(--font-mono)`, weight 600, right-aligned via `margin-left: auto`
- `checklistPulse` keyframes: opacity 1→0.3→1, 1s ease-in-out, 3 iterations
- All animations wrapped in `@media (prefers-reduced-motion: no-preference)`
- Responsive: `display: none` for panel at `max-width: 1024px`

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

### Files exist

- [x] `public/operator.htmx.html` — modified (62 lines added)
- [x] `public/assets/css/operator.css` — modified (148 lines added)

### Commits exist

- [x] e5d639d1 — feat(01-01): add checklist panel HTML structure
- [x] 6fc518b4 — feat(01-01): add checklist CSS rules and alert animation

### Verification counts

- HTML `op-checklist` matches: 22 (threshold: 10+) ✓
- CSS `op-checklist` matches: 21 (threshold: 20+) ✓
- HTML `op-exec-body`: 2 matches (open + close) ✓
- CSS `checklistPulse`: 2 matches (keyframe def + animation usage) ✓

## Self-Check: PASSED
