---
phase: 36-session-creation-flow
plan: "01"
subsystem: wizard-ui
tags: [visual-redesign, wizard, forms, ux, v4.2]
dependency_graph:
  requires: []
  provides: [wizard-visual-redesign]
  affects: [public/wizard.htmx.html, public/assets/css/wizard.css, public/assets/js/pages/wizard.js]
tech_stack:
  added: []
  patterns: [ag-popover-tooltip, gradient-cta, template-card-grid, step-counter-js]
key_files:
  created: []
  modified:
    - public/wizard.htmx.html
    - public/assets/css/wizard.css
    - public/assets/js/pages/wizard.js
decisions:
  - "step-nav-counter uses flex centering (not absolute positioning) to avoid conflict with sticky footer layout"
  - "wiz-template-btn class kept on card buttons for JS selector compatibility (existing querySelectorAll wire)"
  - "STEP_LABELS array in wizard.js maps 0-indexed steps to French names for subtitle update"
metrics:
  duration: "4 minutes"
  completed: "2026-03-20"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 3
---

# Phase 36 Plan 01: Wizard Visual Redesign Summary

**One-liner:** 4-step wizard redesigned with Linear-quality stepper hierarchy, field-level tooltips (ag-popover), gradient CTA footer with live "Etape X sur 4" counter, and template card grid replacing inline buttons.

## What Was Built

### Task 1: Stepper Hierarchy, Field Labels, Sections, and Tooltips

**Stepper visual hierarchy (WIZARD-01):**
- Active `.wiz-snum` enlarged to 32x32px with `font-size: 0.8125rem`
- Pending steps dimmed: `.wiz-step-item:not(.active):not(.done) { opacity: 0.6 }`
- Connector lines between steps via `box-shadow: inset -1px 0 0 var(--color-border)` (avoids pseudo conflict)
- `wizStepSubtitle` element added to page header — shows current step name

**Form field labels and tooltips (WIZARD-02):**
- `.field-label` overridden to 14px semibold, no uppercase, flex display for tooltip alignment
- `.req` indicator made subtle (muted color, 0.65rem) instead of red
- `.field-hint` class added: 12px muted helper text below complex fields
- `.field-info-btn` class: 18px circular transparent button with primary hover
- Quorum field: ag-popover tooltip ("Quorum légal" + art. 22 explanation) + field-hint text
- Majority field: ag-popover tooltip (art. 24/25/26 summary) + field-hint text

**Form section redesign:**
- `.wiz-section` uses transparent background with divider lines (no nested card feel)
- `.wiz-section:first-of-type { border-top: none }` to clean up first section
- `.wiz-section-title` margin-bottom increased to 1.25rem

**Noise reduction:**
- `.wf-step { display: none }` — step header inside card hidden (saves ~80px)
- `.row` gap increased to `var(--space-4, 16px)` for breathing room

**Micro-interactions:**
- Focus ring animation: `transition: box-shadow 0.2s ease, border-color 0.2s ease` on `.field-input:focus`
- Validation state: `transition: border-color 0.3s ease` on `.field-input.field-error`

### Task 2: Step-Nav Gradient CTA, Counter, Template Cards, Review Polish, JS Wiring

**Step navigation footer (WIZARD-03):**
- All back buttons: `btn btn-ghost` (transparent, bordered)
- All next/submit buttons: `btn btn-primary step-nav-next` (gradient + lift)
- `step-nav-counter` spans centered in `.step-nav` via flex: 1
- Counter text "Etape 1 sur 4" through "Etape 4 sur 4" static HTML per step
- CSS: `.step-nav-next` gradient + `0 2px 8px primary-glow` shadow, hover `translateY(-1px)` lift
- CSS: `.btn-ghost` transparent with border, `.step-nav { align-items: center }`

**Motion template cards (WIZARD-04):**
- Replaced `.wiz-templates-row` with `.wiz-template-grid` (3-column CSS grid)
- Each `.wiz-template-card` button: icon (32px primary-subtle bg) + title (14px bold) + desc (12px muted)
- Hover: primary border ring (`0 0 0 3px primary-glow`) + `translateY(-1px)` lift
- `.wiz-template-btn` class preserved on card buttons for existing JS selector wiring
- Responsive: grid collapses to 1 column at 768px

**Review step polish:**
- `.review-section-title` enlarged to 0.875rem (from 11px)
- `.review-modifier` styled as primary pill: `background: primary-subtle`, hover inverts to primary bg/white text

**JS wiring (WIZARD-03 / WIZARD-01):**
- `STEP_LABELS` array: `['Informations générales', 'Participants', 'Résolutions', 'Révision']`
- `showStep(n)` now updates `stepNavCounter` with "Etape N sur 4"
- `showStep(n)` also updates `wizStepSubtitle` with current step name from `STEP_LABELS`

## Deviations from Plan

### Auto-fixed Issues

None — plan executed exactly as written.

**Design decisions made during execution:**

1. **step-nav-counter uses flex centering instead of absolute positioning** — the plan specified `position: absolute; left: 50%; transform: translateX(-50%)` but that conflicts with the sticky footer layout. Used `flex: 1; text-align: center` instead — achieves same visual result without z-index/overflow issues.

2. **wiz-template-btn class kept on card elements** — The plan said to "keep data-template attributes and click handler wiring." The existing JS uses `document.querySelectorAll('.wiz-template-btn')` to wire click handlers. Adding `.wiz-template-btn` to `.wiz-template-card` buttons ensures zero-change JS compatibility.

## Self-Check: PASSED

- [x] `/home/user/gestion_votes_php/public/wizard.htmx.html` — exists
- [x] `/home/user/gestion_votes_php/public/assets/css/wizard.css` — exists
- [x] `/home/user/gestion_votes_php/public/assets/js/pages/wizard.js` — exists
- [x] Task 1 commit `96f2c0d` — exists in git log
- [x] Task 2 commit `7120e09` — exists in git log
- [x] `wizStepSubtitle` ID in HTML and JS
- [x] `stepNavCounter` ID in HTML and JS
- [x] `wiz-template-card` class in CSS and HTML
- [x] `step-nav-counter` class in CSS and HTML
- [x] `STEP_LABELS` array in wizard.js
- [x] `field-info-btn`, `field-hint` classes in CSS
- [x] `wf-step { display: none }` in CSS
