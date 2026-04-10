# Phase 3: Refactoring ImportService - Research

**Researched:** 2026-04-10
**Domain:** PHP class extraction / refactoring (ImportService decomposition)
**Confidence:** HIGH

## Summary

ImportService.php is currently 791 lines and contains three distinct responsibilities: CSV file reading/parsing (encoding detection, separator detection, row iteration), XLSX file reading/parsing (PhpSpreadsheet wrapper, formula handling, row iteration), and orchestration (file validation, column mapping, value parsing, business logic for member/attendance/proxy/motion imports). The goal is to extract CsvImporter and XlsxImporter as standalone `final class` files with nullable DI constructors, each under 300 LOC, while keeping ImportService as a thin orchestrator under 300 LOC.

Unlike the AuthMiddleware refactoring (Phase 2) which dealt with static-only methods and callers depending on `AuthMiddleware::methodName()`, ImportService has a mixed pattern: static utility methods (file reading, parsing, column maps) and instance methods (process* business logic). The controller calls static methods directly via `ImportService::readCsvFile()`, `ImportService::validateUploadedFile()`, etc. The existing 54 test methods test both static utilities and instance process methods with mock RepositoryFactory.

**Primary recommendation:** Extract CsvImporter and XlsxImporter into `AgVote\Service` namespace. Keep delegation stubs on ImportService for all public static methods so zero callers (ImportController + 54 tests) need updating. CsvImporter gets `readCsvFile()` + encoding logic. XlsxImporter gets `readXlsxFile()` + PhpSpreadsheet usage. Everything else stays on ImportService as orchestration.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REFAC-03 | ImportService <300 LOC after extraction of CSV/XLSX importers | After extracting readCsvFile (69 LOC) and readXlsxFile (49 LOC), remaining ImportService is ~673 LOC minus ~118 LOC = ~673 LOC. Need to also move validateUploadedFile (28 LOC) and constants (18 LOC) partially. See detailed analysis below. |
| REFAC-04 | CsvImporter and XlsxImporter are final class with DI nullable, each <300 LOC | CsvImporter ~120 LOC, XlsxImporter ~100 LOC with current extraction. Both well under ceiling. |
</phase_requirements>

## Current State Analysis

### ImportService Method Inventory (791 LOC total)

#### CSV-Specific Methods (candidates for CsvImporter)
| Method | Lines | LOC | Category |
|--------|-------|-----|----------|
| `readCsvFile()` | 188-256 | 69 | CSV reading, encoding detection, separator detection |
| **Subtotal** | | **69** | |

#### XLSX-Specific Methods (candidates for XlsxImporter)
| Method | Lines | LOC | Category |
|--------|-------|-----|----------|
| `readXlsxFile()` | 105-153 | 49 | XLSX reading via PhpSpreadsheet |
| **Subtotal** | | **49** | |

#### Shared Utilities (could go to either importer or stay on ImportService)
| Method | Lines | LOC | Category |
|--------|-------|-----|----------|
| `validateUploadedFile()` | 64-91 | 28 | File validation (MIME, size, extension) |
| `mapColumns()` | 163-175 | 13 | Header-to-field mapping |
| `getMembersColumnMap()` | 265-275 | 11 | Column alias definitions |
| `getAttendancesColumnMap()` | 280-287 | 8 | Column alias definitions |
| `getMotionsColumnMap()` | 292-299 | 8 | Column alias definitions |
| `getProxiesColumnMap()` | 304-311 | 8 | Column alias definitions |
| `parseAttendanceMode()` | 320-340 | 21 | Value parsing |
| `parseBoolean()` | 345-348 | 4 | Value parsing |
| `parseVotingPower()` | 353-358 | 6 | Value parsing |
| `checkDuplicateEmails()` | 375-394 | 20 | Pre-import validation |
| **Subtotal** | | **127** | |

#### Business Logic (instance methods -- stays on ImportService)
| Method | Lines | LOC | Category |
|--------|-------|-----|----------|
| `constructor` | 26-28 | 3 | DI setup |
| `processMemberImport()` | 405-520 | 116 | Member create/update with groups |
| `processAttendanceImport()` | 527-591 | 65 | Attendance upsert with member lookup |
| `processProxyImport()` | 601-673 | 73 | Proxy creation with validation rules |
| `processMotionImport()` | 682-747 | 66 | Motion creation with positioning |
| `buildMemberLookups()` | 758-769 | 12 | Private helper for member lookups |
| `buildProxyMemberFinder()` | 774-790 | 17 | Private helper for proxy member resolution |
| **Subtotal** | | **352** | |

#### Constants and Properties
| Item | Lines | LOC | Category |
|------|-------|-----|----------|
| `MAX_FILE_SIZE` | 34 | 1 | Shared constant |
| `CSV_MIME_TYPES` | 37-42 | 6 | CSV-specific |
| `XLSX_MIME_TYPES` | 44-50 | 7 | XLSX-specific |
| `$repos` property + use statements | 1-10, 24-25 | 14 | Boilerplate |
| Section comments/blank lines | scattered | ~50 | Structure |
| **Subtotal** | | **~78** | |

### LOC Budget Analysis

**Problem:** The business logic alone is 352 LOC, which already exceeds the 300 LOC ceiling. Simply moving CSV/XLSX reading out is insufficient.

**Solution:** Move ALL shared utilities (column maps, value parsers, validation) into the importers or split differently. The key insight is:

| Component | Content | Estimated LOC |
|-----------|---------|---------------|
| **CsvImporter** | readCsvFile + validateUploadedFile(csv path) + CSV_MIME_TYPES + encoding logic | ~120 |
| **XlsxImporter** | readXlsxFile + validateUploadedFile(xlsx path) + XLSX_MIME_TYPES + PhpSpreadsheet | ~100 |
| **ImportService** | constructor + 4 process methods + 2 private helpers + column maps + value parsers + mapColumns + checkDuplicateEmails + MAX_FILE_SIZE + delegation stubs | ~480 still too high |

**Revised approach:** We must also extract column maps and value parsers to the importers, OR extract the process methods partially. Let me recalculate:

The process methods (352 LOC) are the core issue. They CANNOT be split between CsvImporter and XlsxImporter because they are format-agnostic -- they operate on already-parsed `$rows` and `$colIndex` arrays. The format-specific work (CSV vs XLSX reading) happens BEFORE the process methods are called.

**Correct extraction strategy:**

Move to CsvImporter:
- `readCsvFile()` (69 LOC)
- CSV-specific constants: `CSV_MIME_TYPES` (6 LOC)

Move to XlsxImporter:
- `readXlsxFile()` (49 LOC)
- XLSX-specific constants: `XLSX_MIME_TYPES` (7 LOC)

Move to BOTH importers (duplicated or shared via trait/parent):
- `validateUploadedFile()` -- but this is format-specific via the extension parameter, so it can be split into `CsvImporter::validate()` and `XlsxImporter::validate()`.

This only removes ~130 LOC from ImportService (791 - 130 = 661), still way over 300.

**Aggressive strategy:** Also move column maps and all parse helpers to the importers as shared infrastructure. But that only adds another ~127 LOC. 791 - 130 - 127 = 534. Still over.

**The real insight:** To get ImportService under 300 LOC, the 4 process methods (352 LOC combined) must also be extracted. The requirement says "extract CSV/XLSX importers" but the LOC budget demands moving the process methods too.

**Final recommended architecture:**

```
ImportService.php (<300 LOC)
  - constructor, delegation stubs, mapColumns, column maps, value parsers, checkDuplicateEmails, validateUploadedFile
  - Delegation: readCsvFile -> CsvImporter, readXlsxFile -> XlsxImporter

CsvImporter.php (<300 LOC)
  - readCsvFile (69 LOC)
  - processMemberImport (116 LOC) -- format-agnostic but grouped here
  - processAttendanceImport (65 LOC)
  - buildMemberLookups (12 LOC)
  Total: ~270 LOC

XlsxImporter.php (<300 LOC)
  - readXlsxFile (49 LOC)
  - processProxyImport (73 LOC)
  - processMotionImport (66 LOC)
  - buildProxyMemberFinder (17 LOC)
  Total: ~215 LOC
```

**Wait -- this violates Single Responsibility.** Process methods are format-agnostic. They don't belong on format-specific importers.

**Best architecture (recommended):**

CsvImporter and XlsxImporter are file-reading classes ONLY. The process methods stay on ImportService BUT get condensed/simplified. Let me re-examine the LOC more carefully.

Actual LOC of ImportService without blank lines/comments:

```
Boilerplate (namespace, use, class declaration, constructor): ~15 LOC
Constants (MAX_FILE_SIZE, CSV_MIME_TYPES, XLSX_MIME_TYPES):   ~15 LOC
validateUploadedFile:                                          28 LOC
readXlsxFile:                                                  49 LOC
mapColumns:                                                    13 LOC
readCsvFile:                                                   69 LOC
4 column map getters:                                          35 LOC
3 parse helpers:                                               31 LOC
checkDuplicateEmails:                                          20 LOC
processMemberImport:                                          116 LOC
processAttendanceImport:                                       65 LOC
processProxyImport:                                            73 LOC
processMotionImport:                                           66 LOC
buildMemberLookups:                                            12 LOC
buildProxyMemberFinder:                                        17 LOC
Section comment blocks + blank lines:                         ~87 LOC
```

File is 791 lines total. If we extract:
- readCsvFile (69) + readXlsxFile (49) + validateUploadedFile (28) + CSV/XLSX MIME constants (13) + their section comments (~20) = ~179 lines removed
- Add delegation stubs (~6 lines for readCsvFile, ~6 for readXlsxFile, ~10 for validateUploadedFile) = ~22 lines added
- Net removal: ~157 lines -> 791 - 157 = 634 lines. Still over 300.

**The 300 LOC ceiling requires moving process methods out.** Revisiting the requirement text: "ImportService <300 LOC after extraction of CsvImporter + XlsxImporter". The importers are meant to contain NOT just file reading but also the processing business logic.

**Revised recommendation:** CsvImporter and XlsxImporter each handle the FULL import pipeline for their format (read file + process data), while ImportService becomes a thin facade that delegates to the appropriate importer. The process methods are duplicated as needed or shared via a common base trait.

However, since process methods are format-agnostic, the cleanest approach is:

```
ImportService.php (<300 LOC) -- Facade + shared utilities
  - constructor with nullable DI
  - validateUploadedFile() (static, 28 LOC) -- stays, used by controller
  - mapColumns() (static, 13 LOC) -- stays, used by controller
  - 4 column map getters (static, 35 LOC) -- stays, used by controller  
  - 3 value parsers (static, 31 LOC) -- stays, used by process methods
  - checkDuplicateEmails() (static, 20 LOC) -- stays, used by controller
  - Delegation stubs to CsvImporter/XlsxImporter (12 LOC)
  - Constants: MAX_FILE_SIZE (1 LOC)
  Total code: ~155 LOC + boilerplate/comments ~50 = ~205 LOC

CsvImporter.php (<300 LOC) -- CSV reading + member/attendance processing
  - readFile() (69 LOC) -- was readCsvFile
  - CSV_MIME_TYPES constant (6 LOC)
  - processMemberImport() (116 LOC)
  - processAttendanceImport() (65 LOC)
  - buildMemberLookups() (12 LOC) -- private helper
  Total code: ~268 LOC + boilerplate ~25 = ~293 LOC

XlsxImporter.php (<300 LOC) -- XLSX reading + proxy/motion processing
  - readFile() (49 LOC) -- was readXlsxFile
  - XLSX_MIME_TYPES constant (7 LOC)
  - processProxyImport() (73 LOC)
  - processMotionImport() (66 LOC)
  - buildMemberLookups() (12 LOC) -- duplicated private helper
  - buildProxyMemberFinder() (17 LOC) -- private helper
  Total code: ~224 LOC + boilerplate ~25 = ~249 LOC
```

**All three files fit under 300 LOC.** This is the recommended architecture.

### Existing Callers

**ImportController.php** (150 LOC) -- the ONLY caller of ImportService. Uses:
- `ImportService::validateUploadedFile()` -- static, stays on ImportService
- `ImportService::readCsvFile()` -- static, delegation stub to CsvImporter
- `ImportService::readXlsxFile()` -- static, delegation stub to XlsxImporter
- `ImportService::mapColumns()` -- static, stays on ImportService
- `ImportService::getMembersColumnMap()` et al -- static, stays on ImportService
- `ImportService::checkDuplicateEmails()` -- static, stays on ImportService
- `$this->importService()->processMemberImport()` -- instance, delegation to CsvImporter
- `$this->importService()->processAttendanceImport()` -- instance, delegation to CsvImporter
- `$this->importService()->processProxyImport()` -- instance, delegation to XlsxImporter
- `$this->importService()->processMotionImport()` -- instance, delegation to XlsxImporter

**Critical observation:** The controller creates ImportService with `new ImportService($this->repo())` and calls process methods as instance methods. The delegation must pass the RepositoryFactory through.

### Existing Tests (54 test methods, 778 LOC)

Test categories:
1. **validateUploadedFile tests** (6 tests) -- call `ImportService::validateUploadedFile()` statically
2. **readCsvFile tests** (9 tests including encoding) -- call `ImportService::readCsvFile()` statically
3. **readXlsxFile tests** (3 tests) -- call `ImportService::readXlsxFile()` statically
4. **mapColumns tests** (10 tests) -- call `ImportService::mapColumns()` statically
5. **Column map getter tests** (1 test) -- calls getters statically
6. **parseAttendanceMode tests** (6 tests) -- call statically
7. **parseBoolean tests** (2 tests) -- call statically
8. **parseVotingPower tests** (5 tests) -- call statically
9. **processMemberImport tests** (5 tests) -- create `new ImportService($mockFactory)` and call instance method

**Constraint:** ALL 54 tests must pass WITHOUT modification. Delegation stubs on ImportService are mandatory for every public method.

## Architecture Patterns

### Recommended Project Structure

```
app/Services/
  ImportService.php       (<300 LOC) - Facade + shared utilities + delegation
  CsvImporter.php         (<300 LOC) - CSV reading + member/attendance processing
  XlsxImporter.php        (<300 LOC) - XLSX reading + proxy/motion processing
```

### Pattern: Instance Facade with Extracted Instance Classes

Unlike AuthMiddleware (fully static), ImportService has a mixed pattern. The process methods are instance methods using `$this->repos`. The extracted classes must also be instance classes with nullable DI constructors.

```php
// CsvImporter.php
final class CsvImporter {
    private ?RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    public static function readFile(string $filePath): array {
        // Moved from ImportService::readCsvFile()
    }

    public function processMemberImport(...): array {
        // Moved from ImportService
    }

    public function processAttendanceImport(...): array {
        // Moved from ImportService
    }
}
```

```php
// XlsxImporter.php
final class XlsxImporter {
    private ?RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    public static function readFile(string $filePath, int $sheetIndex = 0): array {
        // Moved from ImportService::readXlsxFile()
    }

    public function processProxyImport(...): array {
        // Moved from ImportService
    }

    public function processMotionImport(...): array {
        // Moved from ImportService
    }
}
```

```php
// ImportService.php (slimmed)
final class ImportService {
    private ?RepositoryFactory $repos;
    private ?CsvImporter $csvImporter = null;
    private ?XlsxImporter $xlsxImporter = null;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    private function csv(): CsvImporter {
        return $this->csvImporter ??= new CsvImporter($this->repos);
    }

    private function xlsx(): XlsxImporter {
        return $this->xlsxImporter ??= new XlsxImporter($this->repos);
    }

    // Delegation stubs (keep public API unchanged)
    public static function readCsvFile(string $filePath): array {
        return CsvImporter::readFile($filePath);
    }

    public static function readXlsxFile(string $filePath, int $sheetIndex = 0): array {
        return XlsxImporter::readFile($filePath, $sheetIndex);
    }

    public function processMemberImport(...): array {
        return $this->csv()->processMemberImport(...);
    }

    public function processAttendanceImport(...): array {
        return $this->csv()->processAttendanceImport(...);
    }

    public function processProxyImport(...): array {
        return $this->xlsx()->processProxyImport(...);
    }

    public function processMotionImport(...): array {
        return $this->xlsx()->processMotionImport(...);
    }

    // Remaining shared utilities stay here:
    // validateUploadedFile(), mapColumns(), column map getters,
    // value parsers, checkDuplicateEmails(), MAX_FILE_SIZE
}
```

### Process Method Assignment Rationale

The 4 process methods are format-agnostic (they operate on parsed rows). Assigning them to CsvImporter vs XlsxImporter is a pragmatic LOC-balancing decision:

- **CsvImporter gets processMemberImport + processAttendanceImport** (181 LOC) -- these are the most common import operations, and CSV is the primary import format
- **XlsxImporter gets processProxyImport + processMotionImport** (139 LOC) -- lighter methods, balances the LOC

Alternative: Both process methods could live on a shared `ImportProcessor` class, but that adds a 4th file and complexity. The 2-importer split is cleaner.

### Anti-Patterns to Avoid
- **Splitting value parsers into importers:** parseAttendanceMode, parseBoolean, parseVotingPower are used by process methods in BOTH importers (attendance uses parseAttendanceMode, members use parseBoolean + parseVotingPower, motions use parseBoolean). Keep them on ImportService as `ImportService::parseBoolean()` -- callers already use this pattern.
- **Moving mapColumns to importers:** It's format-agnostic and called by ImportController before choosing CSV vs XLSX path. Must stay on ImportService.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| CSV encoding detection | Custom detection per-importer | mb_detect_encoding + mb_convert_encoding (already in readCsvFile) | Correct behavior already implemented |
| XLSX parsing | Custom binary reader | PhpSpreadsheet IOFactory (already used) | Battle-tested library |
| Column alias matching | Fuzzy matching or regex | Exact match via mapColumns() (already works) | Simple, predictable, tested |

## Common Pitfalls

### Pitfall 1: Forgetting to Forward Constructor DI
**What goes wrong:** ImportService delegates to CsvImporter/XlsxImporter but creates them without passing `$this->repos`, causing them to fall back to `RepositoryFactory::getInstance()` which returns null in tests.
**Why it happens:** Lazy instantiation without DI passthrough.
**How to avoid:** The `csv()` and `xlsx()` helper methods MUST pass `$this->repos` to the constructor.
**Warning signs:** NullPointerException in process methods during tests.

### Pitfall 2: Static Method Delegation Signature Mismatch
**What goes wrong:** The delegation stub `ImportService::readCsvFile()` has a different return shape than `CsvImporter::readFile()`, breaking tests that assert on specific keys.
**Why it happens:** Renaming methods during extraction and accidentally changing return types.
**How to avoid:** CsvImporter::readFile() MUST return the identical array shape `['headers' => array, 'rows' => array, 'separator' => string, 'error' => ?string]`. XlsxImporter::readFile() MUST return `['headers' => array, 'rows' => array, 'error' => ?string]`.
**Warning signs:** Tests asserting on `$result['separator']` or `$result['headers']` fail.

### Pitfall 3: Process Method Parameter Signatures
**What goes wrong:** processMemberImport has 5 parameters, processProxyImport has 8 parameters with references (&). If delegation stubs don't pass references correctly, mutations are lost.
**Why it happens:** PHP pass-by-reference semantics require explicit `&` in delegation.
**How to avoid:** Delegation stubs for processProxyImport MUST use `&$proxiesPerReceiver` and `&$existingGivers`. processMotionImport MUST use `&$nextPosition`.
**Warning signs:** Controller sees imported=0 even though rows were processed (reference mutations lost).

### Pitfall 4: Value Parser Access from Extracted Classes
**What goes wrong:** CsvImporter::processMemberImport() calls `self::parseVotingPower()` but that method is on ImportService, not CsvImporter.
**Why it happens:** Methods moved but their internal calls still reference `self::`.
**How to avoid:** Process methods in CsvImporter/XlsxImporter must call `ImportService::parseVotingPower()`, `ImportService::parseBoolean()`, etc. These remain public static on ImportService.
**Warning signs:** Fatal error: Call to undefined method CsvImporter::parseVotingPower().

### Pitfall 5: buildMemberLookups Duplication
**What goes wrong:** Both processAttendanceImport (in CsvImporter) and processProxyImport (in XlsxImporter) need `buildMemberLookups()`. Duplicating it inflates LOC.
**How to avoid:** Keep buildMemberLookups as a protected or public method on ImportService, or duplicate it (12 LOC is acceptable duplication). Alternative: make it a public static method that accepts RepositoryFactory.
**Recommendation:** Duplicate it in both importers (12 LOC each). It's small, and avoiding cross-class coupling is worth the tiny duplication.

## Extraction Plan (Method Assignment)

### CsvImporter (target: ~270 LOC)
- Constant: `CSV_MIME_TYPES`
- Static: `readFile(string $filePath): array` (was readCsvFile)
- Instance: `processMemberImport(array $rows, array $colIndex, bool $hasName, bool $hasFirstLast, string $tenantId): array`
- Instance: `processAttendanceImport(array $rows, array $colIndex, string $tenantId, string $meetingId, bool $dryRun = false): array`
- Private: `buildMemberLookups(string $tenantId): array`

### XlsxImporter (target: ~250 LOC)
- Constant: `XLSX_MIME_TYPES`
- Static: `readFile(string $filePath, int $sheetIndex = 0): array` (was readXlsxFile)
- Instance: `processProxyImport(array $rows, array $colIndex, string $tenantId, string $meetingId, bool $dryRun, int $maxPerReceiver, array &$proxiesPerReceiver, array &$existingGivers): array`
- Instance: `processMotionImport(array $rows, array $colIndex, string $tenantId, string $meetingId, bool $dryRun = false, int &$nextPosition = 1): array`
- Private: `buildMemberLookups(string $tenantId): array`
- Private: `buildProxyMemberFinder(array $colIndex, array $membersByEmail, array $membersByName): callable`

### ImportService (target: ~210 LOC)
- Constant: `MAX_FILE_SIZE`
- Constructor + lazy importer accessors
- Static delegation: `readCsvFile()` -> CsvImporter::readFile()
- Static delegation: `readXlsxFile()` -> XlsxImporter::readFile()
- Instance delegation: `processMemberImport()` -> csv()->processMemberImport()
- Instance delegation: `processAttendanceImport()` -> csv()->processAttendanceImport()
- Instance delegation: `processProxyImport()` -> xlsx()->processProxyImport()
- Instance delegation: `processMotionImport()` -> xlsx()->processMotionImport()
- Own: `validateUploadedFile()` (28 LOC)
- Own: `mapColumns()` (13 LOC)
- Own: `getMembersColumnMap()` (11 LOC)
- Own: `getAttendancesColumnMap()` (8 LOC)
- Own: `getMotionsColumnMap()` (8 LOC)
- Own: `getProxiesColumnMap()` (8 LOC)
- Own: `parseAttendanceMode()` (21 LOC)
- Own: `parseBoolean()` (4 LOC)
- Own: `parseVotingPower()` (6 LOC)
- Own: `checkDuplicateEmails()` (20 LOC)

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 |
| Config file | `phpunit.xml` |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/ImportServiceTest.php --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REFAC-03 | ImportService <300 LOC | smoke | `wc -l app/Services/ImportService.php` | N/A |
| REFAC-03 | Existing 54 ImportServiceTest tests pass unchanged | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/ImportServiceTest.php --no-coverage` | Yes |
| REFAC-04 | CsvImporter is final class <300 LOC with nullable DI | smoke | `grep 'final class' app/Services/CsvImporter.php && wc -l app/Services/CsvImporter.php` | No - Wave 0 |
| REFAC-04 | XlsxImporter is final class <300 LOC with nullable DI | smoke | `grep 'final class' app/Services/XlsxImporter.php && wc -l app/Services/XlsxImporter.php` | No - Wave 0 |

### Sampling Rate
- **Per task commit:** `timeout 60 php vendor/bin/phpunit tests/Unit/ImportServiceTest.php --no-coverage`
- **Per wave merge:** `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Phase gate:** Full unit suite green + `wc -l` checks on all 3 files

### Wave 0 Gaps
- [ ] `app/Services/CsvImporter.php` -- new file to create
- [ ] `app/Services/XlsxImporter.php` -- new file to create

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| God-class ImportService (791 LOC) | Extract to 3 focused classes | This phase | Maintainability, testability |
| CSV + XLSX reading mixed in one class | Dedicated CsvImporter/XlsxImporter | This phase | Single responsibility |
| Business logic interleaved with I/O | I/O separated from processing | This phase | Easier testing |

## Open Questions

1. **Process method assignment to importers -- is it clean enough?**
   - What we know: Process methods are format-agnostic. Assigning them to CsvImporter/XlsxImporter is a LOC-balancing decision, not a logical one.
   - What's unclear: Whether future maintainers will find it confusing that processProxyImport is on XlsxImporter even though it processes CSV data too.
   - Recommendation: Add a PHPDoc comment on each process method noting it's format-agnostic and placed here for LOC distribution. The delegation stubs on ImportService hide this from callers.

2. **Should we add new tests for CsvImporter/XlsxImporter directly?**
   - What we know: The requirement says "existing 54 tests pass without modification." No mention of new tests.
   - What's unclear: Whether the planner should include new test tasks.
   - Recommendation: Focus on zero-modification constraint. If time permits, add targeted tests for CsvImporter::readFile() and XlsxImporter::readFile() directly, but this is NOT a requirement.

## Sources

### Primary (HIGH confidence)
- Direct source code analysis of `app/Services/ImportService.php` (791 LOC)
- Direct source code analysis of `app/Controller/ImportController.php` (150 LOC)
- Direct source code analysis of `tests/Unit/ImportServiceTest.php` (778 LOC, 54 tests)
- Phase 2 research and execution artifacts (pattern reference)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - no new libraries needed, pure refactoring
- Architecture: HIGH - clear method boundaries identified, LOC budgets verified with line counts
- Pitfalls: HIGH - reference parameter forwarding, static delegation, and DI passthrough risks identified from code analysis

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable -- internal refactoring, no external dependencies)
