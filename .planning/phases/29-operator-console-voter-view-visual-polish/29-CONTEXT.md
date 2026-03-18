# Phase 29: Operator Console, Voter View & Visual Polish - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Final milestone phase. Three pillars: (1) Operator console live session UX — SSE indicator, delta vote counts, contextual guidance. (2) Voter full-screen ballot — optimistic feedback, waiting/confirmation states. (3) All-page visual polish — unified "officiel et confiance" design language across EVERY page, CSS @layer, design tokens, dark mode parity, measurable quality criteria.

**Key principle:** Define the visual style FIRST in design-system.css, THEN apply to all pages. Not page-by-page patches.

</domain>

<decisions>
## Implementation Decisions

### Visual Identity: "Officiel et Confiance"
- **Ambiance:** Like a quality government app — serious, trustworthy, inspires legal confidence
- **Primary palette:** Bleu/indigo dominant. The existing --color-primary (#1650E0) is the anchor.
- **Typography:** Keep Bricolage Grotesque (body) + Fraunces (display) + JetBrains Mono (data) — authoritative feel
- **NOT Notion-like** — the wizard style was too neutral/cold. AG-VOTE needs more color, more structure, more personality
- **Design system first:** Update design-system.css tokens and @layer BEFORE touching page CSS files
- **Coherence:** Every page must feel like the same app. One design language, not patches.

### All-Page Visual Polish Strategy
- **Scope:** ALL pages — operator, vote, postsession, archives, audit, analytics, meetings, members, users, settings, help, email-templates, admin, dashboard
- **CSS @layer:** Add `@layer base, components, v4` to design-system.css. New styles in `@layer v4`.
- **color-mix():** New token families use color-mix() for tints/shades
- **Dark mode:** Full parity — every new token gets a dark variant in the same commit
- **Animations:** Sober transitions (150-200ms) on state changes, hover smooth on buttons/cards. No wow-effects.
- **Measurable criteria:** transitions ≤ 200ms, CLS = 0, focus rings ≥ 3:1 contrast, zero inline style=""

### Operator Console
- **SSE indicator:** Status bar fixed at top of console — "● En direct" (green pulse) / "⚠ Reconnexion..." (amber) / "✕ Hors ligne" (red). Color + icon + label always.
- **Delta vote count:** "47 votes (+3 ▲)" — green badge appears next to total, fades after 10s of inactivity
- **Post-vote guidance:** After closing a vote, show "Vote clôturé — Ouvrez le prochain vote ou clôturez la séance" with two action buttons
- **End-of-agenda:** "Toutes les résolutions traitées — Clôturer la séance →"

### Voter Ballot Card
- **Full-screen:** Hide all navigation and chrome when a vote is open
- **Layout:** 3 stacked full-width cards (POUR / CONTRE / ABSTENTION), minimum 72px height each, 8px spacing
- **Feedback:** Instant selection visual (< 50ms, optimistic). Background server submission. Rollback on error with inline message.
- **Waiting state:** "En attente d'un vote" — single line, nothing else visible
- **Confirmation:** "Vote enregistré ✓" for 3 seconds, then back to waiting state
- **PDF consultation:** ag-pdf-viewer bottom sheet from Phase 25 (already wired)

### Results & Post-Session
- **Result cards:** Collapsed by default — "Résolution 3 — ✓ ADOPTÉ". Click expands: numbers, percentages, bar chart, threshold.
- **Bar chart:** POUR/CONTRE/ABSTENTION horizontal bars with percentages
- **Verdict:** ADOPTÉ/REJETÉ as the largest element in the expanded card
- **Post-session stepper:** Enhance existing 4-step stepper with checkmarks on completed steps + green color
- **Footer context:** "X votes exprimés · Y membres présents" on every result card

### Claude's Discretion
- Exact CSS values for the "officiel" token refresh (specific shadow depths, border radiuses, spacing values)
- Which pages need full CSS rewrites vs token-level updates
- @layer migration strategy details
- Bar chart implementation (CSS-only bars vs canvas)
- Anime.js vs pure CSS for count-up animations
- Order of page CSS updates

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Design system (the foundation to update FIRST)
- `public/assets/css/design-system.css` — 255 tokens, component styles, dark theme block, tour CSS
- `public/assets/css/app.css` — Global styles (.btn, .card, .row, .field, .app-shell, .sidebar)

### Operator console
- `public/assets/js/pages/operator-tabs.js` — Tab container, mode switching, OpS bridge
- `public/assets/js/pages/operator-exec.js` — KPI strip, quorum modal, vote toggle
- `public/assets/js/pages/operator-realtime.js` — SSE connection, polling, event handlers
- `public/assets/css/operator.css` — Current operator CSS
- `public/operator.htmx.html` — Operator HTML structure

### Voter view
- `public/assets/js/pages/vote.js` — Vote casting, SSE, motion display, PDF viewer wiring
- `public/assets/css/vote.css` — Current vote CSS (if exists)
- `public/vote.htmx.html` — Voter HTML structure

### Post-session & results
- `public/assets/js/pages/postsession.js` — 4-step stepper, results, validation, PV
- `public/assets/css/postsession.css` — Current postsession CSS
- `public/postsession.htmx.html` — Post-session HTML

### All other page CSS files (all need polish)
- `public/assets/css/archives.css`
- `public/assets/css/audit.css`
- `public/assets/css/analytics.css`
- `public/assets/css/meetings.css`
- `public/assets/css/members.css`
- `public/assets/css/users.css`
- `public/assets/css/settings.css`
- `public/assets/css/help.css`
- `public/assets/css/email-templates.css`
- `public/assets/css/admin.css`
- `public/assets/css/dashboard.css`

### Research
- `.planning/research/FEATURES.md` — Pattern 6 (Control Room), Pattern 7 (Single-Focus Voting), Pattern 8 (Trustworthy Results)
- `.planning/research/STACK.md` — CSS techniques (View Transitions, @starting-style, color-mix, @layer)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- ag-quorum-bar — Already has threshold tick, amber/green states
- ag-empty-state — Phase 26, for empty containers
- ag-pdf-viewer — Phase 25, bottom sheet mode for voter
- ag-tooltip — For disabled button explanations
- ag-toast — Success/error feedback
- operator-realtime.js — SSE is already wired, just needs UI indicator

### Established Patterns
- IIFE + var for page scripts, one CSS per page
- Web Components for shared UI
- Design-system.css tokens for all colors, spacing, typography
- Dark theme via [data-theme="dark"] selector block

### Integration Points
- design-system.css @layer migration affects ALL page CSS files
- operator-realtime.js SSE events → new UI indicators
- vote.js already has optimistic patterns → enhance with full-screen mode
- postsession.js already has 4-step stepper → add checkmarks

</code_context>

<specifics>
## Specific Ideas

- "Il faut définir le style qu'on veut pour toutes les pages et enfin solidifier le CSS" — design system first, then pages
- "Officiel et confiance" — like a quality government app, serious, trustworthy
- NOT Notion-like (too neutral/cold) — AG-VOTE needs personality, color, authority
- Dark mode parity is non-negotiable
- Sober transitions everywhere, no wow-effects

</specifics>

<deferred>
## Deferred Ideas

- Anime.js count-up KPIs (considered but user chose "transitions sobres" — may add in polish pass)
- Scroll-driven animations (@supports guard) — defer to v5+
- Custom PDF.js toolbar — defer to v5+

</deferred>

---

*Phase: 29-operator-console-voter-view-visual-polish*
*Context gathered: 2026-03-18*
