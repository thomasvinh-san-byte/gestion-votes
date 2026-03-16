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
- **Real-time** — SSE (Server-Sent Events) for live voting updates

## Current State

AG-VOTE is a **brownfield project** with a mature feature set:
- 38 PHP controllers, 30+ repositories, 18 services
- 20 custom Web Components, 29 page JS modules
- Full design system with 64 CSS tokens, dark/light theme switching
- 119 HTML/CSS/JS frontend files, 59,330 LOC
- PHPUnit test suite (20+ test files), E2E suite (21 specs, ~230+ tests)
- All 16 pages aligned with wireframe v3.19.2 "Acte Officiel"

**Shipped v2.0 UI Redesign** (2026-03-16): Complete visual overhaul across all pages — design tokens, navigation, dashboard, sessions, wizard, hub, operator console, room display, voter view, post-session, archives, audit, statistics, users, settings, help/FAQ.

## Requirements

### Validated

- v1.1: E2E test suite, CI pipeline, CDN hardening, app shell audit, error handling, accessibility
- v1.2: Tenant isolation, rate limiting, PWA hardening, audit verification
- v1.3: Unused var cleanup (142→0), innerHTML security, CI lint gate
- v1.4: 100% controller tests, Permissions-Policy header, dead code audit
- v1.5: E2E coverage expansion (21 specs, ~230+ tests), version 1.5.0
- v2.0: Design system alignment (64 tokens, dark/light), navigation & layout (sidebar rail/expand, header, mobile nav, footer, ARIA), dashboard & sessions (KPIs, list/calendar, filters), wizard & hub (4-step accordion, status tracking), operator console (live KPIs, resolution tabs, quorum modal), room display & voter view (full-screen dark, touch-optimized), post-session & records (stepper, archives, audit log), statistics & users (charts, export, role panel, pagination), settings & help (4 tabs, FAQ, guided tours), component library (modal, toast, confirm, popover, progress, tour, banner)

### Active

(No active requirements — next milestone not yet planned)

### Out of Scope

- Framework migration (React, Vue, Laravel, Symfony) — vanilla stack is the identity
- New functional features (new voting modes, new report types)
- Mobile native app — PWA approach maintained
- Multi-database support — PostgreSQL only
- Electronic signature upload/validation (deferred to v2.1)

## Context

The wireframe files (`ag_vote_wireframe.html`, `docs/wireframe/ag_vote_v3_19_2.html`) on the main branch defined the target UI for v2.0. All 16 pages now match the wireframe v3.19.2 specification. The codebase uses Bricolage Grotesque (body), Fraunces (display), JetBrains Mono (data) typography.

Known technical debt:
- Phases 10.1 and 10.2 (gap closure) were planned but superseded by Phases 14-15
- Some duplicate phase directories exist from re-planning (11-post-session-records vs 11-postsession-records)
- Phase 5 plan 04 was a verification-only plan, not a feature plan

## Constraints

- **Tech stack**: No-framework PHP + vanilla JS + Web Components — no change
- **Design reference**: Wireframe v3.19.2 "Acte Officiel" was the source of truth for v2.0
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

---
*Last updated: 2026-03-16 after v2.0 milestone shipped*
