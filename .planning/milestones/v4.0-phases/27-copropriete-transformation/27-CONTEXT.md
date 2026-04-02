# Phase 27: Copropriété Transformation - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Remove all copropriété-specific vocabulary from user-facing UI and rename to generic AG terminology. Remove dead code (lot field, clés de répartition stub). Preserve all weighted voting logic (voting_power, BallotsService, import aliases). Write PHPUnit regression test BEFORE changes, verify AFTER.

</domain>

<decisions>
## Implementation Decisions

### Vocabulary Renames
- "copropriété" / "copropriétaires" → "organisation" / "membres"
- "tantièmes" (display labels) → "poids de vote" (keep `value="tantiemes"` in form selects for API compat)
- "Annuaire des copropriétaires" → "Annuaire des membres"
- "Pondération (tantièmes)" → "Pondération des voix"
- "Clés de répartition" section → remove entirely (stub with no backend)
- "Définit la pondération des voix par lot" → "Définit la pondération des voix par membre"
- "Par tantièmes" → "Par poids de vote"
- "Total tantièmes" → "Total des poids"

### Dead Code Removal
- wizard.js: lot field display, CSV lot parsing, manual add lot prompt, member.lot object field
- wizard.css: `.member-lot` class
- settings.js: openKeyModal function, "Lots" option
- settings.htmx.html: "Clés de répartition" section (152-160)
- admin.htmx.html: "Clés de répartition" card (629-640)

### Preserve (DO NOT TOUCH)
- voting_power column in DB — generic, used in 14+ backend files
- BallotsService weight calculations
- ImportService.php tantième/tantièmes CSV aliases (backward compat for existing imports)
- QuorumEngine, VoteEngine — already use neutral terminology
- `value="tantiemes"` in select options — API contract, only change display text

### Scope
- 21 files total (UI labels, JS, CSS, HTML, seeds, docs, wireframes)
- PHPUnit weighted-vote regression test required before AND after
- Documentation files (docs/) updated for consistency
- Seed data comments updated
- Wireframe files updated

### Claude's Discretion
- Exact replacement wording for documentation files
- Whether to update seed SQL comments or leave them as historical
- Wireframe file updates (ag_vote_wireframe.html, docs/wireframe/)

</decisions>

<canonical_refs>
## Canonical References

### Files to modify (complete inventory from codebase exploration)
- `public/assets/js/pages/wizard.js` — lot field (lines 301, 342, 390, 392)
- `public/assets/css/wizard.css` — .member-lot class (line 413)
- `public/assets/js/pages/settings.js` — tantiemes options + openKeyModal (lines 419-424)
- `public/settings.htmx.html` — Clés de répartition section (lines 104, 152-158)
- `public/admin.htmx.html` — Clés de répartition card (lines 629-634)
- `public/help.htmx.html` — copropriété + tantièmes references (lines 194, 299)
- `public/index.html` — "Pondération (tantièmes)" feature (line 272, 291)
- `public/assets/js/core/shell.js` — sidebar subtitle (line 683)
- `app/Repository/AggregateReportRepository.php` — comment (line 99)
- `app/Services/ImportService.php` — PRESERVE aliases (line 237)

### Seed data
- `database/seeds/01_minimal.sql` — policy name "Quorum 50% (pondéré)" (line 25)
- `database/seeds/03_demo.sql` — comments (lines 7, 103, 238)
- `database/seeds/06_test_weighted.sql` — header comment (line 1)
- `database/seeds/08_demo_az.sql` — comments (lines 104, 223)

### Documentation
- `docs/GUIDE_FONCTIONNEL.md`, `docs/FAQ.md`, `docs/GUIDE_TEST_LOCAL.md`, `docs/RECETTE_DEMO.md`, `docs/UTILISATION_LIVE.md`, `docs/dev/cahier_des_charges.md`, `docs/directive-projet.md`

</canonical_refs>

<code_context>
## Existing Code Insights

### Key Safety Check
- `voting_power` is the generic column name — already neutral. No rename needed.
- `BallotsService`, `QuorumEngine`, `VoteEngine` use `voting_power` — no copro terms.
- Only surface-level labels reference copropriété terminology.

### Integration Points
- PHPUnit test: write in tests/Unit/ following existing test patterns
- wizard.js lot removal: 4 specific locations (display, CSV parse, prompt, push)
- settings.js: openKeyModal function + its trigger in settings.htmx.html

</code_context>

<specifics>
## Specific Ideas

- "Bien vérifier le code puis apporter les changements correspondants, virer le code mort"
- PHPUnit test is the safety net — write it FIRST, run it LAST

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 27-copropriete-transformation*
*Context gathered: 2026-03-18*
