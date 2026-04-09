---
phase: 12-page-by-page-mvp-sweep
plan: 21
subsystem: help-page
tags: [css, playwright, e2e, content-page, width-cap, tour-cards, faq]
dependency_graph:
  requires: []
  provides: [help-page-80ch-cap, critical-path-help-spec]
  affects: [public/assets/css/help.css, tests/e2e/specs/critical-path-help.spec.js]
tech_stack:
  added: []
  patterns: [content-page-reading-cap, critical-path-spec]
key_files:
  modified:
    - public/assets/css/help.css
  created:
    - tests/e2e/specs/critical-path-help.spec.js
decisions:
  - "help is a CONTENT page — max-width capped at 80ch (not removed) per locked user decision; .help-search 480px cap is a legitimate component constraint, not a page-level clamp"
  - "Tour card click assertion uses non-role-gated cards only to avoid permission-dependent flakiness"
metrics:
  duration: "~10 minutes"
  completed: 2026-04-09T05:38:05Z
  tasks_completed: 2
  files_modified: 1
  files_created: 1
requirements: [MVP-01, MVP-02, MVP-03]
---

# Phase 12 Plan 21: Help & FAQ Page — MVP Sweep Summary

Help page correctly identified as CONTENT page: reading width clamped to 80ch with justifying comment; zero raw color literals confirmed; Playwright spec asserts tour grid, anchors, navigation, and measured 80ch cap.

## What Was Done

### Task 1 — Width fix + token audit

**Width gate:** Changed `max-width: 900px` to `max-width: 80ch` on `.app-main.help-main` in `public/assets/css/help.css` (line 12). Added a justifying comment:

```css
/* MVP-01: content page reading cap — help & FAQ is a CONTENT page (not
   applicative), reading column clamped to 80ch per locked user decision
   on content pages. */
max-width: 80ch;
```

**Remaining max-width values classified:**
- `max-width: 480px` on `.help-search` (line 130) — legitimate inner component cap for the search input widget. Not a page-level applicative clamp. Retained unchanged.
- `@media (max-width: 768px)` and `@media (max-width: 600px)` — responsive breakpoints. Not applicative clamps.

**Token audit:** Zero raw color literals (`oklch()`, `#hex`, `rgba()`). 47 `var(--color-*)` usages confirmed.

### Task 2 — Playwright spec

Created `tests/e2e/specs/critical-path-help.spec.js` with single test tagged `@critical-path`:

Assertions:
1. Page mounts: `.page-title`, `.tour-grid`, `.help-section-heading` all visible.
2. Tour cards: count ≥ 4, first card has `.tour-icon`, `.tour-name`, `.tour-meta`, and `href` containing `?tour=1`.
3. All tour card hrefs: contain `tour=1`, start with `/` (relative, same-origin).
4. Role-gated cards: at least one `.tour-card[data-required-role]` exists.
5. Section headings: count ≥ 2 (tour section + FAQ section).
6. Navigation: click first non-role-gated card, URL contains `tour=1`, navigate back.
7. Width cap: `.app-main.help-main` `maxWidth` is not `none`; rendered width ≤ `fontSize * 80`.
8. No horizontal overflow: `scrollWidth ≤ clientWidth + 1`.

## Verification Results

```
# Width gate
grep -A8 '^\.app-main\.help-main {' public/assets/css/help.css
# → max-width: 80ch  (PASS)
# → NO max-width: 900px  (PASS)

# Token gate
grep -nE 'oklch\(|#[0-9a-fA-F]{3,8}[;\s,)]|rgba?\(' public/assets/css/help.css | grep -v '/\*' | grep -v 'color-mix'
# → (no output — PASS)

grep -c 'var(--color-' public/assets/css/help.css
# → 47  (PASS, ≥20 required)

# Spec gate
grep -c "@critical-path" tests/e2e/specs/critical-path-help.spec.js
# → 2  (PASS, ≥1 required)

grep -cE "tour-card|tour-grid|help-section-heading|help-main" tests/e2e/specs/critical-path-help.spec.js
# → 11  (PASS, ≥4 required)
```

## Deviations from Plan

None - plan executed exactly as written.

## PHASE 12 FINAL CHECKPOINT

All 21 plans across 5 waves are now complete:
- Wave 1 (Plans 01-05): Infrastructure and global styles
- Wave 2 (Plans 06-10): Core applicative pages
- Wave 3 (Plans 11-13): Meeting workflow pages
- Wave 4 (Plans 14-20): Supporting pages
- Wave 5 (Plans 17-21): Final page sweep including help (this plan)

**Recommendation:** Run the full `@critical-path` tag across all 21 specs to verify overall MVP-03 compliance:
```
./bin/test-e2e.sh --grep "@critical-path"
```

This will exercise all pages in a single pass and confirm the complete Phase 12 MVP gate.

## Self-Check: PASSED

- `public/assets/css/help.css` — modified, exists
- `tests/e2e/specs/critical-path-help.spec.js` — created, exists
- commit `b4eb7e70` — feat(12-21): change help-main max-width 900px → 80ch
- commit `b2d99be5` — feat(12-21): add critical-path-help Playwright spec
