# AG-VOTE — Project Vision

## What is AG-VOTE?

AG-VOTE is a **web application for managing general assembly votes** (assemblées générales). It provides a complete workflow from meeting creation to official results publication, including real-time voting, quorum management, attendance tracking, proxy delegation, and regulatory compliance (PV generation).

## Target Users

- **Administrators**: Configure the system, manage members, email templates, policies
- **Operators**: Run live meetings — manage agenda, launch votes, track attendance
- **Members**: Participate in votes, view results, access meeting documents
- **Auditors/Public**: View audit trails, official results, public-facing pages

## Core Value Proposition

A **self-hosted, open-source** alternative to commercial voting platforms for organizations that need:
- Legal compliance for official assembly votes (French regulatory context)
- Real-time participation with quorum tracking
- Complete audit trail and official report generation (PV/procès-verbal)
- Multi-tenant architecture for SaaS or hosted deployment

## Technical Identity

- **No-framework PHP + vanilla JS** — minimal dependencies, maximum control
- **Docker-first** deployment — single container with nginx + php-fpm
- **PWA-ready** — service worker, offline capability
- **Real-time** — SSE (Server-Sent Events) for live voting updates with Redis fan-out

## Current State

AG-VOTE is a **brownfield project** with a self-explanatory UX and full session lifecycle:
- 41 PHP controllers, 30+ repositories, 18 services
- 23 custom Web Components (ag-pdf-viewer, ag-empty-state, + 21 originals), 29 page JS modules
- Design system with structured CSS token hierarchy (primitive → semantic → component aliases), @layer (base/components/v4), three-depth background model (bg/surface/raised), dark/light parity
- ~95,000 LOC (30k JS, 30k PHP, 35k CSS)
- PHPUnit: 2331 unit tests (90.8% service coverage, 64.6% controller coverage), 64 integration tests
- Playwright E2E: 18 specs, 177 tests (chromium + mobile-chrome + tablet)
- CI pipeline: 7 GitHub Actions jobs (validate, lint, migrate-check, coverage, build, e2e, integration)
- "Officiel et confiance" visual identity — bleu/indigo, Bricolage Grotesque + Fraunces + JetBrains Mono
- Dompdf ^3.1 for PV PDF, FilePond for document upload, native iframe PDF viewer

**Shipped v4.3 Ground-Up Rebuild** (2026-03-22): Every critical page rebuilt from scratch — dashboard (horizontal KPIs, session list), login (floating labels, gradient orb, Stripe/Clerk quality), wizard (900px track, slide transitions, horizontal fields), operator console (two-panel split, SSE live, vote card centerpiece), hub (hero card, quorum bar, lifecycle checklist), settings/admin (sidebar tabs, KPI cards, user CRUD). 7 phases, 14 plans. All v4.2 regressions fixed. Backend wiring verified (dead endpoints fixed, SSE confirmed, form submissions tested). New admin_settings.php endpoint created for settings persistence.

**Shipped v4.2 Visual Redesign** (2026-03-20): Page-by-page visual redesign of all 20+ pages — horizontal KPIs, gradient CTAs, ag-tooltip guidance, hover-reveal actions, modern tab navigation, hero chart layouts, form field modernization, horizontal-first layouts. Regressions fixed in v4.3.

**Shipped v4.1 Design Excellence** (2026-03-19): CSS token restructuring (primitive→semantic→component hierarchy, shadow system, spacing/radius aliases), component refresh (8 component types tokenized, 4 Web Components reconciled), page layout rebuilds (12 pages with CSS Grid/flex specs, three-depth background, max-width constraints, responsive breakpoints), QA audit (Fraunces discipline, inline style removal, transition/focus/hover standards). Infrastructure-level CSS work — visual identity foundation established but page-by-page visual redesign still needed.

**Shipped v4.0 Clarity & Flow** (2026-03-18): PDF resolution attachments (upload, serve, viewer), guided UX layer (empty states, status cards, help panels, disabled tooltips), copropriété→AG vocabulary transformation, wizard overhaul (named stepper, templates, review card, progressive disclosure), hub enhancements (quorum bar, blocked reasons, convocations), operator console live indicators (SSE, delta badges, guidance), voter full-screen ballot (optimistic feedback, 72px cards), collapsible result cards, all-page CSS polish with @layer and color-mix().

**Shipped v3.0 Session Lifecycle** (2026-03-18): Full session lifecycle wired end-to-end — wizard creates real sessions, SSE multi-consumer infrastructure, live vote flow, post-session stepper, zero demo constants.

**Shipped v2.0 UI Redesign** (2026-03-16): Complete visual overhaul across all pages — design tokens, navigation, component library.

## Requirements

### Validated

- v1.1: E2E test suite, CI pipeline, CDN hardening, app shell audit, error handling, accessibility
- v1.2: Tenant isolation, rate limiting, PWA hardening, audit verification
- v1.3: Unused var cleanup (142→0), innerHTML security, CI lint gate
- v1.4: 100% controller tests, Permissions-Policy header, dead code audit
- v1.5: E2E coverage expansion (21 specs, ~230+ tests), version 1.5.0
- v2.0: Design system alignment (64 tokens, dark/light), navigation & layout, dashboard & sessions, wizard & hub, operator console, room display & voter view, post-session & records, statistics & users, settings & help, component library
- v3.0: Session creation wizard (atomic persistence), hub/dashboard real data, SSE multi-consumer infrastructure, operator console API wiring, live vote flow (open/cast/close/tally via SSE), post-session stepper (results/consolidation/PV PDF/archival), zero demo constants, loading/error/empty states on all pages, hub→operator meeting_id propagation, frozen→live transition with motionOpened SSE
- v4.0: PDF resolution documents (upload/serve/view), guided UX (empty states, status cards, help panels, disabled tooltips), copropriété→AG transformation, wizard overhaul (named stepper, templates, review card, progressive disclosure), hub enhancements (quorum bar, blocked reasons, convocations), operator live indicators (SSE, delta, guidance), voter full-screen ballot (optimistic, 72px), result cards (collapsible, bar charts), CSS @layer + color-mix(), all-page visual polish, "officiel et confiance" design
- v4.1: CSS token hierarchy (primitive→semantic→component, shadow system, spacing/radius aliases, dark mode derivation, zero hardcoded hex), component refresh (8 types tokenized, 4 Web Components reconciled), page layouts (12 pages with grid specs, three-depth background, max-width, responsive), QA audit (font discipline, inline style removal, transitions, focus rings)

- v4.2: Page-by-page visual redesign (20+ pages), horizontal KPIs, gradient CTAs, tooltips, hover-reveal, modern tabs, hero charts, form modernization, horizontal-first layouts. Known regressions: HTML restructuring broke some JS interactions

- v4.3: Ground-up rebuild of 6 critical pages (dashboard, login, wizard, operator console, hub, settings/admin). v4.2 regressions fixed. All pages rewritten HTML+CSS+JS from scratch. Backend wiring verified — dead endpoints fixed, SSE confirmed, admin_settings.php created. Floating labels on login, slide transitions on wizard, two-panel operator console, hero card hub, sidebar-tab settings.

- v4.4: Ground-up rebuild of remaining 13 pages (postsession, analytics, meetings, archives, audit, members, users, vote, help, email-templates, public, report, trust/validate/docs). All JS selectors verified, zero hardcoded hex, token-based CSS throughout. Vote page French data-choice mapping. Print styles for report. FAQ accordion animation.

- v5.0: Quality & Production Readiness — 2241 PHPUnit tests (Services 90.8%, Controllers 64.6%), 177 Playwright E2E tests, Docker healthcheck/entrypoint fixes, migration audit (zero SQLite syntax), CI pipeline with 7 jobs (coverage gate, E2E, integration, migration validation). ControllerTestCase base class for execution-based controller testing.

- v5.1: Operational Hardening — WebSocket→SSE namespace rename, vote/quorum edge cases (token reuse audit, closed-motion 409, zero-member safety), session/import/auth hardening (CSV encoding detection, duplicate email pre-scan, session expiry redirect, configurable rate limiting with audit), dead code cleanup (vocabulary purge, Command file documentation). 2331 PHPUnit tests, 0 failures.

- v6.0: Production & Email — SMTP config from admin UI with DB-over-env merge, customizable email templates with variable tags and server-side preview, invitation/reminder/results email workflows with operator trigger buttons, automatic results email on session close, in-app notifications (bell badge + French labels + mark-as-read + SSE toasts for vote/quorum/session events). 2352 PHPUnit tests, 0 failures.

- v6.1: PDF & Préparation de Séance — Meeting attachment upload in wizard (post-creation FilePond), operator console management (view/add/delete), dual-auth serve endpoint (session OR vote token), hub "Documents de la séance" section with ag-pdf-viewer panel, vote page "Documents" button with sheet mode (read-only). Public list endpoint with safe-fields-only response.

### Active

**v7.0 Production Essentials** : PV officiel PDF (template asso loi 1901 avec signatures), email queue worker (cron Docker), page /setup initial (premier tenant + admin), reset password par email (token sécurisé).

### Out of Scope

- Framework migration (React, Vue, Laravel, Symfony) — vanilla stack is the identity
- New voting modes or report types — functional parity first
- Mobile native app — PWA approach maintained
- Multi-database support — PostgreSQL only
- Electronic signature upload/validation (deferred to later)

## Context

The codebase uses Bricolage Grotesque (body), Fraunces (display, h1 only), JetBrains Mono (data) typography. Design system has structured token hierarchy with three-depth background model.

Known technical debt:
- admin.js KPI load failure catch is silent (non-blocking, admin-only page)
- Controller coverage at 64.6% (3 controllers use exit()/raw binary — structural limit)
- 04_e2e.sql seed data not loaded in CI e2e job (local-only for now)
- Migration idempotency check is local-only, not CI-gated

Deferred ideas:
- AI-assisted PV minutes generation
- ClamAV virus scanning for uploaded PDFs
- Per-tenant motion templates in database
- Electronic signature upload/validation
- Votes pour collectivités territoriales

## Constraints

- **Tech stack**: No-framework PHP + vanilla JS + Web Components — no change
- **Design approach**: Page-by-page visual redesign with concrete references (Linear, Notion, Clerk, Stripe)
- **Backward compatibility**: Existing functionality preserved
- **Accessibility**: WCAG AA compliance maintained (skip links, ARIA landmarks, focus indicators)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| v2.0 major version | Visual overhaul warrants major bump | ✓ Good — clear scope boundary |
| Wireframe as design spec | Comprehensive HTML wireframe defines all UI targets | ✓ Good — unambiguous reference |
| Align, don't rewrite | Upgrade existing code to match wireframe, not start from scratch | ✓ Good — preserved all backend functionality |
| Phase numbering from v1.5 | Continue at Phase 4 (not restart at 1) | ✓ Good — clear history |
| Gap closure phases 14-15 | Address integration bugs found by milestone audit | ✓ Good — caught real API wiring issues |
| IIFE + var pattern | Keep existing JS conventions, no ES modules for page scripts | ✓ Good — consistent with vanilla stack identity |
| One CSS per page | Each page gets dedicated CSS file (wizard.css, hub.css, etc.) | ✓ Good — clean separation |
| Web Components for shared UI | ag-modal, ag-toast, ag-confirm, ag-popover, ag-searchable-select | ✓ Good — reusable across pages |
| Redis SSE fan-out | Per-consumer Redis lists for multi-consumer SSE delivery | ✓ Good — scales without Redis Pub/Sub blocking |
| Gap closure phases 23-24 | Address integration wiring gaps found by milestone audit | ✓ Good — caught hub→operator handoff and frozen→live SSE gaps |
| v4.0 major version | Complete UX/UI overhaul + new features (PDF, copro transform) | ✓ Good — shipped comprehensive feature set |
| v4.1 CSS infrastructure first | Token hierarchy + component specs + layout grids before visual redesign | ⚠️ Revisit — infrastructure delivered but no visible visual impact; page-by-page redesign needed |
| PC-first approach | Optimize for 1024px+, mobile only for voter screen | ✓ Good — matches use case |

| v5.0 quality milestone | Production readiness requires 90%+ test coverage before new features | ✓ Good — Services 90.8%, 2241 unit + 177 E2E tests, full CI pipeline |

---
*Last updated: 2026-03-31 after v5.1 milestone started*
