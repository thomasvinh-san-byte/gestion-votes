# Phase 61: Dead Code Cleanup - Context

**Gathered:** 2026-03-31
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase removes controller stubs, purges copropriete/syndic vocabulary from documentation and demo data, and audits/documents dead files. Scope is limited to cleanup — no new functionality.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — cleanup phase. Key constraints from codebase scan:
- No controller stubs found in production code (CLEAN-01 may be already satisfied — verify and document)
- copropriete/syndic vocabulary remains only in `SETUP.md` (line 158: "Changement de syndic") and `docs/directive-projet.md` — replace with AG/assembly terminology
- No copropriete/syndic in demo seed files or production PHP code
- Dead file audit: identify unused files, delete or document retention reason
- All changes must pass existing test suite with zero regressions

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- Phase 27 (copropriete-transformation) already cleaned most vocabulary from production code
- `scripts/seed_demo_evote.php` and `public/api/v1/dev_seed_*.php` — seed files (already clean)

### Established Patterns
- PHP-CS-Fixer for code style enforcement
- PHPUnit test suite must remain green

### Integration Points
- `SETUP.md` — project setup documentation
- `docs/directive-projet.md` — project directive document
- Controller files in `app/Controller/` — verify no stubs remain

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
