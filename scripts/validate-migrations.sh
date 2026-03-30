#!/usr/bin/env bash
set -euo pipefail
# validate-migrations.sh — AG-VOTE migration dry-run validator
# Usage: ./scripts/validate-migrations.sh [--syntax-only] [--host H] [--port P] [--user U] [--dbname D] [--password P]
# Requires: psql, createdb, dropdb (PostgreSQL client tools)
#
# Exit codes:
#   0 — all passes succeeded (or syntax-only found zero issues)
#   1 — migration failure on first pass
#   2 — idempotency failure on second pass
#   3 — SQLite syntax patterns detected

# ---------------------------------------------------------------------------
# Defaults (match docker-compose.yml and entrypoint.sh)
# ---------------------------------------------------------------------------
HOST="${DB_HOST:-localhost}"
PORT="${DB_PORT:-5432}"
USER="${DB_USERNAME:-vote_app}"
DBNAME="${DB_DATABASE:-vote_app_test}"
PASS="${DB_PASSWORD:-${DB_PASS:-}}"
SYNTAX_ONLY=false
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------
while [[ $# -gt 0 ]]; do
  case "$1" in
    --syntax-only)
      SYNTAX_ONLY=true
      shift
      ;;
    --host)
      HOST="$2"; shift 2
      ;;
    --port)
      PORT="$2"; shift 2
      ;;
    --user)
      USER="$2"; shift 2
      ;;
    --dbname)
      DBNAME="$2"; shift 2
      ;;
    --password)
      PASS="$2"; shift 2
      ;;
    *)
      echo "Unknown argument: $1" >&2
      echo "Usage: $0 [--syntax-only] [--host H] [--port P] [--user U] [--dbname D] [--password PW]" >&2
      exit 1
      ;;
  esac
done

MIGRATIONS_DIR="$PROJECT_ROOT/database/migrations"
SCHEMA_FILE="$PROJECT_ROOT/database/schema-master.sql"

# ---------------------------------------------------------------------------
# Mode: --syntax-only  (no PostgreSQL required)
# ---------------------------------------------------------------------------
if $SYNTAX_ONLY; then
  echo "=== Syntax-only validation: scanning for SQLite patterns ==="
  SQLITE_PATTERNS='AUTOINCREMENT|datetime\(.now.\)|PRAGMA|`[a-z]'
  if grep -rnE "$SQLITE_PATTERNS" "$MIGRATIONS_DIR"/*.sql 2>/dev/null; then
    echo ""
    echo "FAIL: SQLite-specific syntax found in migration files (see above)"
    exit 3
  else
    MIGRATION_COUNT=$(ls "$MIGRATIONS_DIR"/*.sql 2>/dev/null | wc -l | tr -d ' ')
    echo "PASS: Zero SQLite patterns found across $MIGRATION_COUNT migration files"
    exit 0
  fi
fi

# ---------------------------------------------------------------------------
# Mode: Full PostgreSQL validation
# ---------------------------------------------------------------------------
TESTDB="agvote_migration_test_$$"
FAILED=0
IDEMPOTENCY_FAILED=0

export PGPASSWORD="${PASS}"

# Cleanup trap — always drop the test database on exit
cleanup() {
  echo ""
  echo "=== Cleanup: dropping test database $TESTDB ==="
  dropdb -h "$HOST" -p "$PORT" -U "$USER" "$TESTDB" 2>/dev/null || true
}
trap cleanup EXIT

# Helper: run psql with ON_ERROR_STOP=1
pg() {
  psql -v ON_ERROR_STOP=1 \
    -h "$HOST" -p "$PORT" \
    -U "$USER" -d "$TESTDB" "$@"
}

echo "=== Creating test database: $TESTDB ==="
createdb -h "$HOST" -p "$PORT" -U "$USER" "$TESTDB"

# ---------------------------------------------------------------------------
# Apply base schema (matches entrypoint.sh behavior)
# ---------------------------------------------------------------------------
if [ -f "$SCHEMA_FILE" ]; then
  echo "=== Applying schema-master.sql ==="
  pg -f "$SCHEMA_FILE"
else
  echo "WARNING: schema-master.sql not found at $SCHEMA_FILE, skipping base schema"
fi

# ---------------------------------------------------------------------------
# Pass 1: Apply all migrations in sorted order
# ---------------------------------------------------------------------------
echo ""
echo "=== Pass 1: Applying all migrations ==="
for f in "$MIGRATIONS_DIR"/*.sql; do
  [ -f "$f" ] || continue
  echo "  Applying: $(basename "$f")"
  if ! pg -f "$f" > /dev/null 2>&1; then
    echo "  FAIL: $(basename "$f")"
    FAILED=$((FAILED + 1))
  else
    echo "  OK: $(basename "$f")"
  fi
done

if [ "$FAILED" -gt 0 ]; then
  echo ""
  echo "FAIL: $FAILED migration(s) failed on first pass"
  exit 1
fi
echo "PASS: All migrations applied successfully on first pass"

# ---------------------------------------------------------------------------
# Pass 2: Idempotency test — run all migrations a second time
# ---------------------------------------------------------------------------
echo ""
echo "=== Pass 2: Idempotency test (re-applying all migrations) ==="
for f in "$MIGRATIONS_DIR"/*.sql; do
  [ -f "$f" ] || continue
  echo "  Re-applying: $(basename "$f")"
  STDERR_OUTPUT=$(pg -f "$f" 2>&1 >/dev/null || true)
  # Check for actual errors (not just NOTICE/INFO messages)
  if echo "$STDERR_OUTPUT" | grep -qE '^(ERROR|FATAL):'; then
    echo "  IDEMPOTENCY FAIL: $(basename "$f")"
    echo "  Details: $STDERR_OUTPUT"
    IDEMPOTENCY_FAILED=$((IDEMPOTENCY_FAILED + 1))
  else
    echo "  OK: $(basename "$f")"
  fi
done

if [ "$IDEMPOTENCY_FAILED" -gt 0 ]; then
  echo ""
  echo "FAIL: $IDEMPOTENCY_FAILED migration(s) failed idempotency check (second pass)"
  exit 2
fi

echo "PASS: All migrations passed idempotency test (second pass)"
echo ""
echo "=== Validation complete: all migrations are production-safe ==="
exit 0
