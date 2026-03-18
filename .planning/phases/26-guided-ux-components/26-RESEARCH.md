# Phase 26: Guided UX Components - Research

**Researched:** 2026-03-18
**Domain:** Vanilla JS Web Components — contextual help panels, empty states, disabled button explanations, status-aware dashboard cards
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **NO Driver.js tours** — the design guides naturally, not through sequential step tours
- **9 btnTour buttons** on dashboard, members, postsession, operator, analytics, hub, meetings, wizard → transform into contextual help panels (not sequential tours)
- The help panel shows page-relevant hints/tips — not a walkthrough
- **ag-hint (?) tooltips** acceptable but sparingly — only for genuinely technical/legal terms (majorité absolue, quorum, scrutin secret, procuration, tantième → voix) that can't be simplified by rewording
- ag-tooltip (existing component) is sufficient for technical term hints — no new ag-hint component needed
- Driver.js NOT needed — remove from the stack for this phase
- **Empty state tone:** Professional and encouraging — "Aucune séance créée — Créez votre première séance pour démarrer"
- **Empty state visual:** Sober SVG icon above text (existing emptyState() helper already has icon support)
- **Empty state action button:** Secondary (outline/ghost) — the empty state guides without shouting
- **ag-empty-state Web Component** replaces the `Shared.emptyState()` helper function gradually
- Every list/table that can be empty must have a proper empty state with heading + description + action
- **Dashboard card CTA:** One action button at the bottom of each session card, text changes by lifecycle state:
  - draft → "Compléter →"
  - scheduled → "Enregistrer les présences →"
  - frozen → "Ouvrir la console →"
  - live → "● En cours — Rejoindre →" (with green pulse dot)
  - closed → "Générer le PV →"
  - validated → "Archiver →"
  - archived → no action, card muted
- **Role-aware views:** Admin sees stats + user management section first. Operator sees session cards first. Separate layout per role.
- **Live session card:** Visually distinct — colored border + pulsing green dot + "● En cours" text
- **Disabled button mechanism:** Tooltip on hover via existing ag-tooltip component
- **Wording pattern:** "Disponible après [condition]" — tells the user what to DO, not what's missing
- Technical terms get (?) ag-tooltip: majorité absolue, quorum, scrutin secret, procuration, tantième → voix
- **No ag-hint component** — ag-tooltip is sufficient

### Claude's Discretion
- ag-empty-state internal implementation (slots, attributes)
- Help panel design for btnTour replacement
- Exact empty state content per page (heading, description, action text)
- Which technical terms get tooltips (use judgment — only truly opaque terms)
- Dashboard card layout specifics (grid, gap, responsive breakpoints)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| GUX-01 | Status-aware session cards on dashboard — each card shows the ONE next action for its current lifecycle state | dashboard.js renderSeanceRow() needs full replacement; lifecycle states documented below |
| GUX-02 | Contextual empty states on every container (heading + description + secondary action) replacing blank tables/lists | 20 emptyState() call sites mapped; ag-empty-state component API designed |
| GUX-03 | Disabled button explanations — tooltip or inline note explaining WHY a button is locked | ag-tooltip wrapping pattern documented; all disabled buttons identified |
| GUX-04 | ag-guide Web Component wrapping Driver.js — SUPERSEDED by contextual help panels (CONTEXT.md) | btnTour → help panel replacement pattern documented for all 9 pages |
| GUX-05 | ag-hint Web Component — SUPERSEDED (CONTEXT.md says ag-tooltip is sufficient) | ag-popover is the base for contextual help panels |
| GUX-06 | ag-empty-state Web Component — slot-based, replaces emptyState() helper across all pages | Full component API and backward-compat migration strategy documented |
| GUX-07 | Inline contextual help — field descriptions under labels, (?) tooltip popovers for technical terms | ag-popover (trigger=click, default ? button) is the correct implementation |
| GUX-08 | localStorage dismissal for all guided elements (tours, hints) so they don't repeat | Help panel remembers dismissed state; empty states are data-driven, no dismiss needed |
</phase_requirements>

---

## Summary

Phase 26 adds a self-explanatory UX layer to AG-VOTE using existing Web Component infrastructure. No new external libraries are needed. The locked decision — no Driver.js sequential tours — means the main deliverable shifts from a tour engine to three simpler, composable patterns: (1) help panels that replace the 9 btnTour buttons, (2) an ag-empty-state Web Component that standardises the 20+ emptyState() call sites, and (3) ag-tooltip wrappers on all disabled primary action buttons.

The dashboard needs the most significant JS change: `renderSeanceRow()` must be replaced with a status-aware card renderer that reads the lifecycle state and injects the correct CTA. The API already returns `status` and `scheduled_at` fields, so no backend work is required. The role-aware layout (admin vs operator) will be handled by reading the existing auth context (role is already in the JWT/session).

All components follow the established `AgXxx extends HTMLElement` pattern with shadow DOM. The existing ag-tooltip and ag-popover components provide the technical foundation — ag-empty-state is a new component, and the help panel is a thin wrapper around ag-popover with localStorage-based dismiss.

**Primary recommendation:** Build ag-empty-state first (widest blast radius — 20 call sites), then dashboard status-aware cards (GUX-01, highest visibility), then btnTour help panels (GUX-04 replacement), then disabled button tooltips (GUX-03), then term hints (GUX-07).

---

## Standard Stack

### Core (existing — no new installs)
| Component | File | Purpose | Status |
|-----------|------|---------|--------|
| AgTooltip | `public/assets/js/components/ag-tooltip.js` | CSS-only hover/focus tooltip; wraps any element | Exists, ships |
| AgPopover | `public/assets/js/components/ag-popover.js` | Click/hover popover with title+content slots, auto-repositioning | Exists, ships |
| Shared.emptyState() | `public/assets/js/core/shared.js:280` | Renders empty state HTML string; SVG icons built-in | Exists — to be wrapped |

### New Components to Build
| Component | File to Create | Purpose |
|-----------|---------------|---------|
| ag-empty-state | `public/assets/js/components/ag-empty-state.js` | Web Component slot-based wrapper over emptyState() pattern |
| Help panel (no new component) | Inline `<ag-popover>` per page | btnTour replacement — page-specific hints in a popover |

### No New Libraries
Driver.js is explicitly excluded. No npm installs required for this phase.

---

## Architecture Patterns

### Recommended File Structure
```
public/assets/js/
├── components/
│   ├── ag-tooltip.js         (existing — wrap disabled buttons)
│   ├── ag-popover.js         (existing — base for help panels)
│   └── ag-empty-state.js     (NEW — ag-empty-state Web Component)
└── pages/
    └── dashboard.js          (MODIFY — status-aware card rendering + role-aware layout)

public/
├── dashboard.htmx.html       (MODIFY — new card grid markup, role-aware sections)
├── hub.htmx.html             (MODIFY — btnTour → help panel)
├── wizard.htmx.html          (MODIFY — btnTour → help panel, disabled button tooltips)
├── operator.htmx.html        (MODIFY — btnTour → help panel, disabled button tooltips)
├── members.htmx.html         (MODIFY — btnTour → help panel)
├── postsession.htmx.html     (MODIFY — btnTour → help panel, disabled button tooltips)
├── analytics.htmx.html       (MODIFY — btnTour → help panel)
└── meetings.htmx.html        (MODIFY — btnTour → help panel)
```

### Pattern 1: ag-empty-state Web Component

**What:** Slot-based Web Component wrapping the existing emptyState() pattern. Renders icon + heading + description + optional action.

**API design (Claude's discretion — see locked: slot-based):**
```html
<!-- Declarative usage (new pages) -->
<ag-empty-state
  icon="meetings"
  title="Aucune séance"
  description="Créez votre première séance pour commencer."
>
  <a slot="action" class="btn btn-secondary btn-sm" href="/wizard.htmx.html">
    Nouvelle séance
  </a>
</ag-empty-state>

<!-- Attribute-only usage (for programmatic injection from JS) -->
<ag-empty-state
  icon="members"
  title="Aucun membre"
  description="Importez ou ajoutez des membres."
  action-label="Importer"
  action-href="/members.htmx.html"
></ag-empty-state>
```

**Implementation:**
```javascript
// Source: existing AgTooltip/AgPopover pattern in this codebase
class AgEmptyState extends HTMLElement {
  static get observedAttributes() {
    return ['icon', 'title', 'description', 'action-label', 'action-href'];
  }
  connectedCallback() { this.render(); }
  attributeChangedCallback() { this.render(); }

  render() {
    const icon = this.getAttribute('icon') || 'generic';
    const title = this.getAttribute('title') || '';
    const desc = this.getAttribute('description') || '';
    const actionLabel = this.getAttribute('action-label');
    const actionHref = this.getAttribute('action-href');
    const hasSlottedAction = this.querySelector('[slot="action"]');

    // Reuse EMPTY_SVG map from Shared (or copy it in)
    const actionHtml = hasSlottedAction ? '<slot name="action"></slot>' :
      (actionLabel && actionHref)
        ? `<a class="btn btn-secondary btn-sm" href="${actionHref}" style="margin-top:12px;">${actionLabel}</a>`
        : '';

    this.innerHTML = `<div class="empty-state animate-fade-in">
      <div class="empty-state-icon">${EMPTY_SVG[icon] || EMPTY_SVG.generic}</div>
      <div class="empty-state-title">${title}</div>
      ${desc ? `<div class="empty-state-description">${desc}</div>` : ''}
      ${actionHtml}
    </div>`;
  }
}
customElements.define('ag-empty-state', AgEmptyState);
```

**Backward compatibility:** `Shared.emptyState()` stays untouched. Pages migrate progressively.

**Migration strategy for JS call sites:**
```javascript
// Before (20+ call sites like this):
container.innerHTML = Shared.emptyState({ icon: 'meetings', title: '...', description: '...' });

// After (Web Component — for new declarative markup):
// <ag-empty-state icon="meetings" title="..." description="..."></ag-empty-state>

// For JS-rendered contexts (tables etc.) — keep Shared.emptyState() OR:
container.innerHTML = '<ag-empty-state icon="meetings" title="..." description="..."></ag-empty-state>';
// Web Component self-renders on connectedCallback
```

### Pattern 2: btnTour → Contextual Help Panel

**What:** Replace the 9 `tour-trigger-btn#btnTour` buttons with an `<ag-popover>` panel showing page-relevant tips. Not a sequential walkthrough.

**Mechanism:** The existing `ag-popover` component supports `trigger="click"` and slot-based rich content. The help panel is a popover with a bulleted tip list, anchored to the button.

**Pattern (same for all 9 pages, tips vary):**
```html
<!-- Replace this: -->
<button class="tour-trigger-btn" id="btnTour" aria-label="Lancer la visite guidée">
  <svg ...><use href="/assets/icons.svg#icon-help-circle"></use></svg>
  Visite guidée
</button>

<!-- With this: -->
<ag-popover trigger="click" position="bottom" width="320">
  <button slot="trigger" class="tour-trigger-btn" aria-label="Aide sur cette page">
    <svg class="icon icon-xs" aria-hidden="true"><use href="/assets/icons.svg#icon-help-circle"></use></svg>
    Aide
  </button>
  <div slot="content">
    <strong>[Page title] — Comment ça marche</strong>
    <ul style="margin:.5rem 0 0;padding-left:1rem;font-size:.875rem;color:var(--color-text-muted)">
      <li>[Tip 1]</li>
      <li>[Tip 2]</li>
      <li>[Tip 3]</li>
    </ul>
  </div>
</ag-popover>
```

**localStorage dismiss (GUX-08):** Not required for help panels (they are on-demand, user clicks to open). Only apply if a help panel shows proactively (not needed here — all help panels are click-triggered).

**Per-page help panel content (Claude's discretion on exact copy):**

| Page | Key tips to include |
|------|-------------------|
| dashboard | "Cliquez sur une séance pour ouvrir sa fiche — Le bouton d'action change selon l'état de la séance — Créez votre première séance avec le bouton Nouvelle séance" |
| meetings | "Filtrez par état avec les onglets — Cliquez sur une séance pour ouvrir sa fiche hub — Utilisez la barre de recherche pour retrouver une séance par nom" |
| hub | "Suivez les étapes dans l'ordre — Chaque étape débloque la suivante — La console opérateur s'ouvre depuis l'étape Présences" |
| wizard | "Complétez les 4 étapes dans l'ordre — Étapes 2 et 3 sont optionnelles, vous pouvez les compléter depuis la fiche séance — Vos données sont sauvegardées à chaque étape" |
| operator | "Mode Préparation : gérez membres et résolutions — Mode Exécution : pilotez les votes en direct — Le bouton principal change selon l'état de la séance" |
| members | "Importez un CSV pour ajouter des membres en masse — Chaque membre reçoit un lien de vote unique — La colonne Poids définit le droit de vote pondéré" |
| postsession | "Validez les résultats avant de générer le PV — La génération PDF prend quelques secondes — Le PV archivé est accessible depuis la page Archives" |
| analytics | "Les statistiques sont calculées sur les séances clôturées — Exportez en CSV pour vos archives — Filtrez par période pour comparer les séances" |

### Pattern 3: Status-Aware Dashboard Cards (GUX-01)

**What:** `dashboard.js` currently renders flat `session-row` divs. Replace with card-style rendering where the CTA button text and visual treatment depend on the session lifecycle state.

**Current API shape (verified from dashboard.js):**
- `meetings` array from `/api/v1/dashboard`
- Each meeting has: `id`, `title`, `status`, `scheduled_at`, `participant_count`, `motion_count`

**Status values in use (from operator-tabs.js and hub.js):**
`draft`, `scheduled`, `frozen`, `live`, `paused`, `closed` (= ended), `validated`, `archived`

**New `renderSessionCard()` function:**
```javascript
var STATUS_CTA = {
  'draft':      { label: 'Compléter →',                        href: '/wizard.htmx.html',    cls: '' },
  'scheduled':  { label: 'Enregistrer les présences →',        href: '/hub.htmx.html',       cls: '' },
  'frozen':     { label: 'Ouvrir la console →',                href: '/operator.htmx.html',  cls: '' },
  'live':       { label: '● En cours — Rejoindre →',           href: '/operator.htmx.html',  cls: 'live' },
  'paused':     { label: '● En cours — Rejoindre →',           href: '/operator.htmx.html',  cls: 'live' },
  'closed':     { label: 'Générer le PV →',                    href: '/postsession.htmx.html', cls: '' },
  'validated':  { label: 'Archiver →',                         href: '/postsession.htmx.html', cls: '' },
  'archived':   { label: null,                                  href: null,                   cls: 'muted' }
};

function renderSessionCard(s) {
  var cta = STATUS_CTA[s.status] || STATUS_CTA['draft'];
  var href = cta.href ? cta.href + '?meeting_id=' + encodeURIComponent(s.id) : null;
  var isLive = cta.cls === 'live';
  var isMuted = cta.cls === 'muted';

  var card = '<div class="session-card' + (isLive ? ' session-card--live' : '') + (isMuted ? ' session-card--muted' : '') + '">';
  if (isLive) card += '<div class="session-card-live-border"></div>';
  card += '<div class="session-card-body">';
  card += '<div class="session-card-title">' + escapeHtml(s.title) + '</div>';
  card += '<div class="session-card-meta">' + escapeHtml(s.scheduled_at || '') + '</div>';
  if (!isMuted && cta.label && href) {
    card += '<a class="btn btn-sm btn-secondary session-card-cta' + (isLive ? ' session-card-cta--live' : '') + '" href="' + escapeHtml(href) + '">';
    if (isLive) card += '<span class="pulse-dot" aria-hidden="true"></span>';
    card += escapeHtml(cta.label) + '</a>';
  }
  card += '</div></div>';
  return card;
}
```

**CSS needed (new classes):**
```css
.session-card--live { border-left: 3px solid var(--color-success); }
.session-card--muted { opacity: 0.55; }
.pulse-dot {
  display: inline-block; width: 8px; height: 8px;
  border-radius: 50%; background: var(--color-success);
  animation: pulse-glow 1.5s ease-in-out infinite;
}
@keyframes pulse-glow {
  0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-success) 40%, transparent); }
  50% { box-shadow: 0 0 0 6px transparent; }
}
```

**Role-aware layout:** Read role from existing auth context. The API response or a global `App.user.role` should carry this. Dashboard HTML needs two layout sections gated by role attribute:
```html
<!-- In dashboard.htmx.html — admin section (hidden for operators) -->
<section id="sectionAdmin" hidden>
  <!-- KPIs, user management links -->
</section>
<!-- Operator section (shown first for operators, second for admins) -->
<section id="sectionSessions">
  <!-- Session cards grid -->
</section>
```

The JS will toggle section visibility and order based on `App.user.role` after auth resolves.

### Pattern 4: Disabled Button Explanations (GUX-03)

**What:** Wrap any disabled primary action button in `<ag-tooltip>` so hovering explains WHY.

**ag-tooltip observed attributes:** `text`, `position`. Text must escape HTML (component handles this via `_esc()`). Trigger: `:host(:hover)` and `:host(:focus-within)` — CSS-only, no JS needed.

**Wording convention:** "Disponible après [condition]" — never "Indisponible car [missing thing]".

**Identified disabled buttons requiring tooltip:**

| Button | Page | Current code | Tooltip text |
|--------|------|-------------|-------------|
| `#btnPrimary` (Ouvrir la séance) | operator.htmx.html | `disabled` set by `updatePrimaryButton()` when score < 3 | "Disponible après ajout des membres, enregistrement des présences et configuration du vote" |
| `#btnSuivant` | postsession.htmx.html:456 | `disabled` initially | "Disponible après validation des résultats" |
| `#btnValidate` | postsession.htmx.html:194 | `disabled` initially | "Disponible après vérification de tous les résultats" |
| `#btnReject` | postsession.htmx.html:198 | `disabled` initially | "Disponible après vérification de tous les résultats" |
| `#hubSendConvocation` | operator.htmx.html:355 | `disabled` initially | "Disponible après complétion de la fiche séance" |
| `#hubSend2ndConvocation` | operator.htmx.html:359 | `disabled` initially | "Disponible après envoi de la première convocation" |
| `#btnImport` | members.htmx.html:210 | `disabled` initially | "Disponible après sélection d'un fichier CSV" |

**Implementation pattern:**
```html
<!-- Wrap the existing disabled button in ag-tooltip -->
<ag-tooltip text="Disponible après ajout des membres et enregistrement des présences" position="bottom">
  <button class="btn btn-sm btn-primary" id="btnPrimary" disabled>Ouvrir la séance</button>
</ag-tooltip>
```

**Important:** When the button becomes enabled, the tooltip text should update too. The JS that calls `btnPrimary.disabled = false` should also update the parent `<ag-tooltip>` text attribute:
```javascript
var tooltipEl = btnPrimary.closest('ag-tooltip');
if (tooltipEl) tooltipEl.setAttribute('text', '');  // clear when enabled
```

### Pattern 5: Inline Technical Term Tooltips (GUX-07)

**What:** Terms that cannot be simplified by rewording get a (?) ag-popover inline. Use sparingly.

**Approved terms (from CONTEXT.md):** majorité absolue, quorum, scrutin secret, procuration, tantième → voix

**ag-popover default trigger button** is already a "?" circle button (see `default-trigger` CSS in ag-popover.js). So the minimal implementation is:
```html
Majorité absolue
<ag-popover
  title="Majorité absolue"
  content="Plus de la moitié des votes exprimés. Abstentions non comptées."
  position="top"
  trigger="click"
></ag-popover>
```

No `slot="trigger"` needed — ag-popover renders its own ? button by default.

### Anti-Patterns to Avoid
- **Sequential walkthroughs:** The btnTour becomes a single popover, not a step-by-step overlay. Never add next/prev navigation to the help panel.
- **Help panels that open automatically:** They are click-triggered only. Never show proactively.
- **Primary buttons in empty states:** Always use secondary/ghost. Primary CTA competes with the main page action.
- **Disabled tooltip via HTML `title` attribute:** Use `<ag-tooltip>` instead. The native `title` attribute is inaccessible on touch devices and fails keyboard navigation.
- **Tour overlay CSS reuse for help panels:** The `.tour-overlay`, `.tour-spotlight-ring` CSS (lines 3744-3940 of design-system.css) should be left as dead code for now — the help panel does NOT use spotlight overlays. Do not delete these classes; they may be used in v5+ tours.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Hover tooltips | Custom CSS tooltip | ag-tooltip | Already handles shadow DOM, escape, position, focus |
| Click popovers with content slots | Custom popover | ag-popover | Auto-repositions, handles click-outside, keyboard Escape |
| Empty state HTML generation | New emptyState utility | ag-empty-state (new WC) wrapping existing EMPTY_SVG | SVG icons already built; pattern established |
| Role detection | Re-implement auth parsing | Read existing `App.user` or `data-user-role` attr | Already exists in app shell |
| localStorage dismiss | Custom wrapper | `localStorage.setItem('help_dismissed_' + pageId, '1')` | Native API is sufficient — no abstraction needed |

---

## Common Pitfalls

### Pitfall 1: ag-tooltip text on dynamically disabled buttons
**What goes wrong:** The tooltip wraps a button. The JS later calls `btn.disabled = false` and `btn.title = ''` (line 2156 of operator-tabs.js), but the `ag-tooltip` text attribute is not cleared, so the tooltip still shows "Disponible après..." on an enabled button.
**How to avoid:** When enabling a button, also update the parent `ag-tooltip` attribute. Pattern: `btn.closest('ag-tooltip')?.setAttribute('text', '')`.
**Warning signs:** Enabled buttons showing misleading "Disponible après..." text on hover.

### Pitfall 2: Web Component renders before EMPTY_SVG is available
**What goes wrong:** ag-empty-state needs the SVG map. If it imports the SVGs from Shared, and Shared is not yet loaded, the component renders with blank icons.
**How to avoid:** Either (a) inline the EMPTY_SVG map inside ag-empty-state.js, or (b) check `window.Shared && Shared.EMPTY_SVG` and re-render on ready. Option (a) is simpler — duplicate the SVG strings into the component.
**Warning signs:** Empty state renders with no icon area.

### Pitfall 3: dashboard.js renderSeanceRow vs renderSessionCard collision
**What goes wrong:** The current dashboard renders into `#prochaines` using `renderSeanceRow()`. The new status-aware card needs to replace this function entirely, but `#prochaines` is also expected to show the upcoming sessions only (the current filter is: not live, not ended, not archived). Status-aware cards need to show ALL sessions including live ones — meaning the filter logic must change too.
**How to avoid:** Replace both the container filtering AND the render function together. Add a new `#allSessions` container or repurpose `#prochaines` to show all sessions grouped by state.
**Warning signs:** Live sessions not appearing on dashboard, or archived sessions appearing with full card treatment.

### Pitfall 4: ag-popover z-index conflict in operator header
**What goes wrong:** The operator header has a `z-index` stacking context. The help panel `<ag-popover>` inside the header has `z-index: 1000` (from component CSS), which may be occluded by the tab nav or lifecycle bar.
**How to avoid:** The ag-popover component already uses `z-index: 1000` in its shadow DOM. If the header has `position: relative` with no `z-index`, the shadow DOM stacking is self-contained. Verify that operator header does not set a `z-index` that clips the popover.
**Warning signs:** Help panel popup cut off by page elements when opened in operator.htmx.html.

### Pitfall 5: emptyState() call sites inside table cells
**What goes wrong:** Several emptyState() calls inject into `<tr><td colspan="N">` contexts (admin.js, audit.js). ag-empty-state is a block element that may not validate inside `<td>` in all browsers — but in practice this works. The real issue is that `innerText` of `<td>` changes when `<ag-empty-state>` self-renders, which may confuse table accessibility.
**How to avoid:** For table-cell empty states, keep using `Shared.emptyState()` (the HTML string pattern) and do not migrate these to ag-empty-state. ag-empty-state is best for standalone containers (divs, sections).
**Warning signs:** Table rows with empty states look broken in Firefox or Edge.

---

## Code Examples

### ag-empty-state complete usage
```html
<!-- Source: ag-popover.js pattern in this codebase -->

<!-- Standalone container (preferred) -->
<ag-empty-state
  icon="meetings"
  title="Aucune séance"
  description="Créez votre première séance pour commencer."
  action-label="Nouvelle séance"
  action-href="/wizard.htmx.html"
></ag-empty-state>

<!-- With custom action via slot -->
<ag-empty-state icon="members" title="Aucun membre" description="Importez ou ajoutez des membres.">
  <button slot="action" class="btn btn-secondary btn-sm" onclick="openImportModal()">
    Importer des membres
  </button>
</ag-empty-state>

<!-- JS-rendered (into a div, NOT a table cell) -->
container.innerHTML = '<ag-empty-state icon="votes" title="Aucune résolution" description="Ajoutez les résolutions qui seront soumises au vote." action-label="Ajouter" action-href="#addResolution"></ag-empty-state>';
```

### ag-tooltip on disabled button
```html
<!-- Source: ag-tooltip.js in this codebase -->
<ag-tooltip text="Disponible après enregistrement des présences (0 membres présents)" position="bottom">
  <button class="btn btn-sm btn-primary" id="btnPrimary" disabled>Ouvrir la séance</button>
</ag-tooltip>

<!-- JS to update tooltip text when conditions change -->
<script>
var btnPrimary = document.getElementById('btnPrimary');
var tipEl = btnPrimary.closest('ag-tooltip');
// When enabling:
btnPrimary.disabled = false;
if (tipEl) tipEl.setAttribute('text', '');
// When disabling:
btnPrimary.disabled = true;
if (tipEl) tipEl.setAttribute('text', 'Disponible après enregistrement des présences');
</script>
```

### Help panel (btnTour replacement)
```html
<!-- Source: ag-popover.js trigger="click" pattern -->
<ag-popover trigger="click" position="bottom" width="320">
  <button slot="trigger" class="tour-trigger-btn" aria-label="Aide sur cette page">
    <svg class="icon icon-xs" aria-hidden="true"><use href="/assets/icons.svg#icon-help-circle"></use></svg>
    Aide
  </button>
  <div slot="content">
    <strong>Tableau de bord — Comment ça marche</strong>
    <ul style="margin:.5rem 0 0;padding-left:1.25rem;font-size:.8125rem;color:var(--color-text-muted);line-height:1.6">
      <li>Cliquez sur une séance pour ouvrir sa fiche</li>
      <li>Le bouton d'action change selon l'état de la séance</li>
      <li>Créez une nouvelle séance avec le bouton en haut à droite</li>
    </ul>
  </div>
</ag-popover>
```

### Dashboard status-aware card (full example)
```javascript
// Source: dashboard.js — replaces renderSeanceRow()
var STATUS_CTA = {
  draft:     { label: 'Compléter →',                   href: '/wizard.htmx.html',       live: false },
  scheduled: { label: 'Enregistrer les présences →',   href: '/hub.htmx.html',          live: false },
  frozen:    { label: 'Ouvrir la console →',            href: '/operator.htmx.html',     live: false },
  live:      { label: '● En cours — Rejoindre →',       href: '/operator.htmx.html',     live: true  },
  paused:    { label: '● En cours — Rejoindre →',       href: '/operator.htmx.html',     live: true  },
  closed:    { label: 'Générer le PV →',                href: '/postsession.htmx.html',  live: false },
  validated: { label: 'Archiver →',                     href: '/postsession.htmx.html',  live: false },
  archived:  { label: null,                              href: null,                      live: false }
};

function renderSessionCard(s) {
  var cta = STATUS_CTA[s.status] || STATUS_CTA.draft;
  var href = cta.href ? cta.href + '?meeting_id=' + encodeURIComponent(s.id) : null;
  var isLive = cta.live;
  var isMuted = s.status === 'archived';
  var dateStr = s.scheduled_at ? new Date(s.scheduled_at).toLocaleDateString('fr-FR', { day:'numeric', month:'long' }) : '';

  var h = '<div class="session-card' + (isLive ? ' session-card--live' : '') + (isMuted ? ' session-card--muted' : '') + '">';
  h += '<div class="session-card-status-dot" style="background:' + (STATUS_COLORS[s.status] || 'var(--color-text-muted)') + '"></div>';
  h += '<div class="session-card-info">';
  h += '<div class="session-card-title">' + escapeHtml(s.title) + '</div>';
  h += '<div class="session-card-meta">' + escapeHtml(dateStr) + ' &mdash; ' + (s.participant_count || 0) + ' membres &mdash; ' + (s.motion_count || 0) + ' résolutions</div>';
  h += '</div>';
  if (cta.label && href) {
    h += '<a class="btn btn-sm btn-secondary session-card-cta' + (isLive ? ' btn-success' : '') + '" href="' + escapeHtml(href) + '">';
    if (isLive) h += '<span class="pulse-dot" aria-hidden="true"></span> ';
    h += escapeHtml(cta.label);
    h += '</a>';
  }
  h += '</div>';
  return h;
}
```

---

## emptyState() Call Site Inventory

All sites where `Shared.emptyState()` is called — migration priority order:

### Priority 1 — New ag-empty-state component (standalone containers)
| File | Line context | Container type | Migrate? |
|------|-------------|---------------|---------|
| `dashboard.js:123` | `#taches` div | div | YES |
| `meetings.js:283-312` | filter-based empty states | div | YES |
| `archives.js:44,50` | `#archivesList` div | div | YES |
| `settings.js:195` | quorum policy container | div | YES |
| `members.js:563,569` | `#membersList` div | div | YES |
| `users.js:110` | users container | div | YES |
| `operator-tabs.js:1244` | operator tab container | div | YES |

### Priority 2 — Keep as Shared.emptyState() (table cells)
| File | Line context | Container type | Migrate? |
|------|-------------|---------------|---------|
| `admin.js:194` | `<tr><td colspan="7">` | table cell | NO |
| `admin.js:602` | `<tr><td colspan="4">` | table cell | NO |
| `admin.js:809` | div but in admin panel | div | OPTIONAL |
| `admin.js:1226` | alerts container | div | OPTIONAL |
| `audit.js:97` | `<tr><td colspan="6">` | table cell | NO |
| `audit.js:161` | `#_timeline` div | div | OPTIONAL |
| `audit.js:413` | div | div | OPTIONAL |
| `audit.js:438,444` | `<tr><td>` + div | table cell + div | NO for td |

---

## All emptyState Content — Specification

Each ag-empty-state must have: heading (plain language statement) + description (one sentence why/context) + action (secondary button or none).

| Container | Icon | Title | Description | Action |
|-----------|------|-------|-------------|--------|
| Dashboard — no sessions | meetings | Aucune séance | Créez votre première séance pour gérer vos assemblées générales. | Nouvelle séance → /wizard |
| Dashboard — tâches | generic | Aucune tâche en attente | Les tâches automatiques apparaîtront ici. | (none) |
| Meetings — all empty | meetings | Aucune séance | Créez votre première séance pour commencer. | Nouvelle séance → /wizard |
| Meetings — upcoming empty | meetings | Aucune séance à venir | Toutes vos séances sont terminées ou en cours. | (none) |
| Meetings — live empty | meetings | Aucune séance en cours | Lancez une séance depuis la console opérateur. | (none) |
| Meetings — completed empty | meetings | Aucune séance terminée | Les séances terminées apparaîtront ici. | (none) |
| Meetings — search no results | generic | Aucun résultat | Essayez un autre terme de recherche. | (none) |
| Archives — empty | archives | Aucune séance archivée | Les séances validées et archivées apparaissent ici. | (none) |
| Members — no members | members | Aucun membre | Importez une liste CSV ou ajoutez des membres un par un. | Importer des membres |
| Members — search no results | members | Aucun résultat | Essayez un autre terme de recherche. | (none) |
| Settings — no quorum policy | generic | Aucune politique de quorum | Créez une politique pour définir le seuil de présence requis. | Créer une politique |
| Operator tab — no content | generic | Aucun élément | (context-dependent) | (context-dependent) |

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|-----------------|--------|
| Driver.js sequential tours | Click-triggered help panel popovers | Less intrusive, user-controlled |
| `Shared.emptyState()` HTML strings | `<ag-empty-state>` Web Component | Declarative, no innerHTML needed |
| `button.title` for disabled explanations | `<ag-tooltip>` wrapper | Keyboard accessible, styled |
| Global tour CSS (spotlight, overlay) | Dead CSS — kept but unused | Tour CSS preserved for v5+ |

**Deprecated for this phase:**
- `btnTour` elements with `aria-label="Lancer la visite guidée"` — replaced with help panel
- `button.title` attribute for disabled state explanations — replaced with ag-tooltip

---

## Open Questions

1. **Role detection in dashboard.js**
   - What we know: `data-page-role` attribute exists on `<html>` (e.g., `data-page-role="viewer"` on dashboard)
   - What's unclear: Does the JS have access to a `App.user.role` global, or must it be inferred from the API response?
   - Recommendation: Check if `/api/v1/dashboard` response includes a `user.role` field. If not, read from a meta tag or global set by the shell include.

2. **Session card grid on dashboard — current HTML structure**
   - What we know: `#prochaines` currently shows skeleton rows, populated by `renderSeanceRow()` into inline divs
   - What's unclear: Should the new session cards still live in `#prochaines` or does the HTML need a new container that shows all sessions (not just upcoming)?
   - Recommendation: Rename the panel to "Séances" and remove the "upcoming only" filter. Show all sessions, sorted by status priority (live first, then scheduled, then draft, then closed/archived).

3. **ag-empty-state and EMPTY_SVG duplication**
   - What we know: EMPTY_SVG is defined inside the `Shared` IIFE and is not exported
   - What's unclear: Can ag-empty-state.js access `window.Shared.EMPTY_SVG`, or must it duplicate the SVG strings?
   - Recommendation: Export or expose EMPTY_SVG as `Shared.EMPTY_SVG` in shared.js, or duplicate the 5 SVG strings in ag-empty-state.js. Duplication is simpler and avoids coupling.

---

## Validation Architecture

*nyquist_validation key absent from config.json — treating as enabled.*

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (backend) + manual browser smoke (frontend — no JS test runner configured) |
| Config file | `phpunit.xml` (if exists) |
| Quick run command | Manual browser check per modified page |
| Full suite command | `composer test` or `./vendor/bin/phpunit` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| GUX-01 | Dashboard session card shows correct CTA per lifecycle state | smoke/manual | Open dashboard in browser, check each state | ❌ Wave 0 |
| GUX-02 | Empty containers show ag-empty-state with heading + description + action | smoke/manual | Check each listed page with empty data | ❌ Wave 0 |
| GUX-03 | Disabled button tooltip appears on hover with "Disponible après..." | smoke/manual | Hover over disabled operator btnPrimary | ❌ Wave 0 |
| GUX-04 | btnTour → help panel opens on click, shows page-relevant tips | smoke/manual | Click Aide button on each of 9 pages | ❌ Wave 0 |
| GUX-05 | (superseded) ag-tooltip serves as term hint | smoke/manual | Hover (?) next to "majorité absolue" | ❌ Wave 0 |
| GUX-06 | ag-empty-state Web Component renders icon+title+desc+action correctly | unit/manual | Check component in isolation via test page | ❌ Wave 0 |
| GUX-07 | Technical term popovers show on click of ? button | smoke/manual | Click ? next to quorum/majorité absolue labels | ❌ Wave 0 |
| GUX-08 | localStorage dismiss persists across page refresh for help panel | smoke/manual | Dismiss help panel, refresh, verify state | ❌ Wave 0 |

*Note: This project has no JS unit test runner (no jest.config.*, no vitest.config.*). All frontend validation is manual browser smoke testing. PHPUnit covers backend only — no backend changes in this phase.*

### Sampling Rate
- **Per task commit:** Manual smoke — open the affected page, verify the changed element works
- **Per wave merge:** Smoke all 9 pages for btnTour replacement + 3 pages for disabled button tooltips
- **Phase gate:** All 8 requirements pass manual smoke before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `public/assets/js/components/ag-empty-state.js` — new Web Component (GUX-06)
- [ ] CSS for `.session-card`, `.session-card--live`, `.pulse-dot`, `.session-card--muted` — new styles needed in `design-system.css` or a new `dashboard.css`

*(No test framework install needed — project uses manual smoke for frontend)*

---

## Sources

### Primary (HIGH confidence)
- `public/assets/js/components/ag-tooltip.js` — Component API verified by reading source
- `public/assets/js/components/ag-popover.js` — Component API, slots, trigger modes verified by reading source
- `public/assets/js/core/shared.js:260-290` — emptyState() implementation and EMPTY_SVG map verified
- `public/assets/js/pages/dashboard.js` — Full current rendering logic verified
- `public/assets/js/pages/meetings.js:280-317` — All emptyState call sites in meetings verified
- `public/assets/js/pages/operator-tabs.js:2126-2192` — updatePrimaryButton() disabled logic verified
- `public/assets/css/design-system.css:3741-3940` — Tour CSS inventory verified
- `.planning/phases/26-guided-ux-components/26-CONTEXT.md` — All locked decisions
- `.planning/research/FEATURES.md` — UX pattern research (Pattern 3, 4, 5 specifically)

### Secondary (MEDIUM confidence)
- HTML of all 9 btnTour pages verified by reading source (dashboard, hub, wizard, operator, members, postsession, analytics, meetings)
- All 20 `Shared.emptyState()` call sites identified via grep

### Tertiary (LOW confidence)
- Role-aware dashboard layout: assumed `App.user.role` global exists based on pattern in other pages — needs verification during Wave 0

---

## Metadata

**Confidence breakdown:**
- Standard stack (existing components): HIGH — all read directly from source
- Architecture (ag-empty-state design): HIGH — follows established AgXxx pattern exactly
- emptyState call sites: HIGH — grep confirmed all 20 sites
- Disabled button inventory: HIGH — grep + manual read of operator-tabs.js confirmed
- Role detection mechanism: LOW — assumption based on similar patterns; needs Wave 0 verification
- Tour CSS repurposing: HIGH — CSS confirmed read; decision is NOT to repurpose it

**Research date:** 2026-03-18
**Valid until:** 2026-04-18 (stable vanilla stack — no fast-moving dependencies)
