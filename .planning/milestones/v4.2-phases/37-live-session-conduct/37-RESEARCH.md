# Phase 37: Live Session Conduct — Research

**Researched:** 2026-03-20
**Domain:** CSS visual redesign — operator console (operator.css + operator-exec.html) + mobile voter ballot (vote.css + vote.htmx.html)
**Confidence:** HIGH (all findings from direct code inspection)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Design Philosophy**
- Reference-driven: Bloomberg Terminal density for operator, Apple Wallet simplicity for voter
- Top 1% under pressure — used in live sessions where mistakes cost time
- Tooltips on every operator action, confirmation states on every voter action
- Dramatic visible improvement over current state

**Operator Console Visual Redesign (CORE-03)**
- Agenda sidebar (280px): Each motion as a compact card with number badge, title truncated, status indicator (pending/en cours/voté). Active motion highlighted with primary border-left accent. Scrollable independently.
- Status bar: Fixed at top — session status (En direct/Pause), connected members count, elapsed time. Persona-colored accent stripe (operator = cyan `#0891b2`). Compact 40px height, high-contrast text.
- Tab navigation: Clean horizontal tabs below status bar — Votes, Participants, Résultats. Active tab with bottom border accent, not background fill. Badge count on Participants tab showing connected/total.
- Live vote panel: When a vote is open — large motion title, vote progress bar (pour/contre/abstention as colored segments), real-time tally numbers in JetBrains Mono, "Fermer le vote" as danger button.
- Action buttons: Every action button has an ag-tooltip. "Ouvrir le vote" prominent primary CTA when vote can be opened. Disabled buttons show tooltip explaining why.
- SSE indicators: Live/reconnecting/offline as small colored dot in status bar — green/amber/red. Delta badges (+N) use ag-badge pulse animation.
- Guidance panels: Post-vote and end-of-agenda guidance panels styled as info cards (not alerts).
- Density: 13px base font, --space-3 gaps, more items per screen.

**Mobile Voter Ballot Visual Redesign (SEC-05)**
- Full-screen: 100dvh, no browser chrome distraction.
- Motion display: Current motion title large and centered, description below. Motion number as subtle badge.
- Vote buttons: Minimum 72px tall, full-width stacked buttons — Pour (green), Contre (red), Abstention (amber), Blanc (neutral). Each button has icon + label. Pressed state with immediate visual feedback (< 50ms).
- Confirmation state: After voting, show selected choice with large checkmark, "Vote enregistré" text, subtle animation. Irreversibility notice in muted text.
- Waiting state: "En attente du prochain vote" with subtle pulse animation.
- Results display: After vote closes, results with colored bar chart. ADOPTÉ/REJETÉ verdict prominent.
- Speech/hand raise: 72px circular button, fixed position, always accessible. Tooltip on long-press.
- Typography: clamp() fluid scaling. Button labels large and clear.
- Dark consideration: High-contrast option or slightly darker surface.
- Safe area: env(safe-area-inset-bottom) on bottom elements.

### Claude's Discretion
- Exact operator status bar layout (flex items order)
- Vote progress bar segment colors and widths
- Voter button icon choices (checkmark, X, dash, circle)
- Whether to add a subtle haptic-style animation on vote button press
- Exact guidance panel content and styling

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CORE-03 | Operator console — redesign visuel (sidebar agenda, status bar, tabs, live panel, densité opérationnelle, action tooltips) | Full code audit of operator.css, operator-exec.html, operator.htmx.html identifies every element needing redesign |
| SEC-05 | Vote (mobile) — redesign visuel (ballot, boutons, feedback, états, vote confirmation tooltips) | Full code audit of vote.css, vote.htmx.html identifies current button states, confirmation sheet, waiting state |
</phase_requirements>

---

## Summary

Phase 37 is a pure CSS/HTML visual redesign of two pages that run simultaneously during live sessions. The operator console (`operator.htmx.html` + `operator.css` + `operator-exec.html`) runs on a laptop/desktop and manages the session. The voter ballot (`vote.htmx.html` + `vote.css`) runs on each participant's phone or tablet. Both pages already have functional HTML structure and JS behaviour — the redesign touches only CSS and minor HTML class additions.

The operator page currently has a working CSS Grid layout (280px left sidebar + fluid main area) with a meeting bar at top, horizontal tab navigation, and an execution split panel (`op-split`) containing a resolution card (left) and agenda list (right). The status bar (`meeting-bar`) has a 3px persona-cyan top accent stripe. The agenda items (`op-agenda-item`) have small circles and plain text — no badge numbers, no status chips beyond `.current` highlight. The action bar (`op-action-bar`) has "Proclamer" and "Fermer le vote" buttons with keyboard hints but no `ag-tooltip` wrappers. The KPI strip (`op-kpi-strip`) uses JetBrains Mono values but small 0.7rem uppercase labels — already close to the target, needs more visual weight.

The voter ballot uses a flex column with `vote-app` and `100dvh`. Vote buttons are already 72px+ min-height in a 2x2 grid, have gradient backgrounds, and ripple feedback — but they are stacked 2x2 not full-width stacked 1x4. The confirmation sheet is a bottom modal but styled plainly. The waiting state (`vote-waiting-state`) is bare text with no animation. The confirmation post-vote state (`vote-confirmed-state`) shows just a text checkmark.

**Primary recommendation:** In operator.css, lift the status bar to a 40px compact strip with session status + connected count + elapsed time + SSE dot, wrap all action buttons in `ag-tooltip`, upgrade agenda items to card style with number badge + status chip. In vote.css, switch vote buttons to full-width 1-column stack with 88px height, add a `@keyframes vote-btn-press` scale animation, redesign the waiting state with a pulse ring, and build a proper confirmation state with large checkmark icon.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vanilla CSS custom properties | — | All styling; no build step | Project identity — no Tailwind/PostCSS |
| JetBrains Mono | via Google Fonts | Numeric/data values (KPIs, tally counts) | Already loaded; used in op-kpi-value, op-bar-pct |
| Bricolage Grotesque | via Google Fonts | UI text | Project heading font |
| `ag-tooltip` web component | local | Hover tooltips on action buttons | Already built, used in Phase 35-36 |
| `ag-badge` web component | local | Status badges with pulse for live states | Already built, has variant="live" pulse attr |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| CSS `color-mix()` | native | Tinted overlays for button states | Already used throughout both CSS files |
| `env(safe-area-inset-bottom)` | native | iPhone notch clearance | vote-bottom-nav already uses it |
| CSS `clamp()` | native | Fluid motion title scaling | Already used in .motion-title |
| `anime.js` | CDN defer | KPI count-up animation | Already used in operator-exec.js — preserve |

### No New Packages
This phase installs nothing. All tooling already present.

---

## Architecture Patterns

### Operator Page Structure (Current + Target)

```
[data-page-role="operator"] .app-shell
  grid-template-rows: auto auto 1fr
  ├── .meeting-bar (statusbar)           ← REDESIGN: compact 40px strip
  ├── .tabs-nav (tabnav)                 ← MINOR: live tab badge refinement
  └── .app-main                          ← keep grid: 280px sidebar + 1fr main
       ├── .op-agenda (sidebar)          ← REDESIGN: agenda items as cards
       └── .op-main-content
            └── #viewExec (exec mode)
                 ├── .op-kpi-strip       ← ENHANCE: more visual weight
                 ├── .op-resolution-progress ← keep
                 ├── .op-guidance        ← REDESIGN: info card style
                 ├── .op-split
                 │    ├── .op-panel (resolution card)  ← ENHANCE: live panel
                 │    └── .op-sidebar (agenda mini)    ← keep (duplicate of left sidebar)
                 └── .op-action-bar      ← ENHANCE: wrap in ag-tooltip
```

### Voter Ballot Structure (Current + Target)

```
.vote-app (100dvh flex column)
  ├── .page-header                       ← keep minimal
  ├── .vote-identity-banner              ← keep
  ├── .context-bar                       ← keep (pre-auth only)
  ├── .vote-progress-dots                ← keep
  ├── .offline-banner                    ← keep
  ├── .vote-main (flex: 1, overflow-y: auto)
  │    ├── .vote-waiting-state           ← REDESIGN: pulse ring animation
  │    ├── .vote-confirmed-state         ← REDESIGN: large checkmark + animation
  │    ├── .speech-panel                 ← keep (already 72px button, amber/green states)
  │    ├── .motion-card                  ← ENHANCE: better title prominence
  │    └── #vote-buttons (.vote-section)
  │         └── .vote-buttons            ← REDESIGN: 1-column stacked, 88px height
  ├── .vote-bottom-nav (fixed, mobile)   ← keep
  └── .vote-footer                       ← keep
```

### Pattern 1: ag-tooltip on Action Buttons

All operator action buttons that can be disabled need `ag-tooltip` wrapping. Already used in the Hub (Phase 36). Pattern:

```html
<!-- Source: public/operator.htmx.html lines 381-386 (Hub example) -->
<ag-tooltip text="Disponible après complétion de la fiche séance" position="bottom">
  <button class="btn btn-sm btn-secondary" id="hubSendConvocation" disabled>
    <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-mail"></use></svg>
    Envoyer 1ère convocation
  </button>
</ag-tooltip>
```

Apply same pattern to: `opBtnToggleVote`, `opBtnProclaim`, `opBtnUnanimity`, `opBtnPasserelle`, `opBtnProxy`, `opBtnSuspend`.

### Pattern 2: ag-badge with pulse for Live States

```html
<!-- ag-badge already supports pulse attribute -->
<ag-badge variant="live" pulse>En direct</ag-badge>
<ag-badge variant="warning" pulse>Reconnexion...</ag-badge>
<ag-badge variant="danger">Hors ligne</ag-badge>
```

Use `ag-badge` in the status bar for session status. The SSE dot (`.op-sse-indicator` / `.op-sse-dot`) already exists and pulses — elevate it into the compact status bar.

### Pattern 3: Vote Button Full-Width Stack (Target)

```css
/* Source: vote.css current — 2x2 grid, change to 1-column */
.vote-buttons {
  display: grid;
  grid-template-columns: 1fr;          /* was: 1fr 1fr */
  grid-template-rows: repeat(4, 88px); /* was: 1fr 1fr */
  gap: 10px;
  max-width: 480px;
  margin: 0 auto;
  width: 100%;
}

.vote-btn {
  min-height: 88px;                    /* was: 72px */
  flex-direction: row;                 /* was: column */
  gap: 1rem;
  font-size: var(--text-xl);           /* was: var(--text-2xl) */
  border-radius: 14px;
}
```

### Pattern 4: Confirmation State with Large Checkmark

Current `vote-confirmed-state` is just: `<p class="vote-confirmed-text">Vote enregistré ✓</p>` — purely text.

Target: a visually satisfying confirmation card.

```css
/* New class in vote.css */
.vote-confirmed-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  flex: 1;
  animation: confirmReveal 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.vote-confirmed-icon {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--color-success);
  color: var(--color-text-inverse);
  display: flex;
  align-items: center;
  justify-content: center;
}

@keyframes confirmReveal {
  from { opacity: 0; transform: scale(0.7); }
  to   { opacity: 1; transform: scale(1); }
}
```

### Pattern 5: Waiting State Pulse Ring

Current `.vote-waiting-state` has plain text. Target: a calm visual with a pulsing ring.

```css
.vote-waiting-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1.5rem;
  flex: 1;
  padding: 2rem;
}

.vote-waiting-ring {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  border: 3px solid var(--color-border);
  border-top-color: var(--color-primary);
  animation: waitingRing 2s linear infinite;
}

@keyframes waitingRing {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}
```

Alternatively, a concentric ring pulse (not spinner) is less anxiety-inducing for voters:

```css
.vote-waiting-pulse {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--color-primary-subtle);
  animation: waitingPulse 2.5s ease-in-out infinite;
}

@keyframes waitingPulse {
  0%, 100% { transform: scale(1); opacity: 0.7; }
  50% { transform: scale(1.3); opacity: 0.3; }
}
```

**Claude's discretion:** Prefer the concentric pulse — less mechanical, less "loading" feel for a waiting state that might last minutes.

### Pattern 6: Compact Status Bar (Operator)

Current meeting-bar is a 2-row flex column with `meeting-bar-top` and `meeting-bar-actions` that wraps. Too tall. Target: single 40px strip.

```css
/* Override meeting-bar for exec mode */
.op-exec-status-bar {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0 1.25rem;
  height: 40px;
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  border-top: 3px solid var(--persona-operateur);
  flex-shrink: 0;
}
```

Items (left to right): session title (truncated), SSE status dot, "En direct" badge, connected count, elapsed timer (JetBrains Mono).

### Anti-Patterns to Avoid

- **Changing JS logic for visual effects:** All vote button behaviour (disabled state, aria-pressed, confirmation overlay) is controlled by vote.js — CSS must not conflict with JS class toggling (`.selected`, `[aria-pressed="true"]`, `[hidden]`).
- **Removing existing classes:** Operator JS queries `.op-agenda-item`, `.op-tab`, `.op-action-bar`, `.op-kpi-strip` by class — never rename these.
- **Adding new HTML elements without reading JS rendering functions:** `renderAgendaList()` in operator-exec.js builds `.op-agenda-item` HTML directly — any new badge/chip must be added to that function's template string too.
- **Breaking the 2x2 fallback:** If switching vote-buttons to 1-column on desktop, ensure landscape tablet still works (current: `grid-template-columns: 1fr 1fr` at min-width 769px + landscape).

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Tooltip on disabled buttons | Custom CSS :after tooltip | `ag-tooltip` web component | Already handles focus-within, z-index, arrow, SR |
| Live status pill | Custom badge div | `ag-badge variant="live" pulse` | Already handles pulse animation, color variants |
| KPI count-up animation | Custom JS interval | `anime.js` + `animateKpiValue()` | Already exists in operator-exec.js |
| SSE indicator | New component | Existing `.op-sse-indicator[data-sse-state]` | Already wired to SSE state machine in operator-realtime.js |

---

## Common Pitfalls

### Pitfall 1: ag-tooltip on disabled buttons

**What goes wrong:** `disabled` attribute on a `<button>` prevents mouse events, so `ag-tooltip`'s `:hover` listener never fires. Tooltip never shows.

**Why it happens:** The browser swallows pointer events on disabled form elements.

**How to avoid:** Wrap the button in a `<span>` (not the ag-tooltip slot) that absorbs hover, OR use `pointer-events: none` on the button and `pointer-events: all` on the wrapper. The existing pattern in hub.css wraps `ag-tooltip > button[disabled]` — this works because ag-tooltip's `:host(:hover)` fires on the host element (the wrapper), not the button.

**Verified:** hub.html lines 382-386 confirm this pattern works. The `ag-tooltip` web component uses shadow DOM with `:host(:hover) .tip-body { opacity: 1; }` — host receives hover even when slotted content is disabled.

### Pitfall 2: vote-buttons grid layout break on landscape tablet

**What goes wrong:** Switching to 1-column stacked layout on desktop, forgetting the `@media (min-width: 769px) and (orientation: landscape)` override in vote.css line 1294 that sets `max-width: 680px`. If the 2x2 → 1x4 change is global, landscape tablets get ultra-wide buttons.

**How to avoid:** The new 1-column design is fine even on landscape (max-width: 480px on container + full-width buttons still looks good). Remove or replace the landscape override, or set `grid-template-columns: 1fr 1fr` back for landscape if buttons look too wide.

**Warning signs:** Buttons wider than 480px on a tablet in landscape.

### Pitfall 3: op-action-bar hidden attribute vs CSS

**What goes wrong:** The action bar (`#opActionBar`) uses `hidden` attribute toggled by JS. If you add CSS that overrides `[hidden] { display: none }` (e.g. by setting `display: flex !important`), the hide/show logic breaks.

**How to avoid:** Always use `[hidden] { display: none !important }` in operator.css for the action bar, or ensure new CSS rules use `:not([hidden])` selectors.

### Pitfall 4: op-agenda-item HTML generated by JS

**What goes wrong:** Adding a new status badge or number badge to `.op-agenda-item` in CSS but forgetting the HTML template in `renderAgendaList()` in operator-exec.js doesn't emit the new element.

**How to avoid:** Any new inner element (e.g. `.op-agenda-num`, `.op-agenda-status`) must be added to the string template in `renderAgendaList()`. The current template (line 315-318 in operator-exec.js) is:

```javascript
'<div class="op-agenda-item ' + status + '" ...>' +
  '<span class="op-agenda-circle"></span>' +
  '<span class="op-agenda-title">' + (i + 1) + '. ' + title + '</span>' +
'</div>';
```

New template with badge:
```javascript
'<div class="op-agenda-item ' + status + '" ...>' +
  '<span class="op-agenda-num">' + (i + 1) + '</span>' +
  '<span class="op-agenda-title">' + escapeHtml(m.title || '') + '</span>' +
  '<span class="op-agenda-status-dot"></span>' +
'</div>';
```

### Pitfall 5: JetBrains Mono missing on KPI values

**What goes wrong:** Adding new numeric display elements without applying `font-family: var(--font-mono)` — they render in Bricolage Grotesque which has inconsistent character widths for numbers.

**How to avoid:** Any tabular numeric display (counts, percentages, timers) must explicitly set `font-family: var(--font-mono, 'JetBrains Mono', monospace)` and `font-variant-numeric: tabular-nums`.

---

## Code Examples

Verified patterns from direct code inspection:

### Existing ag-tooltip wrapping (Hub pattern — copy this for operator)
```html
<!-- Source: public/operator.htmx.html hub section ~line 381 -->
<ag-tooltip text="Disponible après complétion de la fiche séance" position="bottom">
  <button class="btn btn-sm btn-secondary" id="hubSendConvocation" disabled>
    Envoyer 1ère convocation
  </button>
</ag-tooltip>
```

### Existing KPI strip structure (operator-exec.html)
```html
<!-- Source: public/partials/operator-exec.html lines 3-23 -->
<div class="op-kpi-strip operator-kpi-strip" id="opKpiStrip">
  <div class="op-kpi-item">
    <span class="op-kpi-label">PRESENTS</span>
    <span class="op-kpi-value" id="opKpiPresent">0<span class="op-kpi-total">/0</span></span>
  </div>
  <!-- ... 3 more items: QUORUM, ONT VOTE, RESOLUTION -->
</div>
```

### Existing agenda item rendering (operator-exec.js)
```javascript
// Source: public/assets/js/pages/operator-exec.js lines 313-319
list.innerHTML = O.motionsCache.map(function(m, i) {
  var status = m.closed_at ? 'voted' : (O.currentOpenMotion && O.currentOpenMotion.id === m.id ? 'current' : 'pending');
  return '<div class="op-agenda-item ' + status + '" data-motion-id="' + escapeHtml(m.id) + '" role="button" tabindex="0">' +
    '<span class="op-agenda-circle"></span>' +
    '<span class="op-agenda-title">' + (i + 1) + '. ' + escapeHtml(m.title || '') + '</span>' +
  '</div>';
}).join('');
```

### Existing vote button structure (vote.htmx.html)
```html
<!-- Source: public/vote.htmx.html lines 192-215 -->
<button class="vote-btn vote-btn-for" id="btnFor" data-choice="for" disabled aria-pressed="false">
  <span class="vote-btn-kbd" aria-hidden="true">1</span>
  <span class="vote-btn-icon" aria-hidden="true">
    <svg class="icon icon-lg"><use href="/assets/icons.svg#icon-thumbs-up"></use></svg>
  </span>
  <span>Pour</span>
  <span class="vote-btn-hint">Confirmation requise</span>
</button>
```

### Existing SSE indicator (operator.css)
```css
/* Source: operator.css lines 4390-4430 */
.op-sse-indicator { display: inline-flex; align-items: center; ... }
.op-sse-indicator[data-sse-state="live"] { color: var(--color-success); }
.op-sse-indicator[data-sse-state="reconnecting"] { color: var(--color-warning); }
.op-sse-indicator[data-sse-state="offline"] { color: var(--color-danger); }
.op-sse-dot { width: 8px; height: 8px; border-radius: 50%; ... }
.op-sse-indicator[data-sse-state="live"] .op-sse-dot { animation: ssePulse 2s ease-in-out infinite; }
```

### Persona token (design-system.css)
```css
/* Source: design-system.css lines 381-383 */
--persona-operateur: #0891b2;           /* Cyan — live piloting */
--persona-operateur-subtle: #ecfeff;
--persona-operateur-text: #155e75;
/* Dark mode overrides at line 678: --persona-operateur: #22d3ee; */
```

---

## Before → After: Concrete Visual Changes

### Operator: Meeting Bar (Status Bar)

**Before:**
- Two-row layout: `meeting-bar-top` (session selector, chips) + `meeting-bar-actions` (mode switch, buttons)
- ~80-100px tall, wraps on narrow screens
- 3px cyan top border
- SSE status in bottom row mixed with action buttons

**After:**
- Single `op-exec-status-bar` div, 40px height, flex row
- Items: [session name, truncated] [•] [ag-badge "En direct" pulse] [N membres] [00:00:00 mono elapsed] [SSE dot]
- Meeting bar retains its 3px cyan stripe in BOTH setup and exec modes
- Setup mode: keep existing two-row layout (unchanged)
- Exec mode: replace with 40px compact strip (new class)

### Operator: Agenda Items (op-agenda-item)

**Before:**
- `<span class="op-agenda-circle">` (12px dot, colored by status) + plain text
- No number badge
- `.current` = primary-subtle background + 3px left border
- `.voted` = muted text

**After:**
```
[01]  Approbation des comptes       [● voté]
[02]  Election du bureau            [▶ en cours]   ← active highlight
[03]  Divers                        [○ en attente]
```
- `.op-agenda-num`: 20px×20px rounded square, primary background for current, muted for others
- `.op-agenda-title`: truncate with ellipsis, same as before
- `.op-agenda-status-dot`: 8px dot with status color, right-aligned
- `.current`: stronger highlight — left border 3px primary + primary-subtle bg (keep)
- `.voted`: strike-through text OR muted text + green dot (keep muted text approach)

### Operator: Action Bar Buttons

**Before:**
- `opBtnProclaim`: `btn btn-primary btn-lg`, `<span class="op-kbd-hint">P</span>` only tooltip
- `opBtnToggleVote`: `btn btn-warning btn-lg`, `<span class="op-kbd-hint">F</span>` only
- No ag-tooltip
- Disabled state: opacity 0.5 with no explanation

**After:**
- Both wrapped in `ag-tooltip` with contextual text:
  - Proclaim tooltip: "Proclamer le résultat (raccourci : P)" / disabled: "Vote non encore clôturé"
  - Toggle vote tooltip (open state): "Ouvrir le vote pour cette résolution (F)" / disabled: "Sélectionnez une résolution d'abord"
  - Toggle vote tooltip (close state): "Fermer le vote et calculer le résultat"
- `opBtnToggleVote` when vote open: `btn btn-danger btn-lg` (currently btn-warning)
- `opBtnToggleVote` when no vote: `btn btn-primary btn-lg` (currently btn-warning always)

### Operator: Live Vote Panel (exec-activeVote)

**Before:**
- `.exec-vote-title`: `var(--text-lg)` font, plain text
- `.op-vote-bars`: 24px track height, colored fills — good foundation
- `.exec-kpi` blocks: centered, padded boxes, values in plain text (not mono)
- No "vote open" visual indicator except the op-live-dot (8px hidden dot in header)

**After:**
- `.exec-vote-title`: `var(--text-xl)`, `font-family: var(--font-serif, Fraunces)` for drama, stronger weight
- `.op-vote-bars`: keep 24px track, add animated fill transition already present
- `.exec-kpi-value`: add `font-family: var(--font-mono)` + `font-variant-numeric: tabular-nums`
- Add a pulsing orange/red live indicator pill above the title ("VOTE EN COURS" ag-badge variant="live" pulse)
- `.exec-participation-row`: existing style is adequate

### Voter: Vote Buttons Layout

**Before (2x2 grid):**
```
[Pour      ] [Contre    ]
[Abstention] [Blanc     ]
```
- `grid-template-columns: 1fr 1fr`, `min-height: 72px`
- `flex-direction: column` (icon above label)
- `padding: 2rem 1rem`
- `font-size: var(--text-2xl)`

**After (1x4 stacked):**
```
[✓  Pour                              ]   88px
[✗  Contre                            ]   88px
[—  Abstention                        ]   88px
[○  Blanc                             ]   88px
```
- `grid-template-columns: 1fr` (single column)
- `flex-direction: row` (icon left, label right)
- `min-height: 88px`
- `font-size: var(--text-xl)`
- `justify-content: flex-start` + `gap: 1.25rem` + `padding: 1.25rem 1.5rem`
- Keep all gradient backgrounds, shadows, active transform already present

### Voter: Waiting State

**Before:**
```html
<div class="vote-waiting-state">
  <p class="vote-waiting-text">En attente d'un vote</p>
</div>
```
- Bare text, no visual interest

**After:**
```html
<div class="vote-waiting-state">
  <div class="vote-waiting-pulse" aria-hidden="true"></div>
  <p class="vote-waiting-text">En attente du prochain vote</p>
  <p class="vote-waiting-sub">La séance est en cours</p>
</div>
```
- Concentric pulse ring animation
- Two-line text: main + sub in muted
- `flex: 1`, `align-items: center`, `justify-content: center`

### Voter: Confirmation State

**Before:**
```html
<div class="vote-confirmed-state" hidden>
  <p class="vote-confirmed-text">Vote enregistré ✓</p>
</div>
```
- Just text

**After:**
```html
<div class="vote-confirmed-state" hidden>
  <div class="vote-confirmed-icon" aria-hidden="true">
    <svg class="icon icon-xl"><use href="/assets/icons.svg#icon-check"></use></svg>
  </div>
  <p class="vote-confirmed-choice" id="confirmedChoice">Pour</p>
  <p class="vote-confirmed-text">Vote enregistré</p>
  <p class="vote-confirmed-irreversible">Ce vote est définitif et irréversible.</p>
</div>
```
- 80px circle, success green background, white check icon
- Spring animation on appear: `cubic-bezier(0.34, 1.56, 0.64, 1)` (same as badge-pop)
- Choice text shown in the vote's color (green for Pour, red for Contre, etc.)

---

## State of the Art

| Old Approach | Current Approach | Notes |
|--------------|-----------------|-------|
| 2x2 vote button grid | 1x4 stacked buttons | More legible on phones, eliminates accidental taps on adjacent buttons |
| Plain text waiting state | Pulse ring + two-line text | Reduces voter anxiety during wait |
| Plain text confirmation | Animated checkmark card | "Your voice was heard" satisfaction |
| No tooltips on disabled buttons | ag-tooltip with explanations | Eliminates "why can't I click this?" confusion |
| Agenda items as plain text list | Card-style items with number badge + status | Instantly scannable hierarchy |

---

## Validation Architecture

> nyquist_validation key is absent from .planning/config.json — treating as enabled.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Visual/Browser — CSS-only phase, no server-side test suite |
| Config file | N/A |
| Quick run command | Open `http://localhost:8080/operator.htmx.html` and `http://localhost:8080/vote.htmx.html` in browser |
| Full suite command | Visual review of all states enumerated in Phase Requirements |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CORE-03 | Operator zones visually distinct | manual-visual | browser open | N/A |
| CORE-03 | Every operator button has ag-tooltip | manual-visual | hover each button | N/A |
| CORE-03 | Status bar compact 40px, session info visible | manual-visual | inspect element height | N/A |
| CORE-03 | Agenda items show number badge + status indicator | manual-visual | browser open exec mode | N/A |
| CORE-03 | Live vote panel shows tally in JetBrains Mono | manual-visual | open vote in test session | N/A |
| SEC-05 | Vote buttons 72px+ touch targets, full-width | manual-visual | browser inspect height | N/A |
| SEC-05 | Confirmation state shows checkmark + choice | manual-visual | cast a test vote | N/A |
| SEC-05 | Waiting state has pulse animation | manual-visual | observe waiting state | N/A |

**Justification for manual-only:** This phase produces no new PHP/JS logic — only CSS and minimal HTML structure changes. Functional correctness (vote submission, SSE, auth) is unchanged. Visual correctness requires human eyes.

### Sampling Rate
- **Per task commit:** Open browser, cycle through all states
- **Per wave merge:** Full visual review of both pages in light + dark mode
- **Phase gate:** All success criteria visually confirmed before `/gsd:verify-work`

### Wave 0 Gaps
None — no test infrastructure needed. The verification is browser visual review.

---

## Open Questions

1. **Vote button landscape tablet layout**
   - What we know: Current CSS has `@media (min-width: 769px) and (orientation: landscape) { .vote-buttons { max-width: 680px; } }` — implies 2x2 on landscape.
   - What's unclear: Does the product want 1x4 on landscape tablets too, or revert to 2x2?
   - Recommendation: Use 1x4 (full-width single column) on all viewports up to 1024px, then 2x2 on desktop if ever needed (unlikely — voter interface is tablet/phone only). The CONTEXT.md says "full-width stacked buttons" without viewport exception — implement as 1x4 everywhere.

2. **Confirmation state: show voted choice color**
   - What we know: The HTML `#voteConfirmedState` is shown by JS (vote.js / vote-ui.js) with 3-second display. The current `vote-confirmed-text` is just text.
   - What's unclear: Does vote.js currently pass the choice to the confirmed state element, or is it plain text only?
   - Recommendation: The `#confirmedChoice` element should be set by JS at confirmation time (same moment `#voteConfirmedState` is shown). If vote-ui.js does not already set this, the implementation plan must include adding that one JS line.

---

## Sources

### Primary (HIGH confidence)
- Direct inspection of `/home/user/gestion_votes_php/public/operator.htmx.html` — full HTML structure, 400+ lines
- Direct inspection of `/home/user/gestion_votes_php/public/vote.htmx.html` — full HTML structure, 340 lines
- Direct inspection of `/home/user/gestion_votes_php/public/assets/css/operator.css` — 2600+ lines, all component classes
- Direct inspection of `/home/user/gestion_votes_php/public/assets/css/vote.css` — 1300+ lines, all vote UI classes
- Direct inspection of `/home/user/gestion_votes_php/public/partials/operator-exec.html` — execution panel HTML, 262 lines
- Direct inspection of `/home/user/gestion_votes_php/public/assets/js/pages/operator-exec.js` — renderAgendaList(), keyboard shortcuts, KPI animation
- Direct inspection of `/home/user/gestion_votes_php/public/assets/js/components/ag-tooltip.js` — shadow DOM tooltip, hover/focus-within trigger
- Direct inspection of `/home/user/gestion_votes_php/public/assets/js/components/ag-badge.js` — variants, pulse attribute
- Direct inspection of `/home/user/gestion_votes_php/public/assets/css/design-system.css` — persona tokens (--persona-operateur, --persona-president)

### Secondary (MEDIUM confidence)
- Phase 36 decisions in STATE.md — confirmed gradient CTA pattern, hub step design decisions that establish visual language for this phase
- CONTEXT.md Phase 37 — locked decisions confirmed against what CSS already provides

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries directly observed in use
- Architecture: HIGH — full page HTML and CSS read
- Pitfalls: HIGH — identified from direct code inspection of JS rendering functions and CSS patterns
- Before/After specs: HIGH — precise current values pulled from source, target values derived from CONTEXT.md decisions

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable phase — no dependencies on external APIs or rapidly changing libraries)
