# Phase 36: Session Creation Flow — Research

**Researched:** 2026-03-20
**Domain:** Visual redesign — Wizard (CORE-02) + Hub (CORE-04) pages
**Confidence:** HIGH — all findings from direct source file inspection

---

## Summary

Phase 36 is a complete visual redesign of two pages: the 4-step wizard that creates sessions, and the 6-step hub that manages them before going live. Both pages have functional HTML structures built in earlier phases (32 and 33), with CSS that is competent but not premium. The gap between current state and "top 1%" is significant: forms feel crowded, the stepper lacks hierarchy, the quorum bar is a small component buried inside a card, and checklist items are indistinguishable rows.

The redesign does not touch JS logic, API calls, or PHP. It is purely CSS additions/overrides and HTML structure enhancements within the existing files. Both pages use the same design-system token vocabulary (Bricolage Grotesque font, stone palette, primary blue `#1650E0`, spacing tokens `--space-*`, radius tokens `--radius-*`). Phase 35 established the gradient CTA button, ag-tooltip pattern, and kpi-card composition — all three are directly reusable here.

**Primary recommendation:** Treat the quorum bar as the hero element of the Hub, and the step-nav footer as the hero element of the Wizard. Both get the most visual investment. Everything else flows from those two anchors.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Design Philosophy (carried from Phase 35)**
- Reference-driven: Linear (issue creation flow), Notion (clean forms), Stripe (progression)
- Top 1% = intentional composition, generous whitespace, clear visual hierarchy
- Tooltips for guidance on every field/action, no guided tours
- Before/after contrast must be immediately visible in the browser

**Wizard Visual Redesign (CORE-02)**
- Stepper: Active step clearly highlighted with primary color fill + checkmark on completed steps. Connector lines show progression. Step labels visible, not just numbers. Current step name as subtitle below page title
- Form card: Clean white surface card, generous internal padding (32px), subtle shadow. Form sections visually separated by light dividers or background color shifts
- Field layout: Labels above fields (14px semibold), generous vertical gap between fields (24px). Helper text below fields in muted color. Required indicators subtle (not red asterisk)
- Field tooltips: ag-tooltip on info icons next to complex field labels — "Quorum minimum" gets a tooltip explaining what it means
- Progressive disclosure: Optional/advanced sections collapsed by default with a clean "Options avancées" toggle — not a cluttered form
- Step navigation: Sticky footer with clear Back/Next buttons, space-between layout. Next button is primary gradient (like login CTA), Back is ghost. Step indicator in footer showing "Étape 2 sur 4"
- Review step: Final review card with all entered data in a clean summary table. "Modifier" links next to each section — not a wall of text
- Motion templates: Template selector should feel like Linear's template picker — clean cards with icon + title + description, not a dropdown
- Micro-interactions: Step transition with subtle fade, field focus with ring animation, validation with smooth border-color change
- Empty form state: When starting fresh, show welcoming placeholder text in the first field, not a blank intimidating form

**Hub Visual Redesign (CORE-04)**
- Sidebar stepper: Vertical stepper showing session preparation progress. Active step has a colored dot + bold label, completed steps have checkmarks, pending steps are muted. Sticky at top while content scrolls
- Quorum bar: The most prominent element — large progress bar with clear percentage, colored segments (green when met, amber approaching, red below). Tooltip explaining quorum rules
- Checklist: Each preparation item as a card with checkbox, title, description, and status badge. Completed items have a subtle strikethrough or muted style — not just a checked box
- Session info header: Session title, date, type, and status displayed prominently at the top. Status as a large colored badge
- Action buttons: "Lancer la session" (primary CTA) prominently placed when all prerequisites met. Disabled with tooltip explaining what's missing when blocked
- Convocation section: Clean card with member count, send status, and resend action. Tooltip on the count explaining who hasn't been notified
- Motions list: Each motion as a compact card showing title, type (ordinaire/extraordinaire), and attached document indicator (PDF icon if docs exist)
- Responsive: At 768px stepper stacks above content as a horizontal progress bar

### Claude's Discretion
- Exact animation durations for step transitions
- Whether to use icons or just text in stepper labels
- Exact shading for completed checklist items
- Quorum bar height and label positioning
- Motion card density in the hub

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CORE-02 | Wizard — redesign visuel (stepper, formulaire centré, progression, espacement, micro-interactions, field-level tooltips) | Sections: Wizard Current Anatomy, Wizard Redesign Specs, Field Tooltip Pattern |
| CORE-04 | Hub — redesign visuel (stepper sidebar, quorum bar, checklist, états de session, step guidance tooltips) | Sections: Hub Current Anatomy, Hub Redesign Specs, Quorum Bar Architecture |
</phase_requirements>

---

## Current Anatomy: Wizard

### Stepper (`.wiz-progress-wrap`)

**Current structure:**
```html
<div class="wiz-progress-wrap" id="wizStepper">
  <div class="wiz-step-item active" data-step="0">
    <div class="wiz-snum">1</div><span>Informations</span>
  </div>
  <!-- × 4 -->
</div>
```

**Current CSS behavior:**
- Container: `display: flex`, sticky top-0, `background: var(--color-surface)`, `border-radius: var(--radius-lg)`, 4px padding + 4px gap between items
- Active item: `color: var(--color-primary)`, `background: var(--color-primary-subtle)`, `font-weight: 700`
- Active underline: `::after` pseudo-element, 3px gradient bar at bottom, `left/right: 8px`
- `.wiz-snum`: 26×26px circle, border `2px solid var(--color-border)`. Active: fills primary, adds `box-shadow: 0 0 0 4px var(--color-primary-glow)`. Done: fills green, shows checkmark SVG via JS
- Connector lines: **absent** — no visual connector between steps

**Problems:**
1. Steps are pill-shaped segments with no connector — looks like a tab bar, not a stepper
2. The active underline (`::after` 3px bar) is too subtle — same-weight as tab indicators
3. Step number circle at 26px is small and the step label is inline text at 13px — no visual hierarchy within each step item
4. On mobile, labels hide entirely (`span { display: none }`) — only numbers remain
5. No "current step name" subtitle in the page header

### Form Card (`.wiz-step`, `.wiz-step-body`)

**Current structure:**
- `.wiz-step`: `card` class + custom overrides, `border-radius: 12px`, `box-shadow: var(--shadow-md)`, flex column
- `.wiz-step-body`: `padding: 2rem 2.5rem` (32px/40px) — already generous
- `.wiz-section`: grouped in a `background: var(--color-bg-subtle)` rounded box with `1.25rem 1.5rem` padding
- `.wiz-section-title`: 11px uppercase primary-colored label with icon — ALLCAPS section headers

**Problems:**
1. Sections use `var(--color-bg-subtle)` background boxes — this creates a "card inside card" layering that feels nested/cramped
2. `.wf-step` (the step header inside the card: "1 Informations générales") duplicates the stepper above — redundant element that adds visual noise
3. `.field-label` is 12px uppercase monospace-style — too technical-looking for a clean form
4. Required indicator is a red `*` (`color: var(--color-danger)`) — the CONTEXT says to use subtle indicators instead
5. `.row` flex layout for date/time/type puts 3 fields in a single cramped row (they share `gap: var(--space-3)` = 12px)
6. No helper text below any fields — no guidance on what "quorum policy" means

### Step Navigation (`.step-nav`)

**Current structure:**
```css
.step-nav {
  display: flex;
  gap: 12px;
  justify-content: space-between;
  padding: 1.25rem 2.5rem;
  border-top: 1px solid var(--color-border-subtle);
  background: var(--color-surface-raised);
  border-radius: 0 0 12px 12px;
  position: sticky;
  bottom: 0;
}
```

**Current buttons:**
- Back: `btn` (no primary/ghost variant explicitly) — plain bordered button
- Next: `btn btn-primary` — solid blue, no gradient, no step counter

**Problems:**
1. The Next button is `btn-primary` (solid fill) but not the gradient CTA established in Phase 35 (login button)
2. No step counter ("Étape 2 sur 4") anywhere in the navigation
3. Back button is a plain `.btn` — not clearly a ghost/secondary button
4. No visual separation weight between back (secondary) and next (primary) beyond color

### Motion Templates Row (`.wiz-templates-row`)

**Current structure:**
- `<span class="wiz-templates-label">Modèles rapides :</span>` — small text label
- Three `<button class="btn btn-sm btn-ghost wiz-template-btn">` inline buttons

**Problems:**
1. Template buttons are inline text chips — no icon, no description, no visual card feel
2. The label "Modèles rapides :" is 11px uppercase muted text — not scannable
3. No way to understand what each template contains without clicking it
4. Context panel (`.ctx-panel`) below the resolution list is a blue-bordered info box — different visual style from the template area

### Review Step (Step 4)

**Current structure rendered by `buildReviewCard()` in wizard.js:**
```html
<div class="review-section">
  <div class="review-section-header">
    <span class="review-section-title">Informations</span>
    <button class="review-modifier" data-goto="0">Modifier</button>
  </div>
  <div class="review-row">
    <span class="review-label">Titre</span>
    <span class="review-value">AG Ordinaire...</span>
  </div>
</div>
```

**Current CSS:**
- `.review-section`: `padding: 1.25rem 0`, `border-bottom: 1px solid var(--color-border-subtle)`
- `.review-section-title`: 11px uppercase primary color
- `.review-label`: 13px muted
- `.review-value`: 13px semibold dark

**Problems:**
1. The review card is inside the `.wiz-step-body` padding — there's no visual elevation/card treatment
2. Section titles are 11px — very small for a review that needs to be scannable at a glance
3. No success/completion visual at the top of the review step (the `.alert.alert-success` exists but is a banner)
4. "Modifier" buttons are small ghost text — not clearly actionable

---

## Current Anatomy: Hub

### Identity Banner (`.hub-identity`)

**Current structure:**
```html
<div class="hub-identity">
  <div class="hub-identity-icon"><!-- clipboard SVG --></div>
  <div class="hub-identity-info">
    <div class="hub-identity-date" id="hubTitle">Chargement…</div>
    <div class="hub-identity-meta">
      <!-- calendar icon + date, location icon + place, users icon + count -->
    </div>
  </div>
</div>
```

**Current CSS:**
- `display: flex`, `padding: 1.5rem 1.75rem`, `border-radius: 12px`, `box-shadow: var(--shadow-md)`
- Accent top stripe: `::before` pseudo-element, 3px gradient line at top
- Icon box: 52×52px rounded square, `background: linear-gradient(135deg, primary, primary-hover)`
- `.hub-identity-date`: 1.375rem font-size, bold — this is actually the session **title** (misnamed CSS class)
- `.hub-identity-meta`: 13px, muted color, flex row with icons

**Problems:**
1. No session status badge visible — user cannot instantly see if session is "draft", "en cours", "archivé"
2. The banner shows title + meta but has no session type (AG Ordinaire / AG Extraordinaire)
3. The `hub-identity-date` class name suggests "date" but actually shows the session title — confusing internally (not user-facing)
4. No "type" chip/badge showing session type
5. The "Modifier" and "Séances" buttons are in the header (`app-header`) — distant from the identity banner

### Horizontal Status Bar (`#hubStatusBar` / `.hub-status-bar`)

**Current structure rendered by `renderStatusBar()` in hub.js:**
- 6 colored segments (`hub-bar-segment`): each `height: 8px`, segments for done/active/pending
- Active segment: `height: 12px` (slightly taller), `box-shadow: var(--shadow)`
- Labels hidden at font-size: 0, only visible on hover (`@media (hover: hover)`)
- Colors: done = `var(--color-success)`, active = step's color, pending = `var(--color-border)`

**Problems:**
1. At 8px height the bar is nearly invisible — decorative not informative
2. Hover-only labels means mobile users see no step names
3. The bar is positioned between identity banner and the layout grid — it is context-free (no "Step X of 6" label)
4. Done segments at `opacity: 0.9` vs pending `var(--color-border)` — too subtle a difference

### Vertical Stepper (`.hub-stepper` / `.hub-step-row`)

**Current structure rendered by `renderStepper()` in hub.js:**
```html
<button class="hub-step-row" data-step="0" aria-current="step">
  <div class="hub-step-num active"><!-- checkmark or number --></div>
  <div class="hub-step-text">
    <div class="hub-step-title active">Préparer la séance</div>
    <div class="hub-step-here">← Vous êtes ici</div>
  </div>
</button>
<div class="hub-step-line done"></div>
<!-- × 6 -->
```

**Current CSS:**
- `.hub-stepper`: `display: flex; flex-direction: column; gap: 0; padding: 1.25rem; border-radius: 12px; box-shadow: var(--shadow-md)`
- `.hub-step-row::before`: vertical connector line, `position: absolute; left: 15px; top: 42px; bottom: -6px; width: 2px`
- `.hub-step-num`: 32×32px circle. Active: primary gradient + ring. Done: success green
- `.hub-step-title`: 14.4px (0.9rem), active gets `font-weight: 700`, done gets success color, pending gets muted
- `hub-step-here`: 11px italic "← Vous êtes ici" under active step

**Problems:**
1. The connector line relies on `::before` positioning with fixed `left: 15px` — this breaks if the step number circle alignment changes
2. There is also a separate `.hub-step-line` div rendered between each step — duplicate connector strategy (both `::before` pseudo and explicit div)
3. The "← Vous êtes ici" label is 11px italic muted — not prominent enough to serve as a visual anchor
4. Step numbers are 32px circles but the clickable `.hub-step-row` is the full width button — the hot zone is good but the visual isn't polished
5. No "pending" visual differentiation beyond muted color — pending steps look nearly identical to done steps on gray backgrounds

### Preparation Checklist (`.hub-checklist`)

**Current structure rendered by `renderChecklist()` in hub.js:**
```html
<div class="hub-checklist">
  <div class="hub-checklist-header">
    <span class="hub-checklist-title">Préparation</span>
    <span class="hub-checklist-progress-text">2 / 6</span>
  </div>
  <div class="hub-checklist-bar">
    <div class="hub-checklist-bar-fill" style="--bar-pct:33%"></div>
  </div>
  <div class="hub-check-item done">
    <div class="hub-check-icon"><!-- checkmark svg --></div>
    <span class="hub-check-label">Titre défini</span>
  </div>
  <div class="hub-check-item">
    <div class="hub-check-icon"></div>
    <span class="hub-check-label">Convocations envoyées</span>
    <span class="hub-check-todo">À faire</span>
  </div>
</div>
```

**Current CSS:**
- `.hub-checklist`: plain card, `padding: var(--space-card)` (24px)
- `.hub-checklist-bar`: `height: 8px`, success gradient fill
- `.hub-check-item`: `padding: 10px 8px`, `border-bottom: 1px solid`, flex row with 10px gap
- `.hub-check-icon`: 22×22px circle, plain border. Done: fills success green
- `.hub-check-label`: `font-weight: 500`. Done: `color: var(--color-text-muted)` + `text-decoration: line-through`
- `.hub-check-todo`: 11px warning badge, `padding: 2px 8px`
- `.hub-check-blocked`: italic warning text under label

**Problems:**
1. Each checklist item is just a row — no card treatment, no visual separation between items beyond border-bottom
2. Done items are faded + strikethrough but there's no secondary info (e.g., "2 membres ajoutés" as a detail)
3. "À faire" badge is 11px — very small for an actionable state
4. No description under the label explaining what this item means
5. The progress bar is 8px tall — the same height as the status bar, undersized
6. Checklist header title "Préparation" is 14px bold — matches other card titles, not visually distinct

### Quorum Bar (`.hub-quorum-section` + `<ag-quorum-bar>`)

**Current structure:**
```html
<div class="hub-quorum-section" id="hubQuorumSection" style="display:none;">
  <ag-quorum-bar id="hubQuorumBar" current="0" required="0" total="0"></ag-quorum-bar>
</div>
```

**Current CSS for wrapper:**
```css
.hub-quorum-section {
  margin-bottom: var(--space-card);
  padding: var(--space-card);
  border-radius: 12px;
  border: 1px solid var(--color-border);
  border-left: 3px solid var(--color-primary);
  background: var(--color-surface-raised);
}
```

**ag-quorum-bar Shadow DOM:**
- Inner `.quorum` card: `padding: 1rem`, standard border, radius
- `.quorum-track`: `height: 12px` — small track
- Three status states: `reached` (green), `partial` (amber), `critical` (red)
- Threshold marker: 3px vertical line with downward triangle above it
- Values row: 12px font-size showing "X présents" and "Requis: Y / Z"

**Problems:**
1. The quorum bar is a standard-sized card inside the wrapper card — two card layers stacked
2. The 12px track height is visually small for the most important metric on the page
3. Status badge is `0.75rem` text — very small
4. The `hub-quorum-section` left-border accent (3px primary blue) conflicts with the inner `ag-quorum-bar` which has its own border
5. `ag-quorum-bar` uses Shadow DOM — CSS cannot pierce shadow root to style internals. Must use CSS custom properties or wrapper overrides
6. The component is positioned below the checklist — buried rather than prominent
7. No tooltip explaining quorum rules — the CONTEXT explicitly requires this

### Action Card (`.hub-action`)

**Current structure:**
```html
<div class="hub-action">
  <div class="hub-action-header">
    <div class="hub-action-icon"><!-- 28px SVG white on gradient --></div>
    <div class="hub-action-body">
      <div class="hub-action-title">Envoyer les convocations</div>
      <div class="hub-action-desc">55 participants…</div>
    </div>
  </div>
  <div class="hub-action-footer">
    <a class="btn btn-primary hub-action-btn" id="hubMainBtn">Envoyer…</a>
    <button class="btn hub-action-btn" id="hubPreviewBtn" hidden>Aperçu courriel</button>
  </div>
</div>
```

**Current CSS:**
- `.hub-action`: flex column, `gap: 1rem`, `padding: var(--space-card)`, accent left bar via `::before` (4px primary)
- `.hub-action-icon`: 52×52px `border-radius: 12px`, gradient background, changes color per step (JS inline style)
- `.hub-action-btn`: `min-height: 46px`, `font-size: 14px`, `font-weight: 700`
- `.btn-primary` inside action: `box-shadow: 0 2px 8px var(--color-primary-glow)` and lift on hover

**Problems:**
1. Button is `btn-primary` but not the gradient version used in login/Phase 35 CTAs
2. Left accent bar (`::before`) is 4px primary — subtle accent, but the card reads as a generic card not a "current action" card
3. No "blocked" state treatment — when prerequisites aren't met, the button should be disabled with tooltip
4. The action card and checklist are siblings with identical visual weight — no clear hierarchy that action card is primary

### Motions List (`.hub-motions-section`)

**Current structure:**
```html
<div class="hub-motions-section">
  <div class="hub-motions-header">
    <span class="hub-motions-title">Résolutions</span>
  </div>
  <div id="hubMotionsList"><!-- rendered by JS --></div>
</div>
```

**Each item rendered by `renderMotionsList()`:**
```html
<div class="hub-motion-item">
  <span class="hub-motion-num">1</span>
  <span class="hub-motion-title">Approbation des comptes</span>
  <span class="doc-badge doc-badge--empty">Aucun document</span>
</div>
```

**Current CSS:**
- `.hub-motion-item`: flex, `padding: 10px 8px`, `border-bottom`
- `.hub-motion-num`: 28×28px primary circle
- `.hub-motion-title`: 14px, `font-weight: 500`, truncated
- `.doc-badge--empty`: 12px muted text
- `.doc-badge--has-docs`: 12px primary blue with subtle background

**Problems:**
1. Motion items are flat rows — no distinction between title type and document status at a glance
2. No motion type display (ordinaire/extraordinaire) — the JS doesn't render a type badge
3. Document badge is 12px — barely visible

---

## Standard Stack

### Core (no new installs needed)

| Library/Component | Version | Purpose | How Used |
|-------------------|---------|---------|---------|
| Design System tokens | 2.0 | Color, spacing, typography | All CSS vars already defined |
| ag-tooltip / ag-popover | Phase 31 | Field-level guidance tooltips | `<ag-popover>` already in wizard step 3 |
| ag-quorum-bar | Phase 31 | Quorum progress visualization | Already in hub.htmx.html |
| ag-badge | Phase 31 | Status badges | Available for hub status badge |
| JetBrains Mono | loaded | Numeric values, KPI numbers | `var(--font-mono)` |
| Bricolage Grotesque | loaded | All UI text | `var(--font-sans)` |

### No New Dependencies

All redesign uses existing tokens, existing components, and existing patterns. No npm installs. No new web components. Rule: restyle existing, don't create new.

---

## Architecture Patterns

### Pattern 1: Tooltip on Complex Fields

The pattern is established by Phase 35 and wizard.js step 3 already uses `ag-popover`:

```html
<!-- EXISTING in step 3 -->
<label class="field-label">Vote secret
  <ag-popover title="Scrutin secret" content="Les bulletins de vote sont anonymes." position="top" trigger="click">
  </ag-popover>
</label>

<!-- TARGET pattern for step 1 quorum field -->
<label class="field-label" for="wizQuorum">
  Politique de quorum
  <button class="field-tooltip-trigger" type="button" aria-label="En savoir plus sur le quorum">
    <svg class="icon icon-xs" aria-hidden="true"><use href="/assets/icons.svg#icon-help-circle"></use></svg>
  </button>
  <ag-popover trigger="click" position="top" width="280">
    <div slot="content">
      <strong>Quorum</strong>
      <p>Le nombre minimum de membres présents requis pour que les votes soient valides légalement.</p>
    </div>
  </ag-popover>
</label>
```

**Confidence:** HIGH — `ag-popover` is verified in the HTML. The `field-tooltip-trigger` button style follows the `tour-trigger-btn` pattern from the header.

### Pattern 2: Gradient CTA Button (from Phase 35)

The login page CTA and the Phase 35 dashboard established this:

```css
/* Phase 35 pattern — gradient primary button */
.btn-primary {
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-hover));
  box-shadow: 0 2px 8px var(--color-primary-glow);
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 16px var(--color-primary-glow);
}
```

**Application in wizard step-nav:** Override `.step-nav .btn-primary` specifically to use this gradient. The hub action card already does this for `.hub-action .btn-primary`.

### Pattern 3: Stepper State Differentiation

**Wizard stepper target states:**
- Pending: `wiz-snum` = plain circle, border + number, muted label
- Active: `wiz-snum` = solid primary fill + ring shadow, bold label, bottom underline (existing)
- Done: `wiz-snum` = solid success fill + checkmark SVG (JS already does this)

**The missing connector line between wizard steps:**

Current: no connector. Target: thin line between step circles. Approach:

```css
/* Between each step item — use a flex separator */
.wiz-step-item:not(:last-child)::after {
  /* Override existing ::after (the underline) — need different approach */
}
```

Problem: the existing `::after` on `.wiz-step-item.active` is the bottom underline. Cannot reuse for connector. Solution: use a separate HTML element or use `::before` on the non-active items. The cleaner solution is to add a visual separator div between step items in the HTML, or use a border-right approach on each step item.

**Recommended approach:** Add `position: relative` and a vertical/horizontal gradient right-border to each non-last step item using `box-shadow: inset -1px 0 0 var(--color-border)` — this avoids pseudo-element conflicts.

### Pattern 4: Hub Status Bar Elevation

Transform the status bar from decorative to informational:

```css
/* BEFORE: 8px thin colored segments */
.hub-bar-segment { height: 8px; }

/* AFTER: larger, labeled, visible */
.hub-bar-segment {
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 600;
  color: white;
  /* text shows on active/done, hidden on pending */
}
```

The labels (step names) are already rendered as `<span class="hub-bar-label">` inside each segment — they just need `font-size: 0` removed and proper contrast color added.

### Pattern 5: Quorum Bar as Hero Section

Current: `ag-quorum-bar` is a standard card component inside a wrapper card, positioned below the checklist.

Target: Remove the wrapper card visual treatment, give the quorum section its own visual prominence with large typography and a bigger track.

Since `ag-quorum-bar` uses Shadow DOM, direct style piercing is impossible. Two approaches:

1. **CSS custom properties** — ag-quorum-bar already respects `var(--color-surface)`, `var(--color-border)` etc. through Shadow DOM inheritance. These are the only adjustable surface.
2. **Replace the component** with plain HTML for the hub context, styling it directly in hub.css.

**Recommended approach:** Keep `ag-quorum-bar` for its logic. Elevate the wrapper section visually to be hero-scale, and override what custom properties allow. For the large percentage number and clear segment visualization, add a supplementary display element above the `ag-quorum-bar`:

```html
<!-- In hub.htmx.html, wrap the quorum section differently -->
<div class="hub-quorum-hero" id="hubQuorumSection" style="display:none;">
  <div class="hub-quorum-hero-label">
    <span class="hub-quorum-hero-pct" id="hubQuorumPct">–%</span>
    <span class="hub-quorum-hero-status">
      <ag-tooltip content="Le quorum est le nombre minimum de membres présents requis pour que les votes soient légalement valides.">
      </ag-tooltip>
    </span>
  </div>
  <ag-quorum-bar id="hubQuorumBar" current="0" required="0" total="0"></ag-quorum-bar>
</div>
```

The `hubQuorumPct` percentage is computed by hub.js inside `renderQuorumBar()` and needs a one-line addition to update this element.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Tooltip on field labels | Custom hover div | `ag-popover` (already in page) | Already imported, handles positioning, keyboard, accessibility |
| Status badge for session type | Custom CSS class | `ag-badge` component | Already in design system |
| PDF preview on doc click | Custom modal | `ag-pdf-viewer` (already in wizard.js) | Already wired in wizard document upload |
| Progress tracking | Custom counter | Existing `renderChecklist()` logic | JS already computes done/total |
| Form validation display | Custom error UI | Existing `.field-error` / `.field-error-msg` pattern | Already implemented in wizard.js |

---

## Specific Before → After Specs

### WIZARD-01: Stepper Visual Hierarchy

**Before:**
- Flat row of 4 equal-weight items
- Active: subtle blue background + 3px underline bar
- 26px step number circle
- No connector between steps

**After:**
- Active step: larger step number circle (32px), `font-weight: 800`, label at `1rem` (not 13px)
- Done step: green filled circle with checkmark, label in success color
- Pending step: outlined circle (border only), label 12px muted
- Connector: subtle `1px` right-border on each non-last item via `box-shadow: inset -1px 0 0 var(--color-border-subtle)` on `.wiz-step-item:not(:last-child)`
- Subtlety change: active item gets `transform: none`, subtle lift not needed on stepper items

**CSS changes in wizard.css:**
```css
/* Larger active circle */
.wiz-step-item.active .wiz-snum {
  width: 32px;
  height: 32px;
  font-size: 0.8125rem;
}

/* Pending: clearly dim */
.wiz-step-item:not(.active):not(.done) {
  opacity: 0.6;
}

/* Connector line between items */
.wiz-step-item:not(:last-child) {
  box-shadow: inset -1px 0 0 var(--color-border);
  margin-right: 0;
}
```

**HTML change:** Add step subtitle to page header:
```html
<!-- In app-header, below page-title -->
<p class="page-sub wiz-step-subtitle" id="wizStepSubtitle">Informations générales</p>
```
JS updates this text when `showStep(n)` is called.

### WIZARD-02: Form Field Layout — Field Labels and Tooltips

**Before:**
- `field-label`: 12px uppercase muted (`text-transform: uppercase; letter-spacing: 0.04em`)
- No helper text
- Red asterisk required indicators

**After:**
- `field-label`: 14px semibold, NOT uppercase, normal letter-spacing
- Tooltip trigger button (info icon) inline with complex labels
- Required indicator: subtle dot or `(obligatoire)` text instead of red asterisk
- Helper text div below each complex field: `<div class="field-hint">…</div>` at 12px muted

**CSS changes in wizard.css:**
```css
/* Remove uppercase from field labels in wizard context */
.wiz-step-body .field-label {
  font-size: 0.875rem;   /* 14px */
  font-weight: 600;
  color: var(--color-text-dark);
  text-transform: none;   /* Remove uppercase */
  letter-spacing: 0;
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
}

/* Helper text */
.field-hint {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  margin-top: 4px;
  line-height: 1.4;
}

/* Subtle required indicator */
.field-label .req {
  color: var(--color-text-muted);   /* Override the red */
  font-size: 0.65rem;
  font-weight: 700;
}
```

**HTML additions in wizard.htmx.html** (Step 1, règles de vote section):
```html
<div class="field">
  <label class="field-label" for="wizQuorum">
    Politique de quorum
    <ag-popover trigger="click" position="top" width="300">
      <button slot="trigger" class="field-info-btn" type="button" aria-label="En savoir plus">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
      </button>
      <div slot="content">
        <strong>Quorum légal</strong>
        <p>Nombre minimum de membres présents pour que les votes soient valides (art. 22 de la loi de 1965).</p>
      </div>
    </ag-popover>
  </label>
  <select class="field-input" id="wizQuorum">…</select>
  <div class="field-hint">Règle légale déterminant la validité de l'assemblée.</div>
</div>
```

### WIZARD-03: Step Navigation Footer

**Before:**
- Back: plain `.btn` (gray bordered)
- Next: `.btn.btn-primary` (solid blue, no gradient)
- No step counter

**After:**
- Back: `.btn.btn-ghost` explicitly (lighter treatment)
- Next: `.btn.btn-primary` with gradient + lift + step counter text
- Step counter: `<span class="step-nav-counter">Étape 1 sur 4</span>` centered between the two buttons

**HTML change per step nav in wizard.htmx.html:**
```html
<div class="step-nav">
  <a class="btn btn-ghost" href="/dashboard.htmx.html">
    <svg …>…</svg> Retour
  </a>
  <span class="step-nav-counter">Étape 1 sur 4</span>
  <button class="btn btn-primary step-nav-next" type="button" id="btnNext0">
    Suivant <svg …>…</svg>
  </button>
</div>
```

**CSS additions:**
```css
.step-nav-counter {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
}

.step-nav { position: relative; } /* for counter absolute positioning */

.step-nav-next {
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-hover));
  box-shadow: 0 2px 8px var(--color-primary-glow);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.step-nav-next:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 16px var(--color-primary-glow);
}
```

### WIZARD-04: Motion Template Cards

**Before:**
- Inline text buttons: `btn btn-sm btn-ghost`
- A label "Modèles rapides :" before them
- No template description visible

**After:**
- Cards in a 3-column grid
- Each card: icon area + title + 1-line description
- On click: fills the form (existing JS behavior unchanged)

**HTML change in wizard.htmx.html** (Step 3):
```html
<div class="wiz-template-grid">
  <button class="wiz-template-card" type="button" data-template="approbation-comptes">
    <div class="wiz-template-card-icon">
      <svg …><!-- document check icon --></svg>
    </div>
    <div class="wiz-template-card-body">
      <div class="wiz-template-card-title">Approbation des comptes</div>
      <div class="wiz-template-card-desc">Validation des comptes de l'exercice</div>
    </div>
  </button>
  <!-- × 3 -->
</div>
```

**CSS additions:**
```css
.wiz-template-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-bottom: 1.5rem;
}

.wiz-template-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  padding: 14px;
  background: var(--color-surface);
  border: 1.5px solid var(--color-border);
  border-radius: 10px;
  cursor: pointer;
  text-align: left;
  transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
}

.wiz-template-card:hover {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--color-primary-glow);
  transform: translateY(-1px);
}

.wiz-template-card-icon {
  width: 32px; height: 32px;
  border-radius: var(--radius);
  background: var(--color-primary-subtle);
  display: flex; align-items: center; justify-content: center;
  color: var(--color-primary);
}

.wiz-template-card-title {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-text-dark);
}

.wiz-template-card-desc {
  font-size: 0.75rem;
  color: var(--color-text-muted);
  line-height: 1.3;
}
```

### HUB-01: Identity Banner — Session Type Badge + Status

**Before:**
- Icon + title + meta row
- No session type displayed
- No status badge

**After:**
- Add a `<ag-badge>` or styled `<span>` showing session type ("AG Ordinaire") and status ("En préparation")
- These are populated by JS in `applySessionToDOM()` — requires adding two IDs to the HTML and two lines to the JS function

**HTML addition in hub.htmx.html:**
```html
<div class="hub-identity-info">
  <div class="hub-identity-title-row">
    <div class="hub-identity-date" id="hubTitle">Chargement…</div>
    <div class="hub-identity-badges">
      <span class="badge badge--neutral" id="hubTypeTag">–</span>
      <span class="badge badge--info" id="hubStatusTag">En préparation</span>
    </div>
  </div>
  <div class="hub-identity-meta" id="hubMeta">…</div>
</div>
```

**CSS additions in hub.css:**
```css
.hub-identity-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 0.25rem;
}
```

### HUB-02: Status Bar — Visible Step Labels

**Before:** 8px colored bars, labels hidden (font-size: 0), hover-only

**After:**
- Height 36px
- Labels visible always, truncated with ellipsis if needed
- Active segment: slightly taller (40px), bold text
- Pending segments: `var(--color-neutral-subtle)` with muted text

**CSS changes in hub.css:**
```css
/* Override segment height */
.hub-status-bar {
  align-items: center;
  gap: 2px;
  padding: 6px;
}

.hub-bar-segment {
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.hub-bar-segment.active {
  height: 40px;
}

/* Show labels always */
.hub-bar-label {
  font-size: 11px !important;
  font-weight: 600;
  color: rgba(255,255,255,0.9);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 90%;
  padding: 0 4px;
}

/* Pending bar label */
.hub-bar-segment:not(.done):not(.active) .hub-bar-label {
  color: var(--color-text-muted);
}
```

### HUB-03: Vertical Stepper — Connector Cleanup

**Before:**
- Dual connector: both `::before` pseudo-element AND explicit `.hub-step-line` div
- "← Vous êtes ici" at 11px italic

**After:**
- Remove `::before` connector reliance — use only the `.hub-step-line` div (already rendered by JS)
- Increase `.hub-step-line` visibility: `width: 3px`, height `24px`
- Active indicator: replace "← Vous êtes ici" with a colored dot + "Étape en cours" text at 12px primary bold
- Pending steps: `opacity: 0.5` on the number circle to clearly differentiate from done/active

**CSS changes in hub.css:**
```css
/* Disable the ::before connector (use only hub-step-line divs) */
.hub-step-row::before {
  display: none;
}

/* Cleaner explicit connector */
.hub-step-line {
  width: 3px;
  height: 24px;
  margin-left: 14px;   /* aligns under center of 32px circle */
  border-radius: 2px;
  background: var(--color-border);
}

.hub-step-line.done {
  background: var(--color-success);
}

/* Active step indicator */
.hub-step-here {
  font-size: 0.75rem;
  color: var(--color-primary);
  font-weight: 700;
  margin-top: 2px;
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Pending step dimming */
.hub-step-row:not([aria-current]):not(.done) .hub-step-num {
  opacity: 0.45;
}
```

### HUB-04: Quorum Bar — Hero Treatment

**Before:**
- `ag-quorum-bar` inside a left-bordered card wrapper
- Track height 12px (inside shadow DOM)
- Positioned below the checklist

**After:**
- Section gets prominent header with large percentage number
- Large bold percentage above the bar
- Color-coded clearly: green = reached, amber = close, red = below
- Tooltip on the section title

**HTML change in hub.htmx.html:**
```html
<div class="hub-quorum-hero" id="hubQuorumSection" style="display:none;">
  <div class="hub-quorum-hero-header">
    <div class="hub-quorum-hero-left">
      <span class="hub-quorum-hero-label">
        Quorum
        <ag-popover trigger="click" position="top" width="280">
          <button slot="trigger" class="field-info-btn" type="button" aria-label="En savoir plus sur le quorum">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
          </button>
          <div slot="content">
            <strong>Quorum</strong>
            <p>Nombre minimum de membres requis pour que les votes soient légalement valides. Sans quorum, l'assemblée ne peut délibérer.</p>
          </div>
        </ag-popover>
      </span>
    </div>
    <div class="hub-quorum-hero-pct" id="hubQuorumPct">–%</div>
  </div>
  <ag-quorum-bar id="hubQuorumBar" current="0" required="0" total="0"></ag-quorum-bar>
</div>
```

**CSS additions in hub.css:**
```css
.hub-quorum-hero {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: var(--space-card);
  box-shadow: var(--shadow-md);
}

.hub-quorum-hero-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.hub-quorum-hero-label {
  font-size: 0.6875rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-primary);
  display: flex;
  align-items: center;
  gap: 6px;
}

.hub-quorum-hero-pct {
  font-family: var(--font-mono);
  font-size: 2rem;
  font-weight: 800;
  color: var(--color-text-dark);
  letter-spacing: -0.03em;
  transition: color 0.3s ease;
}

.hub-quorum-hero-pct.reached { color: var(--color-success); }
.hub-quorum-hero-pct.partial  { color: var(--color-warning); }
.hub-quorum-hero-pct.critical { color: var(--color-danger); }
```

**JS change in hub.js `renderQuorumBar()`:**
```js
// Add after bar.setAttribute calls:
var pctEl = document.getElementById('hubQuorumPct');
if (pctEl && total > 0) {
  var pct = Math.round((current / total) * 100);
  pctEl.textContent = pct + '%';
  var statusCls = current >= required ? 'reached' : (pct >= Math.round(required/total*100)*0.8 ? 'partial' : 'critical');
  pctEl.className = 'hub-quorum-hero-pct ' + statusCls;
}
```

### HUB-05: Checklist — Card Items with Status Badges

**Before:**
- Simple flex rows, `border-bottom` separator only
- "À faire" badge at 11px
- No description under label
- Done items: strikethrough only

**After:**
- Each item gets subtle card-like treatment with background on hover
- "À faire" badge: larger (12px bold), amber background
- Done items: muted entire row (not just label), with a "Fait" badge in green
- Blocked items: clearer blocked state with warning icon

**CSS changes in hub.css:**
```css
/* Elevate item padding */
.hub-check-item {
  padding: 12px 10px;
  border-radius: var(--radius);
  margin-bottom: 4px;
  border-bottom: none;   /* Remove border-bottom, use margin instead */
  border: 1px solid transparent;
}

.hub-check-item:hover {
  border-color: var(--color-border-subtle);
  background: var(--color-bg-subtle);
}

.hub-check-item.done {
  opacity: 1;  /* Override 0.7 */
  background: var(--color-success-subtle);
  border-color: var(--color-success-border);
}

.hub-check-item.done .hub-check-icon {
  width: 24px;
  height: 24px;
}

/* "À faire" badge — larger */
.hub-check-todo {
  font-size: 0.75rem;   /* up from 11px */
  padding: 3px 10px;
  font-weight: 700;
}

/* "Fait" badge for done items */
.hub-check-done-badge {
  font-size: 0.75rem;
  padding: 3px 10px;
  background: var(--color-success-subtle);
  color: var(--color-success);
  border-radius: var(--radius-sm);
  font-weight: 700;
  flex-shrink: 0;
}
```

**JS change in hub.js `renderChecklist()`:**
Add `hub-check-done-badge` span inside done items to replace the empty todo slot:
```js
// In the done branch of the IIFE:
if (checked) return '<span class="hub-check-done-badge">Fait</span>';
```

---

## Common Pitfalls

### Pitfall 1: ag-quorum-bar Shadow DOM

**What goes wrong:** Trying to style `.quorum-track`, `.quorum-fill`, etc. from hub.css — CSS does not pierce Shadow DOM.
**Why it happens:** The component uses `attachShadow({ mode: 'open' })`, styles are inside `shadowRoot.innerHTML`.
**How to avoid:** Use only the wrapper div approach (described in HUB-04 above). Style the `.hub-quorum-hero` container, not the component internals. The component handles its own track/fill rendering.
**Warning signs:** Applying `.hub-quorum-section .quorum-track { height: 24px }` has zero effect.

### Pitfall 2: Wizard `::after` Pseudo-element Conflict

**What goes wrong:** Adding a connector line using `::after` on `.wiz-step-item` overwrites the existing active underline bar.
**Why it happens:** `.wiz-step-item.active::after` is already used for the bottom accent line.
**How to avoid:** Use `box-shadow: inset -1px 0 0 var(--color-border)` on `wiz-step-item:not(:last-child)` for the connector, or use a separate `.wiz-step-connector` HTML element.

### Pitfall 3: Hub Step-Row Connector Double-Rendering

**What goes wrong:** Both the `::before` pseudo-element AND the `.hub-step-line` div are rendered as connectors — two lines appear.
**Why it happens:** hub.css uses `::before` for the connector AND hub.js renders `<div class="hub-step-line">` between each step.
**How to avoid:** Disable `hub-step-row::before` by setting `display: none` in the new hub.css rules. Use only the `.hub-step-line` div approach (controlled by JS, easier to style based on done/active state).

### Pitfall 4: Inline Style Conflicts on hub-action-icon

**What goes wrong:** Trying to restyle the action icon color via CSS class — it won't work.
**Why it happens:** hub.js applies `icon.style.background = step.color` as an inline style, which has higher specificity than any CSS class.
**How to avoid:** Leave the dynamic background on hub-action-icon to JS (it's intentional — each step has a different color). Style the border-radius, size, and shadow only.

### Pitfall 5: `.row` Utility Creates Cramped Field Layout

**What goes wrong:** Step 1 uses `.row` class for date + time + type — 3 fields in one horizontal flex row with only 12px gap (`--space-3`).
**Why it happens:** `design-system.css` defines `.row { display: flex; align-items: center; gap: var(--space-3); }` — very tight.
**How to avoid:** Override `.wiz-step-body .row` to use `gap: var(--space-4)` (16px) at minimum, and consider splitting to 2-column grid for type + date, with time in its own line.

### Pitfall 6: Step Counter Requires JS Update

**What goes wrong:** Adding the `step-nav-counter` HTML element without updating wizard.js — the counter shows "Étape 1 sur 4" always.
**Why it happens:** The wizard steps are shown/hidden by JS (via `showStep(n)`), so the counter must be updated by JS too.
**How to avoid:** Update `updateStepper()` or `showStep()` in wizard.js to also set the counter text: `counter.textContent = 'Étape ' + (n+1) + ' sur 4'`.

---

## Code Examples

### Field Info Button (consistent with Phase 35 `tour-trigger-btn`)

```css
/* wizard.css addition */
.field-info-btn {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: none;
  background: transparent;
  color: var(--color-text-muted);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  transition: color 0.15s ease, background 0.15s ease;
  flex-shrink: 0;
  vertical-align: middle;
}

.field-info-btn:hover {
  color: var(--color-primary);
  background: var(--color-primary-subtle);
}
```

### Hub Checklist Progress Bar — Thicker

```css
/* hub.css override */
.hub-checklist-bar {
  height: 12px;  /* up from 8px */
  margin-bottom: 16px;
}
```

### Wizard Section Redesign — Remove Nested Card Feel

Current `.wiz-section` creates "card inside card" (bg-subtle box inside the step card). The fix:

```css
/* wizard.css override */
.wiz-section {
  background: transparent;
  border: none;
  border-top: 1px solid var(--color-border-subtle);
  border-radius: 0;
  padding: 1.5rem 0;   /* Remove horizontal padding, inherit from wiz-step-body */
  margin-bottom: 0;
}

.wiz-section:first-of-type {
  border-top: none;
}

.wiz-section-title {
  /* Keep as-is — uppercase primary label is good section header */
  margin-bottom: 1.25rem;
}
```

### Remove Redundant `.wf-step` Step Header Inside Card

The `.wf-step` element (showing step number + title inside the form card) is redundant when the top stepper is visible. It adds noise:

```css
/* wizard.css — hide the redundant in-card step header */
.wf-step {
  display: none;
}
```

This frees 80px of vertical space at the top of each step card.

---

## State of the Art

| Old Approach | Current Approach | Target (Phase 36) | Impact |
|--------------|------------------|--------------------|--------|
| In-card step header (`.wf-step`) | Shown inside card body | Hidden — stepper above is enough | Removes redundant element |
| Status bar 8px thin | Decorative accent bar | 36px labeled step bar | Informative, scannable |
| Template buttons as inline chips | `btn btn-sm btn-ghost` row | Grid of card-style selectors | Linear-quality template picker |
| Checklist items as plain rows | `border-bottom` separated | Individual card-with-badge items | Clear status at a glance |
| Quorum as buried component | Inside wrapper card below checklist | Hero section with large % number | Most important info = most prominent |
| Form labels uppercase 12px | `text-transform: uppercase` | 14px semibold, no uppercase | Less technical, more approachable |
| Step counter: absent | No step counter anywhere | "Étape X sur 4" in step-nav | Reduces anxiety, shows progress |

---

## Validation Architecture

> nyquist_validation: not explicitly configured — treating as enabled.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Visual regression (manual) — no automated test framework detected for frontend CSS |
| Config file | None |
| Quick run command | Open browser at `http://localhost/wizard.htmx.html` and `http://localhost/hub.htmx.html?id=TEST_ID` |
| Full suite command | Visual inspection across all 4 wizard steps and all 6 hub steps |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CORE-02 | Wizard stepper shows active/done/pending states visually | Manual visual | Open wizard, navigate steps | ✅ (visual check) |
| CORE-02 | Every complex field label has a tooltip trigger | Manual visual | Inspect step 1 quorum + type fields | ❌ Wave 0 — HTML must be added |
| CORE-02 | Step-nav footer shows "Étape X sur 4" counter | Manual visual | Navigate wizard steps | ❌ Wave 0 — HTML + JS must be added |
| CORE-02 | Template selector shows card grid not inline buttons | Manual visual | Open step 3 | ❌ Wave 0 — HTML must be restructured |
| CORE-04 | Hub status bar shows step labels at all times | Manual visual | Open hub page | ✅ (CSS change only) |
| CORE-04 | Quorum bar shows large percentage number | Manual visual | Open hub with a session that has members | ❌ Wave 0 — HTML wrapper + JS line needed |
| CORE-04 | Checklist items show "Fait"/"À faire" badges | Manual visual | Open hub page | ❌ Wave 0 — JS renderChecklist() change |
| CORE-04 | Session type badge visible in identity banner | Manual visual | Open hub page | ❌ Wave 0 — HTML + JS `applySessionToDOM()` change |

### Sampling Rate

- **Per task commit:** Open both pages in browser, verify target element changed visually
- **Per wave merge:** Walk through all 4 wizard steps + all 6 hub steps manually
- **Phase gate:** Before `/gsd:verify-work`, confirm screenshots show clear before/after contrast

### Wave 0 Gaps

- [ ] `wizard.htmx.html` — Add `wiz-step-subtitle` span to app-header (CORE-02)
- [ ] `wizard.htmx.html` — Replace template buttons with `wiz-template-grid` card structure (CORE-02)
- [ ] `wizard.htmx.html` — Add `ag-popover` tooltip to quorum and majority fields in step 1 (CORE-02)
- [ ] `wizard.htmx.html` — Add `step-nav-counter` span to all 4 step-nav footers (CORE-02)
- [ ] `wizard.js` — Add `updateStepper()` step counter text update (CORE-02)
- [ ] `hub.htmx.html` — Replace `hub-quorum-section` with `hub-quorum-hero` structure (CORE-04)
- [ ] `hub.htmx.html` — Add `hub-identity-badges` row to identity banner (CORE-04)
- [ ] `hub.js` — Update `applySessionToDOM()` to populate type + status badges (CORE-04)
- [ ] `hub.js` — Update `renderQuorumBar()` to update `hubQuorumPct` element (CORE-04)
- [ ] `hub.js` — Update `renderChecklist()` to render "Fait" badge on done items (CORE-04)

---

## Sources

### Primary (HIGH confidence)
- Direct read of `public/wizard.htmx.html` — full HTML structure, all form fields, all step IDs
- Direct read of `public/hub.htmx.html` — full HTML structure, all component placements
- Direct read of `public/assets/css/wizard.css` — all current styles, specificity, pseudo-elements
- Direct read of `public/assets/css/hub.css` — all current styles, layout grid, responsive rules
- Direct read of `public/assets/js/pages/wizard.js` — step navigation logic, `updateStepper()`, `buildReviewCard()`
- Direct read of `public/assets/js/pages/hub.js` — `renderChecklist()`, `renderQuorumBar()`, `renderStepper()`, `applySessionToDOM()`
- Direct read of `public/assets/js/components/ag-quorum-bar.js` — Shadow DOM structure, confirmed CSS cannot pierce it
- Direct read of `public/assets/css/design-system.css` — all available tokens (colors, spacing, radius, fonts)
- Direct read of `.planning/phases/36-session-creation-flow/36-CONTEXT.md` — all locked decisions

### Secondary (MEDIUM confidence)
- Phase 35 patterns (gradient CTA, ag-tooltip usage, kpi-card composition) — inferred from CONTEXT.md references and design-system.css existing tokens; no direct Phase 35 CSS read but patterns are token-based and portable

---

## Metadata

**Confidence breakdown:**
- Current anatomy: HIGH — direct source inspection, line-level CSS analysis
- Redesign specs (CSS changes): HIGH — based on actual class names and property values found in source
- JS change requirements: HIGH — specific function names and logic verified in source
- Shadow DOM ag-quorum-bar limitation: HIGH — confirmed `attachShadow({ mode: 'open' })` in component source
- Phase 35 reusable patterns: MEDIUM — referenced from CONTEXT.md, tokens verified in design-system.css

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable — no framework migration, all vanilla CSS)
