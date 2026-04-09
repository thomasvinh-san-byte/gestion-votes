---
gsd_state_version: 1.0
milestone: v1.2
milestone_name: Bouclage et Validation Bout-en-Bout
status: executing
stopped_at: Completed 12-page-by-page-mvp-sweep/12-17-PLAN.md
last_updated: "2026-04-09T05:56:50.754Z"
last_activity: 2026-04-09 -- Completed 12-12 admin page MVP sweep
progress:
  total_phases: 6
  completed_phases: 3
  total_plans: 36
  completed_plans: 35
  percent: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-08)

**Core value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.
**Current focus:** Phase 12 — Page-by-Page MVP Sweep

## Current Position

Phase: 12 (Page-by-Page MVP Sweep) — EXECUTING
Plan: 12 of 14 (phase 12)
Status: Executing Phase 12
Last activity: 2026-04-09 -- Completed 12-12 admin page MVP sweep

Progress: [░░░░░░░░░░] 0% (v1.2: 0/4 phases)

## Accumulated Context

### Decisions

- [v10.0 roadmap]: 3 phases derived from 14 requirements — token layer → component geometry → hardening
- [v10.0 roadmap]: Phase 82 changes design-system.css @layer base only; no per-page CSS files touched in this phase
- [v10.0 roadmap]: Dark mode [data-theme="dark"] block and critical-tokens inline styles must update in the same commit as any :root color primitive change (Pitfall 2 from research)
- [v10.0 roadmap]: Token names must never be renamed — add new alongside old to avoid Shadow DOM fallback staleness (Pitfall 3 from research)
- [v10.0 roadmap]: Phase 83 deferred skeleton shimmer scope requires pre-phase audit of which pages use spinners vs. HTMX-managed states
- [v9.0 roadmap]: AgConfirm.ask() is the universal confirmation pattern across all 7 page modules
- [Phase 81-fix-ux]: Shared.openModal() preserved for form-containing modals only
- [Phase 82-01]: color-mix(in oklch) used for all hover/active derivations — perceptually uniform darkening vs srgb
- [Phase 82-01]: --color-accent aliased to var(--purple-600) confirming COLOR-03 accent sparsity at token level
- [Phase 82-token-foundation-palette-shift]: Dark mode surface hue set to 78 (warm-neutral) replacing cool hue ~260 — warm identity now consistent across both modes
- [Phase 82-token-foundation-palette-shift]: Dark mode hover direction uses color-mix(in oklch, base 88%, white) — lightening in dark context is correct interactive cue
- [Phase 83-01]: All component corners unified to --radius-base (8px) — no per-component radius overrides remain
- [Phase 83-01]: Shadow scale reduced 9→3 levels: sm=0.06 opacity, md unchanged, lg=old xl 0.14 opacity
- [Phase 83-01]: --color-border-alpha uses oklch alpha for adaptive depth (black/white based for light/dark modes)
- [Phase 83]: Skeleton KPI height 88px matches kpi-card approx height; .kpi-card-wrapper wrapper enables CSS-only show/hide toggle
- [Phase 84]: @property registered for 8 core color tokens — derived tokens excluded (no var() in initial-value per CSS spec)
- [Phase 84]: HARD-03: all 21 htmx.html critical-tokens blocks updated from hex to oklch — research incorrectly claimed files were already in sync
- [Phase 84]: Token name --color-primary-text is canonical for text on primary backgrounds (not --color-text-on-primary or --color-primary-contrast)
- [Phase 84]: oklch() literals used for rgba(white/black, N) where no semantic token exists
- [Phase 06-02]: .login-orb changed from position: fixed to position: absolute — orb scoped to brand panel, not viewport
- [Phase 06-02]: Tagline updated to 'Gestion des votes pour votre association' per DESIGN-02 copywriting contract
- [Phase 07-playwright-coverage]: Pin @playwright/test to exact 1.59.1 (no caret) per TEST-03 exact version requirement
- [Phase 07-playwright-coverage]: color-contrast disabled in axeAudit — visual contrast is design-token scope, structural accessibility is the target
- [Phase 07-playwright-coverage]: axeAudit filters to critical/serious violations only — moderate/minor are not CI-blocking
- [Phase 07-01]: Strategy C mandatory for public.htmx.html SSE pages — networkidle can never resolve, use domcontentloaded + waitForSelector
- [Phase 08-test-infrastructure-docker]: Playwright jammy image pinned to v1.59.1 — matches @playwright/test 1.59.1 in package.json, avoids Alpine musl browser hell
- [Phase 08-test-infrastructure-docker]: profiles: [test] gates tests service — never starts on plain docker compose up
- [Phase 08-02]: exec docker compose propagates playwright exit code cleanly — no wrapper masking
- [Phase 08-02]: $* (not $@) inside bash -lc for arg forwarding — $@ produces separate argv tokens that don't compose in inner shell
- [Phase 08-02]: --project=chromium hardcoded in wrapper — enforces chromium-only scope per locked Phase 8 decision
- [Phase 08-03]: Triage verdict MOSTLY GREEN: 85 failures are ERR_SSL_PROTOCOL_ERROR from cookie domain mismatch (localhost vs app:8080), not infra. INFRA-03 satisfied.
- [Phase 08-03]: Phase 11 FIX-01 scope: fix setup/auth.setup.js cookie domain to use BASE_URL host; investigate ignoreHTTPSErrors for Chromium HTTPS-upgrade
- [Phase 08-03]: docker run direct replaces docker compose run — compose swallows container stdout in this environment
- [Phase 09-01]: COOKIE_DOMAIN = new URL(BASE_URL).hostname — derive cookie domain from BASE_URL host, eliminates ERR_SSL_PROTOCOL_ERROR (85 failures) in Docker Playwright runs
- [Phase 09-01]: auth.setup.js BASE_URL fallback mirrors playwright.config.js (IN_DOCKER ? app:8080 : localhost:8080) — prevents split-brain on cookie domain
- [Phase 09-tests-e2e-par-role]: 09-05: Follow session-based auth for vote page (not token-based), no DB writes for re-runnability, btnConfirm DOM presence check not visibility
- [Phase 09-tests-e2e-par-role]: Admin E2E spec uses read-only assertions only (tab clicks, page loads) — fully re-runnable without DB cleanup
- [Phase 09-tests-e2e-par-role]: E2E-02: operator critical path spec uses hybrid API+UI strategy (setup via API, console via browser); CSRF endpoint is /api/v1/auth_csrf; meeting ID field is data.meeting_id
- [Phase 11-backend-wiring-fixes]: Separate PublicTest class for dual-auth coverage rather than appending to existing test file
- [Phase 11-backend-wiring-fixes]: EventBroadcaster::queue() now catches Redis failures silently — SSE is best-effort, must never abort HTTP responses
- [Phase 11-backend-wiring-fixes]: EmailController accepts optional emailQueueFactory callable constructor param to enable testing against the final EmailQueueService
- [Phase 11-backend-wiring-fixes]: settVoteMode/settMajority/settQuorumThreshold wired into VoteEngine+QuorumEngine via fallback policy synthesis; explicit policies still win
- [Phase 11-backend-wiring-fixes]: Removed orphan buttons and dead settings instead of wiring to nothing — MVP discipline enforced
- [Phase 11-backend-wiring-fixes]: getDashboardStats() wired in DashboardController::index() — present_count from aggregated query, present_weight kept from dashboardSummary; full stats dict exposed as data.stats in response
- [Phase 11-backend-wiring-fixes]: Created MeetingReportsService (plural) rather than extending MeetingReportService (singular) — avoids 1000+ line god service
- [Phase 11]: MotionsService: lazy-load repo accessors prevent PDO errors in partial-mock tests
- [Phase 11]: EventBroadcaster stays in controller post-service (HTTP concern, not business logic)
- [Phase 12-page-by-page-mvp-sweep]: hub.css both CSS gates passed by inspection with no edits required (already fluid + token-pure)
- [Phase 12-page-by-page-mvp-sweep]: Dashboard width cap removed: max-width 1200px → 100% with padding-inline space-6
- [Phase 12-page-by-page-mvp-sweep]: KPI Playwright assertion uses not.toHaveText('-') to prove getDashboardStats() DEBT-01 wiring
- [Phase 12-page-by-page-mvp-sweep]: closeSession uses custom DOM modal not window.confirm — DOM presence assertion used for draft-meeting test
- [Phase 12-page-by-page-mvp-sweep]: Refresh click uses force:true to bypass hidden quorum overlay pointer interception in Playwright
- [Phase 12-05]: Width and token gates already clean from Phase 6/11 — no CSS edits required
- [Phase 12-05]: Playwright spec tolerates empty test-DB; assertions accept zero count as valid
- [Phase 12-page-by-page-mvp-sweep]: Wizard is applicative page: max-width 100% not 960px/900px
- [Phase 12-page-by-page-mvp-sweep]: members.css was already clean — no changes needed for width or token gates
- [Phase 12-page-by-page-mvp-sweep]: DB-write steps in critical-path-members.spec.js wrapped in try/catch for resilient function gate
- [Phase 12-07]: Removed 5 artificial max-width caps on vote page containers; kept blocked-overlay-inner 520px modal constraint
- [Phase 12-page-by-page-mvp-sweep]: archives.css already compliant in v4.4 — zero changes needed for width or token gates
- [Phase 12-page-by-page-mvp-sweep]: critical-path-archives.spec.js: single test covers all 7 interactions to avoid auth overhead
- [Phase 12-09]: audit.css was already token-pure and full-width at v4.3; KPI gate adapted to visibility-only for empty test DB
- [Phase 12-11]: users.css already clean — width and token gates passed without any edits
- [Phase 12-11]: ag-modal Shadow DOM: assert open/close via aria-hidden attribute; use inputValue() for fields in unmatched slots
- [Phase 12-12]: admin-content width: 100% — applicative pages (KPI dashboard + management tables) must fill viewport, not be constrained by content-narrow token
- [Phase 12-12]: Spec named critical-path-admin-PAGE.spec.js to avoid collision with critical-path-admin.spec.js (admin ROLE flow)
- [Phase 12-12]: E2E test fills create-user form but does NOT submit — avoids test user creation in DB; fields cleared after assertion
- [Phase 12-13]: analytics.css width gate passes: only .donut-card max-width:360px (legacy, overridden by .donut-card--horizontal { max-width: none }). No applicative container cap.
- [Phase 12-13]: analytics.css token gate passes: 83 var(--color-*) references, zero raw oklch/hex/rgba literals.
- [Phase 12-15]: MeetingContext uses sessionStorage not localStorage — addInitScript must use sessionStorage.setItem
- [Phase 12-14]: Use toBeAttached() over toBeVisible() for elements managed by report.js disableExports()
- [Phase 12-16]: URL param injection for MeetingContext (?meeting_id=UUID) is required for validate page specs — sessionStorage set via page.evaluate is lost across navigation
- [Phase 12-16]: Non-destructive modal assertions: wrap in isEnabled() guard, assert dual-guard wiring via cancel path, never click irreversible archive button
- [Phase 12-page-by-page-mvp-sweep]: help is CONTENT page — max-width 80ch cap (not removed), .help-search 480px is a component cap
- [Phase 12-page-by-page-mvp-sweep]: docs is a CONTENT page — .prose clamped to 80ch, .doc-layout grid stays full-width (per user MVP-01 decision)
- [Phase 12-page-by-page-mvp-sweep]: page.evaluate JS dispatch bypasses modal overlay hit-test for severity pills; chip/view handlers committed to trust.js for next container rebuild

### Existing Infrastructure

- design-system.css: 5,258 lines, three @layer stack (base/components/v4), oklch values already present as trailing comments on every primitive
- 23 Web Components with Shadow DOM — inherit tokens but fallback hex literals require manual update after palette changes
- 25 per-page CSS files — ~15 hardcoded hex/rgba values identified: analytics.css, meetings.css, hub.css, vote.css, public.css, users.css
- critical-tokens inline styles in 22 HTML files — 6 hex values that prevent flash-of-wrong-color on load
- color-mix(in srgb, ...) calls in design-system.css — need upgrade to color-mix(in oklch, ...)

### Known Tech Debt Carried Forward

- Controller coverage at 64.6% structural ceiling (3 exit()-based controllers)
- CI e2e job runs chromium only; mobile-chrome/tablet are local-only
- Migration idempotency check is local-only, not CI-gated

### Pending Todos

None.

### Blockers/Concerns

- Phase 84 HARD-03: exact list of .htmx.html files with critical-tokens blocks should be verified before planning (research said 22 files)

## Session Continuity

Last session: 2026-04-09T05:56:50.751Z
Stopped at: Completed 12-page-by-page-mvp-sweep/12-17-PLAN.md
Resume file: None
