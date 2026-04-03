# Roadmap: AG-VOTE

## Milestones

- ✅ **v1.x Foundations** - Phases 1-3 (shipped)
- ✅ **v2.0 UI Redesign** - Phases 4-15 (shipped 2026-03-16)
- ✅ **v3.0 Session Lifecycle** - Phases 16-24 (shipped 2026-03-18)
- ✅ **v4.0 Clarity & Flow** - Phases 25-29 (shipped 2026-03-18)
- ✅ **v4.1 Design Excellence** - Phases 30-34 (shipped 2026-03-19)
- ✅ **v4.2 Visual Redesign** - Phases 35-41.5 (shipped 2026-03-20)
- ✅ **v4.3 Ground-Up Rebuild** - Phases 42-48 (shipped 2026-03-22)
- ✅ **v4.4 Complete Rebuild** - Phases 49-51 (shipped 2026-03-30)
- ✅ **v5.0 Quality & Production Readiness** - Phases 52-57 (shipped 2026-03-30)
- ✅ **v5.1 Operational Hardening** - Phases 58-61 (shipped 2026-03-31)
- ✅ **v6.0 Production & Email** - Phases 62-64 (shipped 2026-04-01)
- ✅ **v6.1 PDF & Preparation de Seance** - Phases 65-66 (shipped 2026-04-01)
- ✅ **v7.0 Production Essentials** - Phases 67-70 (shipped 2026-04-01)
- ✅ **v8.0 Account & Hardening** - Phases 71-75 (shipped 2026-04-02)
- ✅ **v9.0 Compliance & Robustness** - Phases 76-81 (shipped 2026-04-03)
- 🚧 **v10.0 Visual Identity Evolution** - Phases 82-84 (in progress)

---

<details>
<summary>✅ v4.3 Ground-Up Rebuild (Shipped: 2026-03-22) — 7 phases, 14 plans</summary>

**Milestone Goal:** Rebuild every critical page from scratch — HTML+CSS+JS together in one commit, fix all v4.2 regressions, wire backend properly, achieve genuine top 1% design quality.

### Phases

- [x] **Phase 42: Stabilization** - Fix all v4.2 regressions before any rebuild work begins
- [x] **Phase 43: Dashboard Rebuild** - Complete HTML+CSS+JS rewrite, KPIs and session list wired to backend (completed 2026-03-20)
- [x] **Phase 44: Login Rebuild** - Complete HTML+CSS rewrite, auth flow wired, top 1% entry point (completed 2026-03-20)
- [x] **Phase 45: Wizard Rebuild** - Complete HTML+CSS+JS rewrite, all 4 steps wired, form submissions verified (completed 2026-03-22)
- [x] **Phase 46: Operator Console Rebuild** - Complete HTML+CSS+JS rewrite, SSE wired, live vote panel functional (completed 2026-03-22)
- [x] **Phase 47: Hub Rebuild** - Complete HTML+CSS+JS rewrite, session lifecycle wired, quorum bar functional (completed 2026-03-22)
- [x] **Phase 48: Settings/Admin Rebuild** - Complete HTML+CSS+JS rewrite, all settings save, admin KPIs wired (completed 2026-03-22)

</details>

---

<details>
<summary>✅ v4.4 Complete Rebuild (Shipped: 2026-03-30) — 3 phases, 10 plans</summary>

**Milestone Goal:** Ground-up rebuild of all remaining 13 pages to v4.3 quality standard — HTML+CSS+JS from scratch, backend wiring verified, browser tested.

### Phases

- [x] **Phase 49: Secondary Pages Part 1** - Ground-up rebuild of postsession, analytics, meetings, archives (completed 2026-03-30)
- [x] **Phase 50: Secondary Pages Part 2** - Ground-up rebuild of audit, members, users, vote/ballot (completed 2026-03-30)
- [x] **Phase 51: Utility Pages** - Ground-up rebuild of help, email-templates, public, report/PV, trust/validate/docs (completed 2026-03-30)

</details>

---

<details>
<summary>✅ v5.0 Quality & Production Readiness (Shipped: 2026-03-30) — 6 phases, 18 plans</summary>

**Milestone Goal:** Achieve 90%+ test coverage across all layers, fix infrastructure bugs, harden Docker/CI pipeline, and make AG-VOTE production-ready.

### Phases

- [x] **Phase 52: Infrastructure Foundations** - Fix Docker healthcheck, entrypoint PORT handling, health endpoint JSON response, and all migration SQLite-isms (completed 2026-03-30)
- [x] **Phase 53: Service Unit Tests Batch 1** - Write unit tests for QuorumEngine, VoteEngine, ImportService, MeetingValidator, NotificationsService (completed 2026-03-30)
- [x] **Phase 54: Service Unit Tests Batch 2** - Write unit tests for EmailTemplateService, SpeechService, MonitoringService, ErrorDictionary, ResolutionDocumentController (completed 2026-03-30)
- [x] **Phase 55: Coverage Target & Tooling** - Install pcov, measure baseline, fill gaps to 90%+ on Services and Controllers (completed 2026-03-30)
- [x] **Phase 56: E2E Test Updates** - Update all 18 stale Playwright specs for v4.3/v4.4 rebuilds; all specs pass on Chromium (completed 2026-03-30)
- [x] **Phase 57: CI/CD Pipeline** - Wire PHPUnit coverage gate, E2E suite, migration validation, and integration tests into GitHub Actions (completed 2026-03-30)

</details>

---

<details>
<summary>✅ v5.1 Operational Hardening (Shipped: 2026-03-31) — 4 phases, 8 plans</summary>

**Milestone Goal:** Close all logical holes in the domain layer — rename WebSocket to SSE throughout the codebase, harden vote/quorum/session/import/auth edge cases with explicit error handling and audit logging, and eliminate dead code and vocabulary inconsistencies.

### Phases

- [x] **Phase 58: WebSocket to SSE Rename** - Rename AgVote\WebSocket namespace to AgVote\SSE, WebSocketListener to SseListener (completed 2026-03-31)
- [x] **Phase 59: Vote and Quorum Edge Cases** - Token reuse audit trail, closed-motion 409, zero-member quorum safety, SSE broadcast tests (completed 2026-03-31)
- [x] **Phase 60: Session, Import, and Auth Edge Cases** - Invalid transition messages, CSV encoding detection, duplicate email pre-scan, session expiry redirect, rate limiting with audit (completed 2026-03-31)
- [x] **Phase 61: Dead Code Cleanup** - Vocabulary purge, phpunit.xml path fix, Command file retention documentation (completed 2026-03-31)

### Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 58. WebSocket to SSE Rename | 2/2 | Complete | 2026-03-31 |
| 59. Vote and Quorum Edge Cases | 2/2 | Complete | 2026-03-31 |
| 60. Session, Import, and Auth Edge Cases | 3/3 | Complete | 2026-03-31 |
| 61. Dead Code Cleanup | 1/1 | Complete | 2026-03-31 |

</details>

---

<details>
<summary>✅ v6.0 Production & Email (Shipped: 2026-04-01) — 3 phases, 6 plans</summary>

**Milestone Goal:** Deliver functional email communication (invitations, reminders, results) via SMTP with customizable templates, plus real-time in-app notifications via bell icon and SSE toasts.

### Phases

- [x] **Phase 62: SMTP & Template Engine** - Wire Symfony Mailer SMTP config and make email templates editable from admin UI (completed 2026-04-01)
- [x] **Phase 63: Email Sending Workflows** - Operator sends invitations/reminders, system sends results after session close (completed 2026-04-01)
- [x] **Phase 64: In-App Notifications** - Bell badge with notification list and real-time SSE toasts (completed 2026-04-01)

### Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 62. SMTP & Template Engine | 2/2 | Complete | 2026-04-01 |
| 63. Email Sending Workflows | 2/2 | Complete | 2026-04-01 |
| 64. In-App Notifications | 2/2 | Complete | 2026-04-01 |

</details>

---

<details>
<summary>✅ v6.1 PDF & Preparation de Seance (Shipped: 2026-04-01) — 2 phases, 3 plans</summary>

**Milestone Goal:** Make meeting-level PDF attachments accessible to voters through the hub and vote page.

### Phases

- [x] **Phase 65: Attachment Upload & Serve** — Wizard FilePond upload, operator console management, dual-auth serve endpoint (completed 2026-04-01)
- [x] **Phase 66: Voter Document Access** — Hub "Documents de la seance" section, vote page "Documents" button with ag-pdf-viewer (completed 2026-04-01)

### Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 65. Attachment Upload & Serve | 2/2 | Complete | 2026-04-01 |
| 66. Voter Document Access | 1/1 | Complete | 2026-04-01 |

</details>

---

<details>
<summary>✅ v7.0 Production Essentials (Shipped: 2026-04-01) — 4 phases, 6 plans</summary>

**Milestone Goal:** Deliver four production-essential features: official PV PDF generation for legal compliance, cron-based email queue worker for reliable delivery, browser-based initial setup for new deployments, and secure password reset via email.

### Phases

- [x] **Phase 67: PV Officiel PDF** - Generate legally compliant proces-verbal PDF with asso loi 1901 template (completed 2026-04-01)
- [x] **Phase 68: Email Queue Worker** - Cron-based queue processor in Docker with retry and failure handling (completed 2026-04-01)
- [x] **Phase 69: Initial Setup** - First-run /setup page to create tenant and admin account (completed 2026-04-01)
- [x] **Phase 70: Reset Password** - Secure token-based password reset flow via email (completed 2026-04-01)

</details>

---

<details>
<summary>✅ v8.0 Account & Hardening (Shipped: 2026-04-02) — 5 phases, 8 plans</summary>

**Milestone Goal:** Give every logged-in user a self-service Mon Compte page, harden critical operations with 2-step confirmation, make session timeout configurable, add vote session resume after re-auth, and retire four known tech debt items (controller coverage, silent error, CI seed data, CI migration check).

### Phases

- [x] **Phase 71: Mon Compte** - Profile view and self-service password change page for all connected users (completed 2026-04-02)
- [x] **Phase 72: Security Config** - 2-step confirmation for critical operations and configurable session timeout from admin settings (completed 2026-04-02)
- [x] **Phase 73: Vote Session Resume** - Re-authentication flow that restores voter context after session timeout during a live vote (completed 2026-04-02)
- [x] **Phase 74: CI Hardening** - Load E2E seed data in CI job and gate migration idempotency check in CI pipeline (completed 2026-04-02)
- [x] **Phase 75: Coverage & Observability** - Refactor exit()-based controllers to raise coverage ceiling and surface admin.js KPI errors visibly (completed 2026-04-02)

</details>

---

<details>
<summary>✅ v9.0 Compliance & Robustness (Shipped: 2026-04-03) — 6 phases, 12 plans</summary>

**Milestone Goal:** Deliver legal compliance features (procuration PDF, RGPD data rights), fix concurrency and data integrity holes (transaction locks, proxy TOCTOU), harden the frontend (SSE cleanup, async errors, SSE fallback, pagination), and close quality gaps (PV immutability, ARIA labels). Plus UX interactivity overhaul: universal AgConfirm.ask(), per-page width strategy, AgToast fixes, SSE disconnect banner, wizard 2-column grid, unsaved changes warnings, animation timing contracts.

### Phases

- [x] **Phase 76: Procuration PDF** - Generate a downloadable PDF pouvoir for each recorded delegation (completed 2026-04-02)
- [x] **Phase 77: RGPD Compliance** - Member data export, admin data retention policy, and right-to-erasure deletion (completed 2026-04-02)
- [x] **Phase 78: Data Integrity Locks** - Transaction-level FOR UPDATE locks on ballot mutations and proxy chain validation inside the transaction (completed 2026-04-02)
- [x] **Phase 79: SSE & Async Robustness** - EventSource cleanup on navigation, async error capture in operator console, SSE fallback polling notification (completed 2026-04-02)
- [x] **Phase 80: Pagination & Quality** - List pagination (audit/meetings/members), PV immutable snapshot after validation, ARIA label completeness (completed 2026-04-02)
- [x] **Phase 81: UX Interactivity** - Universal AgConfirm.ask(), per-page width strategy, AgToast fixes, SSE disconnect banner, wizard 2-col grid, unsaved changes warnings (completed 2026-04-03)

</details>

---

## v10.0 Visual Identity Evolution

**Milestone Goal:** Evolve the complete visual identity — colors, component geometry, and codebase hardening — inspired by modern web best practices (Linear, Notion, Clerk, Stripe) while preserving the "officiel et confiance" spirit. Token-first, propagation-driven: change design-system.css, let all 25 page CSS files and 23 Web Components inherit automatically.

## Phases

- [x] **Phase 82: Token Foundation + Palette Shift** - Promote oklch semantic tokens, warm-neutral gray ramp, derived shade computation, dark mode co-update, visible palette change across all pages (completed 2026-04-03)
- [ ] **Phase 83: Component Geometry + Chrome Cleanup** - Consolidate radius to --radius-base, reduce shadows to 3 named levels, apply alpha-based borders, replace spinners with skeleton shimmer on dashboard and session list
- [ ] **Phase 84: Hardened Foundation** - Eliminate all hardcoded hex from page CSS, audit Shadow DOM fallback literals, sync critical-tokens inline styles, register animatable tokens, tokenize focus rings

## Phase Details

### Phase 82: Token Foundation + Palette Shift
**Goal**: Every page simultaneously looks warmer and more refined because all semantic color tokens reference oklch values, the gray ramp shifts toward warm-neutral, derived tints/shades are computed programmatically, and dark mode overrides are fully in sync
**Depends on**: Nothing (design-system.css @layer base only — no page file changes)
**Requirements**: COLOR-01, COLOR-02, COLOR-03, COLOR-04, COLOR-05
**Success Criteria** (what must be TRUE):
  1. Opening the dashboard in light mode shows warm-neutral gray surfaces (hue 200-210 range) rather than the current cool blue-gray — the visual difference is visible in a side-by-side screenshot
  2. Switching to dark mode on any page produces correctly-tinted dark surfaces; no token appears brighter or more saturated than its light-mode counterpart due to stale color-mix() evaluation
  3. Indigo accent color (--color-accent) appears only on interactive elements — CTA buttons, active nav item, focus rings, inline links — and is absent from decorative chrome, headings, and backgrounds
  4. Running `grep -r "color-mix(in srgb" public/assets/css/design-system.css` returns zero results; all blend operations use `color-mix(in oklch, ...)`
  5. The critical-tokens inline styles in all .htmx.html files reflect the updated semantic token values so no flash-of-wrong-color occurs on page load
**Plans**: 2 plans
Plans:
- [x] 82-01-PLAN.md — Light mode semantic token migration (hex/rgba to oklch primitives) + color-mix srgb-to-oklch upgrade
- [x] 82-02-PLAN.md — Dark mode warming + critical-tokens sync + visual checkpoint

### Phase 83: Component Geometry + Chrome Cleanup
**Goal**: All interactive components share a single border-radius language, elevation is expressed through exactly three named shadow levels, borders read as structural cues rather than solid edges, and the dashboard/session list feel instantaneous with skeleton shimmer
**Depends on**: Phase 82 (palette must be stable before geometry work, to avoid double visual QA)
**Requirements**: COMP-01, COMP-02, COMP-03, COMP-04
**Success Criteria** (what must be TRUE):
  1. Every button, input, card, modal, and dropdown visibly shares the same corner radius — adjusting the single `--radius-base` token changes all of them simultaneously with no per-component overrides needed
  2. The shadow scale has exactly three named levels visible in the rendered UI: sm (cards/panels — border-only or near-zero elevation), md (dropdowns/popovers), lg (modals/dialogs); no component uses a shadow outside this vocabulary
  3. Borders on cards and panels appear to "float" subtly on any background color — light or dark — because they use alpha-based color rather than a fixed hex; placing a card on a light gray vs. white surface still shows a visible but not harsh edge
  4. The dashboard KPI cards and session list show a shimmer animation while loading instead of a spinner; the shimmer respects `prefers-reduced-motion` (static placeholder when motion is reduced)
**Plans**: 2 plans
Plans:
- [ ] 83-01-PLAN.md — Radius consolidation + shadow scale reduction + alpha border token
- [ ] 83-02-PLAN.md — Dashboard skeleton shimmer loading for KPI cards and session list

### Phase 84: Hardened Foundation
**Goal**: The codebase has zero escape hatches — no hardcoded hex in any page CSS file, all Shadow DOM fallback literals reflect the current palette, critical-tokens inline blocks are in sync, color tokens can be animated via CSS transitions, and focus rings across all Web Components use the token reference pattern
**Depends on**: Phase 83 (hardening targets the palette and geometry values set in phases 82-83)
**Requirements**: HARD-01, HARD-02, HARD-03, HARD-04, HARD-05
**Success Criteria** (what must be TRUE):
  1. Running `grep -rn "#[0-9a-fA-F]\{3,6\}\|rgba(" public/assets/css/` (excluding design-system.css itself) returns zero results — every color in every per-page CSS file is expressed as `var(--token)`
  2. Running `grep -r "1650E0\|22,80,224\|rgba(22" public/assets/js/components/` returns zero results — all Shadow DOM component fallback hex literals match the current token values
  3. Toggling dark mode while the dashboard page is open produces no visible "flash" of incorrect color on any Web Component — Shadow DOM tokens are consistent with the page-level theme
  4. CSS `transition: color 150ms, background-color 150ms` applied to a button produces a smooth animated color change (not a hard cut) because the color tokens are registered via `@property` with the correct `<color>` syntax type
  5. Focusing any interactive element in any Web Component shows a 2px indigo outline that matches the page-level focus ring — no component displays the legacy `rgba(22,80,224,0.35)` hardcoded value
**Plans**: TBD

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 82. Token Foundation + Palette Shift | 2/2 | Complete    | 2026-04-03 |
| 83. Component Geometry + Chrome Cleanup | 1/2 | In Progress|  |
| 84. Hardened Foundation | 0/TBD | Not started | - |
