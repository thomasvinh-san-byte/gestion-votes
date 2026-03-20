# Phase 41: Public & Utility Pages - Research

**Researched:** 2026-03-20
**Domain:** Public-facing pages (Landing, Projector) + Utility pages (Report/PV, Trust/Audit, Validate, Doc, vote_confirm.php)
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Design Philosophy (carried from Phase 35-40)**
- Public pages = first impression — must feel premium and trustworthy
- "Officiel et confiance" visual identity at its best
- ag-tooltip where needed, clean composition, professional typography
- Every remaining page brought to top 1% quality

**Landing Page (SEC-02)**
- Hero section: Large Fraunces headline, compelling subtitle, gradient primary CTA button ("Commencer"), trust signals below (shield icon + "Sécurisé", scale icon + "Conforme", clock icon + "Temps réel")
- Features section: 3-column grid of feature cards with icons, titles, descriptions. Cards with hover lift effect
- CTA section: Bottom call-to-action with gradient background strip, white text, prominent button
- Footer: Clean minimal footer with version, legal links
- Visual identity: Bleu/indigo primary palette, warm stone backgrounds, professional but approachable

**Public/Projector Display (SEC-06)**
- Large format: Designed for projection on a meeting room screen — large high-contrast type legible from 5 meters at 1080p
- Results display: Motion title very large (clamp for scaling), vote results as colored bar with large percentages, ADOPTÉ/REJETÉ verdict in bold
- Real-time updates: SSE-driven content changes with smooth transitions
- Dark background option: Projector screens benefit from darker backgrounds — use dark theme automatically or provide toggle
- Minimal chrome: No navigation, no sidebar — just the content. AG-VOTE logo watermark subtle in corner

**Report/PV Page (SEC-07)**
- Preview panel: Document preview with clear download CTA button (gradient primary)
- Status timeline: PV generation status as a simple timeline — Generated, Validated, Sent, Archived
- Download area: Large, clear download button with file size and format indicator
- Document metadata: Date, session name, signatory info displayed cleanly

**Trust/Validate/Doc Utility Pages (SEC-08)**
- Consistent treatment: All utility pages share the same minimal layout — centered card on warm background (like login)
- Trust page: Audit verification display — clean data presentation, security indicators
- Validate page: Form or confirmation with clear status indicators
- Doc page: Document viewer with clean header and navigation
- Visual consistency: Must feel like they belong to the same app as the main pages

### Claude's Discretion
- Landing page exact copy and feature descriptions
- Projector display animation timing for result transitions
- Report page document thumbnail implementation
- Whether utility pages need their own nav or are standalone

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SEC-02 | Landing — redesign visuel (hero, features, trust signals, CTA) | Landing HTML/CSS fully audited; before→after specs below |
| SEC-06 | Public/Projector — redesign visuel (display temps réel, résultats) | public.htmx.html + public.css fully audited; projector-specific patterns documented |
| SEC-07 | Report/PV — redesign visuel (preview, download, timeline) | report.htmx.html + report.css audited; PV status timeline pattern specified |
| SEC-08 | Trust/Validate/Doc — redesign visuel (pages utilitaires, cohérence) | trust.css, validate.css, doc.css, vote_confirm.php all audited; utility-card pattern documented |

</phase_requirements>

---

## Summary

Phase 41 closes the v4.2 Visual Redesign milestone by bringing four groups of pages to the same design quality achieved in phases 35-40. The pages fall into two distinct visual contexts: (1) public/marketing context — the Landing page and Projector display, and (2) utility/data context — Report/PV, Trust/Audit, Validate, and Doc pages.

The Landing page (`public/index.html` + `landing.css`) has the right structure but lacks the design depth established in later phases. It has hero text, a login card, a roles grid, and a features section, but misses: a gradient CTA button, proper trust-signal strip, a bottom CTA section, persona-color on role cards (partially present via data-persona attributes), and the Fraunces font on the hero headline. The hero gradient is a single subtle radial — it needs to be more dramatic to establish immediate authority.

The Projector display (`public.htmx.html` + `public.css`) is already technically capable (SSE, clamp scaling, bar chart, quorum bar, decision cards) but has visual gaps: the ADOPTÉ/REJETÉ verdict text is just colored `.decision-value` — it needs to feel dramatic at 5 meters. The waiting state is understated. The footer brand is a plain text span. The chart bars and decision cards need stronger visual weight for room-scale legibility.

The utility pages (Report, Trust, Validate) are app-shell pages that use the standard sidebar + app-header layout. They are not "standalone centered card" pages — they live inside the full app shell. The CONTEXT.md request for "centered card on warm background (like login)" applies specifically to `vote_confirm.php`, which is genuinely standalone and currently uses raw inline styles (`style="padding:18px;"`, `class="btn primary"` — a non-existent class). The Trust, Report, and Validate pages need their section content cards, data displays, and status indicators brought to v4.2 quality while keeping their app-shell layout.

**Primary recommendation:** Four distinct work areas — (A) Landing hero + trust strip + CTA section + features cards, (B) Projector verdict drama + waiting state + footer watermark, (C) Report PV status timeline + download CTA, (D) Trust/Validate section cards + vote_confirm.php rescue.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Raw CSS custom properties | N/A | All styling — no build step | Established project constraint |
| Fraunces serif | Google Fonts (loaded) | Display headings on public pages | Already loaded in landing.css head |
| Bricolage Grotesque | Google Fonts (loaded) | UI body text | Project sans-serif; already in all pages |
| JetBrains Mono | Google Fonts (loaded) | Hash values, numeric data | Already used in trust.css |

### Supporting Patterns (from Phases 35-40)
| Pattern | CSS Location | When to Use |
|---------|-------------|-------------|
| Gradient CTA button | `login.css` `.login-btn` | Primary action CTAs on landing, report download |
| Login-style centered card | `login.css` `.login-page` / `.login-card` | vote_confirm.php — standalone confirmation page |
| Trust signal strip | `login.css` `.login-trust` | Landing hero trust indicators |
| ag-tooltip wrapper | `design-system.css` | Complex elements needing explanation |
| hover lift on cards | `landing.css` `.role-card:hover` | Feature cards, role cards |
| @starting-style entrance | `login.css` `.login-card` | Centered card fade-in on load |

### Reusable Design Tokens
```css
/* Gradient CTA — established in Phase 35 */
background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%);

/* Trust signal icon color */
color: var(--color-success);  /* shield, verified marks */

/* Warm stone backgrounds */
--color-bg: #EDECE6;
--color-bg-subtle: #E5E3D8;

/* Display font */
font-family: var(--font-display);  /* 'Fraunces', Georgia, serif */
```

---

## Architecture Patterns

### Recommended Project Structure (no changes needed)
Files already exist. All changes are CSS additions + HTML element additions within existing files.

```
public/
├── index.html               — Landing: add trust strip, CTA section; restyle hero-title
├── public.htmx.html         — Projector: restyle verdict, waiting state, footer brand
├── assets/css/
│   ├── landing.css          — Add: .hero-trust, .cta-section, upgrade .feature-item to card
│   ├── public.css           — Add: .verdict-dramatic, upgrade .waiting-state, .projection-footer brand
│   ├── report.css           — Add: .pv-status-timeline, .download-cta, .doc-metadata strip
│   └── trust.css            — Add: .integrity-summary token upgrades, section card headers
├── app/Templates/
│   └── vote_confirm.php     — Rescue: replace inline styles, use login-page layout pattern
```

### Pattern 1: Hero Trust Signal Strip (Landing)
**What:** Horizontal row of 3 icon+label pairs below the hero subtitle, communicating key trust properties at a glance.
**When to use:** Landing hero section, below subtitle, before login card appears on mobile.
**Example:**
```html
<!-- Source: Phase 35 login.css pattern, adapted for horizontal strip -->
<div class="hero-trust-strip">
  <div class="trust-item">
    <svg class="trust-icon" ...><!-- shield --></svg>
    <span>Sécurisé</span>
  </div>
  <div class="trust-item">
    <svg class="trust-icon" ...><!-- scale --></svg>
    <span>Conforme</span>
  </div>
  <div class="trust-item">
    <svg class="trust-icon" ...><!-- clock --></svg>
    <span>Temps réel</span>
  </div>
</div>
```
```css
.hero-trust-strip {
  display: flex;
  align-items: center;
  gap: var(--space-6);
  margin-top: var(--space-6);
  flex-wrap: wrap;
}
.trust-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  font-weight: var(--font-medium);
}
.trust-icon {
  width: 16px;
  height: 16px;
  color: var(--color-success);
  flex-shrink: 0;
}
```

### Pattern 2: Landing CTA Section (Bottom Strip)
**What:** Full-width gradient strip with headline, subtitle, and "Commencer" button — matches the locked decision from CONTEXT.md.
**When to use:** After features section, before footer.
**Example:**
```html
<section class="cta-section" aria-labelledby="cta-title">
  <div class="cta-body">
    <h2 id="cta-title" class="cta-title">Prêt à commencer ?</h2>
    <p class="cta-subtitle">Créez votre première séance en quelques minutes.</p>
    <a href="#login-card" class="btn btn-cta">Commencer</a>
  </div>
</section>
```
```css
.cta-section {
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%);
  padding: var(--space-16) var(--space-6);
  text-align: center;
}
.cta-title {
  font-family: var(--font-display);
  font-size: clamp(1.75rem, 4vw, 2.5rem);
  font-weight: 700;
  color: var(--color-text-inverse);
  margin-bottom: var(--space-3);
}
.cta-subtitle {
  color: rgba(255,255,255,0.85);
  font-size: var(--text-lg);
  margin-bottom: var(--space-8);
}
.btn-cta {
  display: inline-flex;
  align-items: center;
  padding: var(--space-4) var(--space-8);
  background: var(--color-surface-raised);
  color: var(--color-primary);
  border-radius: var(--radius-lg);
  font-weight: var(--font-bold);
  font-size: var(--text-md);
  transition: transform var(--duration-fast), box-shadow var(--duration-fast);
}
.btn-cta:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}
```

### Pattern 3: Projector Verdict Drama
**What:** The ADOPTÉ/REJETÉ verdict card needs massive visual weight. Current `.decision-value` is `clamp(1.25rem, 3vw, 2.5rem)` — not enough for a room. The verdict card itself needs a colored border and background.
**When to use:** `.decision-card` when `data-verdict="adopted"` or `data-verdict="rejected"` is set by JS.
**Example:**
```css
/* Dramatic verdict scaling for room display */
.decision-card[data-verdict="adopted"] {
  border-color: var(--color-success);
  background: var(--color-success-subtle);
}
.decision-card[data-verdict="rejected"] {
  border-color: var(--color-danger);
  background: var(--color-danger-subtle);
}
/* Verdict text at projection scale */
.decision-card .decision-value {
  font-size: clamp(1.5rem, 4vw, 3.5rem);
  font-family: var(--font-display);
  font-weight: 800;
  letter-spacing: -0.02em;
}
```

### Pattern 4: PV Status Timeline
**What:** Simple horizontal (or vertical on mobile) timeline showing PV lifecycle: Généré → Validé → Envoyé → Archivé.
**When to use:** Report/PV page, below the PV preview panel.
**Example:**
```html
<div class="pv-timeline">
  <div class="pv-timeline-step done" data-step="generated">
    <div class="pv-step-dot"></div>
    <span class="pv-step-label">Généré</span>
  </div>
  <div class="pv-timeline-connector"></div>
  <div class="pv-timeline-step active" data-step="validated">
    <div class="pv-step-dot"></div>
    <span class="pv-step-label">Validé</span>
  </div>
  ...
</div>
```
```css
.pv-timeline {
  display: flex;
  align-items: center;
  gap: 0;
  padding: var(--space-4) 0;
}
.pv-timeline-step { text-align: center; flex: 1; }
.pv-step-dot {
  width: 12px; height: 12px;
  border-radius: 50%;
  background: var(--color-border);
  margin: 0 auto var(--space-2);
}
.pv-timeline-step.done .pv-step-dot { background: var(--color-success); }
.pv-timeline-step.active .pv-step-dot {
  background: var(--color-primary);
  box-shadow: 0 0 0 4px var(--color-primary-subtle);
}
.pv-timeline-connector {
  flex: 2;
  height: 2px;
  background: var(--color-border);
  margin-bottom: 20px; /* align with dots */
}
.pv-timeline-step.done ~ .pv-timeline-connector { background: var(--color-success); }
```

### Pattern 5: vote_confirm.php Rescue (Standalone Card)
**What:** This template uses `style="padding:18px;"` inline and `class="btn primary"` (non-existent class). Replace with login-page pattern: centered card on warm gradient background.
**When to use:** vote_confirm.php — called when a voter's confirmation is needed server-side.
**Example:**
```html
<!-- Full page replacement following login-page pattern -->
<body class="login-page">
  <div class="login-card vote-confirm-card">
    <div class="vote-confirm-icon"><!-- shield checkmark svg --></div>
    <h1 class="vote-confirm-title">Confirmer votre vote</h1>
    <p class="vote-confirm-choice">Vous allez voter : <strong><?= htmlspecialchars($chosen) ?></strong></p>
    <form method="post">
      <input type="hidden" name="vote" value="...">
      <input type="hidden" name="confirm" value="1">
      <button class="login-btn" type="submit">Confirmer</button>
    </form>
    <form method="post" style="margin-top: var(--space-3);">
      <input type="hidden" name="confirm" value="0">
      <button class="btn btn-ghost btn-lg" style="width:100%;" type="submit">Modifier</button>
    </form>
  </div>
</body>
```
Note: use `<link rel="stylesheet" href="/assets/css/app.css">` + `<link rel="stylesheet" href="/assets/css/login.css">` — no new CSS file needed.

### Anti-Patterns to Avoid
- **Inline styles in PHP templates:** vote_confirm.php currently uses `style="padding:18px;"` — replace entirely with token-based classes.
- **`class="btn primary"`:** This class does not exist in the design system. The real class is `btn btn-primary`. Always use the two-class pattern.
- **Motion title font too small on projector:** The `.motion-title` is already clamp-scaled, but the verdict (`.decision-value`) reads at body scale — needs display-font upgrade for room legibility.
- **Hero title using `--color-text-dark` hardcode:** Currently `color: var(--color-text-dark, #1a1a1a)` — in dark mode this will be invisible. Use `var(--color-text)` which already maps correctly in both themes.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Gradient CTA button | Custom `.cta-btn` CSS | Extend `login.css` `.login-btn` gradient pattern | Identical gradient already established |
| Trust icons/badges | Custom icon component | Inline SVG with `.trust-icon` sizing class | No web component for icon + label needed |
| PV status indicators | Custom step tracker | Simple flexbox dots + connectors | Not a full stepper; no JS needed |
| Centered standalone card | Custom page layout | Reuse `login-page` + `login-card` classes | Already has correct radial gradient, shadow, @starting-style |
| Hero gradient background | Custom gradient design | Extend existing `.hero` radial gradient | Already has the right base |

---

## Common Pitfalls

### Pitfall 1: Landing Page Already Has a `.login-card` Class
**What goes wrong:** `landing.css` defines `.login-card` for the landing page login card inline with the hero. The standalone `login.css` also defines `.login-card` (the redesigned version with `@starting-style`). These are the same class name but in different CSS files.
**Why it happens:** `index.html` loads `app.css` + `landing.css` (not login.css). So the `landing.css` `.login-card` is the one in effect on the landing page.
**How to avoid:** When upgrading the landing page login card to match the login page redesign, update the `landing.css` `.login-card` definition directly — don't import login.css. The landing page version needs to stay within landing.css scope.

### Pitfall 2: Projector Hero Already Forces Dark Theme
**What goes wrong:** `public.htmx.html` line 16 forces `data-theme="dark"` via JS at page load. Any light-mode-only CSS additions (e.g. light gradient backgrounds) will be invisible.
**Why it happens:** Requirement DISP-01 mandates dark theme for projection.
**How to avoid:** All projector CSS additions must either be theme-agnostic (using design tokens) or explicitly target `[data-theme="dark"]`. Test changes with the theme toggle button — the page must look good in both modes since the toggle button is still visible.

### Pitfall 3: `.decision-card` data-verdict Attribute is Not in Current HTML
**What goes wrong:** The current `.decision-card` HTML in public.htmx.html does not have a `data-verdict` attribute. The verdict state is set by JS writing to `#decision_value` text content and adding `.adopted`/`.rejected` classes on that span.
**Why it happens:** The current pattern uses class toggling on the inner value span, not the card.
**How to avoid:** Check public.js before adding CSS rules. The correct selectors are `.decision-value.adopted` and `.decision-value.rejected` — these already exist in public.css. For the dramatic card-level styling, add a JS call that also sets `data-verdict` on `.decision-card`, or use CSS sibling selectors from the existing classes.

### Pitfall 4: report.css is Sparse — the Actual Layout is in app.css + pages.css
**What goes wrong:** `report.css` contains only `.export-btn`, `.email-form`, `.pv-preview`, and empty states. The bulk of the Report page layout (card structure, section headers, tabs) comes from `app.css` and `pages.css` global component styles.
**Why it happens:** The report page uses `.app-shell`, `.app-header`, `.app-main`, `.card`, `.card-header`, `.card-body` — all defined in the design system.
**How to avoid:** Report page changes should use the standard `.card` component with v4.2 card-header styling already established in prior phases. Add to `report.css` only what is report-specific (PV timeline, download CTA, metadata strip).

### Pitfall 5: vote_confirm.php Missing head Tags
**What goes wrong:** The current `vote_confirm.php` has a minimal `<head>` with only `app.css` and no theme-init.js, no font preconnect, no favicon. Loading login.css without app.css design tokens will produce broken layout.
**Why it happens:** The template was never upgraded past v1.
**How to avoid:** Add the complete head block: favicon, charset/viewport/description meta, theme-init.js, Google Fonts preconnect, app.css, login.css. Follow the pattern of `login.html` exactly.

### Pitfall 6: Landing Hero Title Dark Mode Color
**What goes wrong:** `.hero-title` uses `color: var(--color-text-dark, #1a1a1a)` — this hardcodes the light theme dark color and will be nearly invisible on dark backgrounds.
**Why it happens:** The landing hero was written before the v4.2 dark mode system was finalized.
**How to avoid:** Replace with `color: var(--color-text)` which correctly maps to the dark-mode light text token via `[data-theme="dark"]` overrides in design-system.css.

### Pitfall 7: Features Section is a List, Not Cards
**What goes wrong:** The current `.feature-item` is a horizontal `display: flex` with icon + text — no card background, no border, no hover effect. CONTEXT.md requires "feature cards with hover lift effect."
**Why it happens:** The current design is an icon-list style, not a card grid.
**How to avoid:** Upgrade `.feature-item` to have `background: var(--color-surface)`, `border: 1px solid var(--color-border)`, `border-radius: var(--radius-xl)`, `padding: var(--space-6)`, and the standard hover lift: `transform: translateY(-4px)`. This is a CSS-only change to `landing.css`.

---

## Code Examples

### Hero Title — Dark Mode Fix
```css
/* Source: design-system.css token analysis */
/* BEFORE */
.hero-title {
  color: var(--color-text-dark, #1a1a1a);
}
/* AFTER */
.hero-title {
  color: var(--color-text);  /* correctly maps to dark mode text */
}
```

### Feature Item → Feature Card Upgrade
```css
/* Source: landing.css .role-card pattern */
/* BEFORE — icon-list row */
.feature-item {
  display: flex;
  gap: var(--space-4);
}
/* AFTER — card with hover lift */
.feature-item {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding: var(--space-6);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  transition: transform var(--duration-normal) var(--ease-out),
              box-shadow var(--duration-normal) var(--ease-out);
}
.feature-item:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
}
```

### Projector Waiting State Upgrade
```css
/* Source: design-system.css, public.css analysis */
/* BEFORE — understated waiting state */
.waiting-state.visible {
  display: flex;
}
/* AFTER — prominent, dramatic for room display */
.waiting-state {
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: var(--space-16);
  gap: var(--space-4);
}
.waiting-title {
  font-family: var(--font-display);
  font-size: clamp(1.5rem, 4vw, 3rem);
  font-weight: 800;
  letter-spacing: -0.02em;
}
.waiting-text {
  font-size: clamp(0.875rem, 2vw, 1.25rem);
  color: var(--color-text-muted);
}
```

### Projector Footer Brand Watermark
```css
/* Source: landing.css .brand-logo pattern */
/* BEFORE — plain text span */
.footer-brand {
  font-weight: var(--font-bold);
  letter-spacing: 0.08em;
}
/* AFTER — prominent watermark */
.projection-footer {
  background: transparent;
  border-top: 1px solid color-mix(in srgb, var(--color-border) 40%, transparent);
}
.footer-brand {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: var(--text-md);
  color: var(--color-text-muted);
  letter-spacing: 0.05em;
  opacity: 0.6;
}
```

### Landing Gradient Hero Enhancement
```css
/* Source: login.css .login-page, landing.css .hero */
/* BEFORE — single subtle radial */
.hero {
  background:
    radial-gradient(ellipse 80% 50% at 50% -20%, color-mix(in srgb, var(--color-primary) 10%, transparent) 0%, transparent 50%),
    var(--color-bg);
}
/* AFTER — richer dual radial for authority */
.hero {
  background:
    radial-gradient(ellipse 100% 60% at 60% -10%, color-mix(in srgb, var(--color-primary) 15%, transparent) 0%, transparent 55%),
    radial-gradient(ellipse 60% 40% at 20% 80%, color-mix(in srgb, var(--color-primary) 6%, transparent) 0%, transparent 50%),
    var(--color-bg);
}
```

### Report — Prominent Download CTA
```css
/* Source: login.css .login-btn gradient pattern */
.pv-download-cta {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-4) var(--space-6);
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%);
  color: var(--color-primary-text);
  border: none;
  border-radius: var(--radius-lg);
  font-size: var(--text-md);
  font-weight: var(--font-semibold);
  cursor: pointer;
  transition: transform var(--duration-fast), box-shadow var(--duration-fast);
  text-decoration: none;
  justify-content: center;
}
.pv-download-cta:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}
.pv-file-meta {
  font-size: var(--text-xs);
  color: rgba(255,255,255,0.75);
  font-weight: 400;
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Generic feature list rows | Feature cards with hover lift | Phase 41 | Features feel discoverable, premium |
| Plain ADOPTÉ text | Display-font verdict at projection scale | Phase 41 | Legible from 5m, dramatic |
| Inline styles in vote_confirm.php | Reuses login-page pattern | Phase 41 | Visually consistent confirmation |
| Sparse trust signals | Trust strip with icon+label row | Phase 41 | Immediate credibility signal |
| Minimal CTA at footer | Gradient full-width CTA section | Phase 41 | Clear conversion path |

**Deprecated/outdated in this phase:**
- `color: var(--color-text-dark, #1a1a1a)` on hero title — hardcoded fallback breaks dark mode
- `class="btn primary"` in vote_confirm.php — incorrect class name (should be `btn btn-primary`)
- `style="padding:18px;"` inline style in vote_confirm.php — replace with token classes

---

## Open Questions

1. **Report page: PV timeline state from JS**
   - What we know: The PV lifecycle states (generated, validated, sent, archived) are driven by server data loaded via JS
   - What's unclear: Whether the timeline step states are available in the DOM at render time or set after JS fetch
   - Recommendation: Add `data-pv-status` attribute to the timeline container; JS sets it after load. CSS rules on `[data-pv-status="validated"] .pv-timeline-step[data-step="validated"]` handle visual state. If unsure, use simple JS class toggling matching existing patterns.

2. **Trust/Audit page `.integrity-summary` — existing or needs creation**
   - What we know: `trust.css` already has `.integrity-summary`, `.integrity-stat`, `.integrity-hash` defined
   - What's unclear: Whether these are actually used in trust.htmx.html or are dead CSS
   - Recommendation: Scan trust.htmx.html main content section for these class names before deciding whether to upgrade or add new.

3. **Doc page (doc_page.php) — scope clarification**
   - What we know: doc_page.php uses the full app-shell sidebar layout, not a standalone card
   - What's unclear: CONTEXT.md says "Doc page: Document viewer with clean header and navigation" — this could mean just upgrading the breadcrumb, doc-page-header, and sidebar typography
   - Recommendation: Treat doc page as a typography + header polish task within the existing doc.css, not a layout overhaul. The three-column layout (sidebar + content + TOC rail) is correct.

---

## Validation Architecture

> `nyquist_validation` key absent from config.json — treating as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | None detected — v4.2 is a pure visual/CSS phase |
| Config file | None |
| Quick run command | Manual browser inspection |
| Full suite command | Visual review of all 4 page groups in browser |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SEC-02 | Landing hero shows trust strip, gradient CTA, feature cards with hover | manual | Open `/` in browser, verify hover, scroll | N/A |
| SEC-06 | Projector verdict ADOPTÉ/REJETÉ legible at simulated 5m (small viewport) | manual | Open `/public.htmx.html`, verify font sizes at 67% zoom | N/A |
| SEC-07 | Report PV shows timeline with status dots, gradient download button | manual | Open `/report.htmx.html`, verify CTA and timeline | N/A |
| SEC-08 | vote_confirm.php uses login-page card layout; Trust/Validate/Doc consistent | manual | Open `/api/v1/vote_confirm` path, compare to login page | N/A |

### Sampling Rate
- **Per task commit:** Manual browser check of changed page
- **Per wave merge:** Full visual review of all 4 page groups
- **Phase gate:** All 4 requirements visually confirmed before `/gsd:verify-work`

### Wave 0 Gaps
None — no test framework needed. This is a pure CSS/HTML visual phase. Manual review is the validation method.

---

## Per-Page Before → After Specifications

### SEC-02: Landing Page (`public/index.html` + `landing.css`)

**Before → After by element:**

| Element | Before | After |
|---------|--------|-------|
| Hero title | `color: var(--color-text-dark, #1a1a1a)` — breaks dark mode | `color: var(--color-text)` — uses semantic token |
| Hero gradient | Single subtle radial, `primary 10%` opacity | Dual radial — primary at 15% + second accent at 6% |
| Hero below-subtitle | Nothing — text ends, goes straight to login card | Add `.hero-trust-strip` with 3 trust items: shield/Sécurisé, scale/Conforme, clock/Temps réel |
| Login button (in hero) | `btn btn-primary btn-lg` — standard button | Upgrade to gradient via `.login-btn` pattern: `linear-gradient(135deg, primary → primary-hover)` |
| Role card persona borders | Already present via `data-persona` attribute | Keep — already good. Verify all 7 have persona color on icon + border-top |
| Features section items | Horizontal icon-list row, no card background | Upgrade `.feature-item` to card: bg, border, radius-xl, padding-6, hover lift translateY(-4px) |
| Features section layout | `auto-fit minmax(280px, 1fr)` with `gap-8` | Change to `repeat(3, 1fr)` with `gap-6` — forces clean 3-column grid per spec |
| After features section | Nothing — goes straight to footer | Add `.cta-section`: gradient strip, Fraunces headline "Prêt à commencer?", subtitle, white `.btn-cta` button |
| Footer | Plain `text-muted` + `footer-links` | Add `v4.2` version indicator; keep legal links; ensure footer-links have `gap-6` |

**New HTML additions to `index.html`:**
1. After `.hero-bullets` — insert `.hero-trust-strip` with 3 items
2. After `.features-section` — insert `.cta-section` block
3. Upgrade login button class from `btn btn-primary btn-lg` to `login-btn` (or add gradient-btn class)

**New CSS in `landing.css`:**
- `.hero-trust-strip`, `.trust-item`, `.trust-icon`
- `.cta-section`, `.cta-body`, `.cta-title`, `.cta-subtitle`, `.btn-cta`
- Upgrade `.feature-item` to card style
- Fix `.hero-title` color token
- Dark mode variants for cta-section

---

### SEC-06: Public/Projector Display (`public/public.htmx.html` + `public.css`)

**Before → After by element:**

| Element | Before | After |
|---------|--------|-------|
| Decision value font | `clamp(1.25rem, 3vw, 2.5rem)` Bricolage | `clamp(1.5rem, 4vw, 3.5rem)` Fraunces 800 — dramatic at room scale |
| Decision card adopted/rejected | Just text color on `.decision-value.adopted/rejected` | Card-level: `border-color: success/danger`, `background: success-subtle/danger-subtle` |
| Decision card layout | `border: 2px solid var(--color-border)` | When verdict is set: border becomes 3px with semantic color |
| Waiting state title | `var(--text-xl)` Bricolage medium | `font-family: var(--font-display)` Fraunces, `clamp(1.5rem, 4vw, 3rem)`, weight 800 |
| Waiting state layout | Compact, `padding: var(--space-12)` | Expand gap, add `gap: var(--space-4)` between icon/title/text |
| Waiting icon | 64×64 muted clock | Increase to 80×80 |
| Projection footer brand | Plain `font-bold letter-spacing-wide` text | `font-family: var(--font-display)` Fraunces 800, `opacity: 0.6` — subtle watermark feel |
| Projection footer border | `border-top: 1px solid var(--color-border)` | Semi-transparent border: `color-mix(in srgb, var(--color-border) 40%, transparent)` |
| Bar chart label case | `text-transform: uppercase` | Keep — uppercase POUR/CONTRE/ABSTENTION is correct at room scale |
| Resolution tracker pills | OK as-is | Keep current tracker-pill styles — they are correct |
| Status badge "hors séance" | `.status-badge.off` muted bg | Keep — already correct |
| Motion title font | Already `var(--font-display)` via Phase 7.3 | Verify — confirm `font-family: var(--font-display)` is applied (it is, via `public.css` line 921) |

**New CSS in `public.css`:**
- Upgrade `.decision-value` font-size and font-family
- Add `.decision-card` verdict state variants (using class selectors matching existing JS pattern)
- Upgrade `.waiting-title` font
- Upgrade `.projection-footer` brand treatment

---

### SEC-07: Report/PV Page (`public/report.htmx.html` + `report.css`)

**Before → After by element:**

| Element | Before | After |
|---------|--------|-------|
| Export PDF button (header) | `btn btn-primary` — standard gradient button | Keep as-is — already correct class |
| Export buttons grid (`.export-btn`) | Minimal card with emoji icon | Upgrade: `border-radius: var(--radius-xl)`, increase padding to `var(--space-6)`, `font-size: var(--text-md)` for label |
| PV preview panel | `.pv-preview` with `min-height: 200px` bare | Keep — preview is content-driven |
| PV status timeline | Absent — no timeline exists | Add `.pv-timeline` + steps below the preview panel card |
| Download CTA | Standard `.export-btn` in grid | Add a prominent `.pv-download-cta` above the export grid — large gradient button with file format badge |
| Document metadata | Absent — no metadata strip | Add `.pv-doc-metadata` row: date, session name, hash snippet |
| Email form | `.email-form` flex — functional | Keep as-is, add `gap: var(--space-3)` if not present |

**New CSS in `report.css`:**
- `.pv-timeline`, `.pv-timeline-step`, `.pv-timeline-connector`, `.pv-step-dot`, `.pv-step-label`
- `.pv-download-cta`, `.pv-file-meta`
- `.pv-doc-metadata`, `.pv-meta-item`, `.pv-meta-label`, `.pv-meta-value`
- Upgrade `.export-btn` padding/radius/font-size

**New HTML in `report.htmx.html`:**
- After `#pv-preview-card` — insert `.pv-timeline` block
- Above export buttons grid — insert `.pv-download-cta` link
- Near PV title — insert `.pv-doc-metadata` strip

---

### SEC-08a: Trust/Audit Page (`public/trust.htmx.html` + `trust.css`)

**Before → After by element:**

| Element | Before | After |
|---------|--------|-------|
| Integrity summary grid | `.integrity-summary` exists in CSS — confirm if used in HTML | Verify presence; upgrade `.integrity-stat-value` to use `var(--text-4xl)` + `font-family: var(--font-display)` |
| Integrity hash display | `.integrity-hash` with border, mono value | Add `border-left: 3px solid var(--color-primary)` for stronger visual anchor |
| Check rows pass/fail | `.check-row.pass/fail` — colored background | Add `border-left: 3px solid var(--color-success/danger)` for clear severity signal |
| Severity filter pills | `.severity-pill` — functional but standard | Keep — already uses filter-tab pill pattern from Phase 39 |
| Audit timeline dots | `.audit-timeline-dot` color variants | Keep — already correct with severity color mapping |
| Section card headers | Using app.css `.card-header` generic | Add `ag-tooltip` wrappers on section headers explaining audit categories |
| Integrity stat colors | `color: var(--color-success/warning/danger)` | Keep — already semantic |

**New CSS in `trust.css`:**
- Upgrade `.integrity-stat-value` typography
- Add left-border to `.integrity-hash` and `.check-row`
- Add `ag-tooltip` to section title areas (HTML change in trust.htmx.html)

---

### SEC-08b: Validate Page (`public/validate.htmx.html` + `validate.css`)

**Before → After by element:**

| Element | Before | After |
|---------|--------|-------|
| Validation zone | `.validation-zone` gradient border — already styled well | Upgrade: use `box-shadow: 0 0 0 4px var(--color-primary-subtle)` outer glow on focus/active state |
| Summary items | `.summary-item` — simple centered card | Upgrade: add `border-left: 3px solid var(--color-primary)` for primary stats, `success` for pass items |
| Check items | `.check-item.pass/fail` — subtle bg | Add `border-left: 3px solid success/danger` |
| Validate button | Presumably `.btn btn-primary` | Verify it uses gradient pattern; if not, wrap in gradient-btn class |
| Summary grid labels | `.summary-label` at `var(--text-sm) muted` | Uppercase, `var(--text-xs)`, `letter-spacing: 0.05em` — like KPI card labels |
| Summary grid values | `.summary-value` at `var(--text-xl) bold` | Keep sizing; verify font-weight 700 |

---

### SEC-08c: Doc Page (`app/Templates/doc_page.php` + `doc.css`)

**Before → After by element:**

| Element | Before | After |
|---------|--------|-------|
| Doc page header bg | `background: var(--color-surface)` | `background: var(--color-surface-raised)` — elevate header above content |
| Doc page header padding | `padding: 10px 14px` — hardcoded px | `padding: var(--space-3) var(--space-6)` — use tokens |
| Breadcrumb chevron | SVG icon sprite reference | Keep as-is — already uses icon system |
| Doc sidebar background | `var(--color-bg-subtle)` | Keep — correct contrast level |
| TOC rail | `var(--color-surface-raised)` — elevated | Keep QA-02 elevation |
| Active TOC link | `color: var(--color-primary); border-left-color: var(--color-primary)` | Already correct — no change needed |
| H1 in page header | `font-size: var(--text-2xl)` | Add `font-family: var(--font-display)` for Fraunces — makes doc titles feel premium |
| `.prose h1` border bottom | `border-bottom: 2px solid var(--color-primary)` | Keep — distinctive document anchor |

**New CSS in `doc.css`:**
- `.doc-page-header` padding fix (tokens)
- `.doc-page-header` background upgrade
- `.doc-page-header h1` add Fraunces font

---

### SEC-08d: vote_confirm.php (`app/Templates/vote_confirm.php`)

**This template is a full rescue — the biggest individual task.**

**Before → After:**

| Element | Before | After |
|---------|--------|-------|
| `<body style="padding:18px;">` | Inline style | `<body class="login-page">` |
| `<div class="card" style="...">` | Inline styles, old class | `<div class="login-card vote-confirm-card">` |
| `class="btn primary"` | Non-existent class | `class="login-btn"` (gradient CTA) |
| `class="btn"` (Modifier) | Unstyled fallback | `class="btn btn-ghost btn-lg"` |
| No head meta/fonts | Bare minimum head | Full head: favicon, viewport, description, theme-init.js, fonts, app.css, login.css |
| No visual branding | No logo, no identity | Add brand mark (40px primary-colored square, AG letters) above title |
| Title `<h1>` | Plain `margin:0 0 8px` inline | Use `.login-brand h1` typography: Fraunces 3xl |

**Files changed:** `app/Templates/vote_confirm.php` only — no new CSS needed, uses existing login.css classes.

---

## Sources

### Primary (HIGH confidence)
- Direct file inspection: `public/index.html` — landing page HTML structure confirmed
- Direct file inspection: `public/public.htmx.html` — projector HTML structure confirmed
- Direct file inspection: `public/assets/css/landing.css` — all current landing styles read
- Direct file inspection: `public/assets/css/public.css` — all projector styles read
- Direct file inspection: `public/assets/css/report.css` — report styles read (sparse)
- Direct file inspection: `public/assets/css/trust.css` — trust styles read (rich)
- Direct file inspection: `public/assets/css/validate.css` — validate styles read
- Direct file inspection: `public/assets/css/doc.css` — doc styles read
- Direct file inspection: `public/assets/css/login.css` — pattern source for gradient CTA, trust signal, centered card
- Direct file inspection: `public/assets/css/design-system.css` — all design tokens confirmed
- Direct file inspection: `app/Templates/vote_confirm.php` — confirmed inline style / wrong class issues
- Direct file inspection: `app/Templates/doc_page.php` — confirmed layout structure

### Secondary (MEDIUM confidence)
- `.planning/STATE.md` — accumulated phase decisions for phases 35-40 (established patterns)
- `.planning/phases/41-public-and-utility-pages/41-CONTEXT.md` — locked decisions confirmed

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Before/after specs: HIGH — based on direct code inspection
- Standard stack: HIGH — all tokens verified in design-system.css
- Architecture patterns: HIGH — reusing confirmed Phase 35-40 patterns
- Pitfalls: HIGH — identified from actual code conflicts found during inspection

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable CSS project, no fast-moving dependencies)
