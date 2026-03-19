---
phase: 34-quality-assurance-final-audit
plan: "03"
subsystem: ui
tags: [css, design-system, dark-mode, color-tokens, three-depth-model]

# Dependency graph
requires:
  - phase: 34-02
    provides: PV preview panel in report.css, QA criteria established
  - phase: 34-01
    provides: Design system hover transforms, shadow/radius discipline
  - phase: 30-token-foundation
    provides: --color-surface-raised token (light:#FFFFFF dark:#1E2438)

provides:
  - var(--color-surface-raised) applied to all 17 page CSS files
  - Three-depth background model (bg/surface/raised) complete across entire app
  - All 5 QA automated checks passing (QA-01 through QA-05)
  - Dark mode visual parity checkpoint delivered to user

affects: [any future page CSS additions must maintain 3-depth model]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Three-depth background model: body=color-bg, card=color-surface, elevated-sub-element=color-surface-raised"
    - "Elevated sub-element selection rule: only apply surface-raised to elements visually ABOVE their card container"

key-files:
  created: []
  modified:
    - public/assets/css/app.css
    - public/assets/css/archives.css
    - public/assets/css/report.css
    - public/assets/css/users.css
    - public/assets/css/admin.css
    - public/assets/css/audit.css
    - public/assets/css/doc.css
    - public/assets/css/help.css
    - public/assets/css/landing.css
    - public/assets/css/login.css
    - public/assets/css/members.css
    - public/assets/css/postsession.css
    - public/assets/css/settings.css
    - public/assets/css/trust.css
    - public/assets/css/validate.css
    - public/assets/css/vote.css
    - public/assets/css/wizard.css

key-decisions:
  - "Each page CSS file must have var(--color-surface-raised) on its single most-elevated sub-element — not every element"
  - "Modals (.validate-modal, .audit-modal) are legitimate elevated elements for surface-raised since they float above page surface"
  - "Active tabs (.settings-tab.active) are elevated above their bg-subtle container — correct surface-raised target"
  - "Sticky footers (.step-nav, .ps-footer-nav) are elevated action bars — surface-raised appropriate"

patterns-established:
  - "Three-depth model complete: bg → surface → surface-raised on every page"
  - "QA-02 exemption comment pattern: /* QA-02: Single-depth page — no elevated sub-element applicable */ for truly flat pages"

requirements-completed: [QA-02, QA-04]

# Metrics
duration: 10min
completed: 2026-03-19
---

# Phase 34 Plan 03: Three-Depth Background Sweep Summary

**var(--color-surface-raised) applied to the most-elevated sub-element in all 17 page CSS files, completing the three-depth background model (bg/surface/raised) across the entire AG-VOTE app**

## Performance

- **Duration:** 10 min
- **Started:** 2026-03-19T10:32:07Z
- **Completed:** 2026-03-19T10:42:47Z
- **Tasks:** 1/2 complete (Task 2 is checkpoint:human-verify — awaiting user dark mode verification)
- **Files modified:** 17

## Accomplishments

- All 17 page CSS files now have at least 1 reference to `var(--color-surface-raised)`
- Zero files return from `grep -rL "color-surface-raised" public/assets/css/*.css` — full coverage
- QA-01: No literal pill radii (zero violations)
- QA-03: Font discipline maintained (only brand/hero exceptions)
- QA-05: No slow transitions (zero violations)
- QA-02: Three-depth model complete across entire app

## Task Commits

1. **Task 1: Apply var(--color-surface-raised) to all 17 page CSS files** - `4f94548` (feat)
2. **Task 2: Dark mode visual parity verification** - CHECKPOINT — awaiting human verification

## Files Modified

Each file received `var(--color-surface-raised)` on its elevated sub-element:

- `public/assets/css/app.css` — `.vote-result-item` (sub-card above motion-card surface)
- `public/assets/css/archives.css` — `.archive-card-header` (elevated card header, was gradient)
- `public/assets/css/report.css` — `.pv-preview` (PV preview elevated panel)
- `public/assets/css/users.css` — `.roles-explainer .card-body` (elevated reference panel)
- `public/assets/css/admin.css` — `.dash-kpi` (elevated KPI cards above dashboard)
- `public/assets/css/audit.css` — `.audit-detail-item` (elevated fields inside event detail modal)
- `public/assets/css/doc.css` — `.doc-toc-rail` (sticky elevated TOC sidebar)
- `public/assets/css/help.css` — `.faq-answer` (expanded answer panel above question)
- `public/assets/css/landing.css` — `.login-card` (single elevated card on landing)
- `public/assets/css/login.css` — `.login-card` (single elevated form card)
- `public/assets/css/members.css` — `.import-result` (elevated import result panel)
- `public/assets/css/postsession.css` — `.result-card-body` (expanded result content)
- `public/assets/css/settings.css` — `.settings-tab.active` (active tab above bg-subtle container)
- `public/assets/css/trust.css` — `.audit-modal` (elevated modal above backdrop)
- `public/assets/css/validate.css` — `.validate-modal` (elevated confirmation modal)
- `public/assets/css/vote.css` — `.motion-card-header` (elevated header above motion card)
- `public/assets/css/wizard.css` — `.step-nav` (elevated sticky nav footer)

## Decisions Made

- Modals count as elevated elements (they float above page surface behind a backdrop — correct use of surface-raised in dark mode to prevent pure-black modal)
- Active tabs elevated above their bg-subtle container: `var(--color-surface-raised)` replaces `var(--color-surface)` in `.settings-tab.active`
- The `.archive-card-header` gradient (`linear-gradient(bg-subtle → surface)`) replaced with flat `surface-raised` — cleaner dark mode behavior
- No single file needed the QA-02 exception comment — every page had at least one natural elevated element

## Deviations from Plan

None — plan executed exactly as written. All 17 files processed in a single task sweep as planned.

## Issues Encountered

None — all files had clear elevated sub-element candidates matching the three-depth model rules.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

Task 2 (dark mode visual parity checkpoint) is pending user verification. After user confirms dark mode looks intentionally designed on all page categories, Phase 34 QA audit is complete and v4.1 Design Excellence milestone ships.

**Verification steps for user (Task 2 checkpoint):**
1. Open the application in browser
2. Toggle to dark mode (Settings or browser preference)
3. Visit: Dashboard, Wizard, Operator console, Settings, Archives, Members, Users, Hub, Post-session, Analytics, Help, Report
4. Confirm: no pure black backgrounds, no invisible borders, no washed-out text, visible 3-depth layers

---
*Phase: 34-quality-assurance-final-audit*
*Completed: 2026-03-19*
