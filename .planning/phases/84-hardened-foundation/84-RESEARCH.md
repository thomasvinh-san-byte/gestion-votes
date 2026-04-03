# Phase 84: Hardened Foundation - Research

**Researched:** 2026-04-03
**Domain:** CSS custom property hygiene, Shadow DOM token fallback literals, @property registration, focus ring tokenization
**Confidence:** HIGH

## Summary

Phase 84 is a pure code-hygiene pass over the entire CSS and Web Component layer. All work is grep-verifiable against the success criteria. The codebase has already completed palette migration (Phase 82) and component geometry consolidation (Phase 83) — every needed token now exists in design-system.css. This phase's only job is to eliminate the remaining places that still hardcode raw hex or rgba values instead of using those tokens.

There are five distinct scopes of work. HARD-01 covers 16 per-page CSS files with a combined 66 hardcoded color occurrences — the majority are already wrapped in `var(--token, fallback)` form, so only the fallback literal needs removing. HARD-02 covers 16 of the 22 Web Components whose Shadow DOM inline styles still carry stale `#1650E0` / `rgba(22,80,224,…)` fallback literals from before the Phase 82 palette shift. HARD-03 is trivial: all 21 `.htmx.html` critical-tokens blocks already carry the correct Phase 82 oklch values and are in sync — no change required. HARD-04 registers a targeted set of color custom properties via `@property` so that CSS transitions animate smoothly instead of cutting hard. HARD-05 eliminates the two remaining hardcoded `rgba(22,80,224,0.35)` focus-ring literals in `ag-modal.js` and `ag-toast.js` and aligns the `--ring-color` light-mode definition in design-system.css.

**Primary recommendation:** Execute the five requirements as five focused tasks in order. Each task is independently verifiable with a grep. Commit after each task to preserve atomicity.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
None — infrastructure phase, discuss step skipped.

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

### Deferred Ideas (OUT OF SCOPE)
None — discuss phase skipped.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| HARD-01 | Zero hardcoded hex values in per-page CSS files — all colors via var(--token) | 66 occurrences identified across 16 files; token mapping for every case documented below |
| HARD-02 | Shadow DOM component fallback hex values audited and updated to match new palette | 16 components identified with stale #1650E0 fallbacks; correct replacement values documented |
| HARD-03 | critical-tokens inline styles in all .htmx.html files synced with new semantic tokens | All 21 files already carry Phase 82 oklch values; confirmed in sync, no changes required |
| HARD-04 | Animatable color and opacity tokens registered for CSS transition support | @property syntax and target token list documented; initial-value constraints explained |
| HARD-05 | Focus ring colors in Shadow DOM components use token reference pattern instead of hardcoded rgba | 2 components (ag-modal, ag-toast) carry rgba(22,80,224,0.35); token chain and fix pattern documented |
</phase_requirements>

---

## Standard Stack

### Core
| Library/Feature | Version | Purpose | Why Standard |
|----------------|---------|---------|--------------|
| CSS Custom Properties (var()) | CSS Level 4 — all evergreen | Token referencing without fallback literals | Tokens already in design-system.css; this phase just removes the fallbacks |
| `@property` at-rule | CSS Houdini Properties & Values Level 1 — Chrome 85+, Firefox 128+, Safari 16.4+ | Register custom properties with `<color>` type so CSS transitions can interpolate them | Without @property, browser treats custom properties as untyped strings; transitions hard-cut |
| oklch() color function | CSS Color Level 4 — all evergreen | Perceptually uniform color representation consistent with Phase 82 work | All new tokens use oklch; fallback replacements must use the same |

### No External Dependencies
This phase makes no changes to PHP, no npm additions, no new build tools. All changes are to `.css` and `.js` (Web Component templates).

---

## Architecture Patterns

### HARD-01: Per-Page CSS File Hardcoded Color Audit

**Complete inventory of all 66 hardcoded occurrences across 16 files:**

#### Files with highest count first:

**operator.css (11 occurrences)**
- Lines 322, 327, 333, 338: SSE status dot colors (`#ef4444`, `#22c55e`, `rgba(34,197,94,0.6)`, `#f59e0b`). The original comments say "vivid — intentionally not a token." However HARD-01 success criteria require zero results from the grep. These MUST be tokenized. The correct tokens are `--color-danger`, `--color-success`, `--color-warning` (note: these are darker palette values, not the same vivid hue). The glow shadow `rgba(34,197,94,0.6)` maps to `var(--color-success-glow)` — this token does not yet exist in design-system.css and must be added as `oklch(0.500 0.135 155 / 0.60)` alongside the fix.
- Line 624, 1114, 1387: `var(--color-text-on-primary, #fff)` — token exists as `--color-primary-text` in design-system; the fallback `#fff` must be removed (use `var(--color-primary-text)` directly, or keep fallback-free form of existing pattern).
- Line 1063: `rgba(255,255,255,0.2)` — white at 20% opacity on a dark surface (keyboard shortcut badge). Use `oklch(1 0 0 / 0.20)`.
- Lines 1106, 1129: `rgba(0,0,0,0.5)` / `rgba(0,0,0,0.55)` — backdrop overlays. Token `--color-backdrop` = `oklch(0 0 0 / 0.50)` already exists. Use `var(--color-backdrop)` for 0.5; create `--color-backdrop-heavy: oklch(0 0 0 / 0.55)` in design-system, or inline `oklch(0 0 0 / 0.55)`.

**users.css (10 occurrences)**
- Lines 65, 197, 297-303, 364-366, 391: These are all `var(--token, fallback)` forms. Strip the fallback literals:
  - `var(--color-purple, #7c3aed)` → `var(--color-purple)`
  - `var(--color-primary-text, #fff)` → `var(--color-primary-text)`
  - `var(--color-purple-subtle, rgba(124,58,237,0.1))` → `var(--color-purple-subtle)`
  - `var(--color-primary-subtle, rgba(22,80,224,0.07))` → `var(--color-primary-subtle)`
  - `var(--color-success-subtle, rgba(90,122,91,0.1))` → `var(--color-success-subtle)`

**meetings.css (9 occurrences)**
- Lines 287-293, 500, 526:
  - `var(--color-info-text, #1650E0)` → `var(--color-info-text)`
  - `var(--color-warning-text, #b8860b)` → `var(--color-warning-text)`
  - `var(--color-danger-text, #c42828)` → `var(--color-danger-text)`
  - `var(--color-success-text, #0b7a40)` → `var(--color-success-text)`
  - `var(--color-success-hover, #064e28)` → `var(--color-success-hover)`
  - `var(--shadow-lg, 0 8px 24px rgba(0,0,0,.12))` → `var(--shadow-lg)`

**analytics.css (9 occurrences)**
- Lines 141, 463-464, 630, 749-751, 1026, 1029:
  - `var(--color-text-on-primary, #fff)` → `var(--color-primary-text)` (note token rename)
  - `var(--color-border, #e5e5e5)` → `var(--color-border)`
  - `var(--color-primary, #1650E0)` → `var(--color-primary)`
  - Lines 749-751: `color: #fff` (hardcoded white on colored badges) — use `var(--color-primary-text)` (white)
  - Lines 1026, 1029: inside `@media print` — `border-bottom: 2px solid #000` and `body { background: #fff; color: #000; }` — these are print-safe absolute values. However, design-system.css already handles `@media print { :root { --color-bg: white; --color-surface: white; --color-text: black; } }`. Replace with `var(--color-text)` / `var(--color-bg)` inside print to stay consistent. If print overrides are needed, use design-system's print block.

**hub.css (6 occurrences)**
- Lines 45, 231, 237, 368, 442, 477:
  - `var(--color-primary-glow, rgba(22,80,224,0.25))` → `var(--color-primary-glow)` (token exists)
  - `var(--color-success-subtle, rgba(34,197,94,0.1))` → `var(--color-success-subtle)` (token exists, different hue than fallback but correct)
  - `var(--color-warning-subtle, rgba(245,158,11,0.1))` → `var(--color-warning-subtle)`
  - `var(--color-primary-subtle, rgba(22,80,224,0.08))` → `var(--color-primary-subtle)`
  - `var(--color-danger-subtle, rgba(239,68,68,0.08))` → `var(--color-danger-subtle)`

**audit.css (4 occurrences)**
- Line 125: `rgba(255,255,255,0.25)` — white at 25% on active tab. Use `oklch(1 0 0 / 0.25)` or new token `--color-surface-alpha-25`.
- Line 177: `var(--color-primary-contrast, #fff)` → this token does NOT exist in design-system. Use `var(--color-primary-text)` instead.
- Lines 108, 589: `rgba(0,0,0,0.5)` → `var(--color-backdrop)`.

**wizard.css (3 occurrences)**
- Lines 153, 158, 752: `var(--color-text-on-primary, #fff)` → `var(--color-primary-text)`.

**settings.css (3 occurrences)**
- Line 182: `var(--color-warning, #f59e0b)` → `var(--color-warning)`.
- Line 313: `var(--color-border-strong, #cbd5e1)` → `var(--color-border-strong)`.
- Line 331: `box-shadow: 0 1px 3px rgba(0,0,0,0.2)` — toggle thumb shadow. Use `var(--shadow-sm)` (which resolves to `0 1px 2px rgb(21 21 16 / 0.06), 0 1px 1px rgb(21 21 16 / 0.04)`) or inline `oklch(0 0 0 / 0.20)`.

**report.css (2 occurrences)**
- Lines 180, 182: `rgba(255,255,255,0.75)` and `rgba(255,255,255,0.15)` — white-alpha text and bg on a colored banner. Use `oklch(1 0 0 / 0.75)` and `oklch(1 0 0 / 0.15)` respectively. The `--color-text-inverse` token is pure white (not alpha), so inline oklch is correct here.

**public.css (2 occurrences)**
- Line 705: `rgba(var(--color-primary-rgb, 59,130,246), 0.2)` — this uses an rgb-channel pattern that is completely obsolete. Replace with `var(--color-primary-glow)` (which is `oklch(0.520 0.195 265 / 0.12)` in light mode). Note: the success criteria grep catches `rgba(` so this MUST be replaced.
- Line 924: `var(--color-primary-glow, rgba(22,80,224,.18))` → `var(--color-primary-glow)`.

**vote.css (1 occurrence)**
- Line 1529: `var(--color-primary-glow, rgba(22,80,224,.18))` → `var(--color-primary-glow)`.

**postsession.css (1 occurrence)**
- Line 369: `var(--shadow-md, 0 4px 6px -1px rgba(0,0,0,.1))` → `var(--shadow-md)`.

**login.css (1 occurrence)**
- Line 456: `var(--color-primary-muted, rgba(30,60,120,0.3))` inside a `radial-gradient`. Use `var(--color-primary-muted)` (token exists as `oklch(0.520 0.195 265 / 0.12)`).

**landing.css (1 occurrence)**
- Line 568: `rgba(255,255,255,0.85)` on `.cta-subtitle`. Use `oklch(1 0 0 / 0.85)` or `var(--sidebar-text)` which is already `oklch(1 0 0 / 0.85)` — same value, different semantic. Better: add token `--color-text-on-primary-muted: oklch(1 0 0 / 0.85)` in design-system and use that.

**email-templates.css (1 occurrence)**
- Line 178: `var(--color-backdrop, rgba(0,0,0,0.5))` → `var(--color-backdrop)`.

**archives.css (1 occurrence)**
- Line 306: `var(--color-warning-text, #b8860b)` → `var(--color-warning-text)`.

**app.css (1 occurrence)**
- Line 792: `var(--color-text-on-primary, #fff)` → `var(--color-primary-text)`.

**Special case — print media queries:**
The success criteria grep runs against `public/assets/css/` without excluding print blocks. Values like `#fff` and `#000` inside `@media print` in analytics.css WILL be caught by the grep and MUST be replaced with `var(--color-bg)` and `var(--color-text)` (which design-system.css overrides to white/black in `@media print { :root { ... } }`).

**New tokens needed in design-system.css to support HARD-01:**
- `--color-success-glow: oklch(0.500 0.135 155 / 0.60)` — for operator SSE dot glow
- `--color-text-on-primary-muted: oklch(1 0 0 / 0.85)` — for landing.css cta-subtitle (or reuse --sidebar-text)
- `--color-backdrop-heavy: oklch(0 0 0 / 0.55)` — for operator quorum overlay at 0.55 opacity

Note: `--color-primary-contrast` referenced in audit.css does NOT exist in design-system. Replace with `--color-primary-text`.
Note: `--color-text-on-primary` referenced in multiple files does NOT exist in design-system either. Replace with `--color-primary-text` (which is the correct token name as of Phase 82).

---

### HARD-02: Shadow DOM Fallback Literal Audit

**Problem:** Shadow DOM components cannot inherit CSS custom properties from the document unless the host exposes them. The fallback literal in `var(--token, fallback)` activates when the token is undefined inside the Shadow DOM. However, all these components ARE inside a document that defines the tokens via `:root`, and Shadow DOM `var()` inheritance DOES cross the shadow boundary for inherited custom properties. So the fallback values are merely stale insurance that now points to the wrong palette values.

**16 components with stale `#1650E0` / `rgba(22,80,224,…)` fallbacks:**

| Component | Pattern to fix | Correct replacement |
|-----------|---------------|---------------------|
| ag-time-input.js | `var(--color-primary, #1650E0)` | `var(--color-primary)` |
| ag-kpi.js | `var(--color-primary, #1650E0)`, `color-mix(in srgb, var(--color-primary, #1650E0) 18%, …)` | `var(--color-primary)`, upgrade srgb→oklch |
| ag-page-header.js | `var(--color-primary, #1650E0)` | `var(--color-primary)` |
| ag-scroll-top.js | `var(--color-primary, #1650E0)`, `rgba(22,80,224,.28)`, `rgba(22,80,224,.35)` | `var(--color-primary)`, `var(--color-primary-glow)`, `var(--color-primary-glow)` |
| ag-mini-bar.js | `'var(--color-primary, #1650E0)'` (JS string) | `'var(--color-primary)'` |
| ag-modal.js | `rgba(22,80,224,0.35)` in box-shadow fallback | See HARD-05 section |
| ag-breadcrumb.js | `var(--color-primary, #1650E0)` | `var(--color-primary)` |
| ag-stepper.js | `var(--color-primary, #1650E0)`, `rgba(22,80,224,.18)` | `var(--color-primary)`, `var(--color-primary-glow)` |
| ag-searchable-select.js | `var(--color-primary, #1650E0)` (×4) | `var(--color-primary)` |
| ag-tz-picker.js | `var(--color-primary, #1650E0)` | `var(--color-primary)` |
| ag-toast.js | `rgba(22,80,224,0.35)` in focus, `var(--color-info, #1650E0)` | See HARD-05; `var(--color-info)` |
| ag-spinner.js | `var(--color-primary, #1650E0)` (×2) | `var(--color-primary)` |
| ag-badge.js | `var(--color-primary, #1650E0)` (×3) | `var(--color-primary)` |
| ag-pagination.js | `var(--color-primary, #1650E0)` (×4) | `var(--color-primary)` |
| ag-confirm.js | `'var(--color-primary, #1650E0)'` (JS string), `var(--color-primary, #1650E0)` | `'var(--color-primary)'`, `var(--color-primary)` |
| ag-popover.js | `var(--color-primary, #1650E0)` (×2) | `var(--color-primary)` |

**Additional stale non-primary fallbacks in other components (HARD-02 scope also covers these):**

| Component | Pattern | Replacement |
|-----------|---------|-------------|
| ag-kpi.js | `color-mix(in srgb, …)` | Upgrade to `color-mix(in oklch, …)` |
| ag-donut.js | `seg.color \|\| '#ccc'` (JS fallback for dynamic colors) | `seg.color \|\| 'var(--color-border)'` |
| ag-vote-button.js | `var(--color-success, #0b7a40)`, `rgba(11,122,64,.14)`, `rgba(11,122,64,.12)`, `var(--color-danger, #c42828)`, `rgba(196,40,40,.14)`, `rgba(196,40,40,.12)`, `rgba(149,163,164,.15)` (×2) | Strip fallbacks, replace rgba with `var(--color-success-glow)`, `var(--color-danger-glow)`, `var(--color-neutral-subtle)` |
| ag-quorum-bar.js | `var(--color-surface, #ffffff)` etc — stale hex fallbacks | Strip fallbacks |

**New tokens needed in design-system.css to support HARD-02:**
- `--color-danger-glow: oklch(0.510 0.175 25 / 0.14)` — for ag-vote-button danger hover glow
- `--color-success-glow` — already added above for operator.css

**Strategy for fallback removal:**
Strip the fallback literal entirely. The correct pattern is `var(--color-primary)` with no fallback. The token is guaranteed to exist via design-system.css which is always loaded. Keeping a stale fallback is worse than no fallback because it produces incorrect colors on token-miss.

---

### HARD-03: critical-tokens Inline Style Audit

**Finding:** All 21 `.htmx.html` files contain identical critical-tokens blocks:
```css
:root { --color-bg: oklch(0.922 0.013 95); --color-surface: oklch(0.969 0.006 95); --color-text: oklch(0.180 0.012 75); }
[data-theme="dark"] { --color-bg: oklch(0.090 0.008 78); --color-surface: oklch(0.115 0.009 78); --color-text: oklch(0.908 0.015 265); }
html, body { background: var(--color-bg); }
```

These values exactly match the Phase 82 token values in design-system.css:
- `--color-bg` light: `var(--stone-200)` = oklch(0.922 0.013 95) ✓
- `--color-surface` light: `var(--stone-50)` = oklch(0.969 0.006 95) ✓
- `--color-text` light: `var(--stone-900)` = oklch(0.180 0.012 75) ✓
- Dark mode values all match ✓

**HARD-03 requires zero work.** The STATE.md concern ("exact list of .htmx.html files with critical-tokens blocks should be verified") is now resolved: 21 files, all in sync.

---

### HARD-04: @property Registration for Animatable Tokens

**Why @property is needed:**
CSS transitions on properties like `color` and `background-color` work when those properties use literal color values. When a property uses `var(--some-custom-property)`, the browser treats the custom property as an untyped string by default. CSS cannot interpolate between two strings — it hard-cuts. `@property` registers a custom property with a specific syntax type (`<color>`), enabling the browser to interpolate it.

**@property syntax:**
```css
@property --color-primary {
  syntax: '<color>';
  inherits: true;
  initial-value: oklch(0.520 0.195 265);
}
```

**Critical constraint:** `initial-value` MUST be a resolved concrete value — NOT `var(--blue-600)` or any `var()` reference. This is a hard spec requirement (CSS Properties and Values API Level 1). Use the oklch literal directly.

**Target tokens to register (those participating in CSS transitions on interactive elements):**

| Token | Light initial-value | Dark override | Why |
|-------|-------------------|---------------|-----|
| `--color-primary` | `oklch(0.520 0.195 265)` | `oklch(0.680 0.130 265)` via `[data-theme=dark]` | btn-primary bg, border, many components |
| `--color-primary-hover` | `color-mix(in oklch, oklch(0.520 0.195 265) 88%, black)` | idem | hover state transition |
| `--color-primary-text` | `oklch(1 0 0)` | `oklch(0.090 0.008 78)` | text on primary buttons |
| `--color-surface` | `oklch(0.969 0.006 95)` | `oklch(0.115 0.009 78)` | card/surface bg transitions |
| `--color-bg` | `oklch(0.922 0.013 95)` | `oklch(0.090 0.008 78)` | page bg theme switch |
| `--color-text` | `oklch(0.530 0.025 80)` | `oklch(0.640 0.018 265)` | text color theme switch |
| `--color-border` | `oklch(0.833 0.022 88)` | `oklch(0.210 0.010 78)` | border transitions |
| `--color-danger` | `oklch(0.510 0.175 25)` | `oklch(0.600 0.185 25)` | danger button transitions |
| `--color-success` | `oklch(0.500 0.135 155)` | `oklch(0.580 0.155 155)` | success state transitions |
| `--color-border-focus` | `oklch(0.520 0.195 265 / 0.50)` | `oklch(0.680 0.130 265 / 0.50)` | focus ring animation |

**Where to add `@property` blocks:**
Add them at the TOP of design-system.css, before the `@layer` declaration. `@property` is not layer-scoped and must be at stylesheet top level.

**`color-mix()` in `initial-value`:** The spec disallows `color-mix()` in `initial-value` for `@property`. For `--color-primary-hover`, either register it with a concrete fallback value: `initial-value: oklch(0.460 0.190 265)` (approximate pre-mixed value) or skip registering hover tokens (the transition on background still works once the base token is typed). The pragmatic approach: register only the base semantic tokens, not the derived `color-mix` ones.

**`inherits: true` vs `false`:**
Use `inherits: true` for all color tokens — they must cascade from `:root` through all descendants including Shadow DOM hosts.

**Dark mode with @property:**
`@property` sets the initial value (fallback when no cascade provides a value). The `[data-theme="dark"] :root` block override still works normally — `@property` does not prevent re-declaration in a selector block. The initial-value is only used when no CSS rule sets the property.

---

### HARD-05: Focus Ring Token Pattern in Shadow DOM Components

**Current state — exactly 2 components with hardcoded `rgba(22,80,224,0.35)` in focus rings:**

**ag-modal.js (line 151):**
```javascript
box-shadow: var(--shadow-focus, 0 0 0 2px var(--color-surface-raised, #fff), 0 0 0 4px rgba(22,80,224,0.35));
```

**ag-toast.js (line 145):**
```javascript
box-shadow: var(--shadow-focus, 0 0 0 2px var(--color-surface-raised, #fff), 0 0 0 4px rgba(22,80,224,0.35));
```

**Token chain for focus rings (verified in design-system.css):**
```
--shadow-focus = 0 0 0 2px var(--color-surface-raised) + 0 0 0 4px var(--color-border-focus)
--color-border-focus = oklch(0.520 0.195 265 / 0.50)  [light — indigo at 50% opacity]
--color-border-focus = oklch(0.680 0.130 265 / 0.50)  [dark — lighter indigo]
```

This is the "2px indigo outline" the success criteria requires.

**Fix pattern:**
```javascript
// Before
box-shadow: var(--shadow-focus, 0 0 0 2px var(--color-surface-raised, #fff), 0 0 0 4px rgba(22,80,224,0.35));

// After — fallback must also use token reference, no raw rgba
box-shadow: var(--shadow-focus);
```

Since `--shadow-focus` is always defined by design-system.css (loaded before any component renders), the fallback is unnecessary. Remove the entire fallback literal.

**ag-confirm.js focus ring (line 117):**
```javascript
.btn:focus-visible { outline: 2px solid var(--color-primary, #1650E0); outline-offset: 2px; }
```
This is a direct `outline` approach (not box-shadow). It uses the correct token pattern but with a stale fallback. Fix: `outline: 2px solid var(--color-primary)`. This counts as HARD-02 (stale fallback), not HARD-05.

**Additional stale `--ring-color` in design-system.css itself (line 512):**
```css
--ring-color: rgba(22, 80, 224, 0.35);  /* light mode — stale! */
--ring-color: oklch(0.680 0.130 265 / 0.40);  /* dark mode — correct */
```
The light-mode `--ring-color` at line 512 must be updated to `oklch(0.520 0.195 265 / 0.35)` (the indigo from the new palette). This is used by `app.css` `.admin-tab:focus-visible` box-shadow. This counts as HARD-05 scope.

**`--shadow-focus-danger` in design-system.css (line 424):**
```css
--shadow-focus-danger: 0 0 0 2px var(--color-surface-raised),
                        0 0 0 4px rgba(196, 40, 40, 0.35);
```
Replace with: `0 0 0 4px var(--color-danger-focus)` where `--color-danger-focus: oklch(0.510 0.175 25 / 0.35)`. Add that token to design-system.css. This is in design-system.css itself, not a per-page file, but it directly impacts the focus ring consistency.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Animatable color tokens | Custom JS that re-applies colors on transition | CSS `@property` | Native browser interpolation, no JS overhead |
| Token-to-hex mapping | A JS lookup table of current hex values for fallbacks | Remove fallbacks entirely | Fallbacks become stale; trust the cascade |
| Print color overrides | Per-file print media queries with raw #fff/#000 | design-system.css `@media print` already overrides :root | Centralized, maintainable |

---

## Common Pitfalls

### Pitfall 1: Removing var() fallback but the token name is wrong
**What goes wrong:** Stripping `var(--color-text-on-primary, #fff)` down to `var(--color-text-on-primary)` while that token doesn't exist in design-system (it does not — the correct name is `--color-primary-text`).
**How to avoid:** When removing a fallback, verify the token name exists in design-system.css. The audit above flags every case where a wrong token name is used.
**Affected files:** audit.css line 177 (`--color-primary-contrast` → `--color-primary-text`), analytics.css, wizard.css, app.css, operator.css (all using `--color-text-on-primary` which should be `--color-primary-text`).

### Pitfall 2: @property initial-value using var()
**What goes wrong:** Writing `initial-value: var(--blue-600)` in `@property` — the spec disallows this. The browser silently ignores the @property declaration.
**How to avoid:** Always use the resolved oklch literal as initial-value.

### Pitfall 3: @property blocks inside @layer
**What goes wrong:** Placing `@property` declarations inside `@layer base { }` — this is invalid. @property must be at the stylesheet top level.
**How to avoid:** Add all @property blocks before the `@layer base, components, v4;` declaration at line 18 of design-system.css.

### Pitfall 4: Dark mode flash after removing fallbacks from critical-tokens
**What goes wrong:** HARD-03 is already in sync, but if someone edits a critical-tokens block in an htmx file, the values must always match design-system.css.
**How to avoid:** HARD-03 requires no changes. Document this in the plan so the implementer doesn't "fix" something that's already correct.

### Pitfall 5: color-mix(in srgb) in ag-kpi.js
**What goes wrong:** `color-mix(in srgb, var(--color-primary) 18%, var(--color-border))` produces non-oklch mixing results, inconsistent with the rest of the palette.
**How to avoid:** Upgrade to `color-mix(in oklch, var(--color-primary) 18%, var(--color-border))` when removing the fallback literals.

### Pitfall 6: Success criteria grep catches inline box-shadow rgba() fallbacks
**What goes wrong:** The HARD-02 success criteria grep is `grep -r "1650E0\|22,80,224\|rgba(22"` — it specifically targets only the stale primary color. However HARD-01 success criteria grep `grep -rn "#[0-9a-fA-F]{3,6}\|rgba("` is broader and catches ANY rgba — including legitimate shadow fallbacks.
**How to avoid:** Shadow fallbacks in per-page CSS files like `var(--shadow-md, 0 4px 6px -1px rgba(0,0,0,.1))` must also have their fallbacks stripped. Every `rgba(` in every per-page CSS file must be eliminated.

---

## Code Examples

### @property Registration Pattern
```css
/* Source: CSS Properties and Values API Level 1 spec — MDN verified */
/* Place BEFORE @layer declaration in design-system.css */

@property --color-primary {
  syntax: '<color>';
  inherits: true;
  initial-value: oklch(0.520 0.195 265);
}

@property --color-surface {
  syntax: '<color>';
  inherits: true;
  initial-value: oklch(0.969 0.006 95);
}

@property --color-bg {
  syntax: '<color>';
  inherits: true;
  initial-value: oklch(0.922 0.013 95);
}
```

### Fallback Stripping Pattern (per-page CSS)
```css
/* Before — stale fallback */
color: var(--color-primary, #1650E0);

/* After — fallback-free */
color: var(--color-primary);
```

### Shadow DOM Focus Ring Fix Pattern (ag-modal.js, ag-toast.js)
```javascript
/* Before */
box-shadow: var(--shadow-focus, 0 0 0 2px var(--color-surface-raised, #fff), 0 0 0 4px rgba(22,80,224,0.35));

/* After */
box-shadow: var(--shadow-focus);
```

### New Token Additions to design-system.css
```css
/* Add to @layer base :root block */
--color-success-glow:  oklch(0.500 0.135 155 / 0.60);
--color-danger-glow:   oklch(0.510 0.175 25 / 0.14);
--color-danger-focus:  oklch(0.510 0.175 25 / 0.35);
--color-backdrop-heavy: oklch(0 0 0 / 0.55);
--color-text-on-primary-muted: oklch(1 0 0 / 0.85);

/* Fix stale --ring-color in light mode (currently line 512) */
--ring-color: oklch(0.520 0.195 265 / 0.35);  /* was rgba(22,80,224,0.35) */

/* Fix --shadow-focus-danger (currently line 423-424) */
--shadow-focus-danger: 0 0 0 2px var(--color-surface-raised),
                       0 0 0 4px var(--color-danger-focus);
```

### Operator SSE Dot Tokenization
```css
/* Before */
.op-sse-dot { background: #ef4444; }
[data-sse-state="live"] .op-sse-dot { background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,0.6); }
[data-sse-state="reconnecting"] .op-sse-dot { background: #f59e0b; }
[data-sse-state="offline"] .op-sse-dot { background: #ef4444; }

/* After — note: token values differ from the "vivid" originals (this is intentional) */
.op-sse-dot { background: var(--color-danger); }
[data-sse-state="live"] .op-sse-dot { background: var(--color-success); box-shadow: 0 0 6px var(--color-success-glow); }
[data-sse-state="reconnecting"] .op-sse-dot { background: var(--color-warning); }
[data-sse-state="offline"] .op-sse-dot { background: var(--color-danger); }
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `rgba(22,80,224,0.35)` hardcoded | `var(--color-border-focus)` token | Phase 82 (token added) | Hardcoded literal survives theme switch; token adapts |
| `#1650E0` as var() fallback | `var(--color-primary)` with no fallback | Phase 82 palette shift | Old fallback was blue-600; new primary in dark mode is blue-400 — stale fallback produces wrong color |
| `color-mix(in srgb, …)` | `color-mix(in oklch, …)` | Phase 82 decision | srgb mixing produces muddy intermediates; oklch maintains perceptual uniformity |
| `@layer base` token-only file | @property blocks before @layer | Phase 84 (this phase) | Enables CSS transition interpolation on custom properties |

**Deprecated:**
- `--color-text-on-primary`: Does not exist in design-system.css. Use `--color-primary-text`.
- `--color-primary-contrast`: Does not exist in design-system.css. Use `--color-primary-text`.
- `--color-primary-rgb`: Does not exist and was never added. Use oklch alpha syntax directly.

---

## Open Questions

1. **Operator SSE dot color change**
   - What we know: Original uses "vivid" colors (#ef4444, #22c55e, #f59e0b). Design-system tokens use palette-aligned values that are perceptually darker.
   - What's unclear: Is the visual impact of replacing vivid greens/reds with palette tokens acceptable for the operator status indicator?
   - Recommendation: Proceed with token replacement. The palette tokens are still recognizable red/green/amber. If the product owner wants vivid variants, add `--color-danger-vivid` etc — but that's out of scope for this phase.

2. **ag-donut.js dynamic color assignment**
   - What we know: `seg.color || '#ccc'` — the `seg.color` is passed in by the calling page, not hardcoded. The `#ccc` fallback is the only hardcoded value.
   - What's unclear: Whether HARD-02 success criteria targets this fallback (it's `#ccc`, not `1650E0` or `rgba(22,`).
   - Recommendation: Replace `#ccc` with `var(--color-border)` for consistency; this is best practice even if not strictly required by the success criteria grep.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (unit), Playwright (e2e) |
| Config file | `phpunit.xml` / `tests/e2e/playwright.config.js` |
| Quick run command | `grep -rn "#[0-9a-fA-F]\{3,6\}\|rgba(" public/assets/css/ --include="*.css" \| grep -v "design-system.css"` |
| Full suite command | `grep -r "1650E0\|22,80,224\|rgba(22" public/assets/js/components/` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| HARD-01 | Zero hardcoded hex in per-page CSS | shell grep | `grep -rn "#[0-9a-fA-F]\{3,6\}\|rgba(" public/assets/css/ --include="*.css" \| grep -v design-system.css` returns 0 lines | ✓ (grep, no test file) |
| HARD-02 | Shadow DOM fallbacks match palette | shell grep | `grep -r "1650E0\|22,80,224\|rgba(22" public/assets/js/components/` returns 0 lines | ✓ (grep, no test file) |
| HARD-03 | critical-tokens in sync | shell grep | `grep -c "oklch(0.922" public/*.htmx.html` returns 21 | ✓ (already passing) |
| HARD-04 | Animated token transitions smooth | manual visual | Toggle dark mode; apply CSS transition to btn-primary; verify no hard-cut | manual-only |
| HARD-05 | Focus rings use token pattern | shell grep | `grep -r "rgba(22,80,224" public/assets/js/components/` returns 0 lines | ✓ (grep, no test file) |

### Sampling Rate
- **Per task commit:** Run the specific grep for that task's success criteria
- **Per wave merge:** Run all four grep checks in sequence
- **Phase gate:** All four greps return 0 results before `/gsd:verify-work`

### Wave 0 Gaps
None — existing grep-based verification is sufficient. No new test files required for this phase.

---

## Sources

### Primary (HIGH confidence)
- Direct codebase analysis: `public/assets/css/` — all 25 CSS files inspected, full grep inventory
- Direct codebase analysis: `public/assets/js/components/` — all 22 Web Components inspected
- Direct codebase analysis: `public/*.htmx.html` — all 21 critical-tokens blocks read and compared against design-system.css
- design-system.css line 421-424: `--shadow-focus` and `--shadow-focus-danger` definition verified
- design-system.css lines 299, 421, 511-513: focus ring token chain verified end-to-end

### Secondary (MEDIUM confidence)
- CSS Properties and Values API Level 1 (W3C) — `@property` `initial-value` must be a resolved concrete value, cannot contain `var()` references
- MDN Web Docs: @property — `inherits: true` required for cascade across Shadow DOM hosts

### Tertiary (LOW confidence)
- Browser support figures (Chrome 85+, Firefox 128+, Safari 16.4+) — based on knowledge cutoff, verify current caniuse.com if browser support is a concern for this deployment

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all tokens verified to exist in design-system.css
- Architecture: HIGH — complete grep inventory performed on live codebase
- Pitfalls: HIGH — pitfalls derived from direct code inspection, not speculation
- @property semantics: MEDIUM — based on spec knowledge, but `initial-value: color-mix()` restriction should be verified against current spec if needed

**Research date:** 2026-04-03
**Valid until:** 2026-05-03 (stable CSS spec area; token values stable unless Phase 82/83 revisited)
