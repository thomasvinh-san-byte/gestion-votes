# Phase 61: Dead Code Cleanup - Research

**Researched:** 2026-03-31
**Domain:** PHP codebase hygiene — controller stubs, vocabulary cleanup, dead file audit
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
None — all implementation choices are at Claude's discretion.

### Claude's Discretion
All implementation choices are at Claude's discretion — cleanup phase. Key constraints from codebase scan:
- No controller stubs found in production code (CLEAN-01 may be already satisfied — verify and document)
- copropriete/syndic vocabulary remains only in `SETUP.md` (line 158: "Changement de syndic") and `docs/directive-projet.md` — replace with AG/assembly terminology
- No copropriete/syndic in demo seed files or production PHP code
- Dead file audit: identify unused files, delete or document retention reason
- All changes must pass existing test suite with zero regressions

### Deferred Ideas (OUT OF SCOPE)
None.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CLEAN-01 | Les stubs restants dans les controllers sont supprimés ou implémentés | Audit complete: zero stubs found in app/Controller/ — CLEAN-01 is already satisfied; task is to document this finding in verification |
| CLEAN-02 | Les seeds de démo ne contiennent aucune référence copropriété ou syndic | 3 remaining occurrences in docs (SETUP.md line 158, docs/directive-projet.md lines 58 and 153) — all seed SQL and PHP files are already clean |
| CLEAN-03 | Les fichiers dead code identifiés sont nettoyés ou documentés | Key finding: phpunit.xml still references non-existent `app/WebSocket` directory (Phase 58 renamed it to `app/SSE`) — needs correction |
</phase_requirements>

---

## Summary

Phase 61 is a codebase hygiene pass covering three cleanup requirements. The research audit reveals that most of the heavy lifting was done in earlier phases — the codebase is in substantially better shape than the requirements might suggest.

For CLEAN-01 (controller stubs): A comprehensive grep across all 41 controller files for patterns including `not_implemented`, `TODO`, `FIXME`, `placeholder`, and stub return arrays found zero matches. The requirement is already satisfied. The task for the planner is to document this via a verification grep that demonstrates zero stubs remain.

For CLEAN-02 (copropriete/syndic vocabulary): Seed SQL files (`database/seeds/`), PHP seed scripts (`scripts/seed_demo_evote.php`, `public/api/v1/dev_seed_*.php`), and all production PHP code are already clean. Three occurrences remain in documentation files: `SETUP.md` line 158 ("Changement de syndic — en attente") and `docs/directive-projet.md` lines 58 and 153 ("Élection syndic, travaux importants" in a legal majority regime table, and "conseil syndical" as an enum type in the data model section). These two docs-only fixes are small targeted edits.

For CLEAN-03 (dead file audit): The single most actionable finding is that `phpunit.xml` still contains `<directory suffix=".php">app/WebSocket</directory>` in its `<source>` coverage block — a stale reference from before Phase 58 renamed `app/WebSocket` to `app/SSE`. This is a dead path in the coverage source declaration. It should be updated to `app/SSE`. The `coverage.xml` file is a generated artifact (gitignored) and does not need manual editing. Beyond this, no orphaned `.php`, `.html`, `.js`, or `.css` files were found that lack a clear purpose.

**Primary recommendation:** Execute as three focused tasks — (1) document CLEAN-01 as already satisfied with a grep verification, (2) make two targeted text replacements in SETUP.md and docs/directive-projet.md for CLEAN-02, (3) fix the phpunit.xml `app/WebSocket` source path for CLEAN-03.

---

## Standard Stack

### Core (no new dependencies needed)
| Tool | Version | Purpose | Why Standard |
|------|---------|---------|--------------|
| PHP grep / sed | built-in | Text search and replacement in files | Native tooling, no deps |
| PHPUnit | 10.5 | Test runner to verify zero regressions | Already installed |
| PHP-CS-Fixer | project-installed | Code style enforcement after any PHP edits | Project standard per CONTEXT.md |

### No new packages required
This phase is pure file editing and deletion. Zero new composer or npm packages needed.

---

## Architecture Patterns

### Pattern 1: Document-then-delete for dead file removal
**What:** When removing a file, first verify no live references exist, then delete.
**When to use:** Any file deletion under CLEAN-03.
**Protocol:**
```bash
# 1. Search for any references to the file
grep -rn "filename" /path/to/project --include="*.php" --include="*.html" --include="*.js"
# 2. If zero references: delete
# 3. If references found: assess whether they are also dead, document decision
```

### Pattern 2: Grep-then-document for already-satisfied requirements
**What:** When a requirement is already satisfied, prove it with a reproducible grep.
**When to use:** CLEAN-01 — no stubs found.
**Example:**
```bash
# Verification command for CLEAN-01
grep -rn "not.implemented\|TODO\|FIXME\|stub" app/Controller/ --include="*.php"
# Expected: no output
```

### Pattern 3: Targeted text replacement for vocabulary cleanup
**What:** Replace specific copropriete/syndic vocabulary with AG/assembly equivalents.
**When to use:** CLEAN-02 — the three occurrences in SETUP.md and docs/directive-projet.md.
**Rules:** Replace the word, preserve the surrounding context and meaning. Do not add new vocabulary that doesn't belong.

### Anti-Patterns to Avoid
- **Mass search-replace without context:** The word "lot" appears in SQL IN-clause code (`$placeholders`, batch sizes) — do not replace those. Only the three confirmed copropriete/syndic occurrences need editing.
- **Deleting Command files without investigation:** The four files in `app/Command/` have no unit tests but are legitimate CLI tools (`EmailProcessQueueCommand`, `MonitoringCheckCommand`, `RateLimitCleanupCommand`, `RedisHealthCommand`). They are intentionally deferred — document with code comment, do not delete.
- **Editing coverage.xml:** This is a generated artifact (gitignored). Only edit `phpunit.xml` source.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Vocab search | Custom script | Standard `grep -rn` | Already works, reproducible |
| File deletion safety check | Custom indexer | `grep -rn` reference scan | No tooling gap exists |
| Style enforcement | Manual fixes | `vendor/bin/php-cs-fixer fix` | Project standard already configured |

---

## Common Pitfalls

### Pitfall 1: False positive "lot" matches
**What goes wrong:** Grepping for copropriete-adjacent vocabulary like `lot` triggers false positives in SQL batch code (`--batch=N`, `$placeholders`, `par lot`).
**Why it happens:** The word "lot" is French for "batch" and appears in the email queue script `scripts/process_email_queue.php` lines 13 and 36.
**How to avoid:** Search specifically for `syndic` and `copropri` — these have zero false positives. The confirmed locations are SETUP.md line 158 and docs/directive-projet.md lines 58 and 153 only.
**Warning signs:** If grep returns matches in `process_email_queue.php`, these are legitimate batch-size references, not vocabulary violations.

### Pitfall 2: phpunit.xml WebSocket path update
**What goes wrong:** Fixing `app/WebSocket` to `app/SSE` in phpunit.xml without verifying the SSE directory is correctly present.
**Why it happens:** The rename was done in Phase 58. If the path is wrong, coverage will silently drop.
**How to avoid:** Confirm `app/SSE/EventBroadcaster.php` exists before editing (confirmed in research: it does, with namespace `AgVote\SSE`).
**Warning signs:** If `vendor/bin/phpunit --coverage-text` shows zero coverage for SSE files after the fix, the path is wrong.

### Pitfall 3: conseil syndical in directive-projet.md line 153
**What goes wrong:** Over-editing by removing `conseil syndical` from a data-model enum definition that may reflect an actual database enum value.
**Why it happens:** The directive-projet.md is a historical requirements document. The production database `meeting_type` enum may or may not still contain `conseil_syndical` as a valid value.
**How to avoid:** Check the current database schema before editing. If the enum value `conseil_syndical` was already removed from the DB during Phase 27, then the directive-projet.md line is a stale doc reference and can be updated to `AG extraordinaire` or equivalent. If the DB still has it, the doc note is accurate and should stay (or the DB enum needs updating, which is out of scope for this phase).
**Warning signs:** If touching enum values requires a DB migration, defer to out-of-scope. Only edit documentation.

### Pitfall 4: directive-projet.md line 58 — legal context
**What goes wrong:** Replacing "Élection syndic, travaux importants" in a legal majority regime table with terminology that loses legal precision.
**Why it happens:** This line describes what the `art. 25` majority rule is used for in French copropriété law — the context is explicitly about the legal regime this application was originally designed around.
**How to avoid:** The "cas d'usage" column should be updated to examples that apply to associations and collectivités (e.g., "Élection du président, travaux importants"). The legal article numbers are fine to keep — they apply to multiple French legal regimes.

---

## Code Examples

### Verification grep for CLEAN-01 (controller stubs)
```bash
# Source: codebase audit 2026-03-31
# Run this to confirm zero stubs remain
grep -rn "not.implemented\|status.*stub\|TODO\s\|FIXME\s\|return.*\[.*status.*=>.*not" app/Controller/ --include="*.php"
# Expected output: (empty)
```

### Fix phpunit.xml source path (CLEAN-03)
```xml
<!-- Before -->
<directory suffix=".php">app/WebSocket</directory>

<!-- After -->
<directory suffix=".php">app/SSE</directory>
```

### Vocabulary replacements (CLEAN-02)
```
SETUP.md line 158:
  Before: "  - Changement de syndic — **en attente**"
  After:  "  - Renouvellement du bureau — **en attente**"

docs/directive-projet.md line 58:
  Before: "| **Majorité absolue (art. 25)** | >50% des voix de tous les membres | Élection syndic, travaux importants |"
  After:  "| **Majorité absolue (art. 25)** | >50% des voix de tous les membres | Élection du président, travaux importants |"

docs/directive-projet.md line 153:
  Before: "├── id, titre, type (enum: AG ordinaire, AG extra, conseil syndical)"
  After:  "├── id, titre, type (enum: AG ordinaire, AG extraordinaire, conseil d'administration)"
  NOTE: Verify actual DB enum before editing — if conseil_syndical was already removed in Phase 27, this is documentation drift only.
```

---

## Confirmed Audit Results

### CLEAN-01: Controller Stubs — ALREADY SATISFIED
| Check | Result |
|-------|--------|
| grep `not.implemented` in app/Controller/ | 0 matches |
| grep `TODO` / `FIXME` in app/Controller/ | 0 matches |
| grep `stub` / `placeholder` in app/Controller/ | 0 matches (only SQL placeholder helper in AbstractRepository) |
| grep stub return patterns | 0 matches |
| Total controller methods audited | 41 files, all method bodies are real implementations |

**Action required:** Verify command + document result. No deletions or implementations needed.

### CLEAN-02: Copropriete/Syndic Vocabulary
| Location | Line | Current Text | Status |
|----------|------|--------------|--------|
| `SETUP.md` | 158 | "Changement de syndic — en attente" | NEEDS FIX (documentation) |
| `docs/directive-projet.md` | 58 | "Élection syndic, travaux importants" | NEEDS FIX (documentation) |
| `docs/directive-projet.md` | 153 | "conseil syndical" as enum type | NEEDS INVESTIGATION then fix |
| `database/seeds/*.sql` | — | — | CLEAN (no matches) |
| `scripts/seed_demo_evote.php` | — | — | CLEAN (no matches) |
| `public/api/v1/dev_seed_*.php` | — | — | CLEAN (no matches) |
| All production PHP (`app/`) | — | — | CLEAN (no matches) |
| `scripts/process_email_queue.php` | 13,36 | "par lot" / "batch" | NOT A VIOLATION (French "lot" = batch) |

### CLEAN-03: Dead Files
| File | Issue | Action |
|------|-------|--------|
| `phpunit.xml` | References `app/WebSocket` (renamed to `app/SSE` in Phase 58) | Fix: update to `app/SSE` |
| `app/Command/*.php` (4 files) | No unit tests, not called from main app | Retain: CLI tools; add code comment documenting intentional status |
| `coverage.xml` | Stale references to old path | No action: generated artifact, gitignored |
| `coverage-report/` | Stale HTML coverage | No action: generated artifact, gitignored |
| JS components in `public/assets/js/components/` | All imported via `index.js` | No dead components found |
| `public/assets/js/pages/*.js` | All referenced from corresponding `.htmx.html` files | No dead JS pages found |

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `app/WebSocket/EventBroadcaster.php` | `app/SSE/EventBroadcaster.php` | Phase 58 | phpunit.xml source still points to old path — needs update |
| Controller stub methods | Full implementations | Phase 22-24 and subsequent | CLEAN-01 already satisfied |
| copropriete vocabulary in seeds | AG/assembly vocabulary | Phase 27 | Seeds fully clean; only docs remain |

---

## Open Questions

1. **docs/directive-projet.md line 153 — "conseil syndical" enum type**
   - What we know: The directive-projet.md was written pre-Phase 27 and references an enum value `conseil syndical` for meeting types
   - What's unclear: Whether the production DB schema still has this enum value (Phase 27 may have removed it from the schema)
   - Recommendation: Run `grep -r "conseil_syndical\|conseil syndical" database/schema-master.sql database/migrations/` to check. If absent from DB schema: update doc text. If present: this phase should update the doc reference AND note the DB enum as tech debt (not scope for this phase).

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | `phpunit.xml` |
| Quick run command | `vendor/bin/phpunit --testsuite Unit --no-coverage` |
| Full suite command | `vendor/bin/phpunit --no-coverage` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CLEAN-01 | Zero controller stubs in production code | smoke grep | `grep -rn "not.implemented\|TODO\|FIXME" app/Controller/ --include="*.php"` (expect empty output) | N/A — grep |
| CLEAN-02 | No copropriete/syndic in doc files | smoke grep | `grep -rn "syndic\|copropri" SETUP.md docs/directive-projet.md` (expect empty after fix) | N/A — grep |
| CLEAN-03 | phpunit.xml references valid app/SSE path | unit test run | `vendor/bin/phpunit --testsuite Unit --no-coverage` (must stay green after phpunit.xml edit) | ✅ existing suite |

### Sampling Rate
- **Per task commit:** `vendor/bin/phpunit --testsuite Unit --no-coverage`
- **Per wave merge:** `vendor/bin/phpunit --no-coverage`
- **Phase gate:** Full suite green (currently 2267 tests, 0 failures) before `/gsd:verify-work`

### Wave 0 Gaps
None — existing test infrastructure covers all phase requirements. No new test files needed. CLEAN-01 and CLEAN-02 are verified by grep commands, not by new unit tests.

---

## Sources

### Primary (HIGH confidence)
- Direct codebase grep audit — `app/Controller/` (41 files, 0 stubs found)
- Direct file read — `phpunit.xml` (line 29: `app/WebSocket` reference confirmed)
- Direct grep audit — `database/seeds/`, `scripts/seed_demo_evote.php`, `public/api/v1/dev_seed_*.php` (all clean)
- Direct grep audit — `SETUP.md` line 158, `docs/directive-projet.md` lines 58 and 153 (3 vocabulary violations confirmed)
- PHPUnit run — 2267 tests pass, 0 failures (baseline confirmed)

### Secondary (MEDIUM confidence)
- CONTEXT.md pre-scan findings — aligned with and confirmed by direct audit

---

## Metadata

**Confidence breakdown:**
- CLEAN-01 (controller stubs): HIGH — exhaustive grep across all 41 controller files, zero matches
- CLEAN-02 (vocabulary): HIGH — exact line numbers confirmed by direct grep with full file path
- CLEAN-03 (dead files): HIGH — phpunit.xml stale path confirmed; no other dead files found
- Test baseline: HIGH — suite runs in ~1 second, 2267/2267 pass

**Research date:** 2026-03-31
**Valid until:** 2026-04-30 (stable codebase, slow-moving phase)
