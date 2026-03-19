# Phase 35: Entry Points - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete visual redesign of Dashboard and Login pages — the two entry points every user sees first. These pages set the visual quality bar for the entire v4.2 milestone. The redesign covers composition, typography, whitespace, visual hierarchy, micro-interactions, and contextual tooltips. Must achieve top 1% design quality — visually indistinguishable from Stripe/Linear/Clerk level.

</domain>

<decisions>
## Implementation Decisions

### Design Philosophy
- **Reference-driven:** Stripe Dashboard (depth + density), Linear (neutral canvas + data focus), Clerk (auth page polish)
- **Top 1% means:** No generic card grids, no uniform spacing, no AI-generated feel. Every visual choice is intentional and differentiated
- **Before/after contrast:** Changes must be visible immediately in the browser — no subtle token swaps
- **Tooltips for guidance:** Complex elements get hover tooltips explaining their purpose — no guided tours

### Dashboard Visual Redesign (CORE-01)
- **KPI strip:** 4 KPI cards with clear visual hierarchy — primary metric large, secondary small, trend indicator (up/down arrow), semantic color for status. Cards differentiated by content, not just icon color
- **Session list:** Vertical card list with clear status badges, date prominence, action buttons visible on hover. Each session card communicates its lifecycle state instantly
- **Aside/quick actions:** 280px sticky sidebar with actionable shortcuts — not a dumping ground for links. Each action has an icon + label + tooltip
- **Visual hierarchy:** Page title (Fraunces h1) → KPI strip → session list → aside. Eye flow is top-left to bottom-right, Z-pattern
- **Whitespace:** Generous padding between sections (--space-section = 48px), tighter within cards (--space-card = 24px). The page should breathe
- **Typography:** KPI numbers in JetBrains Mono (scannable), session titles in Bricolage Grotesque semibold, metadata in regular weight at smaller size
- **Color for signal:** Success/warning/danger only for status communication, never decorative. Neutral stone palette for chrome
- **Hover states:** KPI cards lift subtly on hover, session cards show action buttons on hover, sidebar items highlight with bg change
- **Empty states:** When no sessions exist, show a clear CTA with illustration/icon — not just "Aucune session"
- **Responsive:** At 1024px aside stacks below, at 768px KPI grid goes 2-col

### Login Page Visual Redesign (SEC-01)
- **Centered card:** Single card, max-width 400px, vertically centered with subtle shadow (shadow-lg)
- **Branding:** AG-VOTE logo/wordmark above the card, Fraunces display font for the product name
- **Form fields:** Full-width inputs with proper label placement (above field, 14px semibold), generous vertical spacing between fields
- **CTA button:** Full-width primary button, 44px height, prominent gradient — the most visually dominant element on the page
- **Background:** Subtle warm gradient or the three-depth model applied — not flat white
- **Trust signals:** "Plateforme sécurisée" or similar subtle indicator below the form
- **Error states:** Clear red border + message below field, not just a toast
- **Dark mode:** Equally polished — card on dark surface, inputs with raised background
- **Micro-interactions:** Focus ring animation on field entry, button hover gradient shift, subtle form card entrance animation on page load

### UX Requirements (UX-01, UX-02)
- Every KPI card tooltip explains what the metric means ("Sessions this month — total active and completed")
- Dashboard action buttons have descriptive tooltips ("Créer une nouvelle session")
- Login field labels are self-explanatory — no tooltip needed for standard auth fields
- Every interactive element has a clear hover/focus state — nothing feels dead
- Top 1% quality: composition and spacing feel intentional, not programmatic. Visual variety across components. No "everything is the same card" syndrome

### Claude's Discretion
- Exact gradient values for login background
- Whether KPI cards use icons or just numbers/labels
- Session card layout details (horizontal vs vertical metadata)
- Exact tooltip positioning (top/bottom/right)
- Animation durations and easing for micro-interactions
- Whether to add a subtle pattern or texture to login background

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Page files
- `public/dashboard.htmx.html` — Dashboard HTML structure
- `public/login.html` — Login page HTML
- `public/assets/css/pages.css` — Dashboard styles (lines 1006+)
- `public/assets/css/login.css` — Login styles
- `public/assets/css/design-system.css` — Token system, component specs, shell layout

### Design system context
- `public/assets/css/design-system.css` — All tokens, component classes, three-depth background model
- `public/assets/js/components/ag-badge.js` — Badge component for session status
- `public/assets/js/components/ag-toast.js` — Toast component

### Requirements
- `.planning/REQUIREMENTS.md` — UX-01, UX-02, CORE-01, SEC-01

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- Design token system (Phase 30) — all primitive/semantic/component tokens available
- Component specs (Phase 31) — buttons, cards, tables, badges, toasts all refreshed
- Three-depth background model — bg/surface/raised tokens established
- `.dashboard-content` wrapper with max-width 1200px (Phase 32)
- `.kpi-grid` with 4-col layout (Phase 32)
- `.dashboard-body` with 1fr+280px aside grid (Phase 32)

### What Needs Visual Redesign (not just CSS restructuring)
- KPI cards: currently generic boxes — need visual differentiation and data hierarchy
- Session list: currently basic card stack — needs status communication and action affordance
- Aside: currently just links — needs visual weight and actionable design
- Login: currently functional but generic — needs Clerk-level polish
- Overall composition: pages have correct layout structure from v4.1 but no visual "wow"

### Integration Points
- Dashboard data comes from HTMX partials via hx-target
- Login form posts to /api/auth/login
- KPI values populated by dashboard.js on page load
- Session list items are server-rendered

</code_context>

<specifics>
## Specific Ideas

- The dashboard should feel like opening Stripe Dashboard — immediate clarity about what's happening
- The login page should feel like Clerk — minimal, polished, confidence-inspiring
- KPI cards should communicate "at a glance" — a user should understand the state of their sessions in 2 seconds
- Session cards should show lifecycle state with color + icon + badge — not just text

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 35-entry-points*
*Context gathered: 2026-03-19*
