# Phase 4: Refactoring ExportService - Research

**Researched:** 2026-04-10
**Domain:** PHP service refactoring — extract ValueTranslator from ExportService
**Confidence:** HIGH

## Summary

ExportService.php is exactly 770 LOC and must be reduced to <300 LOC by extracting a ValueTranslator class (<300 LOC). The service has a clean separation of concerns already visible in its code sections: value translations (constants + translate methods), value formatting (date/number/percent), CSV output mechanics, row formatters for specific export types, header definitions, and XLSX output (both OpenSpout streaming and PhpSpreadsheet legacy).

The extraction target is clear: all translation constants and translate* methods plus all formatting methods (formatDate, formatTime, formatNumber, formatPercent) belong in ValueTranslator. The row formatters (formatAttendanceRow, formatVoteRow, etc.) stay on ExportService but delegate to ValueTranslator for individual field translation. This mirrors the Phase 3 pattern where ImportService became a thin facade delegating to CsvImporter/XlsxImporter.

**Primary recommendation:** Extract ValueTranslator with all 6 const arrays + 6 translate methods + 4 format methods. ExportService keeps CSV/XLSX output, row formatters, headers, and filename generation — delegating value translation to a lazy-instantiated ValueTranslator.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REFAC-05 | ExportService <300 LOC after extraction of ValueTranslator | Method inventory shows ~270 LOC of translation/formatting can be extracted, leaving ~280 LOC on ExportService |
| REFAC-06 | ValueTranslator is a final class <300 LOC | All translation constants + methods + formatting methods total ~195 LOC of logic, ~270 LOC with class boilerplate |
</phase_requirements>

## Method Inventory

### ExportService.php — 770 LOC total

#### VALUE TRANSLATION constants (lines 28-77, ~50 LOC)
| Constant | Lines | LOC | Extract? |
|----------|-------|-----|----------|
| ATTENDANCE_MODES | 28-35 | 8 | YES → ValueTranslator |
| DECISIONS | 38-44 | 7 | YES → ValueTranslator |
| VOTE_CHOICES | 47-54 | 8 | YES → ValueTranslator |
| MEETING_STATUSES | 57-65 | 9 | YES → ValueTranslator |
| VOTE_SOURCES | 68 | 1 | YES → ValueTranslator (delegates to BallotSource::LABELS) |
| BOOLEANS | 71-76 | 6 | YES → ValueTranslator |

**Subtotal constants: ~39 LOC**

#### VALUE TRANSLATION methods (lines 85-131, ~47 LOC)
| Method | Lines | LOC | Extract? |
|--------|-------|-----|----------|
| translateAttendanceMode | 85-88 | 4 | YES → ValueTranslator |
| translateDecision | 93-96 | 4 | YES → ValueTranslator |
| translateVoteChoice | 101-104 | 4 | YES → ValueTranslator |
| translateMeetingStatus | 109-112 | 4 | YES → ValueTranslator |
| translateVoteSource | 117-120 | 4 | YES → ValueTranslator |
| translateBoolean | 125-131 | 7 | YES → ValueTranslator |

**Subtotal translate methods: ~27 LOC**

#### VALUE FORMATTING methods (lines 141-202, ~62 LOC)
| Method | Lines | LOC | Extract? |
|--------|-------|-----|----------|
| formatDate | 141-155 | 15 | YES → ValueTranslator |
| formatTime | 160-171 | 12 | YES → ValueTranslator |
| formatNumber | 177-192 | 16 | YES → ValueTranslator |
| formatPercent | 197-202 | 6 | YES → ValueTranslator |

**Subtotal formatting methods: ~49 LOC**

#### CSV OUTPUT methods (lines 210-256, ~47 LOC) — STAY on ExportService
| Method | Lines | LOC | Visibility |
|--------|-------|-----|------------|
| safeFilename | 211-213 | 3 | private |
| initCsvOutput | 215-220 | 6 | public |
| openCsvOutput | 227-231 | 5 | public |
| sanitizeCsvCell | 239-245 | 7 | private |
| writeCsvRow | 254-256 | 3 | public |

#### FILENAME methods (lines 261-296, ~36 LOC) — STAY on ExportService
| Method | Lines | LOC |
|--------|-------|-----|
| generateFilename | 261-282 | 22 | 
| sanitizeFilename | 287-296 | 10 |

#### ROW FORMATTERS (lines 305-498, ~194 LOC) — STAY on ExportService
| Method | Lines | LOC | Calls translate/format |
|--------|-------|-----|----------------------|
| formatAttendanceRow | 305-315 | 11 | translateAttendanceMode, formatNumber, formatDate |
| formatVoteRow | 320-332 | 13 | translateVoteChoice, formatNumber, translateBoolean, formatDate, translateVoteSource |
| formatMemberRow | 337-347 | 11 | formatNumber, translateBoolean, translateAttendanceMode, formatDate |
| formatMotionResultRow | 353-368 | 16 | formatDate, formatNumber, translateDecision |
| formatProxyRow | 373-381 | 9 | formatNumber, formatDate, translateBoolean |
| formatAuditRow | 478-498 | 21 | translateAttendanceMode, translateVoteChoice, translateBoolean, formatDate, translateVoteSource |

#### HEADER methods (lines 387-473, ~87 LOC) — STAY on ExportService
| Method | Lines | LOC |
|--------|-------|-----|
| getAttendanceHeaders | 387-396 | 10 |
| getVotesHeaders | 399-410 | 12 |
| getMembersHeaders | 413-422 | 10 |
| getMotionResultsHeaders | 425-440 | 16 |
| getProxiesHeaders | 443-450 | 8 |
| getAuditHeaders | 453-473 | 21 |

#### XLSX OUTPUT methods (lines 514-770, ~257 LOC) — STAY on ExportService
| Method | Lines | LOC |
|--------|-------|-----|
| streamXlsx | 514-529 | 16 |
| streamFullXlsx | 535-592 | 58 |
| createSpreadsheet | 607-644 | 38 |
| addSheet | 649-688 | 40 |
| initXlsxOutput | 693-698 | 6 |
| outputSpreadsheet | 703-706 | 4 |
| createFullExportSpreadsheet | 718-769 | 52 |

### LOC Budget Projection

**ValueTranslator (new):**
- Class boilerplate (declare, namespace, use, class): ~10 LOC
- 6 constants: ~39 LOC
- 6 translate methods: ~27 LOC
- 4 format methods: ~49 LOC
- Comments/whitespace: ~30 LOC
- **Total: ~155-180 LOC** (well under 300)

**ExportService (after extraction):**
- Class boilerplate: ~10 LOC
- ValueTranslator lazy property + accessor: ~5 LOC
- CSV output methods: ~47 LOC
- Filename methods: ~36 LOC
- Row formatters (rewritten to use $this->translator()->method()): ~100 LOC
- Header methods: ~87 LOC
- XLSX output methods: ~257 LOC
- Comments/whitespace: ~50 LOC
- **Raw total: ~592 LOC — THIS EXCEEDS 300 LOC**

### Critical Finding: ExportService Cannot Reach <300 LOC with ValueTranslator Extraction Alone

Extracting only ValueTranslator removes ~115 LOC of method bodies but the remaining code (CSV, XLSX, headers, row formatters) totals ~530+ LOC. To hit <300, we must also consider what else can be trimmed.

**Options to reach <300:**
1. **Move row formatters + headers into ValueTranslator** — These are value-formatting logic, not I/O. This is the cleanest option: ValueTranslator handles "how to format a row of data" while ExportService handles "how to output to CSV/XLSX".
2. **Extract a separate XlsxExporter** — The XLSX methods alone are ~257 LOC. But this adds scope beyond the requirement.

**Recommended approach (Option 1):** ValueTranslator gets constants + translate methods + format methods + row formatters + headers. This makes ValueTranslator the "data formatting" class and ExportService the "I/O output" class.

### Revised LOC Budget (Option 1)

**ValueTranslator (new):**
- Constants: ~39 LOC
- Translate methods: ~27 LOC
- Format methods: ~49 LOC
- Row formatters: ~81 LOC
- Header methods: ~77 LOC
- Comments/whitespace: ~30 LOC
- **Total: ~260-280 LOC** (under 300)

**ExportService (after extraction):**
- Class boilerplate + ValueTranslator property: ~15 LOC
- CSV output methods (safeFilename, initCsvOutput, openCsvOutput, sanitizeCsvCell, writeCsvRow): ~24 LOC
- Filename methods (generateFilename, sanitizeFilename): ~32 LOC
- XLSX streaming (streamXlsx, streamFullXlsx): ~74 LOC
- XLSX PhpSpreadsheet (createSpreadsheet, addSheet, initXlsxOutput, outputSpreadsheet, createFullExportSpreadsheet): ~140 LOC
- Delegation methods (formatAttendanceRow, etc. as one-liners to ValueTranslator): ~30 LOC
- Comments/whitespace: ~20 LOC
- **Total: ~285 LOC** (tight but under 300)

**Alternative if too tight:** Drop the delegation stubs and have callers use ValueTranslator directly for row formatting. But this breaks ExportController which calls `$export->formatAttendanceRow()`. The delegation stubs preserve the public API.

## Architecture Patterns

### Recommended Extraction Pattern (from Phase 2 and 3)

```
ExportService (facade, <300 LOC)
├── private ?ValueTranslator $translator = null
├── private function translator(): ValueTranslator
├── CSV I/O methods (stay)
├── XLSX I/O methods (stay)
├── Filename methods (stay)
└── Row formatter delegation stubs (one-liner each)
    └── return $this->translator()->formatAttendanceRow($row)

ValueTranslator (new, <300 LOC)
├── Translation constants (ATTENDANCE_MODES, DECISIONS, etc.)
├── translate*() methods
├── format*() methods (formatDate, formatNumber, etc.)
├── Row formatters (formatAttendanceRow, formatVoteRow, etc.)
└── Header definitions (getAttendanceHeaders, getVotesHeaders, etc.)
```

### Pattern: Lazy Instantiation (consistent with Phase 3)
```php
private ?ValueTranslator $translator = null;

private function translator(): ValueTranslator {
    return $this->translator ??= new ValueTranslator();
}
```

### Pattern: Delegation Stubs (consistent with Phase 3)
```php
public function formatAttendanceRow(array $row): array {
    return $this->translator()->formatAttendanceRow($row);
}
```

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| CSV formula injection | Custom sanitization | Keep existing sanitizeCsvCell | Already correct with tab prefix |
| XLSX streaming | PhpSpreadsheet for large exports | Keep OpenSpout Writer | Constant memory, already working |
| French date formatting | Manual strftime | Keep DateTime::format | Already working, handles edge cases |

## Common Pitfalls

### Pitfall 1: Forgetting Delegation Stubs
**What goes wrong:** ExportController and AnalyticsController call methods directly on ExportService (e.g., `$export->formatAttendanceRow()`, `$export->translateDecision()`, `$export->formatDate()`). If these methods are removed without delegation stubs, callers break.
**How to avoid:** Keep ALL public method signatures on ExportService as one-liner delegation stubs. Run full test suite to verify.
**Callers to check:**
- `app/Controller/ExportController.php` — uses formatAttendanceRow, formatVoteRow, formatMotionResultRow, formatAuditRow, getAttendanceHeaders, getVotesHeaders, getMotionResultsHeaders, getAuditHeaders, generateFilename, initCsvOutput, openCsvOutput, writeCsvRow, streamXlsx, streamFullXlsx
- `app/Controller/AnalyticsController.php` — uses formatDate, formatNumber, formatPercent, translateDecision, writeCsvRow, createSpreadsheet

### Pitfall 2: Breaking Callable References
**What goes wrong:** ExportController passes `[$export, 'formatAttendanceRow']` as callable to `streamXlsx()`. If formatAttendanceRow is removed from ExportService (not delegated), this callable becomes invalid.
**How to avoid:** Delegation stubs preserve the callable interface. The stub on ExportService calls through to ValueTranslator.

### Pitfall 3: Constants Referenced Externally
**What goes wrong:** If any code references `ExportService::ATTENDANCE_MODES` or similar constants directly.
**How to check:** Search for `ExportService::` followed by constant names.
**How to avoid:** Keep delegation constants on ExportService: `public const ATTENDANCE_MODES = ValueTranslator::ATTENDANCE_MODES;` OR check if no external references exist and just remove.

### Pitfall 4: LOC Budget Squeeze
**What goes wrong:** If row formatters + headers stay on ExportService alongside XLSX methods, ExportService remains >400 LOC.
**How to avoid:** Row formatters and headers MUST move to ValueTranslator. ExportService keeps only I/O and delegation stubs.

## Caller Analysis

### Constants referenced externally
```
ExportService::ATTENDANCE_MODES — not found externally
ExportService::DECISIONS — not found externally  
ExportService::VOTE_CHOICES — not found externally
ExportService::MEETING_STATUSES — not found externally
ExportService::VOTE_SOURCES — not found externally (delegates to BallotSource::LABELS)
ExportService::BOOLEANS — not found externally
```
Constants are only used internally by translate methods. No delegation constants needed on ExportService.

### Methods called by ExportController
- generateFilename, initCsvOutput, openCsvOutput, writeCsvRow (CSV I/O — stay)
- streamXlsx, streamFullXlsx (XLSX I/O — stay)
- getAttendanceHeaders, getVotesHeaders, getMotionResultsHeaders, getAuditHeaders (need delegation stubs)
- formatAttendanceRow, formatVoteRow, formatMotionResultRow, formatAuditRow (need delegation stubs)

### Methods called by AnalyticsController
- formatDate, formatNumber, formatPercent (need delegation stubs)
- translateDecision (needs delegation stub)
- writeCsvRow, createSpreadsheet (stay on ExportService)

## Existing Test Coverage

**52 tests, 138 assertions** — all passing. Tests cover:
- All 6 translate methods (with case insensitivity, null, unknown passthrough)
- formatDate (with/without time, null, invalid)
- formatTime (valid, null, invalid)
- formatNumber (integer, decimal, null, custom decimals, large numbers)
- formatPercent (normal, null, empty, zero)
- generateFilename (all type mappings, with title, default extension)
- sanitizeFilename (special chars, length limit)
- All 6 row formatters (attendance, vote, member, motionResult, proxy, audit)
- Header/row length consistency checks (5 types + audit)
- CSV output (openCsvOutput, writeCsvRow, custom separator)
- initCsvOutput, initXlsxOutput (header sending)
- XLSX streaming (OpenSpout: valid output, memory bounded, multi-sheet)

**Test strategy:** All tests instantiate `new ExportService()` and call methods directly. After refactoring, delegation stubs must preserve this exact API. Zero test modifications needed.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 |
| Config file | phpunit.xml |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/ExportServiceTest.php --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REFAC-05 | ExportService <300 LOC, all public methods still work | unit | `wc -l app/Services/ExportService.php && timeout 60 php vendor/bin/phpunit tests/Unit/ExportServiceTest.php --no-coverage` | Yes (52 tests) |
| REFAC-06 | ValueTranslator final class <300 LOC | unit | `wc -l app/Services/ValueTranslator.php && php -l app/Services/ValueTranslator.php` | No — Wave 0 |

### Sampling Rate
- **Per task commit:** `timeout 60 php vendor/bin/phpunit tests/Unit/ExportServiceTest.php --no-coverage`
- **Per wave merge:** `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Phase gate:** Full suite green before verify

### Wave 0 Gaps
- [ ] `tests/Unit/ValueTranslatorTest.php` — covers REFAC-06 (optional, since ExportServiceTest already covers all methods via delegation stubs)
- ValueTranslator test is optional because ExportServiceTest exercises all translate/format methods through delegation. Adding a dedicated test would be nice but not required.

## Sources

### Primary (HIGH confidence)
- Direct code analysis of `app/Services/ExportService.php` (770 LOC)
- Direct code analysis of `tests/Unit/ExportServiceTest.php` (52 tests, all green)
- Direct code analysis of `app/Controller/ExportController.php` (callers)
- Direct code analysis of `app/Controller/AnalyticsController.php` (callers)
- Phase 3 plan (`03-01-PLAN.md`) for extraction pattern reference

### Secondary (MEDIUM confidence)
- LOC projections based on method-by-method counting (may vary +/- 15 LOC due to whitespace/comments)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - no new libraries, pure refactoring
- Architecture: HIGH - follows proven Phase 3 extraction pattern
- Pitfalls: HIGH - identified from direct code and caller analysis

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable internal refactoring, no external dependencies)
