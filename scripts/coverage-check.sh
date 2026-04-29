#!/usr/bin/env bash
# coverage-check.sh — Run PHPUnit with clover coverage and validate per-directory thresholds.
#
# Usage:
#   bash scripts/coverage-check.sh [--services-threshold N] [--controllers-threshold N]
#
# Defaults (set after Phase 75 exit() refactor):
#   --services-threshold 90    (Services/ achieved 90.8% in Phase 55)
#   --controllers-threshold 70 (Controllers/ raised above 70% after Phase 75 — FileServedOkException/EmailPixelSentException enable happy-path tests)
#
# Override thresholds via env vars:
#   COVERAGE_SERVICES_THRESHOLD=95 COVERAGE_CTRL_THRESHOLD=90 bash scripts/coverage-check.sh
#
# Requirements:
#   - pcov extension (loaded via PCOV_SO env var or auto-detected from /tmp/pcov-extract)
#   - php, vendor/bin/phpunit
#   - bc (for float comparison)
#
# CI Usage (Phase 57 Dockerfile must install php8.3-pcov):
#   RUN apt-get install -y php8.3-pcov
#   ENV PCOV_SO=""   # empty = phpunit auto-detects pcov
#
set -euo pipefail

# ── Threshold configuration ────────────────────────────────────────────────
SERVICES_THRESHOLD="${COVERAGE_SERVICES_THRESHOLD:-90}"
CTRL_THRESHOLD="${COVERAGE_CTRL_THRESHOLD:-70}"

# Parse CLI flags
while [[ $# -gt 0 ]]; do
    case "$1" in
        --services-threshold)
            SERVICES_THRESHOLD="$2"; shift 2 ;;
        --controllers-threshold)
            CTRL_THRESHOLD="$2"; shift 2 ;;
        *)
            echo "Unknown flag: $1" >&2; exit 1 ;;
    esac
done

# ── pcov detection ─────────────────────────────────────────────────────────
PHP_FLAGS=""
if [[ -n "${PCOV_SO:-}" ]]; then
    PHP_FLAGS="-d extension=${PCOV_SO}"
elif [[ -f "/tmp/pcov-extract/usr/lib/php/20230831/pcov.so" ]]; then
    PHP_FLAGS="-d extension=/tmp/pcov-extract/usr/lib/php/20230831/pcov.so"
    echo "INFO: Using pcov from /tmp/pcov-extract (dev environment)"
fi

# ── Run PHPUnit with clover output ─────────────────────────────────────────
CLOVER_FILE="coverage.xml"
echo "Running PHPUnit Unit suite with clover coverage output..."
# Coverage job provisions a Redis service so the FULL Unit suite (including
# @group redis tests for RateLimiter/EventBroadcaster) can run. This keeps
# Services coverage thresholds meaningful.
#
# We tolerate PHPUnit test failures here (`|| true` and `set +e/-e`):
# coverage's job is *measurement*, not pass/fail of individual tests.
# Test failures are surfaced by the `validate` job. As long as PHPUnit
# generates clover.xml (it does even with failing tests), this script
# can evaluate thresholds against the resulting coverage data.
set +e
php ${PHP_FLAGS} vendor/bin/phpunit --testsuite Unit --coverage-clover "${CLOVER_FILE}"
PHPUNIT_EXIT=$?
set -e
echo "PHPUnit exit code: ${PHPUNIT_EXIT} (failures don't block coverage measurement)"

if [[ ! -f "${CLOVER_FILE}" ]]; then
    echo "ERROR: ${CLOVER_FILE} was not generated." >&2
    exit 2
fi

# ── Parse clover XML for Services/ coverage ────────────────────────────────
SERVICES_COV=$(php -r '
$xml = simplexml_load_file($argv[1]);
$stmts = 0; $covered = 0;
// PHPUnit 10 clover format: <coverage><project><file> (no <package> wrapper)
foreach ($xml->project->file as $file) {
    if (str_contains((string)$file["name"], "/app/Services/")) {
        foreach ($file->line as $line) {
            if ((string)$line["type"] === "stmt") {
                $stmts++;
                if ((int)$line["count"] > 0) $covered++;
            }
        }
    }
}
echo $stmts > 0 ? round($covered / $stmts * 100, 1) : 0;
' "${CLOVER_FILE}")

# ── Parse clover XML for Controller/ coverage ──────────────────────────────
CTRL_COV=$(php -r '
$xml = simplexml_load_file($argv[1]);
$stmts = 0; $covered = 0;
// PHPUnit 10 clover format: <coverage><project><file> (no <package> wrapper)
foreach ($xml->project->file as $file) {
    if (str_contains((string)$file["name"], "/app/Controller/")) {
        foreach ($file->line as $line) {
            if ((string)$line["type"] === "stmt") {
                $stmts++;
                if ((int)$line["count"] > 0) $covered++;
            }
        }
    }
}
echo $stmts > 0 ? round($covered / $stmts * 100, 1) : 0;
' "${CLOVER_FILE}")

# ── Evaluate thresholds ────────────────────────────────────────────────────
echo ""
echo "Coverage Results:"
echo "  Services/   : ${SERVICES_COV}% (threshold: ${SERVICES_THRESHOLD}%)"
echo "  Controller/ : ${CTRL_COV}% (threshold: ${CTRL_THRESHOLD}%)"

FAIL=0

if (( $(echo "${SERVICES_COV} < ${SERVICES_THRESHOLD}" | bc -l) )); then
    echo "FAIL: Services coverage ${SERVICES_COV}% is below threshold ${SERVICES_THRESHOLD}%" >&2
    FAIL=1
else
    echo "OK:   Services coverage ${SERVICES_COV}% >= ${SERVICES_THRESHOLD}%"
fi

if (( $(echo "${CTRL_COV} < ${CTRL_THRESHOLD}" | bc -l) )); then
    echo "FAIL: Controller coverage ${CTRL_COV}% is below threshold ${CTRL_THRESHOLD}%" >&2
    FAIL=1
else
    echo "OK:   Controller coverage ${CTRL_COV}% >= ${CTRL_THRESHOLD}%"
fi

if [[ $FAIL -eq 1 ]]; then
    echo ""
    echo "Coverage check FAILED. Raise coverage or adjust thresholds."
    exit 1
fi

echo ""
echo "Coverage check PASSED."
exit 0
