# Phase 4: Clarte et Jargon - Research

**Researched:** 2026-04-21
**Domain:** HTML template editing, Web Components, UX copy
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Voter-Facing Jargon Elimination (CLAR-01)**
- Remplacer "Quorum" par "Seuil de participation" sur public.htmx.html (page de projection visible par les votants)
- Réécrire les sections techniques du FAQ (help.htmx.html) en français simple: "empreinte numérique" au lieu de "SHA-256", etc.
- Les pages admin-only (postsession, settings, audit, trust) gardent leurs termes techniques — CLAR-02 ajoute des tooltips à la place

**Confirmation Pattern & Tooltips (CLAR-02, CLAR-03)**
- CLAR-03: Remplacer "tapez VALIDER" (validate.htmx.html) par un modal avec checkbox "Je confirme cette action" + bouton "Confirmer"
- CLAR-02: Tooltips sur tous les termes techniques admin: quorum, procuration, eIDAS, SHA-256, CNIL sur les pages operator, settings, postsession, audit, trust
- Utiliser le composant ag-tooltip existant (déjà 100+ usages dans le codebase)
- Texte des tooltips en français — ex: "Quorum: nombre minimum de votants requis pour que le scrutin soit valide"

**Export Descriptions (CLAR-04)**
- Description visible sous chaque bouton d'export en `<small>` ou `.export-desc` — visible sans hover
- Tous les boutons d'export: modal archives (6 boutons), page audit (2 boutons), postsession PDF, trust export
- Style: une ligne en français décrivant le contenu du fichier

### Claude's Discretion

Aucun — toutes les décisions ont été prises explicitement.

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CLAR-01 | L'interface votant n'affiche aucun terme technique (eIDAS, SHA-256, quorum, CNIL) | Two files targeted: public.htmx.html (quorum label x2) + help.htmx.html (security FAQ section visible to voters) |
| CLAR-02 | Les termes techniques côté admin/opérateur ont des tooltips explicatifs en français | ag-tooltip component confirmed working; 5 pages need tooltips on 6 distinct terms |
| CLAR-03 | Le pattern "tapez VALIDER" est remplacé par un modal avec checkbox + bouton Confirmer | validate.js already wires checkbox + confirmText; only HTML needs updating + JS logic simplification |
| CLAR-04 | Chaque bouton d'export a une description d'une ligne expliquant le contenu du fichier | 4 locations identified; export-grid CSS already exists; need `.export-btn-wrap` + `<small>` pattern |
</phase_requirements>

## Summary

Phase 4 is a pure frontend UX copy and component work — no backend changes, no new API calls. All modifications are in `.htmx.html` files and one `.js` file. The phase is bounded: three types of change across roughly 7 files.

The codebase already has everything needed. The `ag-tooltip` Web Component (CSS-only hover, Shadow DOM) is registered and used 100+ times. The `ag-popover` component (click/hover, richer content) is also available and already used on the trust page for SHA-256. The validate.js modal already has a checkbox (`confirmIrreversible`) and the gate logic `updateModalConfirmState()` — but it still requires BOTH checkbox AND "VALIDER" text. The HTML just needs the `confirmText` form-group removed and the JS gate simplified to checkbox-only.

Export buttons in archives.htmx.html use an `export-grid` CSS class (3-col grid, responsive). Adding a description line under each button requires wrapping the current `<button>` in a `<div class="export-btn-wrap">` and appending a `<small class="export-desc">`. The `.export-grid` currently addresses the buttons directly; a small CSS addition is needed to forward the grid layout to the wrapper.

**Primary recommendation:** Work file-by-file in a clear order: (1) public.htmx.html jargon swap, (2) help.htmx.html FAQ rewrite, (3) validate.htmx.html + validate.js confirm pattern, (4) export descriptions across 4 locations, (5) admin tooltips across 5 pages.

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| ag-tooltip (custom element) | in-repo | Hover tooltip on any inline element | Already 100+ usages, CSS-only, no positioning JS needed |
| ag-popover (custom element) | in-repo | Click/hover popover with richer HTML content | Used on trust page; supports `title` + `content` attributes |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `<small class="export-desc">` | HTML | One-line description under export buttons | CLAR-04: always visible, no hover needed |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| ag-tooltip | ag-popover | ag-popover is richer (click trigger, HTML content) but heavier; use ag-tooltip for single-line admin hints, ag-popover where content is already used (trust SHA-256) |
| `<small>` for export desc | title attribute | title is hover-only, invisible on mobile — CLAR-04 explicitly requires always-visible |

## Architecture Patterns

### Files Touched

```
public/
├── public.htmx.html         # CLAR-01: quorum label (2 locations)
├── help.htmx.html           # CLAR-01: security FAQ + general FAQ SHA-256 mentions
├── validate.htmx.html       # CLAR-03: remove confirmText form-group
├── archives.htmx.html       # CLAR-04: export-grid 6 buttons + ZIP
├── audit.htmx.html          # CLAR-04: 2 export buttons + CLAR-02 SHA-256 tooltip
├── postsession.htmx.html    # CLAR-04: PDF export button + CLAR-02 eIDAS tooltip
├── settings.htmx.html       # CLAR-02: quorum + CNIL tooltips
├── trust.htmx.html          # CLAR-04: 2 export buttons (ag-popover already added for hash)
├── operator.htmx.html       # CLAR-02: quorum + procuration tooltips
assets/js/pages/
└── validate.js              # CLAR-03: remove confirmText gate from updateModalConfirmState
assets/css/
└── archives.css (or design-system.css)  # CLAR-04: .export-btn-wrap flex column + .export-desc style
```

### Pattern 1: ag-tooltip on technical terms (CLAR-02)

**What:** Wrap a technical term in `<ag-tooltip text="..." position="top">...</ag-tooltip>`
**When to use:** Single-line explanations on admin-facing labels and headings

```html
<!-- Before -->
<label for="settQuorumThreshold">Seuil de quorum (%)</label>

<!-- After -->
<label for="settQuorumThreshold">
  <ag-tooltip text="Nombre minimum de participants requis pour que le vote soit valide" position="top">
    Seuil de quorum (%)
  </ag-tooltip>
</label>
```

The ag-tooltip wraps its `<slot>` with hover/focus-within triggers — the label text is the trigger target. No additional JS needed.

### Pattern 2: Remove confirmText gate in validate (CLAR-03)

**What:** HTML removes the `<div class="form-group">` containing the `confirmText` input. JS simplifies `updateModalConfirmState` to only check the checkbox.

```html
<!-- validate.htmx.html: DELETE these lines (224-226) -->
<div class="form-group mt-4">
  <label class="form-label" for="confirmText">Tapez <strong>VALIDER</strong> pour confirmer</label>
  <input class="form-input" type="text" id="confirmText" placeholder="VALIDER" autocomplete="off" spellcheck="false">
</div>
```

```javascript
// validate.js: simplified gate
function updateModalConfirmState() {
  if (!btnModalConfirm) return;
  btnModalConfirm.disabled = !(confirmCheckbox && confirmCheckbox.checked);
}
// Remove all confirmText references: variable declaration, event listeners, keydown, closeValidateModal reset
```

### Pattern 3: Export button with description (CLAR-04)

**What:** Wrap each `<button>` in a `<div class="export-btn-wrap">` and add `<small class="export-desc">` below.

```html
<!-- Before -->
<button class="btn btn-secondary" id="btnExportPV">
  <svg ...></svg>
  PV (HTML)
</button>

<!-- After -->
<div class="export-btn-wrap">
  <button class="btn btn-secondary" id="btnExportPV">
    <svg ...></svg>
    PV (HTML)
  </button>
  <small class="export-desc">Procès-verbal complet de la séance (format web imprimable)</small>
</div>
```

```css
/* archives.css — add to EXPORTS MODAL section */
.export-btn-wrap {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}
.export-btn-wrap .btn {
  width: 100%;
}
.export-desc {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  line-height: 1.3;
  padding: 0 var(--space-1);
}
```

The `.export-grid` grid already applies `gap` and column layout to its direct children — switching children from `<button>` to `<div class="export-btn-wrap">` keeps the grid intact.

### Pattern 4: Public page quorum label swap (CLAR-01)

Two locations in public.htmx.html must change "Quorum" to "Seuil de participation":

- **Line 57:** `<span class="quorum-visual-label">Quorum</span>` → `Seuil de participation`
- **Line 158:** `<div class="decision-label">Quorum</div>` → `Seuil de participation`

No JS change needed — these are static labels. The JS file (public.js) reads `quorum_value` / `quorum_detail` element IDs for dynamic content, not the label text.

### Pattern 5: Help FAQ rewrite (CLAR-01)

Targeted replacements in help.htmx.html:

| Current text | Replacement |
|---|---|
| "hash SHA256 est stocké en base" | "empreinte numérique est stockée en base" |
| "chaîne de hachage SHA256 : chaque événement inclut le hash de l'événement précédent" | "chaîne d'empreintes numériques : chaque événement inclut l'empreinte de l'événement précédent" |
| "tokens de vote (un par votant)" | "codes de vote à usage unique (un par votant)" |
| "Le token est consommé (anti-rejeu)" | "Le code est utilisé une seule fois" |
| General FAQ line 250: "Le journal d'audit à chaînage SHA256 est infalsifiable" | "Le journal d'audit est sécurisé et infalsifiable" |

The "security" FAQ tab has `data-required-role="admin,auditor,assessor"` — so voters never see it. However the `general` section (no role guard) contains line 250 which mentions SHA256. That line needs softening.

The `vote` section (no role guard, accessible to all roles including voter) references "tokens" — this should be rewritten.

### Anti-Patterns to Avoid

- **Adding JS for tooltip positioning:** ag-tooltip is CSS-only; don't introduce JS-based tooltip libraries
- **Modifying ag-tooltip component source:** Add tooltips via HTML usage, never patch the component
- **Using `title` attribute for export descriptions:** Invisible on mobile and keyboard-inaccessible
- **Leaving confirmText JS references after removing the HTML input:** Will cause silent `null` errors in `updateModalConfirmState` and `closeValidateModal` — must clean both HTML and JS together

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Tooltip on technical terms | Custom CSS tooltip class | ag-tooltip component | Already shadow-DOM encapsulated, handles hover+focus-within, 100+ existing usages |
| Rich tooltip with title+body | Custom modal | ag-popover component | Already used on trust page, supports `title` and `content` attributes |
| Export button description | Hover tooltip or title attr | `<small class="export-desc">` visible text | CLAR-04 spec explicitly requires always-visible (not hover) |

## Common Pitfalls

### Pitfall 1: confirmText JS references left orphaned
**What goes wrong:** After removing the HTML `<input id="confirmText">`, validate.js still references `document.getElementById('confirmText')` in three places: variable declaration, input event listener, and `closeValidateModal` reset. These silently return `null` but the gate check `confirmText.value.trim()` will throw a TypeError.
**Why it happens:** HTML and JS modified separately in different tasks.
**How to avoid:** Treat validate.htmx.html + validate.js as one atomic change. Remove the `confirmText` variable and all its uses in the same task.
**Warning signs:** Browser console TypeError on modal open/close.

### Pitfall 2: export-grid children type mismatch
**What goes wrong:** `.export-grid` in archives.css applies grid to `> *`. Wrapping buttons in `<div>` changes the grid item from `<button>` to `<div>` — this is fine. But the ZIP button has `grid-column: 1 / -1` on `.exports-zip-btn` (the button itself). After wrapping, `grid-column` must move to `.export-btn-wrap` containing the ZIP button, not the button.
**How to avoid:** Add `grid-column: 1 / -1` to `.export-btn-wrap:has(#btnExportZip)` or add a modifier class `.export-btn-wrap--full` to the ZIP wrapper.

### Pitfall 3: ag-tooltip inside form labels breaks `for` association
**What goes wrong:** Wrapping label text in `<ag-tooltip>` (a custom element) does not break the `<label for="id">` association because the `for` attribute is on the `<label>` element itself, not inside it. But if `<ag-tooltip>` is placed *between* `<label>` and its associated `<input>`, click-to-focus breaks.
**How to avoid:** Keep `<ag-tooltip>` inside the `<label>` wrapping the text — never between label and input. The existing pattern in trust.htmx.html (`<span class="integrity-hash-label">text <ag-popover ...>`) confirms this is safe.

### Pitfall 4: Voter page quorum JS uses element IDs, not text
**What goes wrong:** Someone edits `quorum_value` or `quorum_detail` IDs thinking they're labels — these are JS-populated data containers. Changing IDs would break the JS.
**How to avoid:** Only change the text content of `.quorum-visual-label` and `.decision-label` (static HTML labels), never the IDs used by JS (`quorumValue`, `quorum_value`, `quorumVisualValue`).

### Pitfall 5: Help FAQ role guards on voter-visible sections
**What goes wrong:** The security FAQ tab (`data-required-role="admin,auditor,assessor"`) correctly hides SHA-256 from voters. But the `general` section and `vote` section have NO role guard — all users including voters see them. SHA-256 appears in the general section (line 250) and "token" appears in the vote section (lines 339, 343).
**How to avoid:** Rewrite jargon in the unguarded sections, not just the security-only section.

## Code Examples

### ag-tooltip usage (confirmed from source)

```html
<!-- Source: public/assets/js/components/ag-tooltip.js -->
<ag-tooltip text="Nombre minimum de participants requis pour que le vote soit valide" position="top">
  Seuil de quorum (%)
</ag-tooltip>
```

The component reads `text` and `position` attributes. `position` defaults to `'top'`. Text is HTML-escaped via `_esc()`.

### ag-popover usage (confirmed from trust.htmx.html line 131)

```html
<!-- Source: trust.htmx.html line 131 -->
<span class="integrity-hash-label">Empreinte d'intégrité
  <ag-popover title="Empreinte d'intégrité" content="Hash SHA-256 calculé sur l'ensemble des votes..." position="top"></ag-popover>
</span>
```

When `title` + `content` attributes are used, no slots are needed. The trigger button is auto-rendered by the component.

### Simplified validate.js gate

```javascript
// Source: public/assets/js/pages/validate.js — simplified version of lines 156-161
function updateModalConfirmState() {
  if (!btnModalConfirm) return;
  btnModalConfirm.disabled = !(confirmCheckbox && confirmCheckbox.checked);
}
if (confirmCheckbox) confirmCheckbox.addEventListener('change', updateModalConfirmState);
```

### Export desc CSS

```css
/* Source: to add in public/assets/css/archives.css, EXPORTS MODAL section */
.export-btn-wrap {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}
.export-btn-wrap .btn {
  width: 100%;
}
.export-btn-wrap--full {
  grid-column: 1 / -1;
}
.export-desc {
  font-size: 0.75rem;
  color: var(--color-text-secondary);
  line-height: 1.3;
  padding: 0 var(--space-1);
}
```

## Export Button Description Inventory

### archives.htmx.html modal (6 buttons + ZIP)

| Button ID | Current Label | Description |
|-----------|--------------|-------------|
| btnExportPV | PV (HTML) | Procès-verbal complet de la séance (format web imprimable) |
| btnExportAttendance | Présences (CSV) | Liste des membres présents et leurs procurations |
| btnExportVotes | Votes (CSV) | Détail de chaque bulletin : votant, résolution, choix |
| btnExportMotions | Résolutions (CSV) | Résultats par résolution : décision, majorité, quorum |
| btnExportMembers | Membres (CSV) | Liste des membres et leur poids de vote |
| btnExportAudit | Audit (CSV) | Journal complet des événements pour vérification légale |
| btnExportZip | Archive ZIP | Tous les fichiers précédents réunis en une seule archive |

### audit.htmx.html (2 buttons)

| Button ID | Current Label | Description |
|-----------|--------------|-------------|
| btnExportSelection | Exporter la sélection | Événements filtrés selon la sélection courante (CSV) |
| btnExportAll | Tout exporter | Journal d'audit complet toutes séances confondues (CSV) |

### postsession.htmx.html

| Element | Current Label | Description |
|---------|--------------|-------------|
| btnExportPDF (anchor) | PDF | Procès-verbal de la séance en format PDF signable |

### trust.htmx.html (2 buttons)

| Button ID | Current Label | Description |
|-----------|--------------|-------------|
| btnExportAuditJson | Export JSON | Données d'audit brutes pour vérification technique |
| btnExportTrust | Exporter le rapport | Rapport de contrôle d'intégrité complet (PDF/HTML) |

## Admin Tooltip Inventory (CLAR-02)

| Page | Element | Term | Tooltip text |
|------|---------|------|-------------|
| settings.htmx.html | label `settQuorumThreshold` | Seuil de quorum | Nombre minimum de participants requis pour que le vote soit valide |
| settings.htmx.html | card title "Niveau de conformité CNIL" | CNIL | Commission Nationale de l'Informatique et des Libertés — règles de protection des données personnelles |
| operator.htmx.html | quorum display | Quorum | Nombre minimum de participants requis pour valider le scrutin |
| operator.htmx.html | procuration references | Procuration | Délégation de vote : un membre absent autorise un autre à voter en son nom |
| postsession.htmx.html | card title "Signature électronique (eIDAS)" | eIDAS | Règlement européen sur l'identification électronique — garantit la valeur légale de la signature |
| audit.htmx.html | onboarding tip "haché SHA-256" | SHA-256 | Algorithme de calcul d'empreinte numérique — toute modification des données change l'empreinte |
| trust.htmx.html | Already has ag-popover on SHA-256 | — | Already done, no change needed |

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| "tapez VALIDER" text gate | Checkbox confirmation | This phase | Simpler, more accessible, no keyboard gymnastics |
| Raw "Quorum" label on voter projection | "Seuil de participation" | This phase | Voters understand without training |

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 |
| Config file | phpunit.xml (project root) |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CLAR-01 | "Quorum" absent from public.htmx.html voter-visible sections | manual-only (HTML grep) | `grep -c "Quorum" public/public.htmx.html` — expect 0 in label text | ✅ (grep, not unit test) |
| CLAR-01 | "SHA-256", "token", "hash" absent from voter-visible FAQ sections | manual-only (HTML grep) | `grep -c "SHA-256\|SHA256" public/help.htmx.html` — expect 0 in unguarded sections | ✅ (grep, not unit test) |
| CLAR-02 | ag-tooltip present on quorum, CNIL, eIDAS, SHA-256 in admin pages | manual-only (HTML inspection) | Visual review | ❌ Wave 0 if automated |
| CLAR-03 | confirmText input absent from validate.htmx.html | manual-only (HTML grep) | `grep -c "confirmText" public/validate.htmx.html` — expect 0 | ✅ (grep) |
| CLAR-03 | validate.js btnModalConfirm enabled on checkbox only | manual-only (JS review) | `grep -c "confirmText" public/assets/js/pages/validate.js` — expect 0 | ✅ (grep) |
| CLAR-04 | export-desc present on all 10 export buttons | manual-only (HTML grep) | `grep -c "export-desc" public/archives.htmx.html` — expect 7 | ✅ (grep) |

Phase 4 is pure HTML/CSS/JS — no PHP unit tests apply. Verification is grep-based or visual.

### Sampling Rate

- **Per task commit:** grep checks as listed above
- **Per wave merge:** Visual review in browser of each modified page
- **Phase gate:** All grep checks pass + no browser console errors on validate page

### Wave 0 Gaps

None — no new test infrastructure needed. All verification is file-content grep or browser visual check.

## Sources

### Primary (HIGH confidence)

- `public/assets/js/components/ag-tooltip.js` — component API confirmed (text, position attributes)
- `public/assets/js/components/ag-popover.js` — component API confirmed (title, content, position, trigger attributes)
- `public/assets/js/pages/validate.js` lines 147-196 — full modal gate logic confirmed
- `public/validate.htmx.html` lines 219-226 — "tapez VALIDER" pattern confirmed
- `public/archives.htmx.html` lines 212-242 — 6 export buttons + ZIP confirmed
- `public/audit.htmx.html` lines 59-65 — 2 export buttons confirmed
- `public/postsession.htmx.html` lines 293-330 — eIDAS section + PDF export confirmed
- `public/trust.htmx.html` lines 42-47, 129-133 — 2 export buttons + ag-popover already on hash
- `public/settings.htmx.html` lines 112, 268-271 — quorum + CNIL sections confirmed
- `public/public.htmx.html` lines 57, 158 — two "Quorum" label occurrences confirmed
- `public/help.htmx.html` lines 244-516 — FAQ sections; role guards confirmed per section
- `public/assets/css/archives.css` lines 370-383 — .export-grid CSS confirmed

### Secondary (MEDIUM confidence)

- Direct code inspection of all 7 target files — HIGH confidence on exact line locations

## Metadata

**Confidence breakdown:**
- File locations and exact line numbers: HIGH — all confirmed by direct file read
- ag-tooltip/ag-popover API: HIGH — confirmed from source files
- JS validate.js gate logic: HIGH — full function read
- CSS export-grid structure: HIGH — confirmed from archives.css
- Export button descriptions (prose text): MEDIUM — logical derivation from button labels and codebase patterns; exact wording is Claude's discretion

**Research date:** 2026-04-21
**Valid until:** 2026-05-21 (stable HTML/CSS/JS, no external dependencies)
