---
phase: 10-live-session-views
plan: "01"
subsystem: public-display
tags: [css, javascript, bar-chart, design-tokens, horizontal-layout]
dependency_graph:
  requires: []
  provides: [horizontal-bar-chart, tokenized-public-css, js-width-animation]
  affects: [public/public.htmx.html, public/assets/css/public.css, public/assets/js/pages/public.js]
tech_stack:
  added: []
  patterns: [CSS grid horizontal bar layout, color-mix() token, width-based bar animation]
key_files:
  created: []
  modified:
    - public/public.htmx.html
    - public/assets/css/public.css
    - public/assets/js/pages/public.js
decisions:
  - "Bar fill uses style.width (%) not style.height (px) — percentage-based width works directly with CSS transition: width"
  - "maxHeight variable removed — horizontal bars use percentage width directly, no px calculation needed"
  - "Footer utility classes added to public.css (not delegated to design-system.css) since public page only loads design-system.css + public.css"
  - ".bar-label-top block removed from reduced-motion media query — class no longer exists in horizontal layout"
metrics:
  duration: "2min"
  completed_date: "2026-03-13"
  tasks_completed: 2
  files_modified: 3
---

# Phase 10 Plan 01: Live Session Views — Room Display Restyle Summary

**One-liner:** Horizontal bar chart with grid layout (label | bar | percentage), design token pass on public.css, JS bar fills changed from height-px to width-%.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Inline styles cleanup + horizontal bar HTML restructure | 011fcc6 | public/public.htmx.html |
| 2 | Horizontal bar CSS + token pass + JS bar direction fix | 582cf1e | public/assets/css/public.css, public/assets/js/pages/public.js |

## What Was Built

### Task 1: HTML Cleanup (011fcc6)

The `public.htmx.html` was restructured so:
- `app-footer` uses the `hidden` attribute instead of `style="display:none"`
- Footer links use semantic CSS classes (`footer-logo`, `footer-logo-mark`, `flex-spacer`, `footer-link`) instead of inline styles
- Bar chart HTML restructured to horizontal layout: `bar-label | bar-wrapper | bar-value` per bar item
- All existing IDs (`bar_for_fill`, `pct_for`, `count_for`, etc.) preserved for JS compatibility
- Only 3 JS-managed inline styles remain (`participation_bar`, `quorumVisualFill`, `quorumSeuil`)

### Task 2: CSS + JS Direction Fix (582cf1e)

**CSS (`public.css`):**
- `.bar-chart` changed from vertical flex with `align-items: flex-end` to `flex-direction: column`
- `.bar-item` changed from vertical column layout to CSS grid `6rem 1fr 5rem` (label | bar | value)
- `.bar-wrapper` now has `width: 100%; height: 32px` (horizontal pill)
- `.bar` now animates `width` (0 → %) with `transition: width 1s ease-bounce`
- Removed vertical artifacts: `.bar-label-top`, `min-height`, `flex-end`, `.bar::after` shimmer
- `.motion-counter` background tokenized: `rgba(255,255,255,0.15)` → `color-mix(in srgb, currentColor 15%, transparent)`
- `.bar-label-top` block removed from `@media (prefers-reduced-motion: reduce)`
- Footer utility classes added: `.app-footer[hidden]`, `.footer-logo`, `.footer-logo-mark`, `.flex-spacer`, `.footer-link`
- Responsive override added: `@media (max-width: 768px)` uses `4rem 1fr 3.5rem` grid

**JS (`public.js`):**
- `animateBars()`: `style.height = (pct/100 * maxHeight) + 'px'` → `style.width = pct.toFixed(1) + '%'`
- `resetBars()`: `style.height = '0'` → `style.width = '0'`
- Removed `maxHeight = 140` variable (no longer needed)
- Removed `.animate` class manipulation from `resetBars()` (`.bar-label-top` no longer in HTML)

## Verification Results

| Check | Result |
|-------|--------|
| `grep -c 'style=' public.htmx.html` returns ≤ 3 | PASS (3) |
| No `style.height` for bar fills in JS | PASS (0 matches) |
| CSS has `grid-template-columns` | PASS |
| JS uses `style.width` for bar fills | PASS |
| `app-footer` has `hidden` attribute | PASS |
| `maxHeight` variable removed | PASS (0 matches) |

## Deviations from Plan

None — plan executed exactly as written. The HTML was already restructured in a prior commit (011fcc6) before this plan execution; only the CSS and JS changes remained to be applied.

## Self-Check

- [x] `public/public.htmx.html` — exists and has correct structure
- [x] `public/assets/css/public.css` — has `grid-template-columns`, horizontal bar layout
- [x] `public/assets/js/pages/public.js` — uses `style.width` for bar fills
- [x] Commits 011fcc6 and 582cf1e exist in git log
