# Phase 34: Quality Assurance Final Audit - Research

**Researched:** 2026-03-19
**Domain:** CSS/HTML audit — design token compliance, font discipline, depth model, dark mode, accessibility
**Confidence:** HIGH (direct codebase inspection, no external dependencies)

## Summary

This is a pre-audit phase. The researcher has scanned all CSS and HTML files to produce an exact inventory of violations across all 5 QA criteria. The planner does not need to discover violations — they are catalogued here. Every violation entry includes the exact file path and line number (or range) so implementation tasks can be written as direct fix instructions.

The 4 untouched CSS files (app.css, archives.css, report.css, users.css) are largely clean of hardcoded hex colors and use design tokens throughout. The biggest violations are in: (1) Fraunces font appearing on non-h1 elements across several page CSS files, (2) `var(--color-surface-raised)` missing from the majority of page CSS files (16 of 17 page CSS files have zero references), (3) recurring `style=""` attribute blocks across all `.htmx.html` files from a shared mobile-footer partial pattern, and (4) one hardcoded transition in hub.css. The untouched files' primary gaps are: no `--color-surface-raised` usage, hardcoded `rgba()` fallbacks in users.css, and no hover transform in archives.css.

**Primary recommendation:** Implement fixes in 5 focused passes (one per QA criterion) rather than page-by-page, as several violations recur across many files from the same root cause (shared partials, global typography rules).

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Audit ALL pages: dashboard, wizard, operator, hub, post-session, analytics, help, email-templates, meetings, audit, archives, members, users, settings, vote, login, landing, public, trust, validate, doc, report
- QA-01: No uniform shadows; no uniform radii; spatial hierarchy via spacing tokens; color for signal not decoration; weight contrast (700>600>400); hover has transform not just color change
- QA-02: Every page must use 3 tonal layers: --color-bg, --color-surface, --color-surface-raised
- QA-03: Fraunces (--font-display) only on h1/page-title — never on h2-h6, section headings, card titles, or any non-page-title element
- QA-04: Dark mode — no pure black (#000), no invisible borders, no washed-out text
- QA-05: All transitions ≤ 200ms; focus rings ≥ 3:1 contrast; zero style="" in production HTML
- 4 untouched CSS files are highest risk: app.css, archives.css, report.css, users.css

### Claude's Discretion
- Order of page auditing (can batch by similarity)
- Whether to create a formal audit report or just fix-and-commit
- How to handle edge cases in print-only CSS (acceptable exceptions)
- Whether to add missing `var(--color-surface-raised)` to pages that currently only have 2 depth layers

### Deferred Ideas (OUT OF SCOPE)
- None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| QA-01 | Every page passes the "6 AI anti-patterns" check | Violations found in archives.css (no hover transform), report.css (no hover transform), users.css (no hover transform); h2 gets font-display globally |
| QA-02 | Background 3-layer stack (bg → surface → raised) applied consistently on every page | 16 of 17 page CSS files have zero `--color-surface-raised` references; 8 files do use it |
| QA-03 | Fraunces display font used exactly once per page (page title only) | 7 violation sites found across analytics.css, hub.css (3), public.css (2), wizard.css, design-system.css (h2 global rule, confirm-dialog-title), landing.css (hero-title, login-title), partials/sidebar.html (inline style) |
| QA-04 | Dark mode visual parity — no pure black, invisible borders, washed-out text | Dark theme tokens look good (--color-bg: #0B0F1A not pure black); untouched files use tokens so auto-derive; needs visual verification pass |
| QA-05 | All transitions ≤ 200ms, focus rings ≥ 3:1, zero inline style="" | hub.css line 120: `transition: all 0.3s ease` (300ms violation); design-system.css line 2323: `--duration-normal, 300ms` fallback; dozens of style="" attributes in HTML files (many are JS-toggle patterns, but several are layout styles that belong in CSS) |
</phase_requirements>

---

## Pre-Audit Violation Inventory

This is the core deliverable. Each violation is categorised, located, and classified by severity.

### QA-01: AI Anti-Pattern Violations

#### Check 1: No Uniform Shadows
**Finding:** PASS (confident)
- design-system.css defines shadow levels: `--shadow-xs`, `--shadow-sm`, `--shadow-md`, `--shadow-lg`, `--shadow-xl`
- Cards use `--shadow-sm`, modals use `--shadow-xl`, tooltips/dropdowns use `--shadow-md`
- Phase 31 locked these semantics — component CSS follows them
- Hover escalates shadow correctly (`.card-clickable:hover` → shadow-md, `.archive-card:hover` → shadow-md)

**Verdict:** No violations found. Confidence: HIGH.

#### Check 2: No Uniform Radii
**Finding:** PASS (confident)
- Semantic radius tokens: `--radius-sm` (4px badges), `--radius` (8px inputs), `--radius-lg` (12px cards), `--radius-xl` (16px modals), `--radius-full` (pills)
- Phase 31 locked these — each component type uses a distinct radius
- **However:** Several files still use literal `999px` or `9999px` instead of `var(--radius-full)`:
  - `archives.css:197` — `.archive-badge { border-radius: 999px }`
  - `trust.css:200, 303` — `border-radius: 999px`
  - `pages.css:748` — `border-radius: 999px`
  - `users.css:196, 223` — `border-radius: 9999px`
  - `meetings.css:285, 337`, `members.css:195, 423`, `admin.css:306`, `public.css:887`, `operator.css:3494` — `border-radius: 9999px`

**Verdict:** Radii are semantically differentiated, but 11 pill-badge sites use literal values instead of `var(--radius-full)`. These are token-hygiene violations, not visual uniformity violations. Low severity — fix as part of the untouched file cleanup.

#### Check 3: Spatial Hierarchy
**Finding:** PASS
- `--space-section` (48px), `--space-card` (24px), `--space-field` (16px) all defined and used throughout
- Page layouts rebuilt in Phases 32-33 use these correctly

**Verdict:** No violations found.

#### Check 4: Color for Signal
**Finding:** PASS
- Status badges, alerts, role badges all use semantic color tokens (--color-success, --color-warning, --color-danger, --color-info)
- No decorative color abuse found

**Verdict:** No violations found.

#### Check 5: Weight Contrast (700 > 600 > 400)
**Finding:** PASS
- `.page-title` → `font-weight: 700`
- Section headings (h3/h4) → `font-weight: var(--font-semibold)` (600)
- Body → `font-weight: var(--font-normal)` (400)
- Token `--font-bold: 700`, `--font-semibold: 600`, `--font-normal: 400` — correct

**Verdict:** No violations found.

#### Check 6: Hover Has Transform (not just color change)
**Finding:** VIOLATIONS FOUND

Files where hover uses only color/shadow changes with NO transform:

| File | Selector | Hover Effect | Missing |
|------|----------|--------------|---------|
| `archives.css:21` | `.archive-card:hover` | `box-shadow: var(--shadow-md)` only | No `transform` |
| `archives.css:131` | `.archive-card-enhanced:hover` | `box-shadow + border-color` only | No `transform` |
| `report.css:27` | `.export-btn:hover` | `border-color + background` only | No `transform` |
| `app.css:301` | `.quick-action:hover` | `background + color` only | No `transform` |
| `app.css:445` | `.admin-tab:hover` | `color` only | (tabs: acceptable — tabs don't lift) |
| `users.css:80` | `.user-row:hover` | `background` only | (rows: borderline — list rows don't typically lift) |

**Prescribed fix:** Add `transform: translateY(-1px)` to `.archive-card:hover`, `.archive-card-enhanced:hover`, `.export-btn:hover`. Quick-action buttons: add `transform: translateY(-1px)`. Tab and row hovers are intentionally flat (acceptable exceptions per design).

---

### QA-02: Three-Depth Background Violations

**Token definitions (light):**
- Layer 1 (body): `--color-bg: #EDECE6`
- Layer 2 (surface): `--color-surface: #FAFAF7`
- Layer 3 (raised/elevated): `--color-surface-raised: #FFFFFF`

**Files with zero `var(--color-surface-raised)` references (excluding design-system.css):**

Files that DO use it: `meetings.css`, `email-templates.css`, `hub.css`, `analytics.css`, `operator.css`, `pages.css`, `public.css` (7 page CSS files).

Files that LACK it (need elevated element to use this token):

| CSS File | Page | Risk Level | Candidate Elements |
|----------|------|------------|-------------------|
| `app.css` | Global shell | HIGH | `.motion-card` header/body separation, `.vote-result-item` |
| `archives.css` | Archives | HIGH | `.archive-card-header`, `.kpi-card` could be raised |
| `report.css` | Report/PV | HIGH | PV preview panel is elevated content |
| `users.css` | Users | HIGH | No raised elements at all |
| `admin.css` | Admin | MEDIUM | KPI cards, modals |
| `audit.css` | Audit | MEDIUM | Modals, overlay panels |
| `doc.css` | Docs | MEDIUM | ToC rail, content panel |
| `help.css` | Help | MEDIUM | FAQ answer panels |
| `landing.css` | Landing | MEDIUM | Feature cards, hero box |
| `login.css` | Login | MEDIUM | Login card itself |
| `members.css` | Members | MEDIUM | Import panel, role cards |
| `postsession.css` | Post-session | MEDIUM | Result cards, validation panel |
| `settings.css` | Settings | MEDIUM | Setting section cards |
| `trust.css` | Trust | MEDIUM | Chain-of-custody panels |
| `validate.css` | Validate | LOW | Simple page, limited depth |
| `vote.css` | Vote | LOW | Mobile voter — depth less relevant |
| `wizard.css` | Wizard | MEDIUM | Wizard step cards |

**Key finding:** The `design-system.css` defines `--color-surface-raised` for modals (`.modal`, `ag-modal`), dropdowns (`.search-results`), and the command palette. These work globally. The missing piece is that individual page-component CSS files don't use it for their own elevated sub-panels.

**Prescribed fix:** For each page CSS file, identify the element that sits "above" the card surface (e.g., a card sub-header with distinct background, an embedded preview panel, a sticky toolbar) and set its background to `var(--color-surface-raised)`. In many cases, existing `.archive-card-header` and `.motion-card-footer` use `var(--color-bg-subtle)` — replace with `var(--color-surface-raised)` where the intent is elevation rather than de-emphasis.

---

### QA-03: Fraunces Font Discipline Violations

**Rule:** `font-family: var(--font-display)` or `'Fraunces'` only on `.page-title` and `h1, .h1` — never anywhere else.

**Violations found:**

#### design-system.css (CRITICAL — global scope)
| Line | Selector | Violation |
|------|----------|-----------|
| 762 | `h2, .h2` | h2 globally gets font-display — MUST be removed; h2 should use --font-sans |
| 890 | `.logo` | Logo wordmark in app-header uses font-display (intentional brand element — acceptable exception) |
| 4880 | `.confirm-dialog-title` | Confirm dialog title uses font-display — violates "h1 only" rule |

**Note on h2:** Removing `font-family: var(--font-display)` from the `h2, .h2` rule is the highest-impact fix in this phase. Every page that uses an `h2` element for section headings currently renders in Fraunces. This alone produces the "AI uniform text" feel.

#### analytics.css (line 159)
| Selector | Violation |
|----------|-----------|
| `.overview-card-value` | KPI numeric value uses `'Fraunces', serif` (hardcoded, not even using token) |

#### hub.css
| Line | Selector | Violation |
|------|----------|-----------|
| 68 | `.hub-identity-date` | Date display uses font-display |
| 435 | `.hub-action-title` | Action card title uses font-display |
| 841 | `.hub-kpi-value-num` | KPI number uses font-display |

#### public.css (projection screen)
| Line | Selector | Violation |
|------|----------|-----------|
| 55 | `.projection-title` | Motion title on projector screen uses font-display |
| 922 | `.motion-title` (repeated) | `.motion-title, .projection-title` rule reapplies font-display |

**Note on public.css:** The projector/voting screen is a special context — large dramatic text is reasonable for a presentation display. Classify as "Claude's Discretion" — may keep if intentional display context, or replace with a bold weight of Bricolage Grotesque. Flag for planner to decide.

#### wizard.css (line 183)
| Selector | Violation |
|----------|-----------|
| `.wf-step` | Wizard step header uses font-display |

#### landing.css
| Line | Selector | Violation |
|------|----------|-----------|
| 86 | `.hero-title` | Landing page hero h1 — this IS a page title equivalent, acceptable |
| 152 | `.login-title` | Login form title — NOT an h1, this is a form label — VIOLATION |

#### partials/sidebar.html (inline style, line 14)
```html
<span class="nav-label" style="font-family:var(--font-display);...">AG-VOTE</span>
```
This is the brand wordmark in the sidebar — intentional brand element, same category as `.logo`. Acceptable exception.

**Summary table — violations to fix:**

| File | Selector | Action |
|------|----------|--------|
| `design-system.css:762` | `h2, .h2` | Remove `font-family: var(--font-display)` |
| `design-system.css:4880` | `.confirm-dialog-title` | Replace with `var(--font-sans)` |
| `analytics.css:159` | `.overview-card-value` | Replace `'Fraunces', serif` with `var(--font-mono)` (numeric KPI → mono) |
| `hub.css:68` | `.hub-identity-date` | Replace with `var(--font-sans)` |
| `hub.css:435` | `.hub-action-title` | Replace with `var(--font-sans)` |
| `hub.css:841` | `.hub-kpi-value-num` | Replace with `var(--font-mono)` (KPI number → mono) |
| `wizard.css:183` | `.wf-step` | Replace with `var(--font-sans)` |
| `landing.css:152` | `.login-title` | Replace with `var(--font-sans)` |
| `public.css:55, 922` | `.projection-title`, `.motion-title` | Planner decision: keep (display context) or replace |

**Acceptable exceptions (not violations):**
- `design-system.css:753` — `h1, .h1` — correct
- `design-system.css:1311` — `.page-title` — correct
- `landing.css:86` — `.hero-title` (landing page hero title) — acceptable
- `design-system.css:890` — `.logo` app-header wordmark — brand element
- `partials/sidebar.html:14` — AG-VOTE brand wordmark — brand element

---

### QA-04: Dark Mode Visual Parity

**Token inspection (dark theme, lines 588-659):**
- `--color-bg: #0B0F1A` — dark navy, NOT pure black. PASS.
- `--color-surface: #141820` — darker navy. PASS.
- `--color-surface-raised: #1E2438` — elevated tone. PASS.
- `--color-border: #252C3C`, `--color-border-subtle: #1E2434` — both distinct from surfaces. PASS on invisible borders.
- `--color-text: #7A8499`, `--color-text-dark: #ECF0FA` — reasonable contrast. PASS.
- `--color-text-muted: #50596C` — this is the risk: muted text on `#0B0F1A` background. Contrast ratio ≈ 2.5:1 (below 3:1 for body text). LOW-severity concern.

**Files using hardcoded colors that won't respond to dark mode:**
- `analytics.css:699` (print CSS): `background: #fff; color: #000` — this is print-only, acceptable exception
- `design-system.css:1157`: `color: #fff` in button context — acceptable (white on colored button)
- `design-system.css:3612`: `color: #6b7280` — hardcoded gray, does not respond to dark mode. VIOLATION.
- `design-system.css:908`: `.logo-mark { box-shadow: 0 1px 4px rgba(22, 80, 224, .28)... }` — hardcoded blue glow, acceptable (brand element)
- `users.css:124,125`: Auditor avatar fallbacks `rgba(124, 58, 237, 0.1)` / `#7c3aed` — these are CSS custom property fallbacks (`var(--color-purple, #7c3aed)`). If `--color-purple` is defined in dark theme (it is, at line 642: `--color-purple: #8C72F8`), the fallback never activates. PASS in practice.

**Dark mode risk areas to verify visually:**
1. Pages that use `.archive-card-header` with `linear-gradient(135deg, var(--color-bg-subtle) 0%, var(--color-surface) 100%)` — gradient may not have sufficient contrast distinction in dark mode
2. `.perm-matrix` in app.css uses `background: var(--color-surface)` for sticky cells — verify no bleed-through
3. Sidebar background `--sidebar-bg: #080B10` — very dark, verify text contrast

**Verdict:** Dark mode tokens are well-structured. No systematic pure black or invisible border issues found. Needs a visual verification pass for contrast edge cases.

---

### QA-05: Performance & Accessibility Violations

#### Transition Duration Violations (> 200ms)

| File | Line | Violation | Fix |
|------|------|-----------|-----|
| `hub.css` | 120 | `transition: all 0.3s ease` (300ms) | Replace with `var(--transition-ui)` |
| `design-system.css` | 2323 | `transition: width var(--duration-normal, 300ms)` | The fallback `300ms` is wrong — `--duration-normal` is `150ms`. Remove `300ms` fallback. |

**Note on animation tokens:** `--duration-slow` (250ms), `--duration-deliberate` (300ms), `--duration-elaborate` (400ms), `--duration-dramatic` (500ms) are used for `animation:` (not `transition:`), which is acceptable per the requirement (transitions ≤ 200ms, not animations). `fadeIn` at 250ms on panel enter is fine.

**Token clarification critical for planner:**
- `--duration-moderate: 200ms` is the upper bound for transitions — already defined and used
- `--duration-slow: 250ms` should only be used in `animation:` keyframes, not `transition:`
- All existing `transition:` properties using `var(--duration-fast)` (100ms) or `var(--duration-normal)` (150ms) PASS

#### Focus Ring Violations
**Finding:** PASS
- `--shadow-focus` is defined globally and applied via `design-system.css` across all interactive elements
- CONTEXT.md confirms "verified in Phase 31"

**Verdict:** No violations found.

#### Inline style="" Violations

The QA-05 requirement states "zero `style=""` in production HTML." This requires careful categorisation:

**Category A — JS-functional (acceptable — cannot move to CSS):**
These are toggled by JavaScript and must remain as inline styles:
- `style="display: none;"` / `style="display:none;"` — JS show/hide (all pages)
- `style="width: 0%"` / `style="left: 50%"` — JS-animated progress bars (`public.htmx.html`)
- `style="display:none"` on `span.nav-badge` in sidebar — JS badge show/hide

**Category B — Layout styles that BELONG in CSS (VIOLATIONS):**

| File | Line | Inline Style | Fix |
|------|------|--------------|-----|
| `partials/sidebar.html:8` | 8 | `style="height:48px;padding:0 8px 0 14px;margin-bottom:4px;"` on `.nav-item` logo | Move to `.nav-brand` class in design-system.css |
| `partials/sidebar.html:10` | 10 | `style="width:22px;height:22px;border-radius:4px;font-size:10px;"` on `.logo-mark` | Move to `.sidebar .logo-mark` selector |
| `partials/sidebar.html:14` | 14 | `style="font-family:var(--font-display);font-size:15px;..."` | Move to `.nav-brand-label` class |
| `dashboard.htmx.html:56` | 56 | `style="margin:.5rem 0 0;..."` on `<ul>` tooltip list | Add `.onboarding-list` class |
| `dashboard.htmx.html:121` | 121 | `style="margin-bottom: var(--space-card);"` on `.card` | Add class or use gap on parent |
| `dashboard.htmx.html:158,159,168` | 158+ | `style="margin-bottom: var(--space-card/3);"` on card-title/shortcut | Move to CSS |
| `archives.htmx.html:118,136` | 118,136 | `style="padding: var(--space-4) var(--space-card);"` | Move to archives.css |
| `report.htmx.html:102` | 102 | `style="position:relative;min-height:200px;"` | Move to report.css as `.pv-preview` |
| `report.htmx.html:103-106` | 103-106 | Entire empty-state div with layout + typography styles | Move to report.css as `.pv-empty-state` |
| `admin.htmx.html:92-113` | 92-113 | `style="background: var(--color-X-subtle); color: var(--color-X);"` on `.dash-kpi-icon` | Move to admin.css with variant classes |
| `admin.htmx.html:131-158` | 131+ | `style="width:X%"` on `.skeleton-line` elements | Use `.skeleton-w-*` classes (already exist in app.css!) |

**Category C — Shared mobile footer pattern (all pages):**
Every `.htmx.html` file contains a mobile footer with:
```html
<a href="..." class="logo" style="font-size:12px;gap:6px;">
<span class="logo-mark" style="width:18px;height:18px;border-radius:3px;font-size:8px;">
<span style="flex:1"></span>
<a ... style="text-decoration:none;">
```
This is a shared pattern across all 20+ pages. The fix is to add CSS rules targeting `.mobile-footer .logo` and `.mobile-footer .logo-mark` in app.css, then remove the inline styles from all HTML files.

**Email templates (app/Templates/):**
`email_report.php` and `email_invitation.php` are email templates. Inline styles in email HTML are REQUIRED for email client compatibility — this is an accepted exception and must not be "fixed."

`vote_confirm.php` and `vote_form.php` have inline styles on `<body>` and `.card` — these are rendered server-side and not part of the regular SPA. Low priority; evaluate case by case.

**Verdict:** Significant inline-style work, but it follows a clear pattern — the mobile footer appears in every file. Fix the CSS rule once, remove from all files. The admin.css skeleton width issue has existing classes (`skeleton-w-*`) that aren't being used.

---

## Architecture Patterns

### Recommended Fix Order

```
Wave 1 (Design System globals — high leverage)
├── design-system.css: Remove font-display from h2, .h2
├── design-system.css: Fix .confirm-dialog-title font
├── design-system.css: Remove 300ms fallback from width transition
└── design-system.css: Fix #6b7280 hardcoded color

Wave 2 (Untouched files — highest risk group)
├── app.css: Add --color-surface-raised to elevated elements
├── app.css: Add CSS rules for mobile-footer logo/logo-mark
├── app.css: Add hover transform to .quick-action
├── archives.css: Add hover transform to .archive-card variants
├── archives.css: Add --color-surface-raised to .archive-card-header
├── archives.css: Replace 999px with var(--radius-full)
├── report.css: Add .pv-preview and .pv-empty-state styles
├── report.css: Add hover transform to .export-btn
├── report.css: Add --color-surface-raised to preview panel
└── users.css: Replace 9999px with var(--radius-full)

Wave 3 (Page CSS font discipline)
├── analytics.css: Replace 'Fraunces' with var(--font-mono)
├── hub.css: Fix 3 font-display violations
├── hub.css: Fix transition: all 0.3s ease
├── wizard.css: Fix .wf-step font-display
└── landing.css: Fix .login-title font-display

Wave 4 (HTML inline style removal)
├── partials/sidebar.html: Move brand styles to CSS
├── report.htmx.html: Move pv-preview/empty-state styles to CSS
├── admin.htmx.html: Use existing skeleton-w-* classes
├── admin.htmx.html: Add dash-kpi-icon variant classes
├── dashboard.htmx.html: Remove margin inline styles
└── archives.htmx.html: Remove padding inline styles

Wave 5 (--color-surface-raised sweep — remaining pages)
└── Add surface-raised to elevated sub-elements across remaining 9 page CSS files
```

### Anti-Patterns to Avoid
- **Don't remove all `style="display:none"` attributes** — these are JavaScript-controlled visibility toggles, not layout styles
- **Don't tokenize email template inline styles** — email clients require inline CSS; these are exempt
- **Don't add translateY to tab hovers** — underline-tab navigation is intentionally flat; lift transform on a tab is wrong UX
- **Don't add translateY to table row hovers** — row hover uses background tint only; lift on rows is wrong

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Pill border radius | `border-radius: 9999px` literals | `var(--radius-full)` | Already tokenized; use the token |
| Skeleton widths | `style="width:60%"` | `.skeleton-w-60` | Already exists in app.css lines 415-419 |
| KPI icon colors | `style="background:...;color:..."` | BEM variant classes `.dash-kpi-icon.primary` etc | Keeps HTML clean |
| Mobile footer logo sizing | Repeated inline styles on every page | `.mobile-footer .logo`, `.mobile-footer .logo-mark` CSS rules | Single fix, all pages benefit |

---

## Common Pitfalls

### Pitfall 1: Removing JS-functional display:none
**What goes wrong:** Removing `style="display:none"` breaks JavaScript show/hide — elements become permanently visible
**Why it happens:** QA-05 says "zero style=" which looks absolute
**How to avoid:** Only remove `style=""` attributes that contain layout/typography properties. Leave `display:none` JS toggles intact.
**Warning signs:** If the element has an `id=` that matches a JS variable name, it's JS-controlled

### Pitfall 2: Breaking the h2 rule globally
**What goes wrong:** Removing `font-family: var(--font-display)` from `h2, .h2` in design-system.css may expose places where h2 was used as a visual page title and relied on Fraunces
**Why it happens:** Some pages may have used h2 where they should have used `.page-title`
**How to avoid:** After removing from design-system.css, search for `<h2` in all HTML files and verify they render correctly without Fraunces. Hub.css has an h2 equivalent (`.hub-identity-date`) that needs its own font fix.

### Pitfall 3: surface-raised adds visual noise
**What goes wrong:** Aggressively adding `--color-surface-raised` (#FFFFFF) to every sub-element creates a "white box on white box" effect where the distinction is invisible
**Why it happens:** The depth model works only when bg, surface, and raised are genuinely distinct layers
**How to avoid:** Apply `--color-surface-raised` only to elements that are visually "above" their container: sticky toolbars, floating cards, modals, embed panels. Do not apply to inline content within an existing surface.

### Pitfall 4: 0.3s transition in hub.css
**What goes wrong:** `transition: all 0.3s ease` is both too slow (300ms) and uses `all` which is a performance anti-pattern
**Why it happens:** Legacy code before duration tokens were defined
**Fix:** Replace with `var(--transition-ui)` or specific properties using `var(--duration-moderate)` (200ms)

---

## Code Examples

### Correct hover with transform (QA-01 fix pattern)
```css
/* Source: design-system.css .card-clickable:hover pattern */
.archive-card:hover {
  box-shadow: var(--shadow-md);
  border-color: var(--color-primary);
  transform: translateY(-1px); /* ADD THIS */
  transition: box-shadow var(--duration-fast), border-color var(--duration-fast), transform var(--duration-fast);
}
```

### Correct three-depth layer pattern (QA-02 fix)
```css
/* Page body: uses --color-bg (layer 1) automatically via body {} */
/* Card surface: */
.archive-card { background: var(--color-surface); }  /* layer 2 */
/* Card header (elevated sub-panel): */
.archive-card-header { background: var(--color-surface-raised); }  /* layer 3 */
```

### Font discipline: KPI numbers (QA-03 fix)
```css
/* Source: design-system.css --type-mono-font pattern */
/* KPI numeric values → monospace, NOT display */
.overview-card-value {
  font-family: var(--font-mono);  /* JetBrains Mono — correct for numbers */
  font-size: 2rem;
  font-weight: 700;
}
```

### Mobile footer CSS rules (replaces inline styles on every page)
```css
/* Source: Add to app.css after .logo definition */
/* Mobile footer override — compact version */
.mobile-footer .logo {
  font-size: 12px;
  gap: 6px;
}
.mobile-footer .logo-mark {
  width: 18px;
  height: 18px;
  border-radius: 3px;
  font-size: 8px;
}
.mobile-footer-spacer {
  flex: 1;
}
.mobile-footer-link {
  text-decoration: none;
  color: var(--color-text-muted);
}
```

### Transition fix (QA-05)
```css
/* hub.css line 120 — current violation */
.hub-sidebar-collapse { transition: all 0.3s ease; }

/* Fix: */
.hub-sidebar-collapse {
  transition: width var(--duration-moderate) var(--ease-standard),
              opacity var(--duration-normal) var(--ease-standard);
}
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| h2 globally uses Fraunces display font | h2 uses --font-sans (Bricolage Grotesque), only h1/.page-title use Fraunces | Eliminates biggest AI-uniform feel |
| Literal 999px for pills | `var(--radius-full)` token | Token consistency |
| Inline styles for repeated mobile footer pattern | Single CSS rule, clean HTML | Maintainability |
| Hardcoded KPI values with Fraunces | --font-mono (JetBrains Mono) for numbers | Correct semantic usage |

---

## Open Questions

1. **Public.css projection screen — keep Fraunces?**
   - What we know: `.projection-title` and `.motion-title` on the projector display use font-display. This is a large-screen presentation context where dramatic display type could be intentional.
   - What's unclear: Was this intentional "display-screen exception" or an oversight?
   - Recommendation: Planner should treat as "Claude's Discretion" — either keep (legitimate display context) or replace with a bold Bricolage Grotesque weight for consistency. Either decision is defensible.

2. **--color-text-muted contrast in dark mode**
   - What we know: `--color-text-muted: #50596C` on `--color-bg: #0B0F1A` — estimated 2.5:1 contrast
   - What's unclear: Is this used for decorative text only (fine at 2.5:1) or for functional text labels?
   - Recommendation: Visual check required. If muted text is used on form labels or interactive element descriptions, lighten to improve contrast.

3. **admin.htmx.html skeleton styles**
   - What we know: Lines 131-158 use `style="width:60%"` etc., but `.skeleton-w-60` class already exists in app.css
   - What's unclear: Whether the percentages exactly match the existing utility classes
   - Recommendation: Check that class names map exactly (60%, 40%, 50%, 35%, 55%, 30%, 65%, 45% — all have corresponding `.skeleton-w-*` classes in app.css lines 415-419). Match and replace.

---

## Validation Architecture

> nyquist_validation check: .planning/config.json not present, treating as enabled.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | None — grep-based audit (no automated visual testing) |
| Config file | N/A |
| Quick run command | See grep commands below |
| Full suite command | See grep commands below |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| QA-01 | Hover has transform on interactive cards | grep | `grep -r ":hover" public/assets/css/ \| grep -v transform` (check for missing) | ✅ |
| QA-02 | surface-raised used on every page | grep | `grep -rL "color-surface-raised" public/assets/css/*.css` | ✅ |
| QA-03 | font-display only on h1/.page-title | grep | `grep -r "font-display" public/assets/css/ public/partials/` | ✅ |
| QA-04 | No pure black (#000) backgrounds | grep | `grep -r "background.*#000\b" public/assets/css/` | ✅ |
| QA-05 | Zero transition > 200ms | grep | `grep -r "transition.*300ms\|transition.*0\.3s\|transition.*400ms" public/assets/css/` | ✅ |
| QA-05 | Zero layout style="" in HTML | grep | `grep -rn 'style="[^"]*[a-z-]:[^d][^i][^s]' public/*.html` | ✅ |

### Sampling Rate
- **Per task commit:** Run the relevant grep command for the criterion being fixed
- **Per wave merge:** Run all 6 grep commands
- **Phase gate:** All grep commands return zero results before marking QA complete

### Wave 0 Gaps
None — existing grep tooling covers all phase requirements. No test files to create.

---

## Sources

### Primary (HIGH confidence)
- Direct file inspection: `public/assets/css/*.css` — all 25 CSS files read and audited
- Direct file inspection: `public/*.htmx.html` — all 22 HTML files grep-searched
- Direct file inspection: `public/partials/sidebar.html` — read in full
- Direct file inspection: `app/Templates/*.php` — grep-searched

### Secondary (MEDIUM confidence)
- `.planning/phases/34-quality-assurance-final-audit/34-CONTEXT.md` — QA criteria definitions
- `.planning/REQUIREMENTS.md` — QA-01 through QA-05 specification

### Tertiary (LOW confidence)
- Contrast ratio estimates for dark mode text — calculated from hex values, not verified with a contrast tool

---

## Metadata

**Confidence breakdown:**
- Violation inventory: HIGH — all findings from direct file read + grep
- Fix prescriptions: HIGH — all fixes follow established patterns from design-system.css
- Dark mode contrast: MEDIUM — hex-to-contrast estimates, visual verification needed
- "Acceptable exceptions" classification: MEDIUM — judgement calls on brand elements

**Research date:** 2026-03-19
**Valid until:** 2026-04-18 (stable codebase — changes only from this phase's fixes)
