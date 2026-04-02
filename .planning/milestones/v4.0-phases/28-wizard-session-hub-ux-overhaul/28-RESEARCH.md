# Phase 28: Wizard & Session Hub UX Overhaul — Research

**Researched:** 2026-03-18
**Domain:** Vanilla JS wizard/hub enhancement — CSS refonte, step 4 review card, motion templates, progressive disclosure, hub checklist blocked-reasons, quorum bar threshold tick, convocation flow
**Confidence:** HIGH (all findings from direct source reading)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Wizard Stepper**
- Named step labels replacing numbers: "Informations → Membres → Résolutions → Révision"
- Checkmark icon on completed steps (existing behavior, keep)
- Stepper is horizontal, persistent at top

**Step 4 Review Card**
- Full recap of all 3 previous steps in sections
- Each section has a "Modifier" link that navigates back to that step
- Sections: Informations (title, date, time, lieu), Membres (count + list preview), Résolutions (count + titles), Documents (count)
- Prominent "Créer la séance →" primary button at bottom
- Warnings if critical items missing: "⚠ Aucun membre ajouté — les votes ne pourront pas être attribués"

**Steps 2 & 3 Optional**
- Can create a session with just title + date (step 1)
- Members and résolutions can be added later from the hub
- Step 4 shows warnings but does NOT block creation

**Motion Templates (Step 3)**
- 3 quick-select buttons above the add resolution form
- "Approbation des comptes", "Élection au conseil", "Modification du règlement"
- Click pre-fills title + description fields; user can modify before adding
- Templates are hardcoded JS objects (not DB-stored)

**Progressive Disclosure (Step 2)**
- Default: each member = 1 voix, voting power column hidden
- Toggle switch: "Activer la pondération des voix" reveals the weight column
- When hidden, all members default to voting_power = 1

**Autosave**
- Save to localStorage on field blur AND step change (not interval)
- Restore silently on page load — no confirmation modal, no toast
- Clear draft after successful creation

**Hub Checklist Enhancements**
- Blocked items show reason: "Disponible après ajout des résolutions"
- Each checklist item shows completion status + blocked reason if applicable
- Existing progress bar kept and enhanced

**Quorum Progress Bar**
- Horizontal bar with threshold tick mark at correct percentage position
- Fill: amber before threshold, green after threshold
- Text: "Présents: 28/42 — Seuil: 60% = 25 membres"
- Animate fill incrementally when a member is marked present
- Use existing ag-quorum-bar component if it fits, or enhance it

**Hub Document Badges**
- Already wired from Phase 25 — integrate cleanly with enhanced hub layout

**Convocations**
- "Envoyer les convocations" button → ag-confirm dialog ("Envoyer à X membres ?") → API call → toast success/error
- Immediate send, no preview modal

**Visual Overhaul (Refonte CSS)**
- Inspiration: Notion-like — minimaliste, typographie forte (Bricolage Grotesque + Fraunces), beaucoup d'espace, peu de couleurs, focus contenu
- Complete CSS rewrite for wizard.css and hub.css
- Micro-interactions: fade transitions (150ms) between wizard steps, progress bar animation, button hover feedback
- PC-first (1024px+), adaptive for smaller screens

**Error Handling**
- Field validation: inline error message in red under the field, appears on blur
- API errors: persistent toast (manual dismiss), form data preserved
- Next button NOT disabled — clicking with invalid fields triggers inline validation on all required fields

### Claude's Discretion
- Exact CSS values (spacing, colors, shadows, border-radius)
- ag-quorum-bar enhancement details
- Review card section HTML structure
- Template content (exact French text for description fields)
- Error message wording for each validation case

### Deferred Ideas (OUT OF SCOPE)
- Motion templates stored per-tenant in DB (v5+ — hardcoded for v4.0)
- Email template preview before convocation send
- Top 1% visual overhaul for ALL other pages (Phase 29)
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| WIZ-01 | Named-step wizard with horizontal stepper (Informations → Membres → Résolutions → Révision) | Stepper already functional in HTML/CSS/JS — needs label update from "Infos générales / Participants / Ordre du jour / Récapitulatif" to locked names; ag-stepper Web Component exists but wizard uses inline `.wiz-step-item` divs (keep existing pattern) |
| WIZ-02 | Autosave on field blur for all wizard steps; back navigation preserves data | Step 1 already has blur autosave via `setupStep1Autosave()`; members/resolutions already save on add/remove; only missing: explicit `saveDraft()` on step transition (already called in btnNext handlers); restore already works silently |
| WIZ-03 | Step 4 full review card before commit with "Modifier" link per section | `buildRecap()` exists but produces flat key-value rows — needs full replacement with sectioned card HTML (4 sections + warnings); "Modifier" links call `showStep(n)` which already exists |
| WIZ-04 | Motion template picker in wizard step 3 (3 hardcoded templates) | No template UI exists — needs 3 button row above reso-add-panel; click handler pre-fills `resoTitle` + `resoDesc` inputs; templates are JS objects in wizard.js |
| WIZ-05 | Progressive disclosure — "Paramètres de vote avancés" toggle in wizard step 2 reveals voting power fields | Member add form uses `window.prompt()` for manual add — needs inline form with toggle to show/hide voting_power field; renderMembersList() shows voix but has no column header — needs voting_power column that hides/shows with toggle |
| WIZ-06 | Session hub pre-meeting checklist with blocked-reason display | `CHECKLIST_ITEMS` array exists (6 items); `renderChecklist()` renders items; missing: `blockedReason` property per item and conditional display in HTML output |
| WIZ-07 | Quorum progress bar with animated fill, threshold tick marker, amber→green transition | `ag-quorum-bar` already has threshold tick, amber/partial/green states, animated fill via CSS transition, and `current`/`required`/`total` attributes — component is READY; needs to be placed in hub.htmx.html and wired to session data |
| WIZ-08 | Hub document status indicators per motion ("Document joint ✓" / "Aucun document") | `renderDocBadge()` + `loadDocBadges()` already implemented in hub.js; `.doc-badge--has-docs` / `.doc-badge--empty` CSS classes already in hub.htmx.html inline style block — integration needed in hub layout motions list |
</phase_requirements>

---

## Summary

Phase 28 is a **visual + functional enhancement** of two pages that already work correctly end-to-end. The wizard (4-step creation) and hub (pre-meeting staging) have a functional backbone — navigation, validation, draft persistence, checklist, and document badges all exist. The gap between current state and requirements is surgical: new HTML structures, new JS functions, and a complete CSS rewrite.

The most important architectural discovery is that **ag-quorum-bar is fully capable of meeting WIZ-07** without modification. It already renders a threshold tick mark, transitions amber→green, animates the fill bar, and accepts `current`/`required`/`total` attributes. The task is purely wiring it into the hub page with real data.

The second key discovery: **validation gating currently blocks steps 2 and 3** (lines 141-142 of wizard.js: `if (n === 1) { return members.length > 0; }`). The locked decision that steps 2/3 are optional means these guards must be removed — clicking Next on an empty step should proceed, not show an error. Step 4 shows warnings but does not prevent creation.

The CSS refonte is the largest surface area of work. Both wizard.css and hub.css are complete rewrites to Notion-like aesthetic using existing design tokens. The fonts (Bricolage Grotesque + Fraunces) are already loaded in both pages.

**Primary recommendation:** Plan as 3 waves — (1) wizard JS/HTML changes (stepper labels, step 4 review card, templates, progressive disclosure, optional steps), (2) hub JS/HTML changes (checklist blocked-reasons, quorum bar wiring, convocation flow), (3) CSS rewrite for both pages.

---

## Standard Stack

### Core (no new dependencies — everything already loaded)

| Asset | Version/Status | Purpose | How Used |
|-------|---------------|---------|----------|
| wizard.js | Existing IIFE | Step navigation, draft, validation, members, resolutions | Enhance in-place |
| hub.js | Existing IIFE | Checklist, KPIs, stepper, data loading | Enhance in-place |
| wizard.css | Existing | Wizard visual styles | Complete rewrite |
| hub.css | Existing | Hub visual styles | Complete rewrite |
| ag-quorum-bar.js | Existing Web Component | Quorum progress bar with threshold tick | Wire to hub |
| ag-confirm.js | Existing Web Component | `AgConfirm.ask()` for convocation send confirmation | Already loaded |
| ag-toast.js | Existing Web Component | `AgToast.show()` / `Shared.showToast()` for success/error | Already loaded |
| localStorage | Browser API | Draft persistence (`ag-vote-wizard-draft`) | Already wired |
| Bricolage Grotesque + Fraunces | Google Fonts, already loaded | Typography | Already in `<head>` of both pages |

### No New Libraries Required

All requirements are achievable with existing assets. No CDN additions, no new Web Components needed.

---

## Architecture Patterns

### Existing Pattern to Follow: IIFE + var

All page scripts use `(function() { 'use strict'; ... })()` with `var` declarations. Do not introduce `const`/`let`/`class` in wizard.js or hub.js — the project standard is ES5-compatible IIFE.

### Existing Pattern to Follow: One CSS per Page

wizard.css and hub.css are standalone files loaded after app.css. They should not import or extend design-system.css — they use its tokens via CSS custom properties.

### Existing Pattern to Follow: Web Components for Shared UI

ag-quorum-bar, ag-confirm, ag-toast are loaded as `<script type="module">`. They expose globals (`window.AgConfirm`, `window.AgToast`) for use from non-module IIFE scripts.

### Recommended File Touch Map

```
wizard.htmx.html          # Stepper labels, step 4 review card HTML, template buttons, member form
wizard.js                 # buildRecap() replacement, templates, progressive disclosure, remove step 2/3 guards
wizard.css                # Complete rewrite — Notion-like

hub.htmx.html             # ag-quorum-bar element placement, motions list with doc badges, convocation button
hub.js                    # CHECKLIST_ITEMS blockedReason, renderChecklist() blocked-reason display, convocation flow
hub.css                   # Complete rewrite — Notion-like
```

### Anti-Patterns to Avoid

- **Do not use `ag-stepper` Web Component for the wizard stepper.** The wizard already uses inline `.wiz-step-item` divs with JS-driven `updateStepper()`. ag-stepper is a standalone shadow-DOM component — replacing the wizard stepper with it would require rewriting event delegation and losing the `data-step` click-to-navigate behavior. Keep the existing pattern; only change the label text.
- **Do not use `ag-stepper` for the hub stepper either.** The hub uses a vertical stepper built by `renderStepper()` — a completely different layout (vertical, clickable, with "← Vous êtes ici" text). ag-stepper is horizontal-only.
- **Do not prompt() for member add.** The current `btnAddManual` uses `window.prompt()`. Phase 28 replaces this with an inline form (needed for progressive disclosure of voting_power).
- **Do not add interval-based autosave.** The locked decision is blur + step change only.

---

## Detailed Component Analysis

### wizard.js — What Exists, What Changes

**Existing functions (keep as-is):**
- `showStep(n)` — shows/hides step divs, calls `updateStepper()`, scrolls to top
- `updateStepper()` — adds done/active classes, replaces number with checkmark SVG
- `saveDraft()` / `restoreDraft()` / `clearDraft()` — localStorage, saves step + s1 fields + members array + resolutions array
- `setupTimeInput()` — HH:MM smart focus, blur saves draft
- `setupStep1Autosave()` — blur listeners on 7 Step 1 fields
- `renderMembersList()` — renders member rows with name + voix
- `handleCsvFile()` / `setupCsvImport()` — CSV import + drag-drop zone
- `renderResoList()` — renders reso rows with drag-drop
- `setupAddReso()` — adds resolution to array, resets form
- `buildPayload()` — builds API payload
- All drag-drop handlers
- FilePond initialization per resolution

**Functions to change:**
- `validateStep(n)` — remove guards for n=1 (members) and n=2 (resolutions); keep n=0 (title + date required)
- `showFieldErrors(n)` — remove error display for steps 1 and 2 (they are now optional)
- `clearFieldErrors(n)` — same
- `buildRecap()` — complete replacement with sectioned review card
- `renderMembersList()` — add voting_power column visibility toggle
- `init()` — add template button handlers, add progressive disclosure toggle, update btnNext1/btnNext2 to skip validation

**Functions to add:**
- `applyTemplate(tpl)` — pre-fills resoTitle + resoDesc from hardcoded template object
- `toggleVotingPower(show)` — shows/hides voting_power column and input in member form
- `buildReviewCard()` — replaces `buildRecap()` with full sectioned review HTML

### hub.js — What Exists, What Changes

**Existing functions (keep as-is):**
- All `HUB_STEPS` and `SVG_ICONS` definitions
- `renderStatusBar()`, `renderStepper()`, `renderAction()`
- `renderKpis(data)`, `renderDocuments(files)`
- `loadDocBadges()`, `renderDocBadge()`, `openDocViewer()`
- `mapApiDataToSession()`, `applySessionToDOM()`
- `loadData()`, `showHubError()`, `checkToast()`

**Functions to change:**
- `renderChecklist(sessionData)` — add `blockedReason` rendering per item
- `CHECKLIST_ITEMS` array — add `blockedReason` property to each item

**Functions to add:**
- `setupConvocationBtn()` — wires "Envoyer les convocations" button to AgConfirm + API call + toast

**HTML additions to hub.htmx.html:**
- `<ag-quorum-bar>` element wired to `current`/`required`/`total` from session data
- Motions list container for per-motion doc badges (WIZ-08)
- "Envoyer les convocations" button in hub action area for step 2 (convocations)

### ag-quorum-bar.js — Full Capability Audit

The component already has ALL features required by WIZ-07:

| WIZ-07 Requirement | Component Capability | Status |
|-------------------|---------------------|--------|
| Threshold tick mark | `.quorum-threshold` absolutely positioned at `thresholdPercent%` with downward triangle `::after` | READY |
| Amber before threshold | `.quorum-fill.partial` — background: `var(--color-warning)` | READY |
| Green after threshold | `.quorum-fill.reached` — background: `var(--color-success)` | READY |
| Animated fill | `transition: width 0.5s ease-out` on `.quorum-fill` | READY |
| Text label | Auto-generated label: "Quorum atteint" or "X manquants"; full text "Présents: X/Y" in `.quorum-values` | READY (minor label format may need override via `label` attribute) |
| `current`/`required`/`total` attributes | `observedAttributes` includes all three; re-renders on change | READY |

**Usage in hub:**
```html
<ag-quorum-bar id="hubQuorumBar" current="0" required="25" total="42"></ag-quorum-bar>
```

Update via JS when session data loads:
```javascript
var bar = document.getElementById('hubQuorumBar');
if (bar) {
  bar.setAttribute('current', sessionData.presentCount || 0);
  bar.setAttribute('required', sessionData.quorumRequired || 0);
  bar.setAttribute('total', sessionData.memberCount || 0);
}
```

The label text "Présents: 28/42 — Seuil: 60% = 25 membres" from the locked decisions is more detailed than the auto-generated label. Use the `label` attribute to override: `label="Présents: 28/42 — Seuil: 60%"`.

**Caveat:** ag-quorum-bar uses shadow DOM. The `.quorum` wrapper has its own padding and border built in. When embedding in hub layout, may need `<ag-quorum-bar style="display:block;">` with outer wrapper for spacing — test visually.

### wizard.htmx.html — Current Stepper Labels

Current stepper text (lines 80-90):
```
Step 0: "Infos générales"
Step 1: "Membres"
Step 2: "Ordre du jour"
Step 3: "Récapitulatif"
```

Required by WIZ-01:
```
Step 0: "Informations"
Step 1: "Membres"        ← same
Step 2: "Résolutions"    ← change from "Ordre du jour"
Step 3: "Révision"       ← change from "Récapitulatif"
```

Only `<span>` text content changes inside `.wiz-step-item`. The `.wiz-snum` circle and data-step attributes remain unchanged.

### saveDraft() — What Is and Is NOT Saved

Currently saved (`s1` object in draft):
- title, type, date, hh, mm, place, addr, quorum, defaultMaj
- members array
- resolutions array
- currentStep

NOT currently saved (missing from `s1`):
- No individual field for `votingPowerEnabled` toggle state (new for WIZ-05)

When implementing WIZ-05 (progressive disclosure), add `votingPowerEnabled` to saveDraft:
```javascript
votingPowerEnabled: (document.getElementById('wizVotingPowerToggle') || {}).checked || false
```

And restore in restoreDraft:
```javascript
if (draft.s1.votingPowerEnabled) toggleVotingPower(true);
```

### Step 2 — Current Manual Add Problem

Current `btnAddManual` handler:
```javascript
var name = window.prompt('Nom du participant :');
var voix = parseInt(window.prompt('Poids de vote...') || '1', 10) || 1;
```

This `window.prompt()` pattern is incompatible with progressive disclosure (WIZ-05). The inline form replacement needs:
- Name input field
- Email input field (optional)
- Voting power input field (hidden by default, shown when toggle active)
- "Ajouter" button

The drop zone text currently says "Colonnes attendues : Nom, Lot, Courriel, Clé Générale..." — the "Lot" and "Clé" references are copropriété vocabulary. Phase 27 cleaned vocabulary; the drop zone description should be updated to "Colonnes attendues : Nom, Courriel, Poids de vote".

### Step 3 — Current resoKey Field

The reso-add-panel has a `resoKey` select with hardcoded options ("Charges générales (défaut)", "Ascenseur Bâtiment A", etc.). This is copropriété vocabulary from previous versions. Phase 28 should remove or replace this field — it is not part of the locked decisions. The locked decisions for step 3 are: title, description, majorité, secret vote, templates. The `resoKey` field can be quietly removed in the new HTML (it maps to nothing relevant for general assemblies).

### CHECKLIST_ITEMS — Blocked Reason Design

Current CHECKLIST_ITEMS (6 items, no blockedReason):
```javascript
{ key: 'title',        label: 'Titre défini',         autoCheck: function(d) { return !!d.title; } },
{ key: 'date',         label: 'Date fixée',            autoCheck: function(d) { return !!d.date; } },
{ key: 'members',      label: 'Membres ajoutés',       autoCheck: function(d) { return d.memberCount > 0; } },
{ key: 'resolutions',  label: 'Résolutions créées',    autoCheck: function(d) { return d.resolutionCount > 0; } },
{ key: 'convocations', label: 'Convocations envoyées', autoCheck: function(d) { return d.convocationsSent; } },
{ key: 'documents',    label: 'Documents attachés',    autoCheck: function(d) { return d.documentCount > 0; } }
```

Required addition — `blockedReason` property per item:
```javascript
{ key: 'convocations', label: 'Convocations envoyées',
  autoCheck: function(d) { return d.convocationsSent; },
  blockedReason: function(d) {
    if (!d.memberCount) return 'Disponible après ajout des membres';
    if (!d.resolutionCount) return 'Disponible après ajout des résolutions';
    return null;
  }
},
```

Items title and date have no blocked reason (they are always actionable). Items members and resolutions have no blocked reason (they are always actionable from the hub). Item documents has no blocked reason.

### renderChecklist() — HTML Output Change

Current output per item:
```javascript
'<div class="hub-check-item' + (checked ? ' done' : '') + '">' +
  '<div class="hub-check-icon">...</div>' +
  '<span class="hub-check-label">' + label + '</span>' +
  (!checked ? '<span class="hub-check-todo">À faire</span>' : '') +
'</div>'
```

Required output per item (add blocked reason display):
```javascript
var reason = !checked && item.blockedReason ? item.blockedReason(sessionData) : null;
'<div class="hub-check-item' + (checked ? ' done' : '') + '">' +
  '<div class="hub-check-icon">...</div>' +
  '<span class="hub-check-label">' + label + '</span>' +
  (reason
    ? '<span class="hub-check-blocked">' + escapeHtml(reason) + '</span>'
    : (!checked ? '<span class="hub-check-todo">À faire</span>' : '')) +
'</div>'
```

New CSS class `.hub-check-blocked` needed in hub.css: muted italic, smaller font, amber color.

### Convocation Send Flow — Implementation

The hub currently has a "Envoyer les convocations" button concept in HUB_STEPS (step index 1: `convocations`). The `renderAction()` function shows this step's button with `step.btnPreview = true`. The actual send button is `id="hubMainBtn"` which is an `<a>` tag.

For WIZ-08 convocation send, a dedicated button needs to be wired (separate from the existing hub action flow):

```javascript
function setupConvocationBtn() {
  var btn = document.getElementById('btnSendConvocations');
  if (!btn) return;
  btn.addEventListener('click', function() {
    var memberCount = /* from loaded session data */ 0;
    window.AgConfirm.ask({
      title: 'Envoyer les convocations',
      message: 'Envoyer les convocations à ' + memberCount + ' membres ?',
      confirmLabel: 'Envoyer',
      variant: 'info'
    }).then(function(ok) {
      if (!ok) return;
      var sessionId = new URLSearchParams(window.location.search).get('id');
      window.api('/api/v1/meetings/' + sessionId + '/convocations', {}, 'POST')
        .then(function() {
          window.AgToast.show('Convocations envoyées', 'success');
        })
        .catch(function() {
          window.AgToast.show('Erreur lors de l\'envoi des convocations', 'error');
        });
    });
  });
}
```

Note: The API endpoint `/api/v1/meetings/{id}/convocations` may not exist — this must be confirmed or the planner must note it as a task to verify. Hub.js currently has no convocation send logic.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Quorum bar with threshold tick | Custom div-based progress bar | `ag-quorum-bar` component | Already has tick, amber/green states, animation |
| Confirmation modal for convocation send | Custom modal | `AgConfirm.ask()` | Already has variants, focus trap, Escape key, Promise API |
| Success/error feedback | Custom toast | `window.AgToast.show()` or `Shared.showToast()` | Already consistent across all pages |
| Draft save/restore | New localStorage wrapper | Existing `saveDraft()` / `restoreDraft()` | Already handles parse errors, quota exceeded |
| Step navigation | Rewrite | Existing `showStep(n)` | Works, just needs label updates |

---

## Common Pitfalls

### Pitfall 1: validateStep() Still Blocking Optional Steps

**What goes wrong:** After Phase 28, clicking "Suivant" on step 1 (members) with 0 members triggers `showFieldErrors(1)` and `return` — user cannot proceed to step 2.
**Root cause:** `validateStep(1)` returns `members.length > 0`. The locked decision says steps 2 and 3 are optional.
**How to avoid:** In btnNext1 and btnNext2 handlers, remove the `if (!validateStep(n))` guard entirely. Step 4 review card shows warnings but the API accepts 0 members.
**Warning sign:** User clicks Suivant on empty step 2 and nothing happens.

### Pitfall 2: buildRecap() Replaced but wizRecap Element ID Reused

**What goes wrong:** New review card HTML is written into `#wizRecap` but the old `buildRecap()` code is still called somewhere, overwriting the new sectioned HTML with flat rows.
**Root cause:** `buildRecap()` is called in btnNext2 handler before `showStep(3)`. If the new function is not renamed and wired, old code still runs.
**How to avoid:** Rename to `buildReviewCard()` and update the btnNext2 handler to call the new function.

### Pitfall 3: ag-quorum-bar Shadow DOM vs Design Tokens

**What goes wrong:** ag-quorum-bar uses `var(--color-warning)` and `var(--color-success)` inside its shadow DOM. If these tokens are not defined on `:root` (or are in a different layer), the colors fall back to hardcoded hex values (`#b8860b`, `#0b7a40`) which may not match the Notion-like redesign.
**Root cause:** Shadow DOM inherits CSS custom properties from the host document's `:root`, but not from classes or scoped selectors.
**How to avoid:** Confirm `--color-warning` and `--color-success` are defined in design-system.css `:root` (they are, at lines ~200+). No action needed; CSS custom properties pierce shadow DOM.

### Pitfall 4: Progressive Disclosure Toggle Not Saved in Draft

**What goes wrong:** User enables voting power toggle, adds members with custom weights, navigates back to step 1, then forward again. The toggle is off again; all members show 1 voix.
**Root cause:** `saveDraft()` doesn't save `votingPowerEnabled`.
**How to avoid:** Add `votingPowerEnabled` to the `s1` object in `saveDraft()` and restore it in `restoreDraft()`.

### Pitfall 5: CSS Rewrite Breaking app.css-Defined Classes

**What goes wrong:** New wizard.css redefines `.field-input`, `.btn`, or `.card` — classes also defined in design-system.css — causing unintended overrides on elements that inherit the global style.
**Root cause:** wizard.css and hub.css are loaded after app.css. Any selector that is too broad bleeds into shared components.
**How to avoid:** Scope ALL wizard-specific rules under `.wiz-step`, `.wiz-progress-wrap`, or `.wiz-step-body`. Use `.hub-*` prefix for hub styles. Never redefine global component classes (`.btn`, `.card`, `.field-input`) — only add modifier classes (`.wiz-field-input` if a variant is truly needed).

### Pitfall 6: Convocation API Endpoint May Not Exist

**What goes wrong:** `setupConvocationBtn()` calls `/api/v1/meetings/{id}/convocations` (POST) which may not be routed.
**Root cause:** Hub.js has never wired a convocation send — this is new behavior.
**How to avoid:** During planning, include a task to verify the convocation send endpoint exists and is wired. If not, the task must create it or stub it with a toast "fonctionnalité à venir".

### Pitfall 7: resoKey Field Removal Breaks buildPayload()

**What goes wrong:** If `resoKey` select is removed from step 3 HTML, `buildPayload()` still reads `keyEl.value` from it and sends a null/undefined key to the API.
**Root cause:** `setupAddReso()` reads `resoKey` select value when adding a resolution (line ~660: `key: keyEl ? keyEl.value : 'Charges générales (défaut)'`).
**How to avoid:** When removing `resoKey` from HTML, also update `setupAddReso()` to hard-code `key: 'Charges générales'` or remove the `key` field from resolution objects entirely, and verify the API does not require it.

---

## Code Examples

### Motion Template Pattern

```javascript
// Source: wizard.js (new addition — hardcoded templates)
var MOTION_TEMPLATES = [
  {
    id: 'approbation-comptes',
    label: 'Approbation des comptes',
    title: 'Approbation des comptes de l\'exercice',
    desc: 'L\'assemblée approuve les comptes de l\'exercice écoulé tels qu\'ils ont été présentés par le président et le trésorier.'
  },
  {
    id: 'election-conseil',
    label: 'Élection au conseil',
    title: 'Élection des membres du conseil',
    desc: 'L\'assemblée procède à l\'élection des membres du conseil d\'administration pour l\'exercice à venir.'
  },
  {
    id: 'modification-reglement',
    label: 'Modification du règlement',
    title: 'Modification du règlement intérieur',
    desc: 'L\'assemblée approuve les modifications proposées au règlement intérieur telles que présentées en séance.'
  }
];

function applyTemplate(tpl) {
  var titleEl = document.getElementById('resoTitle');
  var descEl = document.getElementById('resoDesc');
  if (titleEl) titleEl.value = tpl.title;
  if (descEl) descEl.value = tpl.desc;
  if (titleEl) titleEl.focus();
}
```

Template button HTML (above `.reso-add-panel`):
```html
<div class="wiz-templates-row">
  <span class="wiz-templates-label">Modèles rapides :</span>
  <button class="btn btn-sm btn-ghost" type="button" data-template="approbation-comptes">Approbation des comptes</button>
  <button class="btn btn-sm btn-ghost" type="button" data-template="election-conseil">Élection au conseil</button>
  <button class="btn btn-sm btn-ghost" type="button" data-template="modification-reglement">Modification du règlement</button>
</div>
```

### Step 4 Review Card Structure (buildReviewCard)

```javascript
// Source: wizard.js (replaces buildRecap())
function buildReviewCard() {
  var recap = document.getElementById('wizRecap');
  if (!recap) return;

  var title  = getId('wizTitle').value || '(non renseigné)';
  var type   = getId('wizType').value  || '';
  var date   = getId('wizDate').value  || '';
  var hh     = getId('wizTimeHH').value || '';
  var mm     = getId('wizTimeMM').value || '';
  var place  = getId('wizPlace').value  || '';
  var addr   = getId('wizAddr').value   || '';
  var dateStr = date ? date + (hh && mm ? ' à ' + hh + ':' + mm : '') : '(non renseignée)';
  var lieu = [place, addr].filter(Boolean).join(', ') || '(non renseigné)';

  // Warning flags (non-blocking)
  var warnings = '';
  if (members.length === 0) {
    warnings += '<div class="review-warning">⚠ Aucun membre ajouté — les votes ne pourront pas être attribués</div>';
  }
  if (resolutions.length === 0) {
    warnings += '<div class="review-warning">⚠ Aucune résolution — l\'ordre du jour est vide</div>';
  }

  // Section: Informations + Modifier link
  var html =
    '<div class="review-section">' +
      '<div class="review-section-header">' +
        '<span class="review-section-title">Informations</span>' +
        '<button class="btn btn-sm btn-ghost review-modifier" type="button" data-goto="0">Modifier</button>' +
      '</div>' +
      '<div class="review-row"><span class="review-label">Titre</span><span class="review-value">' + escapeHtml(title) + '</span></div>' +
      '<div class="review-row"><span class="review-label">Type</span><span class="review-value">' + escapeHtml(type) + '</span></div>' +
      '<div class="review-row"><span class="review-label">Date</span><span class="review-value">' + escapeHtml(dateStr) + '</span></div>' +
      '<div class="review-row"><span class="review-label">Lieu</span><span class="review-value">' + escapeHtml(lieu) + '</span></div>' +
    '</div>' +
    // ... Membres section, Résolutions section, Documents section
    warnings;

  recap.innerHTML = html;

  // Wire Modifier buttons
  recap.querySelectorAll('.review-modifier').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var step = parseInt(btn.getAttribute('data-goto'), 10);
      showStep(step);
    });
  });
}
```

### Progressive Disclosure Toggle Pattern

```javascript
// Source: wizard.js (new addition)
function setupVotingPowerToggle() {
  var toggle = document.getElementById('wizVotingPowerToggle');
  var col = document.querySelectorAll('.member-col-votes');  // column headers + cells
  if (!toggle) return;

  toggle.addEventListener('change', function() {
    toggleVotingPower(toggle.checked);
    saveDraft();
  });
}

function toggleVotingPower(show) {
  var toggle = document.getElementById('wizVotingPowerToggle');
  if (toggle) toggle.checked = show;
  // Show/hide voting_power input in manual add form
  var vpField = document.getElementById('wizMemberVpField');
  if (vpField) vpField.style.display = show ? '' : 'none';
  // Show/hide votes column in members list
  document.querySelectorAll('.member-votes').forEach(function(el) {
    el.style.display = show ? '' : 'none';
  });
}
```

Toggle HTML (in step 2, above the member form):
```html
<div class="wiz-toggle-row">
  <label class="toggle-label" for="wizVotingPowerToggle">
    <input type="checkbox" id="wizVotingPowerToggle" role="switch">
    Activer la pondération des voix
  </label>
</div>
```

### ag-quorum-bar Wiring in hub.js

```javascript
// Source: hub.js — add to renderKpis() or a new renderQuorumBar() call after data loads
function renderQuorumBar(sessionData) {
  var bar = document.getElementById('hubQuorumBar');
  if (!bar) return;
  bar.setAttribute('current', sessionData.presentCount || 0);
  bar.setAttribute('required', sessionData.quorumRequired || 0);
  bar.setAttribute('total', sessionData.memberCount || 0);
  if (sessionData.quorumRequired && sessionData.memberCount) {
    var pct = Math.round(sessionData.quorumRequired / sessionData.memberCount * 100);
    bar.setAttribute('label',
      'Présents\u202f: ' + (sessionData.presentCount || 0) + '/' + sessionData.memberCount +
      ' — Seuil\u202f: ' + pct + '%\u202f=\u202f' + sessionData.quorumRequired + ' membres'
    );
  }
}
```

Hub HTML placement (in hub-main-col, after hub-checklist):
```html
<div class="hub-quorum-section">
  <ag-quorum-bar id="hubQuorumBar" current="0" required="0" total="0"></ag-quorum-bar>
</div>
```

Also add to hub.htmx.html `<script>` block:
```html
<script type="module" src="/assets/js/components/ag-quorum-bar.js"></script>
```

### Notion-Like CSS Aesthetic — Key Patterns

The Notion aesthetic applied to wizard.css/hub.css means:

```css
/* Spacing — generous, airy */
.wiz-step-body { padding: 2rem 2.5rem; }
.review-section { padding: 1.5rem 0; border-bottom: 1px solid var(--color-border-subtle); }

/* Typography — strong hierarchy */
.wiz-step-title { font-family: var(--font-display); font-size: 1.5rem; font-weight: 700; }
.review-section-title { font-size: 0.6875rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-text-muted); }

/* Buttons — primary stands out, secondary restrained */
.btn-primary { background: var(--color-text-dark); color: #fff; border: none; }
.btn-ghost { background: transparent; border: 1.5px solid var(--color-border); }

/* Cards — barely-there shadows, clean borders */
.wiz-step { box-shadow: 0 1px 4px rgba(0,0,0,.04); border: 1px solid var(--color-border); border-radius: 12px; }

/* Inputs — spacious, clear focus ring */
.field-input { padding: 10px 14px; font-size: 0.9375rem; border-radius: 8px; }
.field-input:focus { box-shadow: 0 0 0 3px var(--color-primary-subtle); }

/* Step transitions — fade between steps */
.wiz-step { animation: wizFadeIn var(--duration-fast) var(--ease-out); }
@keyframes wizFadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
```

CSS variables to use (all from design-system.css):
- Fonts: `var(--font-sans)` (Bricolage Grotesque), `var(--font-display)` (Fraunces), `var(--font-mono)` (JetBrains Mono)
- Colors: standard token set — `--color-bg`, `--color-surface`, `--color-text`, `--color-primary`, `--color-border`, etc.
- Spacing: `--space-4` (16px), `--space-6` (24px), `--space-8` (32px)
- Transitions: `--duration-fast` (100ms), `--duration-normal` (200ms)

---

## State of the Art

| Old Approach | Current Approach | Impact for Phase 28 |
|--------------|------------------|---------------------|
| `window.prompt()` for member add | Inline form with field + button | Replace in step 2 for progressive disclosure |
| Flat recap rows (key → value) | Sectioned review card with Modifier links | Replace `buildRecap()` with `buildReviewCard()` |
| No motion templates | Quick-select template buttons | Add above reso-add-panel |
| Hard-blocked steps 2+3 | Optional steps with warnings only | Remove guards from validateStep() |
| "À faire" on checklist items | Blocked reason display | Add blockedReason to CHECKLIST_ITEMS |
| Quorum data in KPI card only | ag-quorum-bar with threshold tick | Wire component to hub data |
| No convocation button logic | AgConfirm + API + toast flow | Add setupConvocationBtn() |

---

## Open Questions

1. **Convocation send API endpoint**
   - What we know: hub.js has no convocation send logic; `HUB_STEPS[1]` (convocations step) has `dest: null` and `btnPreview: true` — the send has never been wired
   - What's unclear: Does `/api/v1/meetings/{id}/convocations` (POST) exist? MeetingController? ConvocationController?
   - Recommendation: Planner must include a task to grep the API routes for convocation endpoint, and if absent, create it or stub it

2. **`presentCount` and `quorumRequired` in API response**
   - What we know: `mapApiDataToSession()` maps `data.member_count` but has no `presentCount` or `quorumRequired` field mapping; `kpiQuorum` shows `-` by default
   - What's unclear: Does `/api/v1/wizard_status` return quorum threshold and present count? The hub is pre-meeting (preparation phase) where presence recording hasn't started
   - Recommendation: For Phase 28, show quorum bar with `current=0` and `required` from quorum policy; animate when operator console updates. If no quorum threshold in API, derive from quorum policy string

3. **`resoKey` field — remove or keep hidden?**
   - What we know: `resoKey` select appears in step 3 with copropriété-specific options (Ascenseur, Chauffage, Parking); it is passed to API via buildPayload via resolution.key
   - What's unclear: Does the API / DB require this field?
   - Recommendation: Check if `motions` table has a `key` column that is required or nullable. If nullable, safe to remove from UI. If required, keep hidden with default value.

4. **Hub motions list for WIZ-08 doc badges**
   - What we know: `loadDocBadges()` and `renderDocBadge()` are implemented in hub.js; they query `[data-motion-doc-badge][data-motion-id="X"]` elements in the DOM
   - What's unclear: Hub.htmx.html has NO motions list section — there are no `[data-motion-doc-badge]` elements in the hub HTML, only in wizard step 3. Where does the hub show per-motion doc badges?
   - Recommendation: Phase 28 must add a motions list section to hub.htmx.html with per-motion badge elements, and call `loadDocBadges()` with the motions from the API response

---

## Validation Architecture

> `workflow.nyquist_validation` key absent in config.json — validation section included.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | None detected — PHP project, no JS test runner configured |
| Config file | None — see Wave 0 |
| Quick run command | Manual browser smoke test |
| Full suite command | Manual browser smoke test |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| WIZ-01 | Stepper shows named labels; completed steps show checkmark | smoke | Manual — open wizard, advance steps, verify labels | N/A |
| WIZ-02 | Field blur saves draft; back nav restores data | smoke | Manual — fill step 1, navigate back/forward, verify data | N/A |
| WIZ-03 | Step 4 shows sections with Modifier links that navigate back | smoke | Manual — fill wizard, reach step 4, click Modifier | N/A |
| WIZ-04 | Template buttons pre-fill title + description | smoke | Manual — click each template, verify fields populate | N/A |
| WIZ-05 | Toggle reveals/hides voting power column | smoke | Manual — toggle switch, verify column visibility | N/A |
| WIZ-06 | Hub checklist shows blocked reason on locked items | smoke | Manual — load hub with partial session, verify blocked text | N/A |
| WIZ-07 | Quorum bar renders with threshold tick and correct color | smoke | Manual — load hub, verify ag-quorum-bar renders | N/A |
| WIZ-08 | Doc badges show per-motion count in hub | smoke | Manual — upload doc in wizard, load hub, verify badge | N/A |

### Sampling Rate

- **Per task commit:** Manual browser smoke test of modified page
- **Per wave merge:** Full wizard walkthrough (all 4 steps) + hub load and inspect
- **Phase gate:** Full wizard walkthrough + hub with real meeting ID before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] No automated JS test infrastructure exists — all validation is manual smoke test
- [ ] Recommend: planner adds explicit "smoke test checklist" to VERIFICATION.md items for each WIZ requirement

---

## Sources

### Primary (HIGH confidence — direct file reading)

- `public/assets/js/pages/wizard.js` — full function inventory, validateStep(), buildRecap(), saveDraft/restoreDraft, renderMembersList
- `public/wizard.htmx.html` — all 4 step HTML structures, stepper markup, current labels
- `public/assets/css/wizard.css` — all existing classes and current visual system
- `public/assets/js/pages/hub.js` — CHECKLIST_ITEMS, renderChecklist(), renderDocBadge(), loadDocBadges(), mapApiDataToSession()
- `public/hub.htmx.html` — full hub HTML structure, no motions list confirmed
- `public/assets/css/hub.css` — all hub classes
- `public/assets/js/components/ag-quorum-bar.js` — full capability audit: threshold tick CONFIRMED, amber/green CONFIRMED, animation CONFIRMED
- `public/assets/js/components/ag-stepper.js` — horizontal-only, shadow DOM, NOT suitable for wizard/hub steppers
- `public/assets/js/components/ag-confirm.js` — `AgConfirm.ask()` API, variants, Promise-based
- `public/assets/css/design-system.css` — all CSS custom properties, font stack, spacing tokens
- `.planning/phases/28-wizard-session-hub-ux-overhaul/28-CONTEXT.md` — locked decisions
- `.planning/REQUIREMENTS.md` — WIZ-01 through WIZ-08 definitions
- `.planning/research/FEATURES.md` — Pattern 1 (Staged Wizard), Pattern 9 (Quorum), Pattern 10 (Named-Step Wizard)

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all assets read directly, no speculation
- Architecture: HIGH — all function signatures and IDs verified from source files
- Pitfalls: HIGH — derived from direct reading of current code; each pitfall is a real gap found in the code
- CSS aesthetic: MEDIUM — Notion-like direction is clear from locked decisions; exact values are at Claude's discretion

**Research date:** 2026-03-18
**Valid until:** 2026-04-18 (stable vanilla stack, no upstream version changes possible)
