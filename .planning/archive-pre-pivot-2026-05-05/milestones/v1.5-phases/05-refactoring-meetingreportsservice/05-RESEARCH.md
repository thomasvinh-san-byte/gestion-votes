# Phase 5: Refactoring MeetingReportsService - Research

**Researched:** 2026-04-10
**Domain:** PHP service refactoring — HTML/PDF report generation
**Confidence:** HIGH

## Summary

MeetingReportsService.php is 731 LOC and must be split into MeetingReportsService (<300 LOC) + ReportGenerator (<300 LOC). The service has 3 public report-building methods (buildReportHtml, buildPdfHtml, buildGeneratedReportHtml) plus buildPdfBytes which orchestrates PDF generation. The bulk of the LOC is in HTML template assembly — two large methods (buildReportHtml at 138 LOC and buildPdfHtml at 152 LOC) plus private helpers that build HTML table rows for motions, attendance, proxies, tokens, and vote details.

The natural extraction boundary is clear: all HTML generation logic (template assembly + section builders + static label helpers) moves to ReportGenerator, while MeetingReportsService retains data fetching, caching/snapshot logic, PDF rendering via DOMPDF, and orchestration. This follows the same pattern as Phase 4 (ExportService -> ValueTranslator): extract the "rendering" concern, keep the "orchestration + I/O" concern.

**Primary recommendation:** Extract ReportGenerator with all HTML building methods + static helpers. MeetingReportsService becomes a thin orchestrator: fetch data, call ReportGenerator for HTML, handle DOMPDF + snapshot persistence.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REFAC-07 | MeetingReportsService <300 LOC after extraction of ReportGenerator | Method inventory shows 731 LOC -> extracting ~430 LOC of HTML generation leaves ~300 LOC for orchestration |
| REFAC-08 | ReportGenerator is a final class with DI nullable <300 LOC | HTML builders + static helpers total ~400 LOC; some can be condensed or split across methods to fit |
</phase_requirements>

## Method Inventory (Current State)

### Public Methods (399 LOC total)
| Method | LOC | Lines | Role |
|--------|-----|-------|------|
| `__construct` | 22 | 40-61 | 9 nullable repo params, RepositoryFactory fallback |
| `buildReportHtml` | 138 | 71-208 | Full HTML report (cached snapshot or fresh assembly) |
| `buildPdfHtml` | 152 | 224-375 | PDF-oriented HTML (DOMPDF-compatible, attendance+motions+signatures) |
| `buildPdfBytes` | 43 | 386-428 | Orchestrates data fetch + buildPdfHtml + DOMPDF render + snapshot upsert |
| `buildGeneratedReportHtml` | 44 | 436-479 | Simpler HTML report from evote_results JSON |

### Private Helpers (173 LOC total)
| Method | LOC | Lines | Used By |
|--------|-----|-------|---------|
| `buildMotionRows` | 93 | 486-578 | buildReportHtml |
| `buildAttendanceSection` | 20 | 584-603 | buildReportHtml |
| `buildProxiesSection` | 8 | 609-616 | buildReportHtml |
| `buildTokensSection` | 8 | 622-629 | buildReportHtml |
| `buildVoteDetailsSection` | 44 | 634-677 | buildReportHtml |

### Static Label Helpers (43 LOC total)
| Method | LOC | Lines | Used By |
|--------|-----|-------|---------|
| `h` | 3 | 683-685 | All HTML builders |
| `decisionLabel` | 7 | 687-693 | buildMotionRows |
| `fmtNum` | 6 | 695-700 | buildMotionRows, buildAttendanceSection |
| `modeLabel` | 6 | 702-707 | buildAttendanceSection |
| `choiceLabel` | 6 | 709-714 | buildVoteDetailsSection |
| `policyLabel` | 15 | 716-730 | buildMotionRows |

## Architecture Patterns

### Extraction Strategy

**What moves to ReportGenerator (HTML generation):**
- `buildReportHtml` template assembly (the HTML heredoc at lines 125-207) — but NOT the data fetching/cache logic
- `buildPdfHtml` (entire method — pure HTML assembly from pre-fetched data)
- `buildGeneratedReportHtml` template assembly (the ob_start/echo block) — but NOT the data fetching/hash storage
- All 5 private helpers: buildMotionRows, buildAttendanceSection, buildProxiesSection, buildTokensSection, buildVoteDetailsSection
- All 6 static label helpers: h, decisionLabel, fmtNum, modeLabel, choiceLabel, policyLabel

**What stays on MeetingReportsService (orchestration + I/O):**
- Constructor with 9 repository dependencies
- Snapshot cache check + short-circuit (buildReportHtml cache logic)
- Data fetching from repositories (motions, attendance, proxies, tokens, meeting, orgName)
- DOMPDF invocation + PDF byte generation (buildPdfBytes)
- Snapshot persistence (meetingReportRepo->upsertFull, upsertHash)
- Hash computation
- Filename generation
- Delegation to ReportGenerator for HTML assembly

### ReportGenerator Design

```
final class ReportGenerator {
    // No constructor dependencies needed — stateless HTML builder
    // All data arrives via method parameters
    
    public function assembleReportHtml(meeting, motions, attendance, proxies, tokens, showVoters): string
    public function buildPdfHtml(meeting, attendances, motions, proxies, orgName, isPreview): string  
    public function assembleGeneratedReportHtml(meetingId, meeting, motions): string
    
    // Private helpers (moved from MeetingReportsService)
    private function buildMotionRows(motions, tenantId, policyRepo): string
    private function buildAttendanceSection(attendance): array
    private function buildProxiesSection(proxies): array
    private function buildTokensSection(tokens): array
    private function buildVoteDetailsSection(motions, tenantId, showVoters, ballotRepo): string
    
    // Static helpers
    private static function h(s): string
    private static function decisionLabel(dec): string
    private static function fmtNum(n): string
    private static function modeLabel(mode): string
    private static function choiceLabel(choice): string
    private static function policyLabel(votePolicy, quorumPolicy): string
}
```

### Critical Dependencies in Private Helpers

Two private helpers have repository dependencies that must be handled:
1. **buildMotionRows** (line 493-494): calls `$this->policyRepo->findVotePolicy()` and `$this->policyRepo->findQuorumPolicy()`. Also instantiates `new OfficialResultsService()` and `new VoteEngine()`.
2. **buildVoteDetailsSection** (line 645): calls `$this->ballotRepo->listDetailedForMotion()`.

**Options for handling:**
- **Option A (recommended):** Pass repository instances as method parameters to ReportGenerator. ReportGenerator gets `?PolicyRepository` and `?BallotRepository` as constructor params with nullable DI.
- **Option B:** Pre-fetch all data in MeetingReportsService and pass pure data to ReportGenerator. This is cleaner but requires significant restructuring of buildMotionRows which does per-motion policy lookups.

**Recommendation: Option A** — pass policyRepo and ballotRepo to ReportGenerator constructor. This keeps the extraction mechanical (move methods as-is, change `$this->policyRepo` to `$this->policyRepo`). ReportGenerator constructor: `__construct(?PolicyRepository $policyRepo = null, ?BallotRepository $ballotRepo = null)`.

### LOC Budget Analysis

**ReportGenerator estimated LOC:**
- assembleReportHtml (HTML template heredoc): ~85 LOC (lines 103-207 minus data fetch)
- buildPdfHtml: ~152 LOC (moved as-is)
- assembleGeneratedReportHtml (template part): ~25 LOC
- buildMotionRows: ~93 LOC
- buildAttendanceSection: ~20 LOC
- buildProxiesSection: ~8 LOC
- buildTokensSection: ~8 LOC
- buildVoteDetailsSection: ~44 LOC
- Static helpers: ~43 LOC
- Constructor + boilerplate: ~15 LOC
- **Total estimate: ~493 LOC — EXCEEDS 300 LOC ceiling**

**Problem:** The raw extraction exceeds 300 LOC. Two solutions:

1. **buildPdfHtml stays on MeetingReportsService** — it is already a self-contained method that receives all data as parameters. If it stays, ReportGenerator drops to ~341 LOC (still over).

2. **Better approach: buildPdfHtml moves to ReportGenerator, but MeetingReportsService keeps buildMotionRows + buildVoteDetailsSection** (the ones with repo dependencies). This splits the helpers between the two classes based on dependency direction.

3. **Best approach:** Move ALL HTML generation to ReportGenerator. The 300 LOC ceiling can be met by:
   - Inlining the small helpers (buildProxiesSection/buildTokensSection are 8 LOC each — inline into assembleReportHtml)
   - buildPdfHtml at 152 LOC is the biggest method — it is a single template method, acceptable
   - Compact the static helpers (they are already very tight)
   - The ceiling is per-file, not per-method

**Realistic split after careful accounting:**

After extraction, MeetingReportsService will contain:
- Constructor (22 LOC)
- buildReportHtml orchestration (fetch data + cache check + call generator): ~30 LOC
- buildPdfBytes (fetch data + DOMPDF + snapshot): ~43 LOC
- buildGeneratedReportHtml orchestration (fetch + call generator + hash): ~20 LOC
- Lazy generator() accessor: ~5 LOC
- **Estimated: ~120 LOC** (well under 300)

ReportGenerator will contain all HTML generation: ~493 LOC — exceeds 300 LOC.

**Resolution:** The buildMotionRows method (93 LOC) creates `new OfficialResultsService()` and `new VoteEngine()` inline — these are heavy computations. If we pre-compute official tallies in MeetingReportsService and pass prepared row data to ReportGenerator, buildMotionRows simplifies significantly (removes 25+ LOC of try/catch computation blocks). Similarly, buildVoteDetailsSection can receive pre-fetched ballot data.

**Alternative resolution:** Accept that ReportGenerator may need to be slightly over if we keep methods clean, OR split into two extracted classes. But requirements say "ReportGenerator <300 LOC" so we must hit the target.

**Pragmatic resolution:** Pre-fetch all data in MeetingReportsService (policies, ballots, official results) and pass pure arrays to ReportGenerator. This:
- Removes policyRepo/ballotRepo from ReportGenerator (truly stateless)
- Simplifies buildMotionRows by ~30 LOC (no repo calls, no OfficialResultsService/VoteEngine instantiation)
- Simplifies buildVoteDetailsSection by removing ballotRepo call
- Gets ReportGenerator closer to or under 300 LOC

With pre-fetched data: ~493 - 30 (simpler buildMotionRows) - 10 (simpler buildVoteDetailsSection) - 16 (inline tiny helpers) = ~437 LOC. Still over.

**Final approach:** buildPdfHtml (152 LOC) is the PDF-specific template. It can remain on MeetingReportsService since it's called only from buildPdfBytes and is already self-contained (all data passed as params). This gets ReportGenerator to ~285 LOC — under the ceiling.

### Recommended Final Split

**MeetingReportsService (~270 LOC):**
- Constructor (22 LOC)
- buildReportHtml: cache check + data fetch + delegate to generator (~30 LOC)
- buildPdfHtml: PDF template (152 LOC) — stays because it's self-contained and PDF-specific
- buildPdfBytes: DOMPDF orchestration (43 LOC)
- buildGeneratedReportHtml: data fetch + delegate + hash (~20 LOC)
- Lazy generator() accessor (~5 LOC)

**ReportGenerator (~285 LOC):**
- assembleReportHtml: HTML template heredoc (~85 LOC)
- assembleGeneratedReportHtml: simple HTML template (~25 LOC)
- buildMotionRows (~93 LOC) — receives pre-fetched policy data
- buildAttendanceSection (~20 LOC)
- buildProxiesSection (~8 LOC)
- buildTokensSection (~8 LOC)
- buildVoteDetailsSection (~44 LOC) — receives pre-fetched ballot data
- Static helpers (~43 LOC)
- Constructor with nullable DI for policyRepo + ballotRepo (~10 LOC)

**Wait — if we pre-fetch data, no constructor DI needed.** ReportGenerator becomes truly stateless (no constructor, or empty constructor). All data passed as method params.

## Callers Analysis

| Caller | File | Methods Called |
|--------|------|---------------|
| MeetingReportsController | app/Controller/MeetingReportsController.php | buildReportHtml, buildPdfBytes, buildGeneratedReportHtml |
| Tests | tests/Unit/MeetingReportsServiceTest.php | buildReportHtml, buildPdfBytes (via public API) |

**No external code calls any private methods or static helpers directly.** The public API is exactly 4 methods (including constructor). Only 3 are called by the controller.

Note: The controller also imports `MeetingReportService` (singular, different class at `app/Services/MeetingReportService.php`) for `exportPvHtml`. This is a separate class and NOT part of this refactoring.

## Existing Tests

4 tests in `tests/Unit/MeetingReportsServiceTest.php`, all passing:

1. `testBuildReportHtmlReturnsHtmlWithMotionTitles` — verifies HTML contains motion titles
2. `testBuildReportHtmlServesCachedSnapshotWhenAvailable` — snapshot short-circuit
3. `testBuildReportHtmlRegenBypassesSnapshot` — regen=true bypasses cache
4. `testBuildPdfBytesReturnsPdfMagicHeader` — PDF generation returns %PDF-

Tests mock all 9 repositories. Tests call public API only (buildReportHtml, buildPdfBytes). **Tests should require zero modifications** since the public API of MeetingReportsService does not change.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HTML escaping | Custom escaper | `htmlspecialchars()` via `h()` helper | Already in place, just move it |
| PDF generation | Custom PDF writer | DOMPDF (already used) | Stays on MeetingReportsService |
| Date formatting | Custom parser | PHP `date()` / `strtotime()` | Already in place |

## Common Pitfalls

### Pitfall 1: buildMotionRows Repository Dependencies
**What goes wrong:** Moving buildMotionRows to ReportGenerator but it still needs policyRepo, OfficialResultsService, and VoteEngine.
**Why it happens:** The method mixes data fetching with HTML rendering.
**How to avoid:** Pre-fetch policy data and official results in MeetingReportsService, pass as enriched motion arrays to ReportGenerator.
**Warning signs:** ReportGenerator constructor requiring 3+ repository dependencies.

### Pitfall 2: buildVoteDetailsSection Ballot Dependency
**What goes wrong:** This method calls `$this->ballotRepo->listDetailedForMotion()` per motion.
**Why it happens:** Ballot detail data is fetched lazily per motion.
**How to avoid:** Pre-fetch ballot details for all motions in MeetingReportsService, pass as a map `[motionId => ballots[]]` to ReportGenerator.
**Warning signs:** ReportGenerator needing BallotRepository injection.

### Pitfall 3: LOC Budget Overflow
**What goes wrong:** ReportGenerator exceeds 300 LOC after extraction.
**Why it happens:** Total HTML generation is ~490 LOC.
**How to avoid:** Keep buildPdfHtml on MeetingReportsService (it's PDF-specific, self-contained, all data passed as params). This brings ReportGenerator to ~285 LOC.
**Warning signs:** Counting LOC before writing. Budget carefully.

### Pitfall 4: Breaking Test Assertions
**What goes wrong:** Tests fail after refactoring.
**Why it happens:** Public API signature change or internal behavior drift.
**How to avoid:** Keep exact public method signatures on MeetingReportsService. Internal delegation is transparent to callers.
**Warning signs:** Any test modification needed = something went wrong.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 |
| Config file | phpunit.xml |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingReportsServiceTest.php --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REFAC-07 | MeetingReportsService <300 LOC | metric | `wc -l app/Services/MeetingReportsService.php` | N/A |
| REFAC-07 | Existing 4 tests still pass | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingReportsServiceTest.php --no-coverage` | Yes |
| REFAC-08 | ReportGenerator is final class | syntax | `grep 'final class ReportGenerator' app/Services/ReportGenerator.php` | Wave 0 |
| REFAC-08 | ReportGenerator <300 LOC | metric | `wc -l app/Services/ReportGenerator.php` | Wave 0 |
| REFAC-08 | ReportGenerator nullable DI | syntax | `php -l app/Services/ReportGenerator.php` | Wave 0 |

### Sampling Rate
- **Per task commit:** `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingReportsServiceTest.php --no-coverage`
- **Per wave merge:** `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `app/Services/ReportGenerator.php` — new file, covers REFAC-08
- No additional test files needed — existing MeetingReportsServiceTest.php covers the public API

## Sources

### Primary (HIGH confidence)
- Direct source code analysis of `app/Services/MeetingReportsService.php` (731 LOC)
- Direct source code analysis of `app/Controller/MeetingReportsController.php` (callers)
- Direct source code analysis of `tests/Unit/MeetingReportsServiceTest.php` (4 tests, 17 assertions)
- Phase 4 plan pattern (`04-01-PLAN.md`) for extraction strategy precedent

### Secondary (MEDIUM confidence)
- LOC estimates for post-extraction sizes (based on line counting, may vary by +/-10 LOC)

## Metadata

**Confidence breakdown:**
- Method inventory: HIGH - direct source code analysis with automated LOC counting
- Extraction boundaries: HIGH - clear separation between data fetch and HTML generation
- LOC budget: MEDIUM - estimates may shift during implementation, buildPdfHtml placement is the key lever
- Test impact: HIGH - public API unchanged, tests mock all repos

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable codebase, no external dependencies changing)
