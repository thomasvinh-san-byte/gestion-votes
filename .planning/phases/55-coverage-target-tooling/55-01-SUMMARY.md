---
phase: 55-coverage-target-tooling
plan: 01
subsystem: testing
tags: [phpunit, pcov, coverage, php8.3]

# Dependency graph
requires:
  - phase: 54-service-unit-tests-batch-2
    provides: Service unit tests for all 19 services + controller tests
provides:
  - "pcov 1.0.11 coverage driver operational (via extracted deb, no sudo needed)"
  - "phpunit.xml includes app/Controller/ in source coverage"
  - "Baseline line coverage: Services 66.16%, Controllers 10.39%"
  - "Per-class gap list: 8 services and all 41 controllers below 90%"
affects:
  - 55-02-coverage-threshold-enforcement
  - 56-e2e-tests

# Tech tracking
tech-stack:
  added: ["pcov 1.0.11 (extracted from php8.3-pcov deb, loaded via -d extension flag)"]
  patterns: ["Coverage run: php -d extension=/tmp/pcov-extract/usr/lib/php/20230831/pcov.so vendor/bin/phpunit --testsuite Unit --coverage-text --coverage-html coverage-report/"]

key-files:
  created: []
  modified:
    - phpunit.xml
    - .gitignore

key-decisions:
  - "pcov loaded via -d extension= flag from extracted deb (no sudo available in this env); CI will need Dockerfile installation in Phase 57"
  - "app/Controller/ added to phpunit.xml source — controller coverage was completely invisible before this fix"
  - "Services aggregate 66.16% (not 90%), Controllers aggregate 10.39% — Plan 02 must write gap-filling tests before adding threshold enforcement"

patterns-established:
  - "Coverage measurement: php -d extension=/tmp/pcov-extract/usr/lib/php/20230831/pcov.so vendor/bin/phpunit --testsuite Unit"

requirements-completed:
  - COV-01
  - COV-02

# Metrics
duration: 15min
completed: 2026-03-30
---

# Phase 55 Plan 01: Coverage Tooling Setup Summary

**pcov 1.0.11 operational + app/Controller/ added to source + baseline measured: Services 66.16%, Controllers 10.39% — both below 90% target, Plan 02 must close gaps before threshold enforcement**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-30T07:23:00Z
- **Completed:** 2026-03-30T07:38:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- pcov 1.0.11 coverage driver operational (extracted from ubuntu deb, no sudo required)
- app/Controller/ added to phpunit.xml source includes — was entirely absent before, making controller coverage measurement impossible
- Full baseline measured: 2962 unit tests, HTML report at coverage-report/, per-class line percentages documented
- Critical finding: 90% target NOT met — 8/19 services and all 41/41 controllers are below 90% line coverage

## Baseline Coverage Data (2026-03-30)

### Overall

| Metric | Value |
|--------|-------|
| Total classes | 122 |
| Total methods | 1141 |
| Total lines | 13679 |
| Overall lines covered | 21.88% (2993/13679) |

### Services aggregate: 66.16% (1519/2296 lines)

| Status | Lines % | Covered/Total | Class |
|--------|---------|---------------|-------|
| GAP | 0.00% | 0/211 | EmailQueueService |
| GAP | 0.00% | 0/180 | MeetingReportService |
| GAP | 50.98% | 104/204 | MonitoringService |
| GAP | 54.70% | 157/287 | ExportService |
| GAP | 68.75% | 44/64 | MailerService |
| GAP | 74.54% | 161/216 | OfficialResultsService |
| GAP | 75.59% | 96/127 | ImportService |
| GAP | 89.47% | 119/133 | QuorumEngine |
| OK | 90.18% | 101/112 | NotificationsService |
| OK | 90.62% | 87/96 | BallotsService |
| OK | 92.22% | 83/90 | MeetingWorkflowService |
| OK | 94.74% | 90/95 | SpeechService |
| OK | 96.08% | 49/51 | AttendancesService |
| OK | 96.15% | 25/26 | ProxiesService |
| OK | 99.38% | 161/162 | VoteEngine |
| OK | 100.00% | 121/121 | EmailTemplateService |
| OK | 100.00% | 12/12 | ErrorDictionary |
| OK | 100.00% | 42/42 | MeetingValidator |
| OK | 100.00% | 67/67 | VoteTokenService |

**Services at 90%+: 11/19**
**Services below 90%: 8/19** (EmailQueueService, MeetingReportService, MonitoringService, ExportService, MailerService, OfficialResultsService, ImportService, QuorumEngine)

### Controllers aggregate: 10.39% (660/6353 lines)

| Status | Lines % | Covered/Total | Class |
|--------|---------|---------------|-------|
| GAP | 0.00% | 0/24 | DocContentController |
| GAP | 0.00% | 0/67 | EmailTrackingController |
| GAP | 0.00% | 0/28 | SettingsController |
| GAP | 0.00% | 0/65 | VotePublicController |
| GAP | 0.67% | 2/300 | AnalyticsController |
| GAP | 1.56% | 1/64 | ProjectorController |
| GAP | 2.65% | 4/151 | MemberGroupsController |
| GAP | 3.60% | 12/333 | AdminController |
| GAP | 4.42% | 17/385 | MeetingReportsController |
| GAP | 4.46% | 5/112 | EmailTemplatesController |
| GAP | 4.84% | 6/124 | DashboardController |
| GAP | 4.85% | 10/206 | TrustController |
| GAP | 4.95% | 5/101 | ExportTemplatesController |
| GAP | 4.99% | 18/361 | OperatorController |
| GAP | 5.83% | 6/103 | PoliciesController |
| GAP | 6.44% | 32/497 | ImportController |
| GAP | 6.76% | 24/355 | MeetingWorkflowController |
| GAP | 7.14% | 17/238 | AuditController |
| GAP | 9.09% | 3/33 | EmergencyController |
| GAP | 10.61% | 7/66 | ReminderController |
| GAP | 11.30% | 13/115 | ExportController |
| GAP | 11.71% | 13/111 | QuorumController |
| GAP | 12.26% | 52/424 | MeetingsController |
| GAP | 12.33% | 9/73 | AgendaController |
| GAP | 12.78% | 62/485 | MotionsController |
| GAP | 12.90% | 16/124 | ResolutionDocumentController |
| GAP | 13.14% | 18/137 | EmailController |
| GAP | 13.75% | 11/80 | MeetingAttachmentController |
| GAP | 15.20% | 19/125 | DevicesController |
| GAP | 18.37% | 9/49 | DevSeedController |
| GAP | 20.00% | 10/50 | VoteTokenController |
| GAP | 20.31% | 13/64 | MembersController |
| GAP | 21.24% | 24/113 | InvitationsController |
| GAP | 22.54% | 32/142 | AuthController |
| GAP | 23.01% | 55/239 | BallotsController |
| GAP | 25.71% | 27/105 | SpeechController |
| GAP | 27.27% | 27/99 | AttendancesController |
| GAP | 35.53% | 27/76 | ProxiesController |
| GAP | 38.14% | 37/97 | DocController |
| GAP | 44.44% | 4/9 | NotificationsController |
| GAP | 56.52% | 13/23 | AbstractController |

**Controllers at 90%+: 0/41**
**Controllers below 90%: 41/41**

### Implications for Plan 02

90% target is NOT already met. Plan 02 cannot skip to threshold enforcement. Plan 02 must:

1. Write gap-filling tests for the 8 services below 90% (focus: EmailQueueService, MeetingReportService, MonitoringService, ExportService — the biggest gaps)
2. Either write controller tests or explicitly lower the 90% target to a realistic per-directory level for controllers (controllers have very low baseline: 10.39%)
3. Only after gap-filling: add `<coverage minimum="90"/>` to phpunit.xml

## Task Commits

1. **Task 1: Install pcov and update phpunit.xml source includes** - `4e8a656` (chore)
2. **Task 2: Measure baseline coverage and document gaps** - documented in this SUMMARY (no separate code commit; coverage-report/ is gitignored)

## Files Created/Modified

- `phpunit.xml` - Added `app/Controller/` to `<source>` includes
- `.gitignore` - Added `*.deb` pattern to ignore extracted package files

## Decisions Made

- pcov loaded via `-d extension=` flag from extracted deb (no sudo). The deb was extracted to `/tmp/pcov-extract/`. CI in Phase 57 will need to add `RUN apt-get install -y php8.3-pcov` to Dockerfile.
- `*.deb` added to .gitignore — the downloaded package should not be committed
- app/Controller/ was missing from source — added so controller coverage is now measured

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] pcov install requires sudo, not available**
- **Found during:** Task 1 (pcov installation)
- **Issue:** `sudo apt-get install -y php8.3-pcov` requires sudo which requires a password. No passwordless sudo available.
- **Fix:** Downloaded the deb with `apt-get download php8.3-pcov`, extracted with `dpkg-deb -x` to `/tmp/pcov-extract/`, then loaded via `php -d extension=/tmp/pcov-extract/usr/lib/php/20230831/pcov.so`. This works identically for measuring coverage.
- **Files modified:** none (pcov is in /tmp, not committed)
- **Verification:** `php -d extension=... -m | grep pcov` returns "pcov", PHPUnit shows "Runtime: PHP 8.3.6 with PCOV 1.0.11"
- **Committed in:** 4e8a656 (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (blocking env constraint)
**Impact on plan:** No scope change. Coverage works identically. CI will need Dockerfile fix in Phase 57.

## Issues Encountered

- PHPUnit coverage text output uses aligned spacing `( 13/ 23)` with spaces inside parentheses — required adjusted regex `\(\s*(\d+)/\s*(\d+)\)` for correct parsing.

## Next Phase Readiness

- Plan 02 has the exact gap list needed: 8 services + 41 controllers below 90%
- Recommended Plan 02 strategy: focus service gaps first (EmailQueueService and MeetingReportService are 0%, need tests), then decide on a realistic controller threshold (10.39% aggregate suggests controllers need significant test work or a lower threshold)
- pcov operational for all future coverage measurements using: `php -d extension=/tmp/pcov-extract/usr/lib/php/20230831/pcov.so vendor/bin/phpunit --testsuite Unit --coverage-text`

---
*Phase: 55-coverage-target-tooling*
*Completed: 2026-03-30*
