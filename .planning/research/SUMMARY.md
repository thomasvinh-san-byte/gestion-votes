# Project Research Summary

**Project:** AG-VOTE v4.0 "Clarity & Flow"
**Domain:** General assembly voting platform — guided UX overhaul, PDF resolution attachments, copropriete transformation, top-tier visual polish
**Researched:** 2026-03-18
**Confidence:** HIGH (all four research areas verified against official docs, direct codebase inspection, or multiple authoritative sources)

---

## Executive Summary

AG-VOTE v4.0 is a UX maturity milestone, not a feature-addition milestone. v3.0 shipped a fully wired session lifecycle with SSE, a live operator console, and a complete vote flow. v4.0's goal is to make that lifecycle self-explanatory: operators should be able to run a complete general assembly without reading a manual, voters should feel trust and clarity on their phone, and the visual quality should be indistinguishable from a funded SaaS product. The research across all four files converges on four concrete work streams: (1) a guided UX layer built on Driver.js tours, contextual ag-hint components, and status-aware empty states; (2) a PDF attachment pipeline from FilePond upload through an authenticated PHP serve endpoint to a native browser PDF viewer; (3) a vocabulary cleanup removing copropriete-specific language across 12 files and roughly 25 lines, without touching the generic voting_power mechanics; and (4) CSS polish using modern baseline APIs — View Transitions, @starting-style, container queries, color-mix() — layered into the existing design system without breaking the 29 existing page CSS files.

The recommended approach is strictly incremental and additive. The existing stack (PHP 8.4, PostgreSQL, Redis, vanilla JS IIFE, Web Components, no build step) is not changing. New UI libraries arrive via CDN script tags on the pages that need them, not globally. New CSS tokens and components are added under a @layer v4 block that cannot regress existing specificity. The copropriete rename is a string-replacement pass, not an architecture change. The biggest complexity is the PDF pipeline: the serve endpoint (currently missing) must be built before any viewer UI, storage must migrate from the ephemeral /tmp path to a configurable persistent volume, and PDF.js must be pinned to >= 4.2.67 to close the active CVE-2024-4367 arbitrary-JS-execution vulnerability.

The dominant risk in v4.0 is not technical — it is scope creep under "top 1% UI" language. Research confirms this was the source of the v3.0 sub-phase explosion (phases 20.1 through 20.4). The mitigation is to define measurable done criteria before the first phase opens: interaction transitions under 200ms, CLS = 0, focus rings at 3:1 contrast minimum, dark mode parity in every commit, zero inline style attributes in production HTML. Secondary risks are the missing PDF serve endpoint (a confirmed blocker), the three unwired tour trigger buttons visible in the current UI that do nothing (a confirmed UX defect), and the risk of conflating "remove copropriete terminology" with "remove voting_power logic" (which would silently break all weighted vote sessions).

---

## Key Findings

### Recommended Stack

The existing stack is preserved entirely. No new Composer packages, no build tooling, no framework. New libraries arrive as CDN script tags loaded only on the pages that need them. Four additions are confirmed:

**New libraries:**

- **Driver.js v1.4.0** — guided step tours — MIT license (decisive advantage over Shepherd.js AGPL/commercial and Intro.js AGPL). 20.8 KB minified IIFE. Zero dependencies. Load via CDN only on wizard, hub, operator console.
- **FilePond v4.32.12** — drag-and-drop PDF upload — MIT, 21 KB gzipped, vanilla JS native. Two plugins: file-validate-type (PDF only) and file-validate-size (10 MB max). Load on wizard resolution step only.
- **Anime.js v4.3.6** — complex animations (KPI count-up, staggered list entry, vote pulse) — MIT, 10 KB gzipped UMD. Load on pages with animated numeric data. Pure CSS handles all button and input micro-interactions.
- **PDF.js v5.5.207 (self-hosted prebuilt viewer)** — inline PDF rendering — Mozilla open source. Self-host the prebuilt viewer in public/assets/pdfjs/; embed as an iframe on vote, wizard, and hub pages only.

**CSS-only additions (no library):**

- View Transitions API (same-document): tab switching, wizard step transitions, modal open/close. Progressive enhancement: instant fallback for browsers without support.
- @starting-style: entry animations (modal slide-up, toast appear) without the setTimeout(0) hack. 86% global support; graceful degradation.
- CSS :has(): parent-state styling without JS class toggling. 92%+ support; baseline.
- Container queries: per-component responsive layout for the 20 existing Web Components. 93%+ support; baseline.
- color-mix() in oklch: dynamic token derivation for tints and shades. Replaces any future SCSS dependency. 92%+ support; baseline.
- CSS @layer: specificity management. New v4.0 component styles in @layer v4 so they cannot clobber existing page CSS.
- CSS native nesting: component-scoped styles without a preprocessor.

**No new Composer dependencies.** Dompdf is already installed for PV generation. PDF upload and serving use vanilla PHP.

**php.ini change needed:** `upload_max_filesize = 10M`, `post_max_size = 12M`.

### Expected Features

Derived from FEATURES.md competitor analysis (Loomio, Decidim, Slido, ElectionBuddy, OpaVote, BoardEffect, Mentimeter) and from UX pattern research (Stripe wizard, Notion disclosure, Linear status dashboard, Cloudscape empty states).

**Must have (table stakes for v4.0):**

- Status-aware session cards on dashboard — each card shows the ONE next action for its current lifecycle state (draft: "Completer la configuration", live: "Rejoindre la console", closed: "Generer le PV")
- Contextual empty states with anatomy: heading + description + secondary action button, on every container that can be empty
- Disabled button explanations — tooltip or inline note explaining WHY a button is locked ("Figer la seance — disponible apres ajout des resolutions")
- Named-step wizard with autosave — "Informations -> Membres -> Resolutions -> Revision", back navigation never loses data, step 4 is a full review card before commit
- Post-session stepper with completion states — "Resultats -> Validation -> PV -> Archivage", each step shows a checkmark when done
- Live SSE connectivity indicator — "En direct" / "Reconnexion..." / "Hors ligne" with colour + icon + label (never colour alone, WCAG AA)
- Quorum progress bar with threshold marker — animated fill, threshold tick at the correct position on the bar, colour change from amber to green when quorum is reached
- Mobile full-screen voter view — hide all navigation and chrome when a vote is open; show only the vote card
- Optimistic vote feedback under 50ms — instant button selection, background server submission, rollback on error with inline message
- Trustworthy result cards — absolute numbers + percentages + threshold met/not met + total votes cast, for both post-session and room display
- Inline contextual help — field descriptions under labels, (?) tooltip popovers for technical terms (majorite absolue, etc.), no modal dialogs for explanations

**Should have (differentiators that set AG-VOTE apart):**

- PDF resolution attachments — FilePond upload in wizard step 3, authenticated PHP serve endpoint, native browser viewer embedded in wizard/hub/voter view
- PDF bottom sheet on voter view — slide-up panel keeps voter in context without navigating away; document is read-only (no download link)
- Motion template library — 3 common templates in wizard step 3 (Approbation de comptes, Election au conseil, Modification de reglement) to reduce blank-slate anxiety
- ag-guide Web Component — step tours using Driver.js, triggered from the existing stub buttons in wizard, postsession, and members pages; dismissal persisted in localStorage
- ag-hint Web Component — persistent but dismissible inline callout for single-element contextual help; no tour library dependency
- ag-empty-state Web Component — slot-based, replaces the emptyState() helper function gradually across all pages
- Role-aware dashboard — operator view shows session cards with next-action CTAs; admin view shows stats and user management
- Copropriete vocabulary cleanup — rename 12 files, 25 lines; remove lot field from wizard member input; preserve voting_power logic and tantieme CSV import alias unchanged
- CSS design system evolution — @layer architecture, color-mix() derived tokens, 3-tier token hierarchy (primitive / semantic / derived)

**Defer (v5+):**

- AI-assisted PV minutes generation — significant new backend capability
- Virus scanning of uploaded PDFs via ClamAV — valuable but not a v4.0 blocker
- Motion templates stored per-tenant in the database — v4.0 ships hardcoded templates
- Multi-page cross-session guided tours — complex state management; per-page tours are sufficient for v4.0
- Word cloud or alternative result formats — explicitly identified as anti-patterns for legal AG context
- Polling-based SSE fallback additions — SSE with reconnect is the correct pattern

**Anti-features (never build):**

- Help modal or tour overlay interrupting workflow — inline contextual help is the correct pattern
- Voter results visible during an open vote — breaks secret ballot principle
- Status via colour alone — every status indicator must have colour + icon + label for WCAG compliance
- "Are you sure?" modal chains — reserve modals for destructive actions only (delete session)
- Fun result formats (word clouds, rankings) — undermine the legal gravity of a general assembly

### Architecture Approach

v4.0 adds four integration points to the existing architecture, all following patterns already present in the codebase. The approach is zero-disruption to the 29 existing page modules. Existing page JS files require no changes to support the new guided UX layer.

**PDF pipeline (new):**

- `resolution_documents` DB table — motion-level attachment, separate from the existing `meeting_attachments` (meeting-level). Schema columns: tenant_id, meeting_id, motion_id, stored_name (UUID-based), mime_type, file_size, uploaded_by.
- `ResolutionDocumentController` + `ResolutionDocumentRepository` — identical pattern to `MeetingAttachmentController`; handles upload, list, delete, and the critical missing serve action.
- Secure serve endpoint: `GET /api/v1/resolution_document_serve` — validates auth + tenant + meeting membership, then readfile() with `Content-Type: application/pdf`, `Content-Disposition: inline`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`.
- Storage migration: move from ephemeral `/tmp/ag-vote/` to configurable `AGVOTE_UPLOAD_DIR` environment variable (default `/var/agvote/uploads/`). Add Docker volume mount. Update `MeetingAttachmentController` to read the env var.

**Guided UX system (new):**

- `ag-guide` Web Component — reads `window.AG_GUIDE_STEPS` JSON config injected per-page via a `<script>` block. Wraps Driver.js. Storage key in localStorage for dismissal. No changes needed to existing page JS modules.
- `ag-hint` Web Component — persistent inline callout, dismissible via localStorage. No Driver.js dependency for single-element hints.
- `ag-empty-state` Web Component — slot-based, replaces `emptyState()` helper gradually. Backward compatible with existing emptyState() calls.
- The existing design-system.css already contains complete tour overlay CSS (lines 3741-3940) and z-index tokens (--z-tour-overlay: 9990, --z-tour-spotlight: 9991, --z-tour-bubble: 9992). The JS driver is the only missing piece.

**ag-pdf-viewer Web Component (new):**

- Wraps a native browser iframe. Supports `mode="inline"` (desktop, wizard, hub) and `mode="sheet"` (bottom slide-up for mobile voter view). No PDF.js programmatic API dependency — the browser's native renderer handles the file. PDF.js prebuilt viewer handles edge cases.
- Integration points: wizard.htmx.html (upload + preview), hub.htmx.html (doc status + preview), vote.htmx.html (document consultation bottom sheet), operator.htmx.html (motion detail panel).

**Copropriete transformation (non-architectural):**

- 12 files, ~25 lines changed. Pure vocabulary replacement. Zero backend logic changes. The voting_power column, BallotsService weight calculations, and tantieme CSV alias in ImportService.php are all preserved. The only truly copro-specific item removed is the `lot` field in wizard.js (4 lines) and its CSS class.

**Design system evolution:**

- Add `@layer base, components, v4` declaration to design-system.css. New component styles in `@layer v4` cannot conflict with existing unlayered page CSS.
- Add ~15 new tokens for PDF viewer, guided hints, bottom sheet, and PC-first layout. Existing 255 tokens unchanged.
- Incremental color-mix() adoption for new token families only. Existing hex-value tokens stay until a future migration milestone.

**Component dependency order (strict):**

1. DB migration: resolution_documents table
2. ResolutionDocumentRepository + Controller + routes + storage path env fix
3. ag-pdf-viewer Web Component (depends on serve endpoint)
4. Wire PDF upload to wizard; PDF viewer to vote page
5. ag-hint, ag-empty-state (independent, no backend dependency)
6. ag-guide Web Component (benefits from stable page targets)
7. Copropriete rename pass (independent, can run parallel with 4-6)
8. Design system @layer + CSS polish (should precede component styling)

### Critical Pitfalls

**Top 5 sequenced by priority:**

1. **PDF.js CVE-2024-4367 — arbitrary JavaScript execution via malicious PDF (CRITICAL, P0)** — A crafted PDF triggers JS execution in the viewer's browser session via a missing type check in PDF.js < 4.2.67, enabling session token theft and vote manipulation. Prevention: pin pdfjs-dist >= 4.2.67 (current stable: 5.5.207); set `isEvalSupported: false` in getDocument() options; add `sandbox="allow-scripts allow-same-origin"` to any PDF iframe; enforce CSP `script-src 'self'` on viewer pages. Cannot be deferred past the phase that introduces the PDF viewer.

2. **Missing PDF serve endpoint — uploaded PDFs inaccessible to voters (CRITICAL, P0)** — MeetingAttachmentController stores uploaded PDFs at /tmp/ag-vote/ but no PHP endpoint reads them back to authenticated clients. Confirmed by codebase inspection: no readfile() in public/api/v1/. Building any viewer UI before this endpoint exists is wasted work. The dangerous workaround (storing PDFs in public/) creates unauthenticated direct-URL access. Build the serve endpoint first, before any viewer UI work begins.

3. **"Top 1% UI" scope creep — no objective done criteria (HIGH, P1)** — "Top 1% UI" is aspirational language, not a specification. Without measurable criteria, phases never close. This was the cause of the v3.0 sub-phase explosion (phases 20.1 through 20.4). Define criteria before the first v4.0 phase opens: transitions <= 200ms (DevTools Performance), CLS = 0 (Lighthouse), focus rings at 3:1 contrast (axe-core), dark mode parity in the same commit as any new token, zero inline style="" in production HTML. Cap polish work per phase; log remaining items as a future phase.

4. **Unwired tour trigger buttons — visible UX defects shipping today (HIGH, P1)** — Three pages have tour buttons with zero JS wiring: wizard.htmx.html (`#btnTour`), postsession.htmx.html (`#btnTour`), members.htmx.html (onboarding markup). Clicking them does nothing. v4.0 must either implement the tours with Driver.js or remove the buttons before shipping. A third valid option is replacing them with ag-hint contextual popovers for less linear flows. Any button not implemented must be removed.

5. **Copropriete over-deletion risk — voting_power logic removal breaks weighted votes (HIGH, P1)** — The voting_power column is referenced in 14+ backend locations (BallotsService, AttendancesService, BallotRepository, MemberRepository, ExportService, etc.). Removing it silently makes all weighted vote sessions tally 1:1. Scope for copropriete transformation is strictly: rename UI labels and remove the lot field from wizard only. Write a PHPUnit test asserting weighted tally correctness before the rename phase begins, and run it after to confirm no regression.

---

## Implications for Roadmap

Based on combined research, the dependency graph and risk profile suggest 5 phases for v4.0:

### Phase 1: PDF Infrastructure Foundation

**Rationale:** Both CRITICAL P0 blockers (missing serve endpoint, PDF.js CVE) must be closed before any viewer UI work begins. Storage path hardcoding (MEDIUM P2, confirmed: controller hardcodes /tmp/ag-vote) is in the same files and should be fixed in the same phase to avoid a second touch. This phase has no visual deliverable — it is pure backend and library setup. It is the only phase where a mistake (e.g., storing PDFs in public/) would create a security vulnerability rather than a visual defect.

**Delivers:**
- `AGVOTE_UPLOAD_DIR` env var read by all upload controllers (no more hardcoded /tmp/ag-vote)
- Docker volume mount for persistent PDF storage
- `resolution_documents` DB table and migration
- `ResolutionDocumentController` + `ResolutionDocumentRepository` (upload, list, delete, serve)
- Secure serve endpoint with correct security headers and tenant isolation
- PDF.js v5.5.207 self-hosted prebuilt viewer in public/assets/pdfjs/
- FilePond v4.32.12 integrated into wizard step 3 (resolution attachment)
- ag-pdf-viewer Web Component (inline + bottom sheet modes)
- PDF viewer wired to wizard, hub, and voter view

**Uses from STACK.md:** FilePond v4.32.12, PDF.js v5.5.207 self-hosted prebuilt viewer

**Avoids from PITFALLS.md:** CVE-2024-4367 (pinned version, isEvalSupported: false), missing serve endpoint, storage path hardcoding, missing tenant isolation on serve endpoint, content-type sniffing via X-Content-Type-Options

**Research flag:** Standard patterns (PHP file serving follows existing MeetingAttachmentController exactly). No additional research phase needed.

---

### Phase 2: Guided UX Components

**Rationale:** The three unwired tour buttons are visible UX defects that must be resolved before any screen-level polish work. Building ag-guide, ag-hint, and ag-empty-state as standalone components before wiring them to pages keeps the implementation focused and testable. Driver.js integration is MIT-licensed and fits the IIFE pattern exactly; the pattern is fully documented in STACK.md with working code samples. The existing design-system.css already has all the CSS infrastructure; only the JS driver is missing.

**Delivers:**
- ag-guide Web Component wrapping Driver.js v1.4.0
- Tour steps configured for wizard, postsession, and members pages (the three stub locations)
- ag-hint Web Component for single-element contextual inline help
- ag-empty-state Web Component replacing the emptyState() helper on all major pages
- localStorage dismissal for all guided elements (guides, hints)
- Status-aware session cards on dashboard (next-action CTA per lifecycle state)
- Contextual empty states on every empty container (anatomy: heading + description + secondary action)
- Disabled button explanations on hub, wizard, and operator console

**Uses from STACK.md:** Driver.js v1.4.0 (MIT, 20.8 KB IIFE), existing ag-popover for single hints

**Avoids from PITFALLS.md:** Unwired tour buttons shipping as visible defects, Shepherd.js AGPL license trap, Driver.js loaded globally instead of per-page

**Research flag:** Standard patterns. No additional research phase needed. ag-guide spotlight clip-path positioning across layout states (sidebar pinned vs. collapsed, sticky headers, scrolled views) needs manual testing but not additional research.

---

### Phase 3: Copropriete Transformation

**Rationale:** Lowest-risk and most self-contained work stream. 12 files, 25 lines, zero backend logic. Can run in parallel with Phase 2 if two developers are available, or immediately after. Keeping it as its own phase ensures the PHPUnit regression test for weighted vote tallying is written before any deletion begins, and the settings distribution key modal is confirmed as a stub (no API endpoint) before removal.

**Delivers:**
- UI label rename across all 12 identified files (shell.js sidebar, settings.js modal option, settings.htmx.html, admin.htmx.html, help.htmx.html, index.html)
- lot field removed from wizard.js member input (4 lines) and wizard.css (.member-lot class)
- openKeyModal / "Cle de repartition" stub removed from settings.js (confirmed: no API endpoint backs it)
- AggregateReportRepository comment updated
- ImportService.php tantieme CSV aliases preserved for backward compatibility
- PHPUnit test for weighted vote tally correctness (before and after)

**Avoids from PITFALLS.md:** voting_power logic deletion, tantieme CSV alias removal breaking imports for existing installations, settings modal deletion without endpoint verification

**Research flag:** No research needed. ARCHITECTURE.md provides the exact file list and line numbers. Codebase is the source of truth.

---

### Phase 4: Wizard and Session Hub UX Overhaul

**Rationale:** The wizard and session hub are the highest-frequency operator screens and the entry point to the session lifecycle. They have the most surface area for guided UX patterns (named-step wizard, progress indicators, pre-meeting checklist, blocked-action explanations, motion template picker). This phase depends on Phase 2 (ag-guide, ag-hint, ag-empty-state are available) and Phase 1 (PDF attachment is available in the wizard). The hub's quorum progress bar and pre-meeting checklist are the centrepieces of the self-explanatory goal.

**Delivers:**
- Named-step wizard (Informations -> Membres -> Resolutions -> Revision) with horizontal stepper UI
- Autosave on field blur for all wizard steps; back navigation preserves data
- Step 4 full review card before commit
- Motion template picker in wizard step 3 (3 hardcoded templates)
- Progressive disclosure in wizard step 2 ("Parametres de vote avances" toggle via CollapsibleSection)
- Session hub pre-meeting checklist with blocked-reason display and estimated time per item
- Quorum progress bar with animated fill and threshold tick marker
- Hub document status indicators per motion ("Document joint" vs "Aucun document")
- ag-guide tour for wizard and hub pages

**Uses from STACK.md:** Anime.js v4.3.6 (quorum bar animation), View Transitions API (wizard step transitions), @starting-style (modal entry), CSS :has() (wizard step state styling)

**Avoids from PITFALLS.md:** Feature parity gaps (generate feature inventory from v3.0 before redesigning each screen), WCAG regression (axe-core on each page before phase closes), dark mode token regression (two-token rule enforced in every commit)

**Research flag:** May benefit from a brief (1-2 hour) research task specifically for quorum progress bar threshold tick implementation. The FEATURES.md notes this is an AG-specific concept with no off-the-shelf reference — confirm the CSS/SVG approach before committing to implementation.

---

### Phase 5: Operator Console, Voter View, and Visual Polish

**Rationale:** The operator console and voter view are the highest-pressure UX contexts in the system. They come last because they depend on the complete guided UX component library (Phase 2) and the complete PDF pipeline (Phase 1). The voter view's mobile-first constraint is an explicit risk when PC-first design work is in progress — the mobile-viewport.spec.js test suite must run after every shared component change. The final visual polish (CSS micro-interactions, count-up animations, scroll-driven animations, design system @layer migration) is completed in this phase to avoid it becoming a separate open-ended polish spiral.

**Delivers:**
- Operator console layout: status bar (quorum + SSE connectivity indicator), left panel (attendance list), main panel (active motion + live vote counts with delta indicators)
- Live vote count delta indicators ("+3 votes in last 30s" pattern) via Anime.js count-up
- Voter view full-screen single-focus ballot card (all chrome hidden when a vote is open)
- Voter view optimistic feedback: instant selection visual, background submission, rollback on server error with inline message
- Voter view PDF bottom sheet (ag-pdf-viewer mode="sheet")
- Post-session stepper (Resultats -> Validation -> PV -> Archivage) with completion checkmarks per step
- Trustworthy result cards with bar charts, absolute numbers, percentages, threshold outcome
- CSS design system @layer declaration + color-mix() derived tokens for all new components
- Anime.js KPI count-up on dashboard, staggered motion card list entry
- Scroll-driven animations with @supports guard (motion card fade-in on scroll)
- PC-first layout validation (1024px+ default); mobile voter screen verified at 375px

**Uses from STACK.md:** Anime.js v4.3.6, View Transitions API (tab switching), @starting-style, scroll-driven animations, color-mix(), CSS @layer, container queries for Web Components

**Avoids from PITFALLS.md:** PC-first changes breaking voter screen (run mobile-viewport.spec.js after every shared component touch), CSS animation GPU layer overuse (no will-change in static CSS), "top 1% UI" scope creep (objective done criteria enforced from start), WCAG regression (axe-core per page), dark mode token regression (two-token rule enforced)

**Research flag:** No additional research needed. All CSS techniques are verified baseline or baseline-newly-available with confirmed browser support matrices in STACK.md and working code samples.

---

### Phase Ordering Rationale

- **Phase 1 first** because both CRITICAL P0 blockers live in the infrastructure layer, and all viewer UI work is blocked on the serve endpoint existing. Building UI before the serve endpoint creates throwaway work.
- **Phase 2 second** because the unwired tour buttons are visible defects shipping in the current UI, and the guided UX components must exist before any screen redesign can use them.
- **Phase 3 third (or parallel with Phase 2)** because the copropriete vocabulary pass is fully independent and can proceed without waiting for Phase 2. If one developer is available, run sequentially after Phase 2. If two are available, run in parallel.
- **Phase 4 before Phase 5** because the wizard is the entry point to the session lifecycle — operators encounter it before the console. A working wizard with the full guided experience validates the component library before the higher-pressure live session screens are redesigned.
- **Phase 5 last** to avoid premature polish. Visual refinement of the console and voter view is more efficient once the component library is stable and the wizard has validated the design patterns in production.

### Research Flags

Phases likely needing additional research during planning:
- **Phase 4 (Wizard/Hub):** Quorum progress bar threshold tick and wizard autosave-on-blur are AG-specific patterns with no direct off-the-shelf reference. A brief (1-2 hour) research task is recommended before committing to the implementation approach. Everything else in Phase 4 follows well-documented wizard and checklist patterns.

Phases with well-documented patterns (skip research-phase):
- **Phase 1 (PDF Infrastructure):** Direct codebase analogue (MeetingAttachmentController) and well-documented PHP file serving patterns. ARCHITECTURE.md provides the exact schema and endpoint design.
- **Phase 2 (Guided UX Components):** Driver.js integration pattern fully documented in STACK.md with working IIFE code samples. ag-guide architecture documented in ARCHITECTURE.md.
- **Phase 3 (Copropriete):** ARCHITECTURE.md contains the exact file list, line numbers, and action for each location. No ambiguity.
- **Phase 5 (Console/Voter/Polish):** All CSS techniques have verified browser support and code examples in STACK.md. Anime.js UMD integration is documented with working code samples.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All library choices verified against official docs, CDN file listings, and license sources. Bundle sizes confirmed from jsDelivr. Browser support percentages from caniuse.com and web.dev baseline announcements. GSAP, Shepherd.js, Intro.js, Motion One explicitly rejected with documented rationale. |
| Features | HIGH | Competitor analysis covers 7 platforms with sourced reviews. UX patterns sourced from PatternFly, Nielsen Norman Group, Cloudscape, Smashing Magazine 2025. Screen-by-screen specs derived from cross-platform synthesis with AG-specific adaptations. |
| Architecture | HIGH | Based on direct codebase inspection, not training-data assumptions. File paths, line numbers, and existing controller patterns confirmed from source. Schema design follows verified existing migration patterns. 10 copro references across 9 files confirmed by grep. |
| Pitfalls | HIGH | Critical P0 pitfalls verified against CVE databases and direct codebase grep. Confirmed gaps (missing serve endpoint, hardcoded /tmp path, unwired tour buttons, STORAGE_PATH env var ignored) verified by inspection. Medium-severity risks derived from established rendering and performance research. |

**Overall confidence: HIGH**

### Gaps to Address

- **PDF.js prebuilt viewer vs. programmatic API:** ARCHITECTURE.md recommends the native browser iframe approach (no PDF.js JS dependency beyond the prebuilt viewer); STACK.md documents both approaches. Recommendation: start with native iframe (`ag-pdf-viewer` wrapping `<iframe>`), defer custom toolbar to v5+. This avoids the 220 KB gzipped PDF.js worker on pages where it is not needed.

- **Exact pdfjs-dist v5.x gzipped bundle size:** PITFALLS.md notes this as unconfirmed. The pattern recommendation (lazy-load, page-specific only) is correct regardless of exact size. Measure with Lighthouse TBT baseline before and after adding the library to confirm no regression on voter view.

- **Motion template content:** FEATURES.md specifies 3 templates but the exact field values (title, description, vote type defaults) need product decisions before Phase 4 begins. These can be hardcoded for v4.0 and moved to per-tenant DB storage in v5+.

- **Driver.js cross-page tour state for wizard:** STACK.md documents the localStorage + URL param approach for multi-page tours. The wizard tour spans multiple steps. Phase 2 planning should confirm whether the wizard uses full page navigation or HTMX partial replacement between steps, as the cross-page tour strategy differs between the two approaches.

---

## Sources

### Primary (HIGH confidence — direct codebase inspection)

- `app/Controller/MeetingAttachmentController.php` — upload handler, 10 MB limit, finfo MIME check, /tmp hardcode confirmed
- `public/api/v1/meeting_attachments.php` — GET/POST/DELETE only, no serve/readfile endpoint confirmed
- `public/assets/css/design-system.css` — 255 CSS custom properties inventory; tour overlay CSS lines 3741-3940; z-index tokens; dark theme block at line 310; prefers-reduced-motion at line 2491
- `public/assets/js/components/` — 20 Web Components enumerated
- `public/assets/js/core/shared.js` — emptyState() helper pattern
- `wizard.htmx.html`, `postsession.htmx.html`, `members.htmx.html` — unwired tour stub buttons confirmed by grep returning no JS wiring
- `app/Services/ImportService.php` line 237 — tantieme is a CSV column alias, not a separate feature
- `public/assets/js/pages/settings.js` lines 407-419 — openKeyModal is a stub with no API call
- `.env` + `docker-compose.yml` — STORAGE_PATH env var defined, volume mounted, PHP ignores it (hardcodes /tmp/ag-vote)
- `database/migrations/20260219_meeting_attachments.sql` — schema pattern for resolution_documents

### Primary (HIGH confidence — official external documentation)

- [Driver.js official docs + jsDelivr CDN listing](https://driverjs.com) — v1.4.0, 20.8 KB IIFE, MIT confirmed
- [PDF.js Getting Started — Mozilla](https://mozilla.github.io/pdf.js/getting_started/) — prebuilt viewer approach, v5.5.207
- [FilePond GitHub + jsDelivr](https://github.com/pqina/filepond) — v4.32.12, MIT, 115.5 KB minified / 21 KB gzipped confirmed
- [Anime.js v4 Documentation](https://animejs.com/documentation/getting-started/installation/) — v4.3.6, UMD bundle, 10 KB gzipped
- [CVE-2024-4367 — Codean Labs](https://codeanlabs.com/blog/research/cve-2024-4367-arbitrary-js-execution-in-pdf-js/) — arbitrary JS execution in PDF.js < 4.2.67
- [GitHub Advisory GHSA-wgrm-67xf-hhpq](https://github.com/advisories/GHSA-wgrm-67xf-hhpq) — CVE-2024-4367 patch threshold >= 4.2.67
- [MDN — View Transition API](https://developer.mozilla.org/en-US/docs/Web/API/View_Transition_API) — browser support matrix
- [web.dev — @starting-style Baseline Entry Animations](https://web.dev/blog/baseline-entry-animations) — Chrome 117+, Firefox 129+, Safari 17.5+
- [MDN — @layer](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@layer) — Chrome 99+, Firefox 97+, Safari 15.4+
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html) — MIME + extension check + magic bytes pattern
- [PatternFly wizard design guidelines](https://www.patternfly.org/components/wizard/design-guidelines/) — named step wizard pattern
- [Cloudscape empty state patterns](https://cloudscape.design/patterns/general/empty-states/) — empty state anatomy (heading + description + secondary action)

### Secondary (MEDIUM confidence — community consensus)

- [Inline Manual: Driver.js vs Intro.js vs Shepherd.js comparison](https://inlinemanual.com/blog/driverjs-vs-introjs-vs-shepherdjs-vs-reactour/) — license comparison verified
- [ElectionBuddy Capterra reviews 2025](https://www.capterra.com/p/235336/ElectionBuddy/reviews/) — 92 verified reviews confirming simplicity rating
- [Smashing Magazine: UX strategies for real-time dashboards 2025](https://smashingmagazine.com/2025/09/ux-strategies-real-time-dashboards/) — operator console design rules
- [Smashing Magazine: GPU Animation](https://www.smashingmagazine.com/2016/12/gpu-animation-doing-it-right/) — will-change overuse causes GPU layer explosion
- [Nielsen Norman Group — Progressive disclosure](https://www.nngroup.com/articles/progressive-disclosure/) — canonical reference
- [Centre for Civic Design — voting system design](https://civicdesign.org/topics/roadmap/) — trustworthy result presentation
- [vCast assemblee generale digitale guide 2025](https://www.vcast.vote/assemblee-generale-digitale-guide-2025/) — AG-specific context validation

---

*Research completed: 2026-03-18*
*Ready for roadmap: yes*
