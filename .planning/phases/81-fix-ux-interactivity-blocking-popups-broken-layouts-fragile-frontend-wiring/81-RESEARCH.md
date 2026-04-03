# Phase 81: Fix UX interactivity — blocking popups, broken layouts, fragile frontend wiring - Research

**Researched:** 2026-04-03
**Domain:** Vanilla JavaScript Web Components, CSS Grid layouts, SSE, frontend wiring patterns
**Confidence:** HIGH — all findings verified directly from codebase source files

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Popups & confirmations**
- D-01: Choose ONE confirmation design pattern (AgConfirm.ask() vs inline confirm vs undo toast) and apply it uniformly across all pages. Eliminate any remaining window.confirm() or window.alert() usage entirely.
- D-02: All modales must close on backdrop click, Escape key, and have proper focus trap. Fix ag-modal behavior if needed.
- D-03: Reduce confirmation fatigue — only confirm truly destructive/irreversible actions. Minor actions should execute directly with feedback toast.

**Layouts & mise en page**
- D-04: Wizard stays multi-step but must be corrected — horizontal field layout, fluid transitions, no per-step scrolling. Use width aggressively.
- D-05: Form fields use stacked labels (label above input) but on 2-3 column CSS grid where space allows. Not single-column stacking.
- D-06: Page width strategy is context-dependent: operator/dashboard can go full-width, form pages and settings can use max-width. Claude decides per page.
- D-07: ALL pages must exploit horizontal space. Screens are horizontal — layouts must reflect this.

**Frontend wiring & API**
- D-08: Every fetch/API call must have visible feedback: loading indicator during request, success toast or UI update on completion, error toast on failure. No silent failures.
- D-09: Claude standardizes the feedback pattern per interaction type: loading+toast for creates/deletes, optimistic for toggles/quick actions, as fits context.
- D-10: Form validation follows codebase standards and best practices — HTML5 native as baseline, JS custom for complex rules. No double-submit.
- D-11: SSE/real-time connections must reconnect reliably and show connection status when disconnected.
- D-12: Navigation/state changes must not lose data — warn on unsaved changes where relevant.

**Coherence visuelle**
- D-13: Two-pass approach: first consolidate CSS design tokens (spacing, radius, shadows) and enforce their usage via @layer cascade, then audit each page for compliance.
- D-14: Components (buttons, cards, tables, badges) must use consistent variants — eliminate ad-hoc styling overrides.
- D-15: Transitions/animations: Claude decides appropriate timing and easing per context (modals, toasts, tabs, loading states). Professional feel, not flashy.

### Claude's Discretion
- Specific confirmation pattern choice (AgConfirm vs inline vs undo toast) — as long as it's ONE pattern applied everywhere
- Per-page width decisions (full-width vs max-width)
- Animation timing and easing per component type
- Validation approach (HTML5 vs JS) per form complexity
- Toast audit — fix whatever is broken
- Loading state implementation (spinner, skeleton, disabled state)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

## Summary

Phase 81 is a cross-cutting frontend quality pass. The app has a fully-built component library (ag-modal, ag-confirm, ag-toast, ag-spinner, ag-stepper) and a mature CSS design system with semantic tokens and @layer cascade. The systemic problem is that these components are not universally used — some pages still have hand-rolled modal wrappers (`Shared.openModal` in shared.js, inline `div` modals in operator-tabs.js), inconsistent toast coverage (members.js, meetings.js, users.js use `setNotif` against a local DOM element rather than `AgToast`), and no SSE disconnect banner.

Layout issues are structural: wizard.css is already 900px-tracked with slide transitions but individual step content still stacks single-column. Operator console is full-width but some of its sub-sections use inline style constraints. The form field grid rule (D-05) is defined in the UI-SPEC but not yet applied to most forms.

The main work is systematic substitution and audit: replace every `Shared.openModal()`/inline modal with `AgConfirm.ask()`, wire every unhandled `.catch()` to `AgToast.show('error', ...)`, implement the SSE warning banner in `event-stream.js`, and apply the 2-column grid CSS rule to all forms with 4+ fields.

**Primary recommendation:** Follow the UI-SPEC (already approved) as the authoritative contract. Work page-by-page, highest interaction density first (operator-tabs.js, members.js, users.js, meetings.js, wizard.js, settings.js).

---

## Standard Stack

### Core (already installed — no new packages)
| Asset | Location | Purpose | Status |
|-------|----------|---------|--------|
| ag-modal | `public/assets/js/components/ag-modal.js` | Dialog modal with Shadow DOM, focus trap, Escape, backdrop-click | Functional — audit `closable` attribute usage |
| ag-confirm | `public/assets/js/components/ag-confirm.js` | Promise-based `AgConfirm.ask()` | Functional — not yet universally adopted |
| ag-toast | `public/assets/js/components/ag-toast.js` | `AgToast.show(type, message, duration)` | Functional — inconsistent coverage |
| ag-spinner | `public/assets/js/components/ag-spinner.js` | Loading spinner (sm/md/lg/xl, primary/white/default variants) | Functional — underused |
| ag-stepper | `public/assets/js/components/ag-stepper.js` | Horizontal step indicator | Functional — wizard wires it |
| design-system.css | `public/assets/css/design-system.css` | CSS tokens @layer base/components/v4 | Functional — tokens may not be enforced in all per-page CSS |
| event-stream.js | `public/assets/js/core/event-stream.js` | SSE client with auto-reconnect, onDisconnect/onFallback callbacks | Missing disconnect banner |

### Competing/Duplicate Patterns (must be eliminated)
| Existing (to remove) | Replace with | Where |
|----------------------|-------------|-------|
| `Shared.openModal()` from `shared.js` | `AgConfirm.ask()` | `operator-tabs.js` (local `confirmModal` wrapper) |
| Inline `div` modal creation (`modal.innerHTML = ...`) | `ag-modal` or `AgConfirm.ask()` | `operator-tabs.js` line 1427, `operator-attendance.js` |
| Local `setNotif(type, msg)` DOM-box function | `AgToast.show()` via global `setNotif()` in utils.js | `users.js` line 27–38 |
| `window.AgToast ? ... : setNotif(...)` ternary guards | Direct `AgToast.show()` | `operator-tabs.js` throughout |

**Note:** `setNotif()` in `utils.js` already delegates to `AgToast.show()` when available — pages that import it correctly via the global scope are already safe. The `users.js` local override shadows the global `setNotif` and bypasses AgToast entirely.

---

## Architecture Patterns

### Pattern 1: Confirmation for Destructive Actions
**What:** `AgConfirm.ask()` — Promise-based, returns `true`/`false`, injects a custom element into `document.body`, self-cleans.
**When to use:** Any irreversible action (delete, close session, password reset). Non-destructive saves execute directly.
**Example:**
```js
// Source: public/assets/js/components/ag-confirm.js — static ask() method
const ok = await AgConfirm.ask({
  title: 'Supprimer ce membre ?',
  message: 'Cette action est irreversible.',
  confirmLabel: 'Supprimer le membre',
  variant: 'danger'
});
if (ok) {
  // proceed with delete
}
```

### Pattern 2: API Feedback — Create/Delete (Pattern A from UI-SPEC)
**What:** Disable button, show spinner or text change, show toast on result.
**When to use:** Any create/delete/long operation.
**Example:**
```js
// Pattern A — Create/Delete
Shared.btnLoading(btn, true);  // disables, shows spinner text
try {
  const r = await api('/api/v1/members', { method: 'POST', body: JSON.stringify(data) });
  if (r.body?.ok) {
    AgToast.show('success', 'Membre ajouté');
    // refresh list
  } else {
    AgToast.show('error', r.body?.error || 'Une erreur est survenue', 8000);
  }
} catch (e) {
  AgToast.show('error', 'Erreur de connexion — vérifiez votre connexion et réessayez', 8000);
} finally {
  Shared.btnLoading(btn, false);
}
```

### Pattern 3: API Feedback — Toggle/Quick Action (Pattern B from UI-SPEC)
**What:** Optimistic UI update, revert on error.
**When to use:** Toggle switches, status badges, any instant-feedback action.
**Example:**
```js
// Pattern B — Optimistic toggle
el.classList.toggle('active');  // immediate UI update
try {
  const r = await api('/api/v1/members/toggle', { method: 'POST', body: JSON.stringify({ id }) });
  if (!r.body?.ok) {
    el.classList.toggle('active');  // revert
    AgToast.show('error', 'Modification non enregistrée', 8000);
  }
} catch (e) {
  el.classList.toggle('active');  // revert
  AgToast.show('error', 'Modification non enregistrée', 8000);
}
```

### Pattern 4: Form Field Grid (D-05)
**What:** CSS Grid with `repeat(auto-fit, minmax(240px, 1fr))` for 4+ field forms.
**When to use:** Any form section wider than 768px with 4+ fields.
**Example:**
```css
/* Source: UI-SPEC D-05 — apply to .form-grid containers */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: var(--gap-md, 16px);
}
/* Exceptions — always full-width */
.form-grid textarea,
.form-grid .field-full { grid-column: 1 / -1; }
```

### Pattern 5: SSE Disconnect Banner (D-11)
**What:** A persistent (not dismissable) warning banner injected on `onDisconnect`, removed on `onConnect`.
**When to use:** Any page using `EventStream.connect()`.
**Location:** Implement in `event-stream.js` as an exported helper, or have each page's `onDisconnect` callback add it.
**Example:**
```js
// Add to pages using EventStream.connect()
function showSseWarning() {
  if (document.getElementById('sseWarningBanner')) return;
  const banner = document.createElement('div');
  banner.id = 'sseWarningBanner';
  banner.className = 'sse-warning-banner';  // styled in per-page CSS
  banner.textContent = 'Connexion temps réel interrompue — actualisation automatique toutes les 30 secondes';
  document.querySelector('.app-main')?.prepend(banner);
}
function hideSseWarning() {
  document.getElementById('sseWarningBanner')?.remove();
}
```

### Pattern 6: Unsaved Changes Warning (D-12)
**What:** `AgConfirm.ask()` on `beforeunload` or shell navigation intercept when dirty state detected.
**When to use:** Wizard steps, settings page, member edit modal — after any field has been changed from loaded state.
**Note:** `operator-tabs.js` already has `_settingsSnapshot` + `_isSettingsDirty()` — replicate this pattern where missing.
**Example:**
```js
// Already in operator-tabs.js lines 77, 281–287 — reference implementation
// For beforeunload intercept on wizard/settings:
window.addEventListener('beforeunload', function(e) {
  if (isDirty()) {
    e.preventDefault();
    e.returnValue = '';  // shows browser default dialog — AgConfirm not usable here
  }
});
```

### Anti-Patterns to Avoid

- **`Shared.openModal()` for confirmations:** Hand-rolled modal from `shared.js` — has no focus trap guarantees, no backdrop-click by default, no consistent styling. Replace with `AgConfirm.ask()`.
- **Inline modal DOM creation with `modal.innerHTML = ...`:** Found in `operator-tabs.js` line 1427 (device management modal) and `operator-attendance.js`. No focus trap, no Escape handling. Migrate to `ag-modal`.
- **Local `setNotif()` override in `users.js`:** Defines a local function at line 27 that writes to a DOM box `#notif_box`. Shadows the global `setNotif` in utils.js. Delete the local version; let the global delegate to `AgToast`.
- **`window.AgToast ? ... : setNotif(...)` guards:** Safety guards in `operator-tabs.js` that check if AgToast exists before calling it. AgToast is always loaded — remove the ternaries, call `AgToast.show()` directly.
- **Inline `style.cssText` for modals:** `operator-tabs.js` line 1430 uses `style.cssText` for positioning — violates CSP `unsafe-inline` reduction goal and bypasses design tokens.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Confirmation dialog | Custom `<div>` modal + Promise wrapper | `AgConfirm.ask()` | Already handles focus trap, Escape, backdrop, variants, self-cleanup |
| Toast notifications | DOM box (`#notif_box`) or alert() | `AgToast.show()` via `setNotif()` | Handles stacking, auto-dismiss, accessible aria-live |
| Loading button state | Custom disabled + innerHTML swap | `Shared.btnLoading(btn, true/false)` | Already in shared.js, handles re-enable, text restore |
| Form field validation errors | Custom DOM injection | `Shared.fieldError()` / `Shared.fieldClear()` / `Shared.fieldValid()` | Already in shared.js |
| Modal with full behavior | Custom backdrop + event listeners | `ag-modal` web component | Escape, focus trap, backdrop-click, open/close lifecycle all built in |
| Spinner | CSS animation from scratch | `ag-spinner` web component | sizes, variants, accessible sr-only label |

**Key insight:** The project already has every primitive needed. The work is adoption, not invention.

---

## Common Pitfalls

### Pitfall 1: AgToast.show() argument order
**What goes wrong:** `AgToast.show()` signature is `(type, message, duration)` but `operator-tabs.js` calls it as `window.AgToast.show(message, type)` in some places (line 3359: `AgToast.show('Le fichier depasse 10 Mo', 'error')`).
**Why it happens:** The argument order was swapped — message and type are reversed in some call sites.
**How to avoid:** Always: `AgToast.show('error', 'message text', optionalDuration)`. Type first, message second.
**Warning signs:** Toast appears with type="Le fichier depasse..." and no visible color accent.

### Pitfall 2: Local setNotif() in users.js shadows global
**What goes wrong:** `users.js` defines a local `setNotif()` at line 27–38 that writes to `#notif_box`. This completely bypasses `AgToast` because the local function shadows the global one defined in `utils.js`.
**Why it happens:** `users.js` was written before global `setNotif` delegated to `AgToast`.
**How to avoid:** Delete the local `setNotif` function in `users.js`. The global one in `utils.js` already does the right thing.
**Warning signs:** Notifications appear in a DOM box element instead of floating toast.

### Pitfall 3: confirmModal() wrapper in operator-tabs.js uses Shared.openModal
**What goes wrong:** `operator-tabs.js` has a `confirmModal()` wrapper at line 231 that calls `Shared.openModal()`. This is not `AgConfirm.ask()` — it uses a different component with different behavior (no focus trap guarantee from `ag-confirm`).
**Why it happens:** Written before `AgConfirm` was mature.
**How to avoid:** Replace `confirmModal()` body to delegate to `AgConfirm.ask()`. Keep the same API surface (`{ title, body, confirmText, confirmClass }`) so call sites don't change, or migrate call sites directly.

### Pitfall 4: ag-modal closable attribute misuse
**What goes wrong:** `ag-modal` respects `closable="false"` attribute by removing the close button AND disabling backdrop-click and Escape. If any modal has been set to `closable="false"` unintentionally, users cannot dismiss it.
**Why it happens:** D-02 says all modals must be closable; audit needed.
**How to avoid:** Search all HTML templates and JS for `closable="false"` usage. Ensure only truly non-dismissable modals (none in this app) use it.

### Pitfall 5: CSS grid on wizard steps breaks step-height assumption
**What goes wrong:** wizard.css assumes each step fits in viewport without scroll. Adding 2-column grid compresses height but if a step has many fields, they may still overflow.
**Why it happens:** UI-SPEC D-04 says "no per-step scrolling: each step's content must fit in viewport at 1024px without scroll".
**How to avoid:** After applying 2-column grid to each step, verify at 1024px height. Move non-critical fields to next step if needed.

### Pitfall 6: SSE onDisconnect fires on every failed reconnect attempt
**What goes wrong:** `event-stream.js` `source.onerror` calls `handlers.onDisconnect()` on every error, not just the first. If the banner creation is naive, duplicate banners will appear.
**Why it happens:** EventSource reconnects automatically; each attempt that fails triggers `onerror`.
**How to avoid:** Banner creation function must check `document.getElementById('sseWarningBanner')` before creating — already shown in Pattern 5 above.

### Pitfall 7: Double-submit on slow API calls
**What goes wrong:** User clicks "Enregistrer" twice before the first request completes — two API calls fire.
**Why it happens:** Button is not disabled before the async call.
**How to avoid:** `Shared.btnLoading(btn, true)` immediately on click, `Shared.btnLoading(btn, false)` in `finally` block.

---

## Code Examples

Verified patterns from codebase source files:

### Shared.btnLoading — button loading state
```js
// Source: public/assets/js/core/shared.js — btnLoading()
// Sets button disabled, saves original text, shows loading label
Shared.btnLoading(btn, true, 'Enregistrement...');  // optionally pass loading text
// ... after API call in finally:
Shared.btnLoading(btn, false);  // restores original text, re-enables
```

### ag-modal usage
```html
<!-- Source: public/assets/js/components/ag-modal.js -->
<ag-modal id="editMemberModal" title="Modifier le membre" size="md">
  <div><!-- form content --></div>
  <div slot="footer">
    <button class="btn btn-secondary" onclick="this.closest('ag-modal').close()">Annuler</button>
    <button class="btn btn-primary" id="btnSaveMember">Enregistrer</button>
  </div>
</ag-modal>
<!-- Open via JS: -->
<script>document.getElementById('editMemberModal').open();</script>
```

### AgConfirm.ask() — canonical usage
```js
// Source: public/assets/js/components/ag-confirm.js — static ask()
const ok = await AgConfirm.ask({
  title: 'Supprimer ce membre ?',
  message: 'Cette action est irréversible.',
  confirmLabel: 'Supprimer le membre',
  cancelLabel: 'Annuler',
  variant: 'danger'   // 'danger' | 'warning' | 'info' | 'success'
});
if (ok) { /* proceed */ }
```

### AgToast.show() — correct argument order
```js
// Source: public/assets/js/components/ag-toast.js — static show()
// Signature: show(type, message, duration?)
AgToast.show('success', 'Membre ajouté');                          // 5000ms auto-dismiss
AgToast.show('error', 'Erreur de connexion — réessayez', 8000);   // 8000ms explicit
AgToast.show('warning', 'Attention : modifications non sauvegardées');
AgToast.show('info', 'Synchronisation en cours...');
```

### CSS form grid (D-05)
```css
/* Apply to any form-body or section with 4+ fields */
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: var(--gap-md, 16px);
}
/* Full-width exceptions */
.form-grid .field-full,
.form-grid textarea,
.form-grid .upload-zone { grid-column: 1 / -1; }
```

### Shared.openModal — existing pattern (DO NOT add new usages)
```js
// Source: public/assets/js/core/shared.js — openModal()
// This exists and works, but must NOT be used for new confirmation dialogs.
// Migrate existing usages to AgConfirm.ask() or ag-modal per D-01/D-02.
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `window.confirm()` / `window.alert()` | `AgConfirm.ask()` | Phase 45 (component rebuild) | None found in codebase — no existing usage to remove |
| Local DOM-box `setNotif()` | Global `setNotif()` → `AgToast.show()` | utils.js already updated | `users.js` still has local override; `admin.js` uses global correctly |
| Inline `div` modals | `ag-modal` web component | Phase 45 | Several operator pages still use inline divs |
| `Shared.openModal()` for confirmations | `AgConfirm.ask()` | Not yet migrated | `operator-tabs.js` `confirmModal()` wrapper |
| Single-column form stacking | 2-col CSS grid | UI-SPEC approved | Not yet applied to most forms |
| No SSE disconnect feedback | Banner pattern (to implement) | D-11 | `event-stream.js` has `onDisconnect` callback, banner not wired |

**Deprecated/outdated:**
- `Shared.openModal()` for confirmation flows: replaced by `AgConfirm.ask()` per D-01
- Local `setNotif()` override in `users.js`: replaced by global `setNotif()` in utils.js which already delegates to `AgToast`

---

## Open Questions

1. **Device Management Modal in operator-tabs.js (line 1427)**
   - What we know: Creates an inline `div` modal with inline styles for device management. No focus trap.
   - What's unclear: Is this modal critical enough to warrant full migration to `ag-modal`, or is a simpler fix (add keyboard handler) sufficient for this phase?
   - Recommendation: Migrate to `ag-modal` — it's a patient-facing operator workflow and deserves full behavior.

2. **`confirmModal()` wrapper in operator-tabs.js — call sites**
   - What we know: `confirmModal()` wraps `Shared.openModal()`. It's called in at least the dirty-state unsaved-changes flow (line 281).
   - What's unclear: Are there other call sites beyond line 281 that need auditing?
   - Recommendation: Grep `confirmModal(` in operator-tabs.js before planning the migration task.

3. **Wizard step height at 1024px after 2-col grid**
   - What we know: UI-SPEC says steps must fit in viewport at 1024px without scroll.
   - What's unclear: Step 2 (Participants) has a member table that could be tall.
   - Recommendation: Implement 2-col grid first, then visually verify each step. Step 2 may need a max-height with overflow-y: auto on the member list sub-section specifically.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright (E2E) — `tests/e2e/` |
| Config file | `playwright.config.js` (project root assumed) |
| Quick run command | `npx playwright test tests/e2e/specs/ux-interactions.spec.js --headed=false` |
| Full suite command | `npx playwright test tests/e2e/` |

### Phase Requirements → Test Map
| Behavior | Test Type | Automated Command | File Exists? |
|----------|-----------|-------------------|-------------|
| AgConfirm.ask() replaces Shared.openModal() — confirmation dialog appears on destructive action | E2E smoke | `npx playwright test tests/e2e/specs/ux-interactions.spec.js -g "confirm"` | Partial (ux-interactions.spec.js exists) |
| No window.confirm() / window.alert() calls anywhere | Lint / grep | `grep -r "window\.confirm\|window\.alert" public/assets/js/pages/` | N/A (0 found) |
| AgToast.show() fires on all API error paths | E2E | Manual observation during test runs | Partial |
| SSE disconnect banner appears and disappears | E2E | Manual / new spec needed | No |
| Form fields use 2-col grid on desktop | Visual / CSS | `npx playwright test tests/e2e/specs/ux-interactions.spec.js` | Partial |
| ag-modal closes on Escape, backdrop, X button | E2E | `npx playwright test tests/e2e/specs/ux-interactions.spec.js -g "modal"` | Partial |
| No double-submit on slow API calls | E2E | Manual | No |
| Unsaved changes warning fires before navigation | E2E | Manual | No |

### Sampling Rate
- **Per task commit:** `npx playwright test tests/e2e/specs/ux-interactions.spec.js --headed=false`
- **Per wave merge:** `npx playwright test tests/e2e/`
- **Phase gate:** Full E2E suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/e2e/specs/ux-interactions.spec.js` — Extend with: SSE disconnect banner, AgConfirm usage, unsaved changes, double-submit prevention
- [ ] No PHP unit tests are needed for this phase (pure frontend JS/CSS work)

---

## Sources

### Primary (HIGH confidence)
- Direct source read: `public/assets/js/components/ag-confirm.js` — AgConfirm.ask() API verified
- Direct source read: `public/assets/js/components/ag-toast.js` — AgToast.show() signature verified (type, message, duration)
- Direct source read: `public/assets/js/components/ag-modal.js` — backdrop-click at line 172, Escape at line 32, focus trap at _trapFocus()
- Direct source read: `public/assets/js/components/ag-spinner.js` — variants and sizes confirmed
- Direct source read: `public/assets/js/core/event-stream.js` — onDisconnect/onFallback callback hooks confirmed
- Direct source read: `public/assets/js/core/shared.js` — Shared.openModal(), Shared.btnLoading() API confirmed
- Direct source read: `public/assets/js/pages/operator-tabs.js` — confirmModal() wrapper, _settingsSnapshot dirty-state pattern, AgToast argument-order bugs
- Direct source read: `public/assets/js/pages/users.js` — local setNotif() override confirmed at line 27
- Direct source read: `public/assets/css/design-system.css` — @layer base/components/v4, CSS tokens confirmed
- Direct source read: `.planning/phases/81-fix-ux-interactivity-blocking-popups-broken-layouts-fragile-frontend-wiring/81-UI-SPEC.md` — approved design contract
- Direct source read: `.planning/codebase/CONCERNS.md` — inline modal focus trap gap confirmed

### Secondary (MEDIUM confidence)
- Grep across `public/assets/js/pages/*.js` for `window.confirm|window.alert` — 0 results (no native dialogs in codebase)
- Grep for `AgConfirm.ask|AgToast.show` per page — coverage map confirmed: members.js, meetings.js, admin.js have no AgConfirm usage; settings.js, hub.js, operator-tabs.js have most coverage

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components read directly, APIs verified from source
- Architecture: HIGH — patterns derived from existing working code in the codebase
- Pitfalls: HIGH — concrete bugs found in specific files with line numbers
- Layout decisions: HIGH — UI-SPEC already approved, contracts are binding

**Research date:** 2026-04-03
**Valid until:** 2026-05-03 (stable custom component codebase)
