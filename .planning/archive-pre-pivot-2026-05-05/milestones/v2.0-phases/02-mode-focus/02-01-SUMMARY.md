---
phase: 02-mode-focus
plan: 01
subsystem: operator-ui
tags: [html, css, focus-mode, exec-view, accessibility]
requirements: [FOCUS-01, FOCUS-02]

dependency_graph:
  requires: [01-01-PLAN (op-exec-body wrapper, op-checklist-panel)]
  provides: [op-focus-mode CSS modifier, op-focus-toggle button, op-focus-quorum dedicated zone-3 block]
  affects: [public/operator.htmx.html, public/assets/css/operator.css]

tech_stack:
  added: []
  patterns: [CSS class modifier on container, aria-pressed toggle, :has parent selector, sticky bottom action bar, design tokens reuse]

key_files:
  created: []
  modified:
    - public/operator.htmx.html
    - public/assets/css/operator.css

decisions:
  - Substituted icon-maximize/icon-minimize (locked in CONTEXT D-4) with icon-zoom-in/icon-eye after sprite verification — neither maximize/minimize nor zoom-out exist in /assets/icons.svg; icon-zoom-in (focus on) + icon-eye (full view, focus off) preserves visual swap intent
  - Used :has(#viewExec.op-focus-mode) on .op-body to hide .op-sidebar (lives outside #viewExec) — fallback class deferred per RESEARCH guidance
  - .op-focus-quorum block emitted with [hidden] attribute by default; CSS .op-focus-quorum[hidden] { display: none } + .op-focus-mode .op-focus-quorum { display: flex !important } pattern overrides hidden in focus mode
  - All design tokens reused from Phase 1 (--color-success/danger/primary[-subtle/-text], --space-*, --text-*, --radius-lg, --font-mono) — zero new tokens introduced
  - No transitions on .op-focus-mode or .op-focus-toggle (Pitfall 5) — instant snap

metrics:
  duration: "~6 minutes"
  completed: "2026-04-29T00:00:00Z"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 2
---

# Phase 2 Plan 01: Focus Mode HTML + CSS Structure Summary

**One-liner:** Static focus-mode toggle button in `.op-exec-header-right` + dedicated 5th-zone quorum block + `.op-focus-mode` CSS cascade hiding 13 secondary zones, making the action bar sticky bottom, and enlarging focal typography — all reusing Phase 1 design tokens.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add focus-mode toggle button and dedicated quorum block | 197c4c51 | public/operator.htmx.html |
| 2 | Add .op-focus-mode CSS rules and toggle button styling | 25e6128e | public/assets/css/operator.css |

## What Was Built

### Task 1 — HTML Structure (operator.htmx.html, 17 insertions)

- Toggle button `#opBtnFocusMode` inserted in `.op-exec-header-right` BEFORE the `<ag-tooltip>` wrapping `#opBtnCloseSession` (CONTEXT D-4 placement)
- Wrapped in `<ag-tooltip text="Mode focus : 5 zones essentielles">` for hover hint
- Button uses base classes `op-focus-toggle btn btn-sm btn-secondary`
- ARIA: `type="button"`, `aria-pressed="false"` (initial), `aria-label="Activer le mode focus — masquer les informations secondaires"`, `title` mirrors tooltip
- Two SVG icons inside the button:
  - `.op-focus-icon-on` → `#icon-zoom-in` (visible when focus inactive)
  - `.op-focus-icon-off` → `#icon-eye` (visible when focus active, has `hidden` attribute initially; CSS swaps via `[aria-pressed]`)
- French label "Focus" in `<span class="op-focus-toggle-label">`
- Dedicated quorum aside `<aside id="opFocusQuorum">` inserted as direct child of `#viewExecContent`, sibling of `.op-exec-body` (NOT nested inside `.op-exec-main` or `.op-checklist-panel`)
- `role="region"`, `aria-label="Quorum (mode focus)"`, `hidden` attribute (default invisible outside focus mode)
- Three child spans: `.op-focus-quorum-label` ("Quorum"), `.op-focus-quorum-value#opFocusQuorumValue` (`aria-live="polite"`, "—" placeholder), `.op-focus-quorum-status#opFocusQuorumStatus` ("en attente")

### Task 2 — CSS Rules (operator.css, 142 insertions)

New section appended after the `@media (prefers-reduced-motion: no-preference)` block (~line 2117) and before the `@media (max-width: 1024px)` responsive block:

- **Rule 1 (D-7 hide list):** Single combined selector hides 13 zones via `display: none !important` when `.op-focus-mode` is on `#viewExec` — `.op-exec-status-bar`, `.op-kpi-strip`, `.op-tags`, `.op-resolution-progress`, `.op-guidance`, `.exec-close-banner`, `.op-resolution-header`, `.op-tabs`, `.op-checklist-panel` (Phase 1 panel hidden, D-2), `.exec-speaker-panel`, `#execManualVoteList`, `#execManualSearch`, `#opBtnRoomDisplay`
- **Rule 1b:** `.op-body:has(#viewExec.op-focus-mode) .op-sidebar` hides the left agenda (lives outside `#viewExec`)
- **Rule 2 (FOCUS-02, D-6):** `.op-focus-mode .op-action-bar` is `position: sticky; bottom: 0; z-index: 10` with surface background and subtle top shadow — buttons stay visible without scrolling
- **Rule 3 (typography for distance readability):** `.exec-vote-title` / `.op-resolution-title` enlarged to `--text-2xl`; `.op-vote-card` gets `--space-6` vertical padding; `.op-live-chrono` enlarged to `--text-2xl` mono semibold
- **Rule 4 (zone 3 dedicated block):** `.op-focus-quorum[hidden] { display: none }` honors initial state; `.op-focus-mode .op-focus-quorum { display: flex !important; flex-direction: column; ... }` reveals as a centered card with border, radius, surface bg
  - Label uppercase muted, value `--text-3xl` mono, status `--text-sm` semibold
  - State variants: `--ok` uses `--color-success` border + `--color-success-subtle` bg + `--color-success-text` typo; `--alert` uses danger triplet (will be applied by Plan 02 JS)
- **Rule 5 (toggle button):** `.op-focus-toggle` flex layout with gap; `[aria-pressed="true"]` switches to `--color-primary-subtle` bg + `--color-primary-text` color + `--color-primary` border; pure-CSS icon swap via `[aria-pressed]` selector toggling display of `.op-focus-icon-on` / `.op-focus-icon-off`
- **No transitions** added on `.op-focus-mode` or `.op-focus-toggle` per RESEARCH Pitfall 5 (instant snap)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking issue] Icon substitution: locked icons missing from sprite**
- **Found during:** Task 1 (pre-edit verification per plan instructions)
- **Issue:** CONTEXT.md D-4 locked `icon-maximize` (focus on) / `icon-minimize` (focus off). Plan provided fallback `icon-zoom-in` / `icon-zoom-out`. Sprite check (`grep id="icon-maximize|minimize|zoom-out"`) confirms NEITHER pair is fully present — only `icon-zoom-in` exists.
- **Fix:** Substituted with `icon-zoom-in` (focus on) + `icon-eye` (focus off — semantic "see everything" for the full-view state). The visual swap on aria-pressed transition is preserved.
- **Files modified:** `public/operator.htmx.html`
- **Commit:** 197c4c51
- **Why this is Rule 3 (not Rule 4):** the plan explicitly authorized the fallback in its `<action>` block; choice between two unavailable icons is a sub-detail of the existing instruction, not an architectural decision.

## Authentication Gates

None — Wave 1 is pure frontend (HTML + CSS). No PHP, no API, no auth touched.

## Self-Check

### Files exist

- [x] `public/operator.htmx.html` — modified (17 lines added)
- [x] `public/assets/css/operator.css` — modified (142 lines added)

### Commits exist

- [x] 197c4c51 — feat(02-01): add focus-mode toggle button and dedicated quorum block
- [x] 25e6128e — feat(02-01): add focus-mode CSS rules and toggle button styling

### Verification block (from plan)

- HTML `op-focus` count: 9 (threshold ≥6) ✓
- CSS `op-focus` count: 37 (threshold ≥20) ✓
- `opBtnFocusMode` in `.op-exec-header-right` BEFORE `opBtnCloseSession`: confirmed via line ordering ✓
- `opFocusQuorum` aside inside `#viewExecContent`, sibling of `.op-exec-body`: confirmed (insertion right after `</div><!-- /.op-exec-body -->`) ✓
- D-2 checklist-panel hidden in focus mode: `.op-focus-mode .op-checklist-panel` selector present ✓
- D-6 action bar sticky: `position: sticky; bottom: 0; z-index: 10` confirmed ✓
- No PHP / JS files modified ✓
- No existing rule altered (only additions) ✓

### Acceptance criteria (Task 1)

- `id="opBtnFocusMode"`: 1 match ✓
- `op-focus-toggle`: 2 matches ✓
- `aria-pressed="false"`: 2 matches ✓
- `op-focus-quorum`: 4 matches ✓
- `id="opFocusQuorum"`: 1 match ✓
- `id="opFocusQuorumValue"`: 1 match ✓
- `id="opFocusQuorumStatus"`: 1 match ✓
- Toggle BEFORE `<ag-tooltip>` of `#opBtnCloseSession` ✓
- `aside#opFocusQuorum` is sibling of `.op-exec-body` inside `#viewExecContent` ✓
- `aside#opFocusQuorum` carries `hidden` attribute literally ✓

### Acceptance criteria (Task 2)

- `.op-focus-mode` matches: 30 (≥10) ✓
- `.op-focus-mode .op-kpi-strip`: present in hide list ✓
- `.op-focus-mode .op-checklist-panel`: present (D-2 lock honored) ✓
- `.op-focus-mode .op-action-bar` followed by `position: sticky` + `bottom: 0` ✓
- `.op-focus-mode .op-focus-quorum` with `display: flex !important` ✓
- `op-focus-quorum--ok` with `--color-success`: 3 matches ✓
- `op-focus-quorum--alert` with `--color-danger`: 3 matches ✓
- `.op-focus-toggle[aria-pressed="true"]`: 3 matches ✓
- `.op-body:has(#viewExec.op-focus-mode)`: 1 match ✓
- No `transition` inside `.op-focus-mode` rules: 0 matches ✓
- No existing rule modified: only new section appended ✓

## Self-Check: PASSED
