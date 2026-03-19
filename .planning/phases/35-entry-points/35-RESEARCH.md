# Phase 35: Entry Points (Dashboard + Login) — Research

**Researched:** 2026-03-19
**Domain:** Visual redesign — CSS composition, micro-interactions, typography hierarchy, tooltip wiring
**Confidence:** HIGH (based on direct code inspection of all target files)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Design Philosophy**
- Reference-driven: Stripe Dashboard (depth + density), Linear (neutral canvas + data focus), Clerk (auth page polish)
- Top 1% means: No generic card grids, no uniform spacing, no AI-generated feel. Every visual choice is intentional and differentiated
- Before/after contrast: Changes must be visible immediately in the browser — no subtle token swaps
- Tooltips for guidance: Complex elements get hover tooltips explaining their purpose — no guided tours

**Dashboard Visual Redesign (CORE-01)**
- KPI strip: 4 KPI cards with clear visual hierarchy — primary metric large, secondary small, trend indicator (up/down arrow), semantic color for status. Cards differentiated by content, not just icon color
- Session list: Vertical card list with clear status badges, date prominence, action buttons visible on hover. Each session card communicates its lifecycle state instantly
- Aside/quick actions: 280px sticky sidebar with actionable shortcuts — not a dumping ground for links. Each action has an icon + label + tooltip
- Visual hierarchy: Page title (Fraunces h1) → KPI strip → session list → aside. Eye flow is top-left to bottom-right, Z-pattern
- Whitespace: Generous padding between sections (--space-section = 48px), tighter within cards (--space-card = 24px). The page should breathe
- Typography: KPI numbers in JetBrains Mono (scannable), session titles in Bricolage Grotesque semibold, metadata in regular weight at smaller size
- Color for signal: Success/warning/danger only for status communication, never decorative. Neutral stone palette for chrome
- Hover states: KPI cards lift subtly on hover, session cards show action buttons on hover, sidebar items highlight with bg change
- Empty states: When no sessions exist, show a clear CTA with illustration/icon — not just "Aucune session"
- Responsive: At 1024px aside stacks below, at 768px KPI grid goes 2-col

**Login Page Visual Redesign (SEC-01)**
- Centered card: Single card, max-width 400px, vertically centered with subtle shadow (shadow-lg)
- Branding: AG-VOTE logo/wordmark above the card, Fraunces display font for the product name
- Form fields: Full-width inputs with proper label placement (above field, 14px semibold), generous vertical spacing between fields
- CTA button: Full-width primary button, 44px height, prominent gradient — the most visually dominant element on the page
- Background: Subtle warm gradient or the three-depth model applied — not flat white
- Trust signals: "Plateforme sécurisée" or similar subtle indicator below the form
- Error states: Clear red border + message below field, not just a toast
- Dark mode: Equally polished — card on dark surface, inputs with raised background
- Micro-interactions: Focus ring animation on field entry, button hover gradient shift, subtle form card entrance animation on page load

**UX Requirements (UX-01, UX-02)**
- Every KPI card tooltip explains what the metric means
- Dashboard action buttons have descriptive tooltips
- Login field labels are self-explanatory — no tooltip needed for standard auth fields
- Every interactive element has a clear hover/focus state — nothing feels dead
- Top 1% quality: composition and spacing feel intentional, not programmatic

### Claude's Discretion
- Exact gradient values for login background
- Whether KPI cards use icons or just numbers/labels
- Session card layout details (horizontal vs vertical metadata)
- Exact tooltip positioning (top/bottom/right)
- Animation durations and easing for micro-interactions
- Whether to add a subtle pattern or texture to login background

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| UX-01 | Every page is self-explanatory — tooltips on complex elements, contextual help via hover, no guided tours | ag-tooltip component available, wraps any element, supports top/bottom position |
| UX-02 | Every page achieves top 1% design quality — composition, typography, whitespace, visual hierarchy, micro-interactions | All primitive tokens available; font stack (Fraunces, Bricolage Grotesque, JetBrains Mono) already loaded |
| CORE-01 | Dashboard — redesign visuel complet (KPI, session list, aside, densité, typographie, guidance tooltips) | Full CSS audit complete; all target classes identified with before-state documented |
| SEC-01 | Login — redesign visuel (card centré, branding, champs, CTA) | login.css fully audited; all classes documented with specific before→after specs |
</phase_requirements>

---

## Summary

Phase 35 is a pure CSS visual redesign — no new HTML structure, no new JavaScript, no new components. All the infrastructure is in place from v4.1: the three-depth background model, the design token system, the font stack, the ag-tooltip and ag-badge components, the session-card renderer in dashboard.js. What is missing is visible visual quality.

The current state of both pages is "functionally correct but visually generic." The KPI cards are centered boxes with a number and a label — no hierarchy, no font distinction, no icon differentiation. Session cards are minimal rows with an 8px status dot and truncated text — no badge, no date prominence, no hover-reveal CTA. The login page has a flat white card on a warm grey background with standard blue input focus rings — technically correct but indistinguishable from a default Bootstrap form.

This phase produces dramatic, immediately visible changes by targeting specific CSS rules across `pages.css`, `login.css`, and `design-system.css`. Every change is measurable: a specific class gets specific new values. No infrastructure, no token reshuffling — just high-impact visual upgrades to real rendered elements.

**Primary recommendation:** Work element by element (KPI cards → session cards → aside → login card → login background), applying concrete CSS specs derived from the before-state audit below. The planner should create one task per visual element group so each task is visually self-contained and reviewable.

---

## Current State Audit (Before)

This section is the core research output. Every target element has been directly inspected in source files.

### Dashboard: KPI Cards

**HTML structure** (dashboard.htmx.html lines 91–112):
```html
<div class="kpi-grid" id="kpiRow">
  <a href="..." class="kpi-card" aria-label="AG a venir">
    <svg .../>                          <!-- icon, inline, colored with stroke="var(--color-primary)" -->
    <div class="kpi-value primary" id="kpiSeances">-</div>
    <div class="kpi-label">AG à venir</div>
  </a>
  <!-- × 3 more cards -->
</div>
```

**Current CSS** (design-system.css lines 2444–2474 and pages.css lines 1018–1028):
- `.kpi-grid`: `grid-template-columns: repeat(4, 1fr)`, `gap: var(--space-card)` = 24px
- `.kpi-card`: `background: var(--color-surface-raised)`, `border: 1px solid var(--color-border)`, `border-radius: var(--radius-lg)` = 8px, `padding: var(--space-5)` = 20px, `text-align: center`
- `.kpi-card:hover`: `transform: translateY(-2px)`, `box-shadow: var(--shadow-md)`
- `.kpi-value`: `font-size: var(--text-3xl)` = 1.875rem (pages.css overrides design-system.css 4xl to 3xl), `font-weight: 700`, `font-family: var(--font-sans)` (inherited, no mono override)
- `.kpi-label`: `font-size: var(--text-xs)` = 12px, `color: var(--color-text-muted)`, `margin-top: 0.25rem`
- SVG icon: 18×18px inline, `stroke="var(--color-primary)"` — same color for all 4 cards except en-cours (danger) and convoc (warning)
- Card layout: center-aligned column — icon, then number, then label, all stacked

**Problems to fix:**
1. Number not in JetBrains Mono — defeats the "scannable data" intent
2. Icon is too small (18px) relative to the 30px number — hierarchy is backwards
3. Center alignment creates weak visual anchor — left-aligned content reads better for data
4. No visual differentiation between cards — all look the same except text color
5. `text-align: center` means icon + number + label share no grid; they just stack
6. No tooltip — user can't understand what "Convoc. en attente" means without context
7. `var(--color-text-secondary)` = `#151510` for the label — too dark for secondary info

### Dashboard: Urgent Action Card

**HTML structure** (dashboard.htmx.html lines 74–88):
```html
<a href="..." class="card urgent-card" id="actionUrgente">
  <div class="urgent-card-body">
    <div class="urgent-card-icon"><!-- SVG --></div>
    <div class="session-row-info">
      <div class="urgent-card-label">Action urgente</div>
      <div class="urgent-card-title">Envoyer les convocations</div>
      <div class="urgent-card-sub">Chargement…</div>
    </div>
    <div class="urgent-card-chevron"><!-- chevron SVG --></div>
  </div>
</a>
```

**Current CSS** (pages.css lines 938–993):
- `.urgent-card`: `border: 2px solid var(--color-danger)`, `padding: 14px`, `margin-bottom: 14px`
- `.urgent-card-icon`: `width/height: 48px`, `background: var(--color-danger)`, `border-radius: 12px`
- `.urgent-card-label`: `font-size: 12px`, `font-weight: 700`, `color: var(--color-danger)`, `text-transform: uppercase`, `letter-spacing: 1px`
- `.urgent-card-title`: `font-size: var(--text-md)` = 16px, `font-weight: 800`
- Card only renders when `live.length > 0` (dashboard.js line 121) — hidden otherwise

**Assessment:** This card is actually well-structured. The danger border + icon background communicates urgency. Main improvement: the card should only be visible when a session is live (already works), and the visual weight should be high enough to demand attention without being aggressive. Minor polish needed.

### Dashboard: Session Cards (JS-rendered)

**Rendered by** dashboard.js `renderSessionCard()` (lines 59–74). The JS renderer controls all session card markup.

**Current rendered HTML:**
```html
<div class="session-card [session-card--live] [session-card--muted]" onclick="...">
  <div class="session-card-status-dot" style="background: [var(--color-...)]"></div>
  <div class="session-card-info">
    <div class="session-card-title">Session title</div>
    <div class="session-card-meta">12 janvier — 45 membres — 3 résolutions</div>
  </div>
  <a class="btn btn-sm btn-secondary session-card-cta [btn-success]" href="...">
    [<span class="pulse-dot"></span>] CTA label
  </a>
</div>
```

**Current CSS** (design-system.css lines 4960–5041):
- `.session-card`: `display: flex`, `align-items: center`, `gap: 12px`, `padding: 12px 16px`, `border-radius: var(--radius-md)` = 6px, `background: var(--color-surface)`, `border: 1px solid var(--color-border-subtle)`
- `.session-card:hover`: `border-color: var(--color-border)`, `box-shadow: var(--shadow-sm)` — very subtle
- `.session-card-status-dot`: `width/height: 8px`, `border-radius: 50%` — tiny colored dot only
- `.session-card-title`: `font-weight: 600`, `font-size: 0.9375rem` = 15px, `white-space: nowrap`, `text-overflow: ellipsis`
- `.session-card-meta`: `font-size: 0.8125rem` = 13px, `color: var(--color-text-muted)`, `margin-top: 2px`
- `.session-card--live`: `border-left: 3px solid var(--color-success)`, `background: color-mix(in srgb, var(--color-success) 4%, var(--color-surface))`
- CTA button: always visible in the HTML (not hover-reveal)

**Critical problems:**
1. Status communication = only a tiny 8px dot — nearly invisible for colorblind users and too small to read quickly
2. `ag-badge` component exists but is NOT used in session cards — the badge system is orphaned from dashboard
3. Date and meta are run together as a single string ("12 janvier — 45 membres — 3 résolutions") — no visual hierarchy
4. CTA button is always visible but per the design brief it should appear on hover for a cleaner resting state
5. The `.session-card:hover` border change is too subtle — users don't get a clear hover feedback

**Note:** The JS renderer in dashboard.js must be updated alongside CSS to add `<ag-badge>` tags and restructure meta layout. This is a task dependency the planner must sequence.

### Dashboard: Panel Layout (Sessions + Tasks)

**HTML structure** (dashboard.htmx.html lines 121–153):
```html
<div class="card dashboard-panel">
  <div class="flex-between">
    <div class="card-title"><!-- icon + "Séances" --></div>
    <a class="btn btn-sm btn-ghost" href="...">Tout voir →</a>
  </div>
  <div id="prochaines"><!-- JS-rendered session cards --></div>
</div>
<div class="card dashboard-panel">
  <div class="card-title"><!-- icon + "Tâches en attente" --></div>
  <div id="taches"><!-- JS-rendered task rows --></div>
</div>
```

**Current CSS** (pages.css lines 1074–1102, app.css lines 796–798):
- `.dashboard-panel`: `padding: 16px`
- `.dashboard-panel .flex-between`: `margin-bottom: 8px`
- `.card-title`: `font-size: var(--text-sm)`, `font-weight: var(--font-bold)`, `text-transform: uppercase`, `letter-spacing: .7px`, `color: var(--color-text)`

**Assessment:** Panel padding (16px) is tight. The `card-title` uppercase small-caps style is appropriate for section labels. Main improvement: increase panel padding to `var(--space-card)` = 24px and add a border-bottom divider between the card header area and the list content.

### Dashboard: Aside (Quick Actions)

**HTML structure** (dashboard.htmx.html lines 157–186):
```html
<aside class="dashboard-aside">
  <div class="card-title dashboard-shortcuts-title">Accès rapides</div>
  <a href="..." class="card shortcut-card">
    <div class="shortcut-card-icon accent"><!-- SVG --></div>
    <div class="shortcut-card-text">
      <div class="shortcut-card-title">Créer une séance</div>
      <div class="shortcut-card-sub">Assistant en 4 étapes</div>
    </div>
  </a>
  <!-- × 2 more shortcut cards -->
</aside>
```

**Current CSS** (pages.css lines 1039–1177):
- `.dashboard-aside`: `position: sticky`, `top: 80px`, `background: var(--color-surface)`, `border-radius: var(--radius-lg)`, `padding: var(--space-card)` = 24px
- `.shortcut-card`: `padding: 14px`, `display: flex`, `align-items: center`, `gap: 12px`, `transition: transform, box-shadow`
- `.shortcut-card:hover`: `transform: translateY(-2px)`, `box-shadow: var(--shadow-md)`
- `.shortcut-card-icon`: `width/height: 40px`, `border-radius: 10px`, `.accent` → `background: var(--color-primary)`, `.danger` → `background: var(--color-danger)`, `.muted` → `background: var(--color-text-muted)`
- `.shortcut-card-title`: `font-weight: 700`, `font-size: var(--text-base)` = 14px
- `.shortcut-card-sub`: `color: var(--color-text-muted)`, `font-size: var(--text-sm)` = 13px, `margin-top: 2px`
- `margin-bottom: var(--space-3)` = 12px between cards

**Assessment:** Shortcut cards have good bones. Problems: no tooltip on any shortcut card, hover effect (lift + shadow) duplicates the kpi-card hover without feeling distinct, the `.muted` grey icon for "Consulter le suivi" is weaker than the other two, title font size = 14px matches body text (should be slightly larger or heavier), and there is no border or divider between the aside title and the cards.

### Dashboard: Page Header

**Current state** (dashboard.htmx.html lines 34–68, design-system.css):
- `<h1 class="page-title">` uses `var(--type-page-title-font)` = Fraunces (display font) — already correct
- `<p class="page-sub">Vue d'ensemble</p>` — small, muted text — correct
- Header contains: breadcrumb + h1 + page-sub on left, help popover + "Nouvelle séance" button on right
- `.bar` span before the title icon: this is a thin colored bar accent element

**Assessment:** Header is already well-structured from v4.1. No major changes needed except ensuring the page title hierarchy reads Fraunces correctly at the correct weight.

### Login Page: Full Audit

**HTML structure** (login.html):
```html
<main class="login-page">
  <div class="login-card">
    <div class="login-brand">
      <h1>AG-VOTE</h1>
      <p>Gestion des assemblées délibératives</p>
    </div>
    <form class="login-form">
      <div>
        <label for="email">Adresse email</label>
        <input type="email" ...>
      </div>
      <div>
        <label for="password">Mot de passe</label>
        <div class="field-wrap">
          <input type="password" ...>
          <button class="toggle-visibility"><!-- eye icon --></button>
        </div>
      </div>
      <div id="errorBox" class="login-error">...</div>
      <div id="successBox" class="login-success">...</div>
      <button type="submit" class="login-btn">Se connecter <span class="login-spinner"></span></button>
      <div class="login-forgot">...</div>
    </form>
    <div class="login-footer">
      <a href="...">Retour à l'accueil</a>
      <button class="login-theme-toggle"><!-- theme icon --></button>
    </div>
  </div>
</main>
```

**Current CSS** (login.css — complete file audited):

| Element | Current | Problem |
|---------|---------|---------|
| `.login-page` | `background: var(--color-bg)` = flat warm grey `#EDECE6` | No depth, flat page behind card |
| `.login-card` | `max-width: 420px`, `background: var(--color-surface-raised)` = white, `border: 1px solid`, `border-radius: var(--radius-xl)` = 12px, `padding: var(--space-8)` = 32px, `box-shadow: var(--shadow-lg)` | Max-width 420px vs spec 400px, card is correct structure |
| `.login-brand h1` | `font-size: var(--text-2xl)` = 24px, `font-weight: var(--font-bold)`, `font-family` inherited (Bricolage Grotesque) | Not using Fraunces display font, no logo mark |
| `.login-brand p` | `font-size: var(--text-sm)` = 13px, `color: var(--color-text-secondary)` | OK |
| `.login-form label` | `font-size: var(--text-sm)` = 13px, `font-weight: var(--font-medium)` = 500 | Should be 14px semibold (600) |
| Input fields | `padding: var(--space-3)` = 12px, `border-radius: var(--radius-md)` = 6px, `background: var(--color-bg)` | Padding correct, but no height lock at 40–44px, no error-state red border |
| Input focus | `border-color: var(--color-primary)`, `box-shadow: 0 0 0 3px var(--color-primary-subtle)` | OK but could be more prominent |
| `.login-btn` | `padding: var(--space-3)` = 12px vertical, `background: var(--color-primary)` = flat solid blue, `font-size: var(--text-base)`, `font-weight: var(--font-semibold)`, `border-radius: var(--radius-md)` | No gradient, no minimum height (44px WCAG), hover = opacity 0.9 (weak) |
| `.login-btn:hover` | `opacity: 0.9` | No gradient shift, no transform — button hover feels dead |
| No trust signal | — | Nothing below the form communicating security |
| No entrance animation | — | Page appears instantly — missed micro-interaction opportunity |
| `.login-error` | Shows red bg + text when `.visible` | Missing: red border on the affected input, error positioned inline |
| Dark mode | Card gets `var(--color-surface-raised)` = `#1E2438`, inputs get `var(--color-bg)` = `#0B0F1A` | Works structurally but no dark-mode-specific tweaks for polish |

---

## Standard Stack

### Core (all already in project — no new installs needed)

| Asset | Current State | Role in This Phase |
|-------|--------------|-------------------|
| `public/assets/css/design-system.css` | Full token system + base components | Source of all tokens to reference |
| `public/assets/css/pages.css` | Dashboard layout + session/task/shortcut CSS | Primary edit target for dashboard |
| `public/assets/css/login.css` | Login page styles | Primary edit target for login |
| `public/assets/js/pages/dashboard.js` | JS renderer for session cards + KPIs | Must be updated to add ag-badge and restructure meta |
| `public/assets/js/components/ag-tooltip.js` | `<ag-tooltip text="..." position="top/bottom">` | Use to wrap KPI cards and shortcut cards |
| `public/assets/js/components/ag-badge.js` | `<ag-badge variant="success/warning/...">` | Use in session card renderer for status |

**No new packages. No build step. Pure CSS custom properties + vanilla JS.**

### Font Stack (already loaded in both pages)

```
Bricolage Grotesque — body, UI, titles
Fraunces — display headings (h1, login brand)
JetBrains Mono — numeric data (KPI values)
```

All three loaded via Google Fonts in `<head>` of both pages. Use them directly.

---

## Architecture Patterns

### Recommended Edit Strategy

```
pages.css                  → All dashboard-specific visual changes
login.css                  → All login-specific visual changes
design-system.css          → ONLY if session-card changes needed (they live here)
dashboard.js               → JS renderer changes (add ag-badge, restructure meta)
dashboard.htmx.html        → Add ag-tooltip wrappers around KPI cards + shortcut cards
login.html                 → Add trust signal markup, possibly add entrance animation class
```

### Pattern 1: KPI Card Redesign (Left-aligned, Mono numbers, Differentiated)

**What:** Transform centered text boxes into left-aligned data cards with number hierarchy, mono font, and icon that visually differentiates each metric.

**Current → Target:**

```css
/* BEFORE (pages.css ~line 1109) */
.kpi-card .kpi-value {
  font-size: var(--text-3xl, 1.875rem);
  font-weight: 700;
  line-height: 1;
}
.kpi-card .kpi-label {
  font-size: var(--text-xs, 0.75rem);
  color: var(--color-text-muted);
  margin-top: 0.25rem;
}

/* AFTER */
.kpi-card {
  text-align: left;                        /* break from centered layout */
  padding: var(--space-6);                 /* 24px — more breathing room */
  display: flex;
  flex-direction: column;
  gap: var(--space-3);                     /* 12px between sections */
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);         /* 12px — more modern */
  transition: var(--transition-shadow), transform var(--duration-moderate) var(--ease-standard);
}
.kpi-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-lg);
  border-color: var(--color-border-strong);
}
.kpi-card .kpi-icon {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
/* Per-card icon backgrounds for differentiation */
.kpi-card:nth-child(1) .kpi-icon { background: var(--color-primary-subtle); color: var(--color-primary); }
.kpi-card:nth-child(2) .kpi-icon { background: var(--color-danger-subtle); color: var(--color-danger); }
.kpi-card:nth-child(3) .kpi-icon { background: var(--color-warning-subtle); color: var(--color-warning); }
.kpi-card:nth-child(4) .kpi-icon { background: var(--color-accent-subtle); color: var(--color-accent); }

.kpi-card .kpi-value {
  font-family: var(--font-mono);           /* JetBrains Mono — KEY CHANGE */
  font-size: var(--text-4xl);             /* 36px — restore 4xl for impact */
  font-weight: 700;
  line-height: 1;
  letter-spacing: var(--tracking-tight);
}
.kpi-card .kpi-label {
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  font-weight: var(--font-medium);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wider);  /* 0.05em — make label readable at small size */
}
```

**HTML change** — KPI card template in dashboard.htmx.html needs:
1. Add `<div class="kpi-icon"><!-- icon --></div>` as first child
2. Wrap entire card in `<ag-tooltip text="Sessions AG à venir ce mois-ci" position="bottom">` (or position="top")
3. Remove `text-align` from HTML (handled by CSS)

### Pattern 2: Session Card Visual Hierarchy (Badge + Date + Hover CTA)

**What:** Replace the minimal row (dot + text + always-visible button) with a rich card using ag-badge for status, prominent date display, and hover-reveal CTA.

**JS renderer changes** (dashboard.js `renderSessionCard()`):

```javascript
// BEFORE: status dot only
h += '<div class="session-card-status-dot" style="background:' + color + '"></div>';
// BEFORE: flat meta string
h += '<div class="session-card-meta">' + escapeHtml(dateStr) + ' — ' + count + ' membres — ' + motions + ' résolutions</div>';
// BEFORE: CTA always visible
h += '<a class="btn btn-sm btn-secondary session-card-cta"...>';

// AFTER: ag-badge for status (confidence HIGH — ag-badge variants match STATUS_COLORS exactly)
var badgeVariant = { draft: 'draft', scheduled: 'primary', frozen: 'info',
                     live: 'live', paused: 'live', closed: 'success',
                     validated: 'primary', archived: 'draft' }[s.status] || 'draft';
var badgePulse = (s.status === 'live' || s.status === 'paused') ? ' pulse' : '';
h += '<ag-badge variant="' + badgeVariant + '"' + badgePulse + '>' + escapeHtml(statusLabel[s.status] || s.status) + '</ag-badge>';

// AFTER: structured meta with date prominence
h += '<div class="session-card-meta">';
h += '  <span class="session-card-date">' + escapeHtml(dateStr) + '</span>';
h += '  <span class="session-card-meta-sep">·</span>';
h += '  <span>' + (s.participant_count || 0) + ' membres</span>';
h += '  <span class="session-card-meta-sep">·</span>';
h += '  <span>' + (s.motion_count || 0) + ' résolutions</span>';
h += '</div>';

// AFTER: CTA visible only on hover via CSS
// Add class session-card-cta--hidden to the button; CSS hover-reveals it
h += '<a class="btn btn-sm btn-secondary session-card-cta" href="...">';
// CSS handles visibility: opacity 0 at rest, 1 on .session-card:hover
```

**CSS additions** to design-system.css session-card block:

```css
/* AFTER: session card redesign */
.session-card {
  padding: 14px 16px;
  border-radius: var(--radius-lg);        /* 8px — slightly more rounded */
  gap: 14px;
  transition: var(--transition-ui);
  position: relative;
}
.session-card:hover {
  border-color: var(--color-border-strong);
  box-shadow: var(--shadow-md);            /* more visible lift on hover */
  background: var(--color-surface-raised); /* subtle elevation on hover */
}

/* Hover-reveal CTA */
.session-card-cta {
  opacity: 0;
  transform: translateX(4px);
  transition: opacity var(--duration-normal) var(--ease-standard),
              transform var(--duration-normal) var(--ease-standard);
}
.session-card:hover .session-card-cta {
  opacity: 1;
  transform: translateX(0);
}

/* Date prominence */
.session-card-date {
  font-weight: var(--font-semibold);
  color: var(--color-text);              /* darker than the rest of meta */
}

/* Separator between meta items */
.session-card-meta-sep {
  color: var(--color-border-strong);
  margin: 0 4px;
}

/* Status badge placement — flex row between badge and info */
.session-card-header {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-1);
}
```

**Session card info restructured:**
```
[ag-badge "En cours" pulse]     ← status prominently placed
Session title (semibold)        ← main content
12 janvier · 45 membres · 3 résolutions  ← meta with date bold
                                [Rejoindre →]  ← CTA appears on hover
```

### Pattern 3: Aside Shortcut Cards (Icon + Tooltip + Distinct Hover)

**What:** Replace generic lift-on-hover shortcut cards with interactive cards that have bg-change hover (not transform), clear tooltips, and a slightly larger title.

```css
/* AFTER */
.shortcut-card {
  padding: var(--space-4);               /* 16px — from 14px */
  border-radius: var(--radius-lg);
  border: 1px solid transparent;        /* border on hover only */
  transition: var(--transition-ui);
}
.shortcut-card:hover {
  transform: none;                       /* no lift — bg change instead */
  box-shadow: none;
  background: var(--color-bg-subtle);   /* subtle bg change — Linear-style */
  border-color: var(--color-border);
}
.shortcut-card-title {
  font-size: var(--text-md);            /* 16px — from 14px */
  font-weight: var(--font-semibold);    /* 600 — from 700 (less aggressive) */
}
```

**HTML change** — Wrap each shortcut card in ag-tooltip:
```html
<ag-tooltip text="Créer une nouvelle session en 4 étapes" position="right">
  <a href="..." class="card shortcut-card">...</a>
</ag-tooltip>
```

### Pattern 4: Login Page Redesign (Background + Brand + Button + Trust)

**What:** Four focused changes that together achieve Clerk-level polish.

#### 4a: Background — Gradient Depth

```css
/* AFTER — login.css */
.login-page {
  background:
    radial-gradient(ellipse 80% 50% at 50% -20%, var(--color-primary-subtle) 0%, transparent 60%),
    var(--color-bg);
  /* Creates a soft blue glow at the top — the card floats against depth */
}

[data-theme="dark"] .login-page {
  background:
    radial-gradient(ellipse 80% 50% at 50% -20%, var(--color-primary-muted) 0%, transparent 60%),
    var(--color-bg);
}
```

#### 4b: Brand — Fraunces + Logo Mark

```css
/* AFTER */
.login-brand h1 {
  font-family: var(--font-display);      /* Fraunces — KEY CHANGE */
  font-size: var(--text-3xl);            /* 30px — from 24px */
  font-weight: var(--font-bold);
  letter-spacing: var(--tracking-tight);
  color: var(--color-text-dark);         /* darkest text — from var(--color-text) */
}

/* Logo mark — small colored square before the text */
.login-brand-mark {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-lg);
  background: var(--color-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto var(--space-4);
}
```

**HTML change** (login.html login-brand div):
```html
<div class="login-brand">
  <div class="login-brand-mark">
    <svg ...><!-- existing grid icon from footer --></svg>
  </div>
  <h1>AG-VOTE</h1>
  <p>Gestion des assemblées délibératives</p>
</div>
```

#### 4c: Submit Button — Gradient + 44px + Better Hover

```css
/* AFTER */
.login-btn {
  min-height: 44px;                       /* WCAG 2.5.8 — already correct on mobile, extend to all */
  padding: var(--space-3) var(--space-4);
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%);
  border-radius: var(--radius-lg);        /* 8px — from 6px, more modern */
  font-size: var(--text-md);              /* 16px — more prominent than 14px */
  font-weight: var(--font-semibold);
  letter-spacing: 0.01em;
  transition: var(--transition-ui), transform var(--duration-moderate) var(--ease-standard);
}
.login-btn:hover:not(:disabled) {
  background: linear-gradient(135deg, var(--color-primary-hover) 0%, var(--color-primary-active) 100%);
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}
.login-btn:active:not(:disabled) {
  transform: translateY(0);
  box-shadow: none;
}
```

#### 4d: Trust Signal + Card Entrance Animation

```css
/* Add below .login-footer in login.css */
.login-trust {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  margin-top: var(--space-4);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}
.login-trust svg {
  width: 12px;
  height: 12px;
  color: var(--color-success);
}

/* Card entrance animation */
@starting-style {
  .login-card {
    opacity: 0;
    transform: translateY(12px);
  }
}
.login-card {
  transition: opacity var(--duration-deliberate) var(--ease-emphasized),
              transform var(--duration-deliberate) var(--ease-emphasized);
}
```

**HTML addition** (below `.login-footer`):
```html
<div class="login-trust">
  <svg><!-- lock icon --></svg>
  Plateforme sécurisée — données chiffrées
</div>
```

#### 4e: Error State — Red Border on Input

```css
/* Add to login.css */
.login-form .field-error input {
  border-color: var(--color-danger);
  box-shadow: 0 0 0 3px var(--color-danger-subtle);
}
.login-form .field-error-msg {
  font-size: var(--text-xs);
  color: var(--color-danger);
  margin-top: var(--space-1);
  display: none;
}
.login-form .field-error .field-error-msg {
  display: block;
}
```

**JS change** (login.js): Apply `.field-error` class to the field wrapper div when validation fails, not just show `#errorBox`. This is additive — `#errorBox` can remain for general errors.

#### 4f: Form Field — Label Weight + Input Height

```css
/* AFTER */
.login-form label {
  font-size: var(--text-sm);             /* 13px — keep */
  font-weight: var(--font-semibold);     /* 600 — from 500 */
  margin-bottom: var(--space-1-5);       /* 6px — from 4px (space-1) */
  display: block;
  color: var(--color-text-dark);         /* stronger label color */
}

.login-form input[type="email"],
.login-form input[type="password"],
.login-form input[type="text"] {
  min-height: 44px;                      /* explicit height lock */
  padding: 0 var(--space-3);
  font-size: var(--text-md);             /* 16px — prevent iOS zoom, readable */
}
```

#### 4g: Dark Mode Login Polish

```css
[data-theme="dark"] .login-card {
  background: var(--color-surface-raised);
  border-color: var(--color-border-subtle);
}

[data-theme="dark"] .login-form input {
  background: var(--color-surface);      /* slightly elevated from page bg */
  border-color: var(--color-border);
}

[data-theme="dark"] .login-form input:focus {
  background: var(--color-surface-raised);  /* raises on focus */
}
```

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Status badges | Custom colored spans | `ag-badge` component | Already has 8 variants matching STATUS_COLORS exactly |
| Tooltip | CSS pseudo-element tooltips | `ag-tooltip` component | Shadow DOM, focus-within support, keyboard accessible, already loaded |
| Entrance animation | JS timeout + class toggle | `@starting-style` CSS | Baseline 2024 support, already used in Phase 29 (design-system.css line 5062) |
| Dark mode login background | `[data-theme="dark"]` media duplicates | `var(--color-bg)` + `var(--color-primary-muted)` | Tokens auto-switch; only need one rule with vars |
| KPI number formatting | Custom JS formatter | `el.textContent = value` | Already working, no changes needed |
| Live session pulse | Custom CSS animation | `pulse-dot` class | Already defined in design-system.css lines 5013–5027 |

---

## Common Pitfalls

### Pitfall 1: Overriding kpi-card Styles in Wrong File

**What goes wrong:** `pages.css` and `design-system.css` both define `.kpi-card` and `.kpi-value`. Pages.css overrides design-system.css (it's loaded after). If you edit design-system.css `kpi-card` but pages.css still has overrides, the change has no effect.

**How to avoid:** For this phase, make all `.kpi-card` visual changes in `pages.css` (the override layer). Only touch design-system.css for `.session-card` changes (those live only there).

**Specifically:** `.kpi-card .kpi-value` in pages.css line 1109–1113 overrides design-system.css. Edit pages.css, not design-system.css, for KPI value styles.

### Pitfall 2: ag-tooltip Wrapping Links Breaks Click

**What goes wrong:** `<ag-tooltip>` uses Shadow DOM with `display: inline-flex`. Wrapping an `<a href>` card inside ag-tooltip may prevent the click from reaching the anchor because Shadow DOM intercepts events differently.

**How to avoid:** Test click pass-through. If broken, use the CSS-only `data-tooltip` pattern with `::after` pseudo-element instead, or use `pointer-events: none` on the tooltip body (already done in ag-tooltip.js line 44: `pointer-events: none`). Click should pass through. Verify in a quick browser test before committing.

**Alternative:** Wrap with ag-tooltip but use `role="none"` on the outer element and `tabindex="-1"` so the inner anchor handles all interaction.

### Pitfall 3: session-card CTA Hover State Breaks Touch Devices

**What goes wrong:** Making CTA buttons opacity:0 at rest and opacity:1 on hover means mobile users (no hover state) never see the action buttons.

**How to avoid:** Use `@media (hover: none)` to always show CTAs on touch devices:

```css
@media (hover: none) {
  .session-card-cta {
    opacity: 1;
    transform: none;
  }
}
```

### Pitfall 4: @starting-style Support in Older Safari

**What goes wrong:** `@starting-style` for the login card entrance animation is Baseline 2024. Safari 17.5+ supports it, but users on Safari < 17.5 see no animation (acceptable degradation, not a breakage).

**How to avoid:** The animation is purely cosmetic. No fallback needed. The card still appears; it just doesn't animate. The pattern is already used in this project (design-system.css line 5062+ in @layer v4).

### Pitfall 5: font-family: var(--font-mono) on KPI Numbers Breaks Number Width

**What goes wrong:** JetBrains Mono is a fixed-width font. KPI values like "0", "1", "12" will take up different visual space than body text. The card layout must accommodate this — fixed-width numbers are a feature (they align), not a bug, but the card padding must not be too tight.

**How to avoid:** Keep `padding: var(--space-6)` = 24px on kpi-cards, set `min-width: 0` on the value element, and `font-variant-numeric: tabular-nums` for perfect column alignment.

### Pitfall 6: Gradient on Login Button Doesn't Respect Dark Mode

**What goes wrong:** `linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%)` uses semantic tokens that auto-switch. In dark mode, `--color-primary` = `#3D7EF8` and `--color-primary-hover` = `#5C96FA` — the gradient becomes light→lighter which may look odd.

**How to avoid:** Test dark mode explicitly. In dark mode the gradient may need reversal: `linear-gradient(135deg, var(--color-primary-hover) 0%, var(--color-primary) 100%)` or simply use solid `var(--color-primary)` in dark mode:

```css
[data-theme="dark"] .login-btn {
  background: var(--color-primary);
}
```

### Pitfall 7: JavaScript Renderer Must Change Before CSS

**What goes wrong:** Adding `.session-card-cta { opacity: 0 }` in CSS before updating dashboard.js to include the `<ag-badge>` element means session cards look broken (no badge, CTA invisible) during the transition period.

**How to avoid:** Plan tasks so JS changes and CSS changes for session cards are in the same commit wave. The planner should not split session-card JS and CSS into separate waves.

---

## Code Examples

### Using ag-tooltip (confirmed working pattern)

```html
<!-- Source: public/assets/js/components/ag-tooltip.js -->
<ag-tooltip text="Sessions AG planifiées ce mois-ci" position="bottom">
  <a href="/meetings.htmx.html" class="kpi-card" aria-label="AG à venir">
    <!-- card contents -->
  </a>
</ag-tooltip>
```

The component script must be loaded. Add to dashboard.htmx.html:
```html
<script type="module" src="/assets/js/components/ag-tooltip.js"></script>
```
Or use the index.js bundle if already imported.

### Using ag-badge in JS renderer

```javascript
// Source: confirmed variants from ag-badge.js lines 91–136
var BADGE_VARIANTS = {
  'draft':     'draft',
  'scheduled': 'primary',
  'frozen':    'info',
  'live':      'live',
  'paused':    'live',
  'closed':    'success',
  'validated': 'primary',
  'archived':  'draft'
};
var BADGE_LABELS = {
  'draft':     'Brouillon',
  'scheduled': 'Planifiée',
  'frozen':    'Convoquée',
  'live':      'En cours',
  'paused':    'En pause',
  'closed':    'Clôturée',
  'validated': 'Validée',
  'archived':  'Archivée'
};

// In renderSessionCard:
var badgeVariant = BADGE_VARIANTS[s.status] || 'draft';
var isPulse = s.status === 'live' || s.status === 'paused';
h += '<ag-badge variant="' + badgeVariant + '"' + (isPulse ? ' pulse' : '') + '>'
  + escapeHtml(BADGE_LABELS[s.status] || s.status) + '</ag-badge>';
```

### KPI Grid — HTML After Redesign

```html
<!-- Revised kpi-card structure — wrap with ag-tooltip, restructure internals -->
<ag-tooltip text="Sessions AG à venir — planifiées ou en cours de préparation" position="bottom">
  <a href="/meetings.htmx.html" class="kpi-card" aria-label="AG à venir">
    <div class="kpi-icon">
      <svg width="18" height="18" .../>
    </div>
    <div class="kpi-value primary" id="kpiSeances">-</div>
    <div class="kpi-label">AG à venir</div>
  </a>
</ag-tooltip>
```

The `ag-tooltip` script must be added to dashboard.htmx.html's script list.

### Login Card Entrance Animation

```css
/* Source: pattern from design-system.css @layer v4, line ~5062 */
/* @starting-style is Baseline 2024 — Chrome 117+, Firefox 129+, Safari 17.5+ */
@starting-style {
  .login-card {
    opacity: 0;
    transform: translateY(16px);
  }
}
.login-card {
  transition: opacity 300ms var(--ease-emphasized),
              transform 300ms var(--ease-emphasized);
}
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| Font-family: system-ui for numbers | `font-family: var(--font-mono)` (JetBrains Mono) | Numbers become scannable data — Stripe/Vercel pattern |
| Centered KPI cards | Left-aligned with icon cluster | Creates visual hierarchy instead of uniform grid |
| Always-visible CTA buttons | Hover-reveal with opacity+transform | Cleaner resting state — Linear/Notion pattern |
| Status dot (8px circle) | `ag-badge` with label + color | Full status communication — color-blind accessible |
| Flat button color | Gradient with hover gradient shift | Depth and interaction feedback — Clerk pattern |
| Flat login background | Radial gradient depth layer | Card appears to float — Clerk/Vercel pattern |
| `@starting-style` not used on login | Card entrance animation | Page feel polished on load — already used in project for modals |

---

## Open Questions

1. **ag-tooltip click pass-through on anchor cards**
   - What we know: Shadow DOM wrapping intercepts events in some cases
   - What's unclear: Whether the current ag-tooltip implementation passes pointer events cleanly to inner `<a>` elements
   - Recommendation: Implementer should test in browser immediately after adding first ag-tooltip wrapper. If click is blocked, switch to CSS `data-tooltip` attribute pattern: `[data-tooltip]::after { content: attr(data-tooltip); ... }` — add as fallback rule in pages.css

2. **ag-badge custom element registration in dashboard.js context**
   - What we know: `ag-badge.js` is a module export, not globally auto-registered
   - What's unclear: Whether dashboard.htmx.html currently imports ag-badge.js — it imports ag-empty-state.js but not ag-badge.js explicitly
   - Recommendation: Add `<script type="module" src="/assets/js/components/ag-badge.js"></script>` to dashboard.htmx.html. The custom element must be defined before the JS renderer calls innerHTML with `<ag-badge>` tags.

3. **login.js field-level error integration**
   - What we know: login.js uses `#errorBox` for all errors via the `.visible` class toggle
   - What's unclear: Whether login.js is accessible for modification without breaking the auth flow
   - Recommendation: The field-error CSS can be added without changing login.js. The JS change (applying `.field-error` to wrapper) is additive and can be scoped to just the email/password validation path.

---

## Validation Architecture

> `workflow.nyquist_validation` key is absent from `.planning/config.json` — treated as enabled.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | None detected — visual redesign phase, browser verification |
| Config file | none |
| Quick run command | Open browser at `http://localhost:8080/dashboard.htmx.html` and `http://localhost:8080/login.html` |
| Full suite command | Visual inspection checklist (see Phase Requirements → Test Map) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CORE-01 | KPI numbers render in JetBrains Mono | visual | Open dashboard, inspect element computed font | n/a — visual |
| CORE-01 | Session cards show ag-badge with status label | visual | Open dashboard with sessions present | n/a — visual |
| CORE-01 | Shortcut cards have tooltip on hover | visual | Hover shortcut cards in browser | n/a — visual |
| CORE-01 | Empty state shows CTA when no sessions | visual | Clear localStorage, reload dashboard | n/a — visual |
| UX-01 | KPI cards show tooltip on hover | visual | Hover each KPI card | n/a — visual |
| UX-02 | Developer can point to 5 visual improvements without prompting | subjective | Peer review against pre-v4.2 screenshot | n/a — manual |
| SEC-01 | Login card uses Fraunces font for h1 | visual | Inspect element on login h1 | n/a — visual |
| SEC-01 | Login button is 44px tall | visual | Inspect element computed height | n/a — visual |
| SEC-01 | Login background has gradient depth | visual | Open login.html in both light/dark | n/a — visual |
| SEC-01 | Trust signal appears below form | visual | Scroll login card | n/a — visual |
| SEC-01 | Dark mode is visually equivalent | visual | Toggle dark mode on both pages | n/a — manual |

### Sampling Rate

- **Per task commit:** Open browser, verify target element changed visually
- **Per wave merge:** Full visual check of both pages in light + dark mode
- **Phase gate:** Visual checklist complete, no regressions on mobile layout before `/gsd:verify-work`

### Wave 0 Gaps

None — existing test infrastructure (browser) covers all phase requirements. This is a visual-only phase; there are no automated tests to write. The verifier will assess visual quality directly.

---

## Sources

### Primary (HIGH confidence)

- Direct source inspection: `public/dashboard.htmx.html` — full HTML structure read
- Direct source inspection: `public/login.html` — full HTML structure read
- Direct source inspection: `public/assets/css/login.css` — all 355 lines read
- Direct source inspection: `public/assets/css/pages.css` lines 920–1350 — dashboard CSS read
- Direct source inspection: `public/assets/css/design-system.css` — tokens (lines 1–700), card (1639–1690), kpi (2438–2474), session-card (4960–5041), transitions (480–510)
- Direct source inspection: `public/assets/js/pages/dashboard.js` — full renderer logic read
- Direct source inspection: `public/assets/js/components/ag-tooltip.js` — API and Shadow DOM implementation read
- Direct source inspection: `public/assets/js/components/ag-badge.js` — all variant names confirmed

### Secondary (MEDIUM confidence)

- Clerk design pattern (auth card with radial gradient background, centered branding, gradient CTA) — from knowledge of Clerk.com auth page design as of 2025
- Linear app pattern (bg-change hover instead of lift-transform, tight typography, neutral canvas) — from knowledge of Linear.app dashboard design
- Stripe Dashboard pattern (left-aligned KPI cards, mono numbers, data density) — from knowledge of Stripe Dashboard design

### Tertiary (LOW confidence — applies Claude's discretion areas)

- Radial gradient exact values (`ellipse 80% 50% at 50% -20%`) — recommended based on design knowledge; exact values are Claude's discretion per CONTEXT.md
- `@starting-style` login entrance animation timing (300ms, ease-emphasized) — recommended; adjust to taste

---

## Metadata

**Confidence breakdown:**
- Current state (before): HIGH — directly read from source files
- Standard stack: HIGH — files confirmed present and functional
- CSS before→after specs: HIGH — based on actual current values
- JS renderer changes: HIGH — renderSessionCard() read completely
- Design pattern sources (Clerk/Stripe/Linear): MEDIUM — knowledge-based, not URL-verified

**Research date:** 2026-03-19
**Valid until:** 2026-04-19 (30 days — CSS/HTML files are stable, no external dependencies)
