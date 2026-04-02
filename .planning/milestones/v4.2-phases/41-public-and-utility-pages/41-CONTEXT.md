# Phase 41: Public & Utility Pages - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete visual redesign of Landing, Public/Projector display, Report/PV, and Trust/Validate/Doc utility pages. These are the public-facing pages that make the first impression on external users and close every remaining visual gap.

</domain>

<decisions>
## Implementation Decisions

### Design Philosophy (carried from Phase 35-40)
- Public pages = first impression — must feel premium and trustworthy
- "Officiel et confiance" visual identity at its best
- ag-tooltip where needed, clean composition, professional typography
- Every remaining page brought to top 1% quality

### Landing Page (SEC-02)
- **Hero section:** Large Fraunces headline, compelling subtitle, gradient primary CTA button ("Commencer"), trust signals below (shield icon + "Sécurisé", scale icon + "Conforme", clock icon + "Temps réel")
- **Features section:** 3-column grid of feature cards with icons, titles, descriptions. Cards with hover lift effect
- **CTA section:** Bottom call-to-action with gradient background strip, white text, prominent button
- **Footer:** Clean minimal footer with version, legal links
- **Visual identity:** Bleu/indigo primary palette, warm stone backgrounds, professional but approachable

### Public/Projector Display (SEC-06)
- **Large format:** Designed for projection on a meeting room screen — large high-contrast type legible from 5 meters at 1080p
- **Results display:** Motion title very large (clamp for scaling), vote results as colored bar with large percentages, ADOPTÉ/REJETÉ verdict in bold
- **Real-time updates:** SSE-driven content changes with smooth transitions
- **Dark background option:** Projector screens benefit from darker backgrounds — use dark theme automatically or provide toggle
- **Minimal chrome:** No navigation, no sidebar — just the content. AG-VOTE logo watermark subtle in corner

### Report/PV Page (SEC-07)
- **Preview panel:** Document preview with clear download CTA button (gradient primary)
- **Status timeline:** PV generation status as a simple timeline — Generated, Validated, Sent, Archived
- **Download area:** Large, clear download button with file size and format indicator
- **Document metadata:** Date, session name, signatory info displayed cleanly

### Trust/Validate/Doc Utility Pages (SEC-08)
- **Consistent treatment:** All utility pages share the same minimal layout — centered card on warm background (like login)
- **Trust page:** Audit verification display — clean data presentation, security indicators
- **Validate page:** Form or confirmation with clear status indicators
- **Doc page:** Document viewer with clean header and navigation
- **Visual consistency:** Must feel like they belong to the same app as the main pages

### Claude's Discretion
- Landing page exact copy and feature descriptions
- Projector display animation timing for result transitions
- Report page document thumbnail implementation
- Whether utility pages need their own nav or are standalone

</decisions>

<canonical_refs>
## Canonical References

### Page files
- `public/index.html` — Landing page
- `public/public.htmx.html` — Projector/public display
- `public/assets/css/landing.css` — Landing styles
- `public/assets/css/public.css` — Projector styles
- `public/assets/css/report.css` — Report styles
- `public/assets/css/trust.css` — Trust page styles
- `public/assets/css/validate.css` — Validate page styles
- `public/assets/css/doc.css` — Doc page styles
- `app/Templates/doc_page.php` — Doc PHP template
- `app/Templates/vote_confirm.php` — Vote confirmation template

### Requirements
- `.planning/REQUIREMENTS.md` — SEC-02, SEC-06, SEC-07, SEC-08

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable from Phase 35-40
- Gradient CTA button pattern
- Login-style centered card on warm background (for utility pages)
- ag-tooltip wrapping
- Trust signals pattern
- Hover lift effect on feature cards

### Current State
- Landing: basic hero + features, functional but generic
- Public/projector: full-screen SSE display, functional
- Report: PV preview and download
- Trust/validate/doc: minimal utility pages

</code_context>

<specifics>
## Specific Ideas

- Landing hero should communicate "officiel et confiance" instantly — this is a voting platform for legal assemblies
- Projector display should be dramatic — large verdicts readable from across a meeting room
- Utility pages should feel cohesive with the main app, not like afterthoughts

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 41-public-and-utility-pages*
*Context gathered: 2026-03-20*
