---
gsd_state_version: 1.0
milestone: v4.1
milestone_name: Design Excellence
status: planning
stopped_at: Completed 33-03 — email templates grid fix and meetings density alignment
last_updated: "2026-03-19T09:08:32.396Z"
last_activity: 2026-03-19 — Roadmap for v4.1 (phases 30-34) written
progress:
  total_phases: 10
  completed_phases: 9
  total_plans: 31
  completed_plans: 31
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-18)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v4.1 Design Excellence — Top 1% visual quality across every page

## Current Position

Phase: 30 — Token Foundation (not started)
Plan: —
Status: Roadmap defined, ready to plan Phase 30
Last activity: 2026-03-19 — Roadmap for v4.1 (phases 30-34) written

## Progress Bar

```
Phase 30 [ ] Token Foundation
Phase 31 [ ] Component Refresh
Phase 32 [ ] Page Layouts — Core Pages
Phase 33 [ ] Page Layouts — Secondary Pages
Phase 34 [ ] Quality Assurance Final Audit

0/5 phases complete (0%)
```

## Accumulated Context

(Carried from v4.0)
- @layer (base/components/v4) cascade in design-system.css
- 265+ CSS custom properties with color-mix() derived tokens — to be reduced to ~100
- 23 Web Components, IIFE + var page scripts, one CSS per page
- "Officiel et confiance" visual identity (being upgraded to "Design Excellence")
- Dark/light parity maintained
- All functionality complete — v4.1 is pure visual/layout refonte

### Decisions

- **Light-first design** — all pages designed light, dark mode parity maintained
- **Design references** — Stripe Dashboard (depth model), Linear (neutral canvas), shadcn/ui (component specs), Sonner (toast), Polaris (enterprise tokens)
- **Exact values locked** — ARCHITECTURE.md has implementation-ready CSS specs; no research needed for Phases 30-31
- **Sequence is mandatory** — tokens before components, components before pages; reversing order produces rework
- **14px base for chrome** — UI labels/chrome drop from 16px to 14px; body reading text stays 16px; two-stage migration required (add --text-14 alias first, sweep, then rename)
- **Phase 32 dashboard validation** — light validation of 1200px max-width in browser context before committing; not a full research phase
- **Purple accent boundary** — purple only for voter-persona contexts; general UI uses blue/indigo only; boundary documented during Phase 32-33 ballot/session pages
- [Phase 30]: --text-base stays at 1rem (16px) in plan 30-01; 14px migration done in plan 30-02 sweep
- [Phase 30]: Shadow-color variable pattern: single dark override drives all 8 shadow levels automatically
- [Phase 30]: --text-base flipped to 0.875rem (14px); reading-text contexts use --text-md (1rem) for 16px; body line-height updated to --leading-base
- [Phase 30]: color-mix(in srgb, var(--token) N%, transparent) used for opacity variants to avoid bloating design-system.css
- [Phase 30]: --radius-badge and --radius-tooltip now 4px via updated --radius-sm; --type-* tokens moved to COMPONENT ALIASES layer; --space-section/card/field kept in SEMANTIC (layout-role aliases, not component)
- [Phase 31-component-refresh]: Stepper connector-line pattern chosen over card-box — matches ag-stepper.js production rendering and industry standard
- [Phase 31-component-refresh]: Button hover uses shadow-md deepening instead of translateY(-1px) lift — lift reserved for clickable cards only
- [Phase 31-component-refresh]: Toast accent uses inset box-shadow instead of border-left — inset respects border-radius corners
- [Phase 31-component-refresh]: Toast accent uses inset box-shadow combined with shadow-lg in each type variant — CSS box-shadow does not auto-merge across selectors
- [Phase 31-component-refresh]: Icon intrinsic sizes (12px-22px SVGs) left as literals — no design tokens exist for SVG icon dimensions; only visual design properties tokenized
- [Phase 31-component-refresh]: Use --space-card (semantic alias = 24px) in .card-body, not --space-5 (20px) — closes CMP-02 gap
- [Phase 31-component-refresh]: ag-toast [type=info] uses var(--color-info) not var(--color-primary) — semantic token alignment with canonical .toast-info CSS class
- [Phase 32-page-layouts-core-pages]: Wizard .step-nav made sticky per-step (not extracted to shared footer) — JS manages step visibility; CSS sticky bottom is functionally equivalent without JS refactoring
- [Phase 32-page-layouts-core-pages]: Operator uses nested grid approach: app-shell = 3-row outer grid; app-main = 2-col inner grid (280px sidebar + 1fr); fixed app-sidebar stays out of grid flow
- [Phase 32-page-layouts-core-pages]: Bug fix: always add explicit display:grid when overriding display:flex from design-system; grid-template-* on a flex container is silently ignored
- [Phase 32-page-layouts-core-pages]: Settings CSS Grid 220px+1fr layout: sidenav sticky at top:80px, content max-width:720px, collapses to horizontal scroll at 768px
- [Phase 32-page-layouts-core-pages]: Mobile voter: clamp() fluid typography, position:fixed bottom nav with env(safe-area-inset-bottom), 72px min touch targets
- [Phase 32]: Dashboard aside sticky top 80px matches app-header height; table-page max-width 1400px wider than dashboard 1200px; members page wraps only members-layout in table-page to preserve full-bleed stats bar
- [Phase 33-page-layouts-secondary-pages]: Hub sidebar column: 220px matching Phase 32 settings/operator patterns for consistent sidebar width across stepper pages
- [Phase 33-page-layouts-secondary-pages]: Post-session sticky stepper uses background:var(--color-bg) to prevent content bleed-through on scroll
- [Phase 33-page-layouts-secondary-pages]: Analytics charts-grid uses repeat(2, minmax(0,1fr)) at min-width:768px to guarantee 2-col floor at tablet viewports
- [Phase 33-page-layouts-secondary-pages]: Email templates preview panel fixed at 400px (asymmetric editor grid 1fr+400px) — preview needs only phone-viewport width
- [Phase 33-page-layouts-secondary-pages]: Meetings sessions-list density: container gap var(--space-3) replaces per-item margin-bottom — canonical flex spacing pattern

### Pending Todos

- Run `/gsd:plan-phase 30` to begin Token Foundation

### Blockers/Concerns

None at roadmap stage. Research complete, all specs available.

## Session Continuity

Last session: 2026-03-19T09:00:00.554Z
Stopped at: Completed 33-03 — email templates grid fix and meetings density alignment
Resume file: None
Next action: `/gsd:plan-phase 30`
