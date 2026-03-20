---
phase: 36-session-creation-flow
plan: "02"
subsystem: hub
tags: [visual-redesign, hub, quorum, checklist, stepper, badges]
dependency_graph:
  requires: [36-01]
  provides: [CORE-04]
  affects: [public/hub.htmx.html, public/assets/css/hub.css, public/assets/js/pages/hub.js]
tech_stack:
  added: []
  patterns:
    - Hub identity banner with type/status badge chips
    - Quorum hero card with 2rem mono percentage and color-coded states
    - Status bar with 36px always-visible labeled segments
    - Single-connector vertical stepper (no double line)
    - Checklist card items with Fait/A faire status badges
    - Gradient CTA pattern applied to action card button
key_files:
  created: []
  modified:
    - public/hub.htmx.html
    - public/assets/css/hub.css
    - public/assets/js/pages/hub.js
decisions:
  - Disabled hub-step-row::before pseudo-connector entirely — hub-step-line div rendered by JS is the single source of truth for connector lines; eliminates double-connector visual artifact
  - Quorum hero percentage color uses three states (reached/partial/critical) based on current vs required threshold, with 75% of required as the partial boundary
  - Done checklist items use opacity:1 (not the previous 0.7 fade) because the green badge and subtle green background communicate completion without losing readability
  - Motions title changed from 0.6875rem uppercase label to 1rem bold heading — aligns with card section titles elsewhere
metrics:
  duration: ~4 minutes
  tasks_completed: 2
  tasks_total: 2
  files_modified: 3
  completed_date: "2026-03-20"
---

# Phase 36 Plan 02: Hub Visual Redesign Summary

**One-liner:** Hub transformed to Notion-dashboard quality with type/status badges in identity banner, 36px always-visible status bar labels, single-connector stepper with pending dimming, quorum bar promoted to hero card with 2rem color-coded percentage, and checklist items with Fait/A faire badge treatment.

## Tasks Completed

| Task | Name | Commit | Key Changes |
|------|------|--------|-------------|
| 1 | Identity badges, status bar labels, stepper cleanup, quorum hero | 3742c04 | hubTypeTag, hubStatusTag, 36px bar segments, ::before disabled, hub-quorum-hero, hubQuorumPct |
| 2 | Checklist badges, action gradient CTA, motions polish, responsive | e8bef54 | hub-check-done-badge, 12px bar, gradient btn, 30px motion nums, 768px responsive |

## What Was Built

### Task 1: Identity, Status Bar, Stepper, Quorum Hero

**Identity Banner Badges:**
- Added `.hub-identity-title-row` wrapper div that holds `#hubTitle` + `.hub-identity-badges` inline
- Two badge spans: `#hubTypeTag` (badge--neutral, shows AG type) and `#hubStatusTag` (badge--info, shows status)
- `.badge`, `.badge--neutral`, `.badge--info` CSS classes added to hub.css
- `applySessionToDOM()` in hub.js now populates both badge elements

**Status Bar Always-Visible Labels:**
- Segments: `height: 36px` (was 8px), active: `40px`, with `display: flex; align-items: center`
- `.hub-bar-label` now always visible at `font-size: 11px !important` with `color: rgba(255,255,255,0.9)` on colored segments
- Pending segments get `color: var(--color-text-muted)` for the label
- Padding updated to `6px`, `align-items: center`, `gap: 2px`

**Vertical Stepper Cleanup:**
- `hub-step-row::before { display: none; }` — disables the pseudo-element connector
- `.hub-step-line` is now the only connector: `width: 3px; height: 24px; margin-left: 14px; border-radius: 2px`
- Pending step dimming: `hub-step-row:not([aria-current]):not(.done) .hub-step-num { opacity: 0.45; }`
- "Etape en cours" replaces "← Vous etes ici" — bolder, primary colored, `font-style: normal`

**Quorum Hero:**
- HTML: `.hub-quorum-section` replaced with `.hub-quorum-hero` (id preserved for JS compatibility)
- Hero header: left side has uppercase "QUORUM" label + ag-popover tooltip explaining rules; right side has `#hubQuorumPct` showing percentage
- CSS: card with 12px radius, shadow-md, 1.5rem padding, subtle top accent stripe via `::before`
- `hub-quorum-hero-pct`: `font-family: var(--font-mono); font-size: 2rem; font-weight: 800`
- Color states: `.reached` (success green), `.partial` (warning amber), `.critical` (danger red)
- `renderQuorumBar()` computes `Math.round(current / total * 100)` and applies color class

### Task 2: Checklist, Action CTA, Motions, Responsive

**Checklist Card Items:**
- Items now use `border: 1px solid transparent` (not border-bottom) with card-like hover: `border-color: var(--color-border-subtle); background: var(--color-bg-subtle)`
- Done items: green subtle background `rgba(34,197,94,0.06)` + border `rgba(34,197,94,0.2)`, opacity stays at 1
- Done icon: 24×24px (up from 22px)
- Progress bar: 12px tall (was 8px), margin-bottom 16px
- `hub-check-done-badge`: green `Fait` badge, `font-size: 0.75rem; padding: 3px 10px; font-weight: 700`
- `hub-check-todo`: `font-size: 0.75rem; padding: 3px 10px; font-weight: 700` (was 11px)
- `renderChecklist()` now emits `<span class="hub-check-done-badge">Fait</span>` for done items

**Action Card Gradient CTA:**
- `.hub-action`: now has `border: 1.5px solid var(--color-primary-subtle); box-shadow: var(--shadow-md)`
- `.hub-action .btn-primary`: `background: linear-gradient(135deg, var(--color-primary), var(--color-primary-hover))`
- Hover lift: `transform: translateY(-1px); box-shadow: 0 4px 16px var(--color-primary-glow)`

**Motions List Polish:**
- `.hub-motion-item`: card hover treatment (`border: 1px solid transparent` → border-color on hover), `padding: 12px 10px`
- `.hub-motion-num`: 30×30px (was 28px)
- `.hub-motions-title`: `font-size: 1rem; font-weight: 700` (was 0.6875rem uppercase)
- `.doc-badge--has-docs`: `padding: 3px 10px; font-weight: 600`

**Responsive 768px:**
- `.hub-stepper { position: static; }` added at 768px
- `.hub-quorum-hero-pct { font-size: 1.5rem; }` at 768px

## Deviations from Plan

None — plan executed exactly as written. All HUB-01 through HUB-05 specs from RESEARCH.md applied.

## Self-Check: PASSED

- FOUND: public/hub.htmx.html
- FOUND: public/assets/css/hub.css
- FOUND: public/assets/js/pages/hub.js
- FOUND: commit 3742c04 (Task 1)
- FOUND: commit e8bef54 (Task 2)
- Key patterns verified: hub-quorum-hero (11 occurrences in hub.css), hub-identity-title-row (1), hub-step-row::before (2), hub-check-done-badge (1), hubQuorumPct (1), hubTypeTag (1)
