# Phase 28: Wizard & Session Hub UX Overhaul - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete visual and functional overhaul of the session creation wizard and session hub. Named stepper with labels, step 4 review card with "Modifier" links, motion templates, progressive disclosure for voting power, hub blocked-reason checklist, quorum progress bar, convocation send flow. Refonte CSS visuelle complète Notion-like (minimaliste, typographie forte, aéré). Micro-interactions and transitions.

</domain>

<decisions>
## Implementation Decisions

### Wizard Stepper
- Named step labels replacing numbers: "Informations → Membres → Résolutions → Révision"
- Checkmark icon on completed steps (existing behavior, keep)
- Stepper is horizontal, persistent at top

### Step 4 Review Card
- Full recap of all 3 previous steps in sections
- Each section has a "Modifier" link that navigates back to that step
- Sections: Informations (title, date, time, lieu), Membres (count + list preview), Résolutions (count + titles), Documents (count)
- Prominent "Créer la séance →" primary button at bottom
- Warnings if critical items missing: "⚠ Aucun membre ajouté — les votes ne pourront pas être attribués"

### Steps 2 & 3 Optional
- Can create a session with just title + date (step 1)
- Members and résolutions can be added later from the hub
- Step 4 shows warnings but does NOT block creation

### Motion Templates (Step 3)
- 3 quick-select buttons above the add resolution form:
  - "Approbation des comptes" — pre-fills title + description about financial approval
  - "Élection au conseil" — pre-fills title + description about board election
  - "Modification du règlement" — pre-fills title + description about rules amendment
- Click pre-fills fields, user can modify before adding
- Templates are hardcoded JS objects (not DB-stored)

### Progressive Disclosure (Step 2)
- Default: each member = 1 voix, voting power column hidden
- Toggle switch: "Activer la pondération des voix" reveals the weight column
- When hidden, all members default to voting_power = 1

### Autosave
- Save to localStorage on field blur AND step change (not interval)
- Restore silently on page load — no confirmation modal, no toast
- Clear draft after successful creation

### Hub Checklist Enhancements
- Blocked items show reason: "Disponible après ajout des résolutions" (consistent with Phase 26 tooltip pattern)
- Each checklist item shows completion status + blocked reason if applicable
- Existing progress bar kept and enhanced

### Quorum Progress Bar
- Horizontal bar with threshold tick mark at the correct percentage position
- Fill: amber before threshold, green after threshold
- Text: "Présents: 28/42 — Seuil: 60% = 25 membres"
- Animate fill incrementally when a member is marked present
- Use existing ag-quorum-bar component if it fits, or enhance it

### Hub Document Badges
- Already wired from Phase 25 — "📎 N documents joints" / "Aucun document" per motion
- Phase 28 ensures these badges integrate cleanly with the enhanced hub layout

### Convocations
- "Envoyer les convocations" button → ag-confirm dialog ("Envoyer à X membres ?") → API call → toast success/error
- Immediate send, no preview modal

### Visual Overhaul (Refonte CSS)
- **Inspiration: Notion-like** — minimaliste, typographie forte (Bricolage Grotesque + Fraunces), beaucoup d'espace, peu de couleurs, focus contenu
- Complete CSS rewrite for wizard.css and hub.css
- Spacing: generous padding/margin, airy layout
- Cards: subtle shadows, clean borders
- Buttons: primary clearly visible, secondary restrained
- Forms: large input fields, clear labels, proper spacing between fields
- Micro-interactions: fade transitions (150ms) between wizard steps, progress bar animation, button hover feedback
- PC-first (1024px+), adaptive for smaller screens

### Error Handling
- **Field validation:** Inline error message in red under the field, appears on blur (not on submit)
- **API errors:** Persistent toast (manual dismiss). Form data preserved — user can retry.
- **Next button:** NOT disabled — clicking it with invalid fields triggers inline validation on all required fields

### Claude's Discretion
- Exact CSS values (spacing, colors, shadows, border-radius)
- ag-quorum-bar enhancement details
- Review card section HTML structure
- Template content (exact French text for description fields)
- Error message wording for each validation case

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Wizard (existing code to enhance)
- `public/assets/js/pages/wizard.js` — showStep(), updateStepper(), saveDraft(), restoreDraft(), validateStep(), buildPayload()
- `public/wizard.htmx.html` — HTML structure with 4 steps, stepper, FilePond (Phase 25)
- `public/assets/css/wizard.css` — Current wizard CSS (to be rewritten Notion-like)

### Hub (existing code to enhance)
- `public/assets/js/pages/hub.js` — renderChecklist(), hubChecklist, CHECKLIST_ITEMS, progress bar
- `public/hub.htmx.html` — Hub HTML with stepper and checklist sections
- `public/assets/css/hub.css` — Current hub CSS

### Components available
- `public/assets/js/components/ag-quorum-bar.js` — Existing quorum bar component
- `public/assets/js/components/ag-empty-state.js` — Phase 26, for empty containers
- `public/assets/js/components/ag-stepper.js` — Existing stepper component
- `public/assets/js/components/ag-confirm.js` — For convocation send confirmation
- `public/assets/js/components/ag-toast.js` — For success/error feedback

### Design system
- `public/assets/css/design-system.css` — CSS tokens, component styles

### Research
- `.planning/research/FEATURES.md` — Pattern 1 (Staged Wizard), Pattern 10 (Named-Step Wizard), Pattern 4 (Empty States), Pattern 9 (Quorum)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `showStep(n)` + `updateStepper()` — Step navigation already works, needs label enhancement
- `saveDraft()` / `restoreDraft()` — localStorage draft already works, needs blur triggers
- `validateStep(n)` — Step 1 validation exists, needs inline error display
- `CHECKLIST_ITEMS` in hub.js — Checklist structure exists, needs blocked-reason display
- `ag-quorum-bar` — May already have threshold support
- `ag-stepper` — Horizontal stepper component exists
- `ag-confirm` — Confirmation dialog for convocations

### Established Patterns
- IIFE + var for page scripts
- One CSS per page (wizard.css, hub.css)
- Web Components for shared UI
- localStorage for draft persistence

### Integration Points
- wizard.js buildPayload() → POST /api/v1/meetings (existing, working)
- hub.js loadData() → GET /api/v1/wizard_status (existing, working)
- FilePond upload in step 3 (Phase 25, already wired)
- Document badges in hub (Phase 25, already wired)
- Empty states (Phase 26, ag-empty-state available)
- Disabled button tooltips (Phase 26, already wired)

</code_context>

<specifics>
## Specific Ideas

- "Refonte visuelle complète" — not just functional improvements, full CSS rewrite
- "Notion-like" — minimaliste, typographie forte, aéré, peu de couleurs
- "Le design guide naturellement" (from Phase 26) — the wizard should be so clear that no help is needed
- Steps 2 + 3 optional — can create with just title + date
- Voting power hidden by default — toggle "Activer la pondération" reveals it

</specifics>

<deferred>
## Deferred Ideas

- Motion templates stored per-tenant in DB (v5+ — hardcoded for v4.0)
- Email template preview before convocation send
- Top 1% visual overhaul for ALL other pages (Phase 29)

</deferred>

---

*Phase: 28-wizard-session-hub-ux-overhaul*
*Context gathered: 2026-03-18*
