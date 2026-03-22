# Phase 45: Wizard Rebuild - Research

**Researched:** 2026-03-22
**Domain:** Multi-step form wizard — HTML/CSS/JS rebuild, viewport-fit layout, API wiring
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Layout & Step Fitting**
- Full-width 900px content track — wider than current 680px to use horizontal space, fit more fields side-by-side without scrolling
- Slide + fade step transitions — horizontal slide between steps for wizard feel, refined from current wizFadeIn
- Segmented pill stepper bar — keep current sticky segmented stepper with filled/active/pending states, refined with connector lines between steps
- Compact sections with collapsible advanced — advanced rules collapsed by default, tighter spacing, each step's primary content fits viewport at 1024px without scroll

**Form Fields & Horizontal Layout**
- 3-column grid for short fields — type/date/time on one row, place/address on one row. Wide fields (title, description) stay full-width
- Traditional labels above — consistent with form density needs (wizard has too many fields for floating labels)
- Member add form: single horizontal row — name, email, voting power, add button all on one line
- Resolution add form: compact inline panel — title + majority on one row, description below, add button right-aligned, expandable from "+" button

**Features & Polish**
- Keep template grid — refine card styling with better hover states and icons, keep the 3 motion templates
- Review step: summary table with edit buttons — structured recap showing all data with inline "Modifier" links back to relevant step
- Full dark mode parity — all wizard-specific components get dark variants
- Inline field errors + step-level banner — per-field red borders on validation failure, plus summary banner at step top listing all errors

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming (can rename if cleaner)
- Whether to refactor wizard.js or just update selectors
- Exact slide animation timing and easing
- Stepper connector line implementation (CSS pseudo-elements vs SVG)
- Responsive breakpoint behavior (how 3-col goes to 2-col or 1-col)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REB-03 | Wizard — complete HTML+CSS+JS rewrite, all 4 steps fit viewport, form submissions wired, stepper functional, horizontal fields | All sections below directly address this |
| WIRE-01 | Every rebuilt page has verified API connections — no dead endpoints, no mock data, no broken HTMX targets | API wiring section; `api()` helper and POST `/api/v1/meetings` endpoint verified in existing JS |
| WIRE-03 | Form submissions verified — wizard creates sessions, settings save, user CRUD works | Step 4 btnCreate flow confirmed; success redirects to `/hub.htmx.html?id=...` |
</phase_requirements>

---

## Summary

The wizard is a fully working 4-step form today. The HTML, CSS, and JS all exist and are wired. The rebuild task is a ground-up rewrite of the visual structure and layout — not a feature addition. The JS logic is sound and must be preserved almost entirely; only DOM selectors and a few structural additions (slide transition, step-level error banner) need changing.

The core challenge is viewport-fit: every step must show its primary content AND the "Suivant" button without vertical scrolling at 1024px. The current layout uses a narrow card that scrolls inside `.wiz-step-body`. The new layout uses a 900px track, eliminates the overflow-y scroll on the body, compresses field groupings into horizontal grids, and keeps the advanced section collapsed by default.

The second challenge is the slide transition: the current `display:none/block` show/hide must be replaced with a CSS animation approach. Because we still need the current step to slide out and the next to slide in, the simplest correct approach is `translateX` on both the outgoing and incoming panel, driven by class toggling in JS (`showStep()`).

**Primary recommendation:** Rewrite `wizard.htmx.html` and `wizard.css` completely; update `wizard.js` selectors and add slide transition logic. Preserve all existing JS business logic verbatim.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vanilla JS (IIFE) | ES5-compatible | Step navigation, validation, state, API | Project convention — no framework |
| CSS custom properties | Design system v2 | All theming, spacing, colors | Already used throughout; tokens in design-system.css |
| FilePond | 4.32.12 (CDN) | PDF upload for resolution documents | Already integrated, must be kept |
| ag-toast, ag-popover | Custom WC (module) | Notifications and help popovers | Already loaded at bottom of HTML |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| HTML5 drag-and-drop API | Native | Resolution reorder | Already implemented in wizard.js; keep as-is |
| FileReader API | Native | CSV import | Already implemented; keep as-is |
| localStorage | Native | Draft save/restore | DRAFT_KEY = 'ag-vote-wizard-draft'; keep as-is |
| sessionStorage | Native | Post-creation toast | Used after creation redirect; keep as-is |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| CSS translateX slide | View Transitions API | View Transitions not supported in older browsers; CSS transform is universal |
| Vanilla JS IIFE | Module syntax | Project uses IIFE convention for all page JS — do not break pattern |
| Native `<details>` for advanced section | JS toggle | `<details>` already used and works; keep it |

**Installation:** No new dependencies needed. All libraries already loaded.

---

## Architecture Patterns

### Recommended Project Structure
```
public/
├── wizard.htmx.html          # Complete rewrite — new 900px track layout
├── assets/css/wizard.css     # Complete rewrite — slide transitions, new grid, dark mode
└── assets/js/pages/wizard.js # Selector updates + slide logic; all business logic preserved
```

### Pattern 1: Step Slot Architecture (900px track)

**What:** A single `.wiz-content` wrapper holds 4 `.wiz-step` panels. Only one is visible at a time. The wrapper clips overflow so slides don't cause page-level horizontal scroll.

**When to use:** The locked decision specifies slide + fade transitions. This requires both panels to exist in the DOM simultaneously during the animation.

**Example structure:**
```html
<!-- .wiz-content clips overflow, holds all steps as adjacent siblings -->
<div class="wiz-content">
  <div class="wiz-step" id="step0" data-step="0">...</div>
  <div class="wiz-step" id="step1" data-step="1">...</div>
  <div class="wiz-step" id="step2" data-step="2">...</div>
  <div class="wiz-step" id="step3" data-step="3">...</div>
</div>
```

**CSS approach for slide transition:**
```css
/* .wiz-content: overflow hidden to clip slides */
.wiz-content {
  overflow: hidden;
  position: relative;
}

/* All steps are positioned; only active one is shown */
.wiz-step {
  display: none;
  animation: wizSlideIn 220ms cubic-bezier(0.22, 1, 0.36, 1) both;
}
.wiz-step.active {
  display: block;
}
.wiz-step.slide-out {
  display: block;
  animation: wizSlideOut 180ms ease-in both;
}

@keyframes wizSlideIn {
  from { opacity: 0; transform: translateX(32px); }
  to   { opacity: 1; transform: translateX(0); }
}
@keyframes wizSlideOut {
  from { opacity: 1; transform: translateX(0); }
  to   { opacity: 0; transform: translateX(-32px); }
}
```

**JS `showStep()` update:**
```javascript
function showStep(n) {
  var prev = document.getElementById('step' + currentStep);
  // Trigger slide-out on current (if navigating, not first render)
  if (prev && n !== currentStep) {
    prev.classList.add('slide-out');
    setTimeout(function() {
      prev.classList.remove('active', 'slide-out');
    }, 180); // match wizSlideOut duration
  }
  currentStep = n;
  var next = document.getElementById('step' + n);
  if (next) next.classList.add('active');
  // ... rest of showStep (updateStepper, subtitle, scroll) unchanged
}
```

### Pattern 2: 900px Content Track

**What:** `.wiz-content` uses `max-width: 900px; margin: 0 auto` — wider than current implicit narrowness. Inside each step, fields use `form-grid-3` and `form-grid-2` which already exist in `design-system.css`.

**CSS:**
```css
.wiz-content {
  max-width: 900px;
  margin: 0 auto;
  padding: var(--space-4);
}
```

**Key: `form-grid-3` already collapses at 1024px to 2-col, at 768px to 1-col.** No new breakpoint work needed for the 3-col field row — use the existing utility class from `design-system.css`.

### Pattern 3: Viewport-fit Step Content

**What:** Each step's content must fit in the viewport at 1024px without triggering vertical scroll. The current `.wiz-step-body { overflow-y: auto }` is the culprit — it makes the step card independently scrollable, hiding content below fold.

**Change:** Remove `overflow-y: auto` from `.wiz-step-body`. Instead, ensure content is genuinely compact:
- `.wiz-section` padding reduced: current `0.875rem 0` — can stay
- Advanced rules section collapsed by default (already done via `<details>`)
- Remove the redundant `.wf-step` header (already `display: none` in current CSS — keep that)
- Reduce padding on `.wiz-step-body` from `1.25rem 1.5rem` to `1rem 1.5rem`

**Compact step 1 inventory (must fit in ~560px available height at 1024px):**
- Stepper bar: ~60px
- Page header: ~70px
- Step card with: title field (56px), 3-col row type/date/time (56px), 2-col row place/addr (56px), collapsed details (40px), step-nav footer (64px) = ~332px card height. Fits.

**Compact step 2 inventory:**
- Upload zone: 56px (compact horizontal)
- Toggle row: 40px
- Member add form single-row: 56px
- Member list (empty state or entries): variable
- Total footer: 40px + step-nav 64px. Fits with empty/few members.

### Pattern 4: Member Add Form — Single Horizontal Row

**What:** Name, email, voting-power, Add button on one row using `.wiz-member-add-row` (new CSS).

**Example:**
```html
<div class="wiz-member-add-row" id="wizMemberAddForm">
  <div class="field field--flex">
    <label class="field-label" for="wizMemberName">Nom <span class="req">*</span></label>
    <input class="field-input" id="wizMemberName" placeholder="Nom du participant" ...>
  </div>
  <div class="field field--flex">
    <label class="field-label" for="wizMemberEmail">Courriel</label>
    <input class="field-input" id="wizMemberEmail" type="email" ...>
  </div>
  <div class="field field--w-narrow" id="wizMemberVpField" style="display:none;">
    <label class="field-label" for="wizMemberVp">Poids</label>
    <input class="field-input" id="wizMemberVp" type="number" min="1" value="1">
  </div>
  <button class="btn btn-primary btn-sm wiz-member-add-btn" type="button" id="btnAddMemberInline">
    + Ajouter
  </button>
</div>
```

```css
.wiz-member-add-row {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  padding: 12px 14px;
  background: var(--color-bg-subtle);
  border-radius: 10px;
  border: 1px solid var(--color-border-subtle);
  margin-bottom: 1rem;
}
.wiz-member-add-row .field { margin: 0; }
.wiz-member-add-row .field--flex { flex: 1; }
.wiz-member-add-row .field--w-narrow { width: 90px; flex-shrink: 0; }
.wiz-member-add-btn { flex-shrink: 0; align-self: flex-end; margin-bottom: 0; }
```

**Note:** DOM IDs do NOT change (`wizMemberName`, `wizMemberEmail`, `wizMemberVp`, `wizMemberVpField`, `btnAddMemberInline`). wizard.js selects them by ID — the class structure around them can change freely.

### Pattern 5: Step-Level Error Banner

**What:** When Next is pressed and validation fails, show a summary banner at the TOP of the step listing all errors, in addition to per-field red borders. Banner is hidden by default, shown with class toggle.

**HTML (one per step that has required fields):**
```html
<div class="wiz-error-banner" id="errBannerStep0" hidden>
  <svg ...></svg>
  <span id="errBannerStep0Text">Le titre est obligatoire.</span>
</div>
```

**CSS:**
```css
.wiz-error-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: var(--color-danger-subtle);
  border: 1px solid var(--color-danger);
  border-radius: var(--radius);
  color: var(--color-danger);
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 1rem;
}
.wiz-error-banner[hidden] { display: none; }
```

**JS: extend `showFieldErrors(n)` to also populate and reveal the banner.**

### Anti-Patterns to Avoid

- **Keeping `overflow-y: auto` on `.wiz-step-body`:** Makes the card independently scrollable, which hides the "Suivant" button. Remove it.
- **Changing DOM IDs:** wizard.js uses `getElementById` for ~20 IDs. Renaming any of them breaks functionality without a corresponding JS change.
- **Using `display:none/block` for transitions:** Results in instant jump. Use the `active` / `slide-out` class pattern described above.
- **Adding step transitions with `position: absolute` + full-screen overlay:** Creates z-index conflicts with the sticky stepper. Use the simpler sequential class approach.
- **Rebuilding FilePond configuration:** The existing `initResolutionPond()` function is complete and correct. Only the containing HTML structure around `.filepond-input` changes.
- **Using floating labels:** Locked decision says traditional labels above. Do not implement.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PDF upload | Custom fetch + FormData | FilePond 4.32.12 (already loaded) | Handles multipart, progress, validation, error display |
| CSV parsing | Custom parser | Existing `handleCsvFile()` in wizard.js | Already handles header detection, multi-column, concat |
| Drag-and-drop reorder | SortableJS | Existing HTML5 DnD in wizard.js | Already fully working; adding a library would require rewriting |
| Toast notifications | Custom toast div | `window.AgToast.show()` (ag-toast WC) | Already loaded; used by wizard.js |
| Confirm dialogs | `window.confirm()` | `window.AgConfirm.ask()` (used for doc delete) | Already used in renderDocCard |
| Form state persistence | Custom serialization | Existing `saveDraft()`/`restoreDraft()` | DRAFT_KEY = 'ag-vote-wizard-draft'; all 20+ fields covered |

---

## Common Pitfalls

### Pitfall 1: Slide animation while restoring draft
**What goes wrong:** `restoreDraft()` calls `showStep(n)`. If the new `showStep()` triggers a slide-out animation on step 0 before step N is shown, you get a visual flash.
**Why it happens:** The slide logic animates based on "current step changing." On first load with a saved draft, currentStep = 0 but step 0 was never actually visible.
**How to avoid:** Add a `skipAnimation` parameter to `showStep()`, or track `isFirstRender = true` and skip the slide-out on first call. Example: `showStep(draft.step || 0, true)`.
**Warning signs:** Brief flash of step 0 sliding out when page loads with a draft.

### Pitfall 2: Viewport overflow from `.wiz-step-body overflow-y:auto`
**What goes wrong:** If `.wiz-step-body` retains `overflow-y: auto`, the step card looks fine but the "Suivant" button is clipped inside the card's scroll container — not the page scroll.
**Why it happens:** Current CSS sets `flex: 1; overflow-y: auto; min-height: 0` on `.wiz-step-body`. This makes the card a fixed-height scroll box.
**How to avoid:** Remove `overflow-y: auto` and `flex: 1; min-height: 0` from `.wiz-step-body`. The step card should grow to fit its content naturally.

### Pitfall 3: FilePond re-initialization on re-render
**What goes wrong:** `renderResoList()` rebuilds the entire resolution list innerHTML. On re-render (after drag-drop or delete), calling `FilePond.create()` again on an already-initialized `<input>` throws an error or creates duplicate instances.
**Why it happens:** Each call to `renderResoList()` appends fresh `.filepond-input` elements, but `initResolutionPond()` guards via `inputEl._pondInitialized`. This guard is on the old DOM element — new elements after re-render don't have it.
**How to avoid:** This is already correctly handled by the guard flag `inputEl._pondInitialized = true`. The new HTML must keep the same `.filepond-input` class and `name="filepond"` attribute on the input element inside `.resolution-documents[data-motion-id]`.

### Pitfall 4: Stepper connector lines conflicting with `.wiz-step-item.active::after`
**What goes wrong:** The current CSS uses `::after` on `.wiz-step-item.active` for the active underline indicator. If connector lines are implemented also via `::after` on adjacent items, they conflict.
**Why it happens:** A pseudo-element can only be used once per selector rule.
**How to avoid:** Implement connector lines via `::before` on `.wiz-step-item:not(:first-child)`, or use a separate `.wiz-step-connector` element in HTML, or use the existing box-shadow approach (already used: `box-shadow: inset -1px 0 0 var(--color-border)` — refine this instead of switching to pseudo-elements).

### Pitfall 5: Dark mode gaps in new CSS classes
**What goes wrong:** New CSS classes for the slide transition, error banner, and member add row don't have dark mode variants, resulting in white backgrounds on dark theme.
**Why it happens:** Dark mode is implemented via `[data-theme="dark"]` scoped overrides in `design-system.css` and per-component CSS. New classes must explicitly override any hardcoded colors.
**How to avoid:** Use only CSS custom properties (tokens) from `design-system.css` — never hardcode hex values. Token-only CSS gets dark mode for free since tokens resolve differently under `[data-theme="dark"]`.
**Warning signs:** Step transitions or error banners appear white in dark mode.

### Pitfall 6: `form-grid-3` collapses to 2-col at 1024px
**What goes wrong:** The `form-grid-3` class from `design-system.css` collapses to 2-col at `max-width: 1024px`. At exactly 1024px (the target breakpoint), type/date/time row becomes 2-col and may push content below fold.
**Why it happens:** The media query in design-system.css: `@media (max-width: 1024px) { .form-grid-3 { grid-template-columns: 1fr 1fr; } }`.
**How to avoid:** Test at exactly 1024px. The 2-col layout for 3 fields means the third field wraps to a second row. Since each row is ~56px and there's only one overflow field, this adds one row height and should still fit. Verify during execution.

---

## Code Examples

Verified patterns from existing source:

### API call — session creation (from wizard.js line 967)
```javascript
// Source: public/assets/js/pages/wizard.js
api('/api/v1/meetings', payload)
  .then(function(res) {
    if (!res.body || !res.body.ok) {
      var err = new Error(res.body && res.body.error || 'creation_failed');
      if (res.body && res.body.details) err.details = res.body.details;
      throw err;
    }
    clearDraft();
    var d = res.body.data || {};
    window.location.href = '/hub.htmx.html?id=' + (d.meeting_id || '');
  })
  .catch(function(err) {
    btnCreate.disabled = false;
    btnCreate.textContent = 'Créer la séance →';
    // Show error message without losing form data
    if (window.Shared && Shared.showToast) {
      Shared.showToast(msg, 'error');
    }
  });
```

### Stepper update (from wizard.js line 111) — stable, no changes needed
```javascript
// Source: public/assets/js/pages/wizard.js
function updateStepper() {
  var items = document.querySelectorAll('.wiz-step-item');
  items.forEach(function (item, i) {
    item.classList.remove('done', 'active');
    if (i < currentStep) item.classList.add('done');
    else if (i === currentStep) item.classList.add('active');
    var snum = item.querySelector('.wiz-snum');
    if (snum) {
      if (i < currentStep) {
        snum.innerHTML = '<svg ...checkmark...</svg>';
      } else {
        snum.textContent = i + 1;
      }
    }
  });
}
```

### Existing design-system.css grid classes (confirmed present)
```css
/* Source: public/assets/css/design-system.css lines 1918-1947 */
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-field) 12px; }
.form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-field) 12px; }
/* @media (max-width: 1024px) { .form-grid-3 { grid-template-columns: 1fr 1fr; } } */
/* @media (max-width: 480px)  { .form-grid-2 { grid-template-columns: 1fr; } } */
```

### Dark mode token usage pattern (project convention)
```css
/* Use tokens — they auto-resolve to dark values under [data-theme="dark"] */
.wiz-error-banner {
  background: var(--color-danger-subtle);   /* NOT hardcoded */
  border-color: var(--color-danger);
  color: var(--color-danger);
}
/* No [data-theme="dark"] block needed when only tokens are used */
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| wizFadeIn (Y-translate 8px + opacity) | wizSlideIn (X-translate + opacity) | Phase 45 rebuild | Horizontal slide fits wizard mental model better |
| `display: none/block` step toggle | `active` class + CSS animation | Phase 45 rebuild | Enables smooth transition; requires animation-aware `showStep()` |
| 680px implicit narrow track | 900px explicit `max-width` on `.wiz-content` | Phase 45 rebuild | Fits more fields per row, reduces vertical scrolling |
| `overflow-y: auto` on step body | Remove overflow, let card grow naturally | Phase 45 rebuild | Step height adapts to content; "Suivant" button always visible |
| Simple per-field error messages | Per-field + step-level banner | Phase 45 rebuild | Errors visible even if field is above fold |

**Still current (do NOT change):**
- IIFE module pattern for wizard.js
- `window._wizRemoveMember(i)` global (inline onclick in rendered member rows)
- FilePond 4.32.12 CDN (do not version-bump)
- ag-toast, ag-popover web components (loaded as `type="module"` at bottom)
- `window.api()` helper (from `utils.js`) used for all fetch calls

---

## Open Questions

1. **Slide-out animation duration vs. user perception**
   - What we know: 180ms slide-out + 220ms slide-in = 400ms total per step change
   - What's unclear: Whether 220ms feels fast enough or sluggish on older hardware
   - Recommendation: Use 180ms/220ms as default; these values are Claude's discretion per CONTEXT.md

2. **`form-grid-3` at exactly 1024px**
   - What we know: The grid collapses to 2-col at max-width 1024px, meaning 3-field rows become 2+1
   - What's unclear: Whether the resulting extra row height pushes "Suivant" below fold in step 1
   - Recommendation: During execution, verify by measuring: if overflow occurs, add a custom `.wiz-grid-3` that collapses only at 900px instead of 1024px

3. **Review step layout at 900px**
   - What we know: Current review uses a 2-column `.review-grid` that collapses at 900px
   - What's unclear: Whether the single-column layout at 900px still fits in viewport without scroll
   - Recommendation: Keep 2-col down to 640px (narrower collapse) so the review step fits at 1024px

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | None detected — project uses manual browser verification |
| Config file | none |
| Quick run command | Open browser at `http://localhost/wizard.htmx.html` |
| Full suite command | Manual: complete all 4 steps, submit, verify session appears in DB |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REB-03 | All 4 steps fit viewport at 1024px, no scroll | Manual visual | Browser DevTools at 1024px width | N/A |
| REB-03 | Stepper shows active/done/pending states correctly | Manual visual | Navigate steps, inspect DOM | N/A |
| REB-03 | Slide transition plays between steps | Manual visual | Click Next/Prev | N/A |
| REB-03 | Horizontal field layout: type/date/time on one row | Manual visual | Inspect step 1 at 1024px | N/A |
| REB-03 | Member add form is single horizontal row | Manual visual | Inspect step 2 | N/A |
| WIRE-01 | No dead endpoints | Manual API | Submit valid form, check Network tab | N/A |
| WIRE-03 | Session creation POST succeeds | Manual functional | Complete wizard, verify redirect to hub with real meeting_id | N/A |
| WIRE-03 | Network failure shows error without data loss | Manual functional | Throttle network, submit, verify error banner | N/A |

### Sampling Rate
- **Per task commit:** Open browser, navigate all 4 steps visually
- **Per wave merge:** Full creation flow: fill all fields, submit, verify session in hub
- **Phase gate:** All 5 success criteria verified before `/gsd:verify-work`

### Wave 0 Gaps
None — no automated test infrastructure exists or is expected for this project. All verification is manual browser testing as per project convention.

---

## Sources

### Primary (HIGH confidence)
- `public/wizard.htmx.html` — Full HTML read; 500 lines, 4 steps, all DOM IDs catalogued
- `public/assets/js/pages/wizard.js` — Full JS read; 1037 lines, all functions documented
- `public/assets/css/wizard.css` — Full CSS read; 1303 lines, all class names catalogued
- `public/assets/css/design-system.css` (lines 1910-1947) — `form-grid-2`, `form-grid-3` confirmed present with exact breakpoints
- `.planning/phases/45-wizard-rebuild/45-CONTEXT.md` — All locked decisions

### Secondary (MEDIUM confidence)
- `public/assets/css/app.css` — Import chain confirmed; wizard.css loaded separately in HTML head
- `.planning/REQUIREMENTS.md` — REB-03, WIRE-01, WIRE-03 requirements read directly

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries read from actual source files
- Architecture: HIGH — patterns derived from reading existing working code + locked decisions
- Pitfalls: HIGH — identified from direct code inspection (overflow-y, re-initialization guards, pseudo-element conflicts, form-grid-3 breakpoint)

**Research date:** 2026-03-22
**Valid until:** Until wizard.htmx.html is rewritten (single-use research)
