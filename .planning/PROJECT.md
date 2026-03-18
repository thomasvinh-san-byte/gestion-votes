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

AG-VOTE is a **brownfield project** with a fully wired session lifecycle:
- 38 PHP controllers, 30+ repositories, 18 services
- 20 custom Web Components, 29 page JS modules
- Full design system with 64 CSS tokens, dark/light theme switching
- ~80,000 LOC (27k JS, 30k PHP, 24k CSS)
- PHPUnit test suite (20+ test files), E2E suite (21 specs, ~230+ tests)
- All pages aligned with wireframe v3.19.2 "Acte Officiel"
- Dompdf ^3.1 installed for PV PDF generation

**Shipped v3.0 Session Lifecycle** (2026-03-18): Full session lifecycle wired end-to-end — wizard creates real sessions with atomic member+motion persistence, SSE multi-consumer infrastructure with Redis fan-out, live vote flow (operator opens/closes motions, voters cast ballots via SSE), post-session stepper (verified results, consolidation, PV PDF, archival), zero demo constants, every API call has loading/error/empty states.

**Shipped v2.0 UI Redesign** (2026-03-16): Complete visual overhaul across all pages — design tokens, navigation, dashboard, sessions, wizard, hub, operator console, room display, voter view, post-session, archives, audit, statistics, users, settings, help/FAQ.

## Requirements

### Validated

- v1.1: E2E test suite, CI pipeline, CDN hardening, app shell audit, error handling, accessibility
- v1.2: Tenant isolation, rate limiting, PWA hardening, audit verification
- v1.3: Unused var cleanup (142→0), innerHTML security, CI lint gate
- v1.4: 100% controller tests, Permissions-Policy header, dead code audit
- v1.5: E2E coverage expansion (21 specs, ~230+ tests), version 1.5.0
- v2.0: Design system alignment (64 tokens, dark/light), navigation & layout, dashboard & sessions, wizard & hub, operator console, room display & voter view, post-session & records, statistics & users, settings & help, component library
- v3.0: Session creation wizard (atomic persistence), hub/dashboard real data, SSE multi-consumer infrastructure, operator console API wiring, live vote flow (open/cast/close/tally via SSE), post-session stepper (results/consolidation/PV PDF/archival), zero demo constants, loading/error/empty states on all pages, hub→operator meeting_id propagation, frozen→live transition with motionOpened SSE

### Active

**Current Milestone: v4.0 "Clarity & Flow"**

**Goal:** Transform AG-VOTE into a self-explanatory, visually impressive application where users never need external help to run a complete assembly — from session creation to archival.

**Target features:**
- Guided UX overhaul — progressive disclosure, smart defaults, contextual hints, step-by-step flows across all screens
- PC-first design approach (1024px+, adaptive) — mobile only for voter screen (in-room voting on phone)
- PDF résolutions — attach documents during creation and before meeting, inline viewer for voters
- Copropriété transformation — convert tantièmes/millièmes logic into useful AG-standard features
- Top 1% UI/UX — research-driven redesign from scratch, no wireframe constraint

### Out of Scope

- Framework migration (React, Vue, Laravel, Symfony) — vanilla stack is the identity
- New voting modes or report types — functional parity first
- Mobile native app — PWA approach maintained
- Multi-database support — PostgreSQL only
- Electronic signature upload/validation (deferred to later)
- Copropriété as separate module — tantièmes/millièmes logic to be transformed for AG-standard use, not maintained as copro-specific

## Context

The wireframe files (`ag_vote_wireframe.html`, `docs/wireframe/ag_vote_v3_19_2.html`) on the main branch defined the target UI for v2.0. All pages match the wireframe v3.19.2 specification. The codebase uses Bricolage Grotesque (body), Fraunces (display), JetBrains Mono (data) typography.

Known technical debt:
- admin.js KPI load failure catch is silent (non-blocking, admin-only page)
- PST-01-04 verified manually + by integration checker (no E2E specs for postsession stepper)
- Phase 20.4 VERIFICATION.md has human_needed visual items pending review

Deferred ideas from v3.0:
- Retrait copropriété — remove all copropriete-related code from codebase
- PDFs résolutions — attach PDF documents to resolutions, with voter consultation access
- Suivi budget & documents PDF pour votants
- Votes pour collectivités territoriales (syndicats, communes, départements)

## Constraints

- **Tech stack**: No-framework PHP + vanilla JS + Web Components — no change
- **Design reference**: v4.0 designs from scratch (wireframe v3.19.2 retired as reference)
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
| LOAD_SEED_DATA rename | LOAD_DEMO_DATA renamed for production clarity | ✓ Good — zero demo references in codebase |
| Frozen→live via operator_open_vote | Atomic status transition + SSE broadcast when opening first vote | ✓ Good — clean state machine path |
| Gap closure phases 23-24 | Address integration wiring gaps found by milestone audit | ✓ Good — caught hub→operator handoff and frozen→live SSE gaps |

| v4.0 major version | Complete UX/UI overhaul + new features (PDF, copro transform) | — Pending |
| Design from scratch | Retire wireframe v3.19.2, research-driven design for top 1% UX | — Pending |
| PC-first approach | Optimize for 1024px+, mobile only for voter screen | — Pending |

---
*Last updated: 2026-03-18 after v4.0 milestone started*
