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
- Full design system with dark/light theme support
- PHPUnit test suite (20+ test files)
- Recently completed UX/UI audit with P1/P2/P3 fixes applied

## Current Milestone: v2.0 UI Redesign

**Goal:** Align all pages and components with the AG-Vote v3.19.2 "Acte Officiel" wireframe design system — upgrading design tokens, layout structure, navigation, components, and page content across the entire application.

**Target features:**
- Design system overhaul (tokens, colors, typography, shadows, borders, spacing)
- Sidebar navigation redesign (58px rail / 252px expanded, 5 sections, hover/pin behavior)
- Header with glassmorphism, global search (Cmd+K), notifications panel
- Dashboard redesign (KPI cards, urgent actions, upcoming sessions, task list)
- Sessions page (list/calendar view toggle, filters, sort, empty states)
- Create Session wizard (4-step accordion with stepper)
- Session Hub (status bar, checklist, KPI cards, documents)
- Operator page redesign (live KPI strip, progress track, resolution tabs, right sidebar agenda)
- Room Display (full-screen, dark background, vote visualization)
- Post-Session workflow (4-step stepper: verification, validation, PV, send)
- Archives page refinement
- Audit page (table/timeline toggle, event detail modal)
- Statistics page (KPI cards, donut chart, line graph)
- Users management page
- Settings page (tabs: rules, communication, security, accessibility)
- Help & FAQ page (accordion, category filter, tour launchers)
- Voter tablet/mobile view (touch-optimized, bottom nav)
- Dark/light theme with complete token set
- Guided tours system
- Toast notification system
- Modal and confirmation dialog system

## Requirements

### Validated

- v1.1: E2E test suite, CI pipeline, CDN hardening, app shell audit, error handling, accessibility
- v1.2: Tenant isolation, rate limiting, PWA hardening, audit verification
- v1.3: Unused var cleanup (142→0), innerHTML security, CI lint gate
- v1.4: 100% controller tests, Permissions-Policy header, dead code audit
- v1.5: E2E coverage expansion (21 specs, ~230+ tests), version 1.5.0

### Active

- [ ] Design system alignment with wireframe v3.19.2
- [ ] All 16 pages match wireframe specifications
- [ ] Navigation and layout match wireframe structure
- [ ] Component library matches wireframe components
- [ ] Dark/light theme tokens match wireframe

### Out of Scope

- Framework migration (React, Vue, Laravel, Symfony) — vanilla stack is the identity
- New functional features (new voting modes, new report types) — this is visual only
- Mobile native app — PWA approach maintained
- Multi-database support — PostgreSQL only

## Context

The wireframe files (`ag_vote_wireframe.html`, `docs/wireframe/ag_vote_v3_19_2.html`) on the main branch define the target UI. The codebase already uses the same fonts and has a design system — this milestone aligns the existing implementation with the comprehensive wireframe specification.

## Constraints

- **Tech stack**: No-framework PHP + vanilla JS + Web Components — no change
- **Design reference**: Wireframe v3.19.2 "Acte Officiel" is the source of truth
- **Backward compatibility**: Existing functionality must be preserved
- **Accessibility**: WCAG AA compliance maintained (already partially implemented)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| v2.0 major version | Visual overhaul warrants major bump | — Pending |
| Wireframe as design spec | Comprehensive HTML wireframe defines all UI targets | — Pending |
| Align, don't rewrite | Upgrade existing code to match wireframe, not start from scratch | — Pending |

---
*Last updated: 2026-03-12 after milestone v2.0 started*
