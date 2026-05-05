#!/usr/bin/env bash
# Apply v2.5 + post-archive DB migrations.
#
# Usage:
#   bin/apply-migrations-v2.5.sh                      # interactive (asks confirmation)
#   bin/apply-migrations-v2.5.sh --yes                # non-interactive
#   bin/apply-migrations-v2.5.sh --dry-run            # show what would run
#
# Reads connection params from .env (or env vars). Translates the PDO-format
# DB_DSN (`pgsql:host=...;port=...;dbname=...`) into psql-compatible flags.
#
# All 3 migrations are idempotent (CREATE TABLE IF NOT EXISTS / WHERE filter).
# Safe to re-run.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

DRY_RUN=0
ASSUME_YES=0
for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=1 ;;
        --yes|-y)  ASSUME_YES=1 ;;
        --help|-h)
            head -n 14 "$0" | grep -E "^# " | sed 's/^# //'
            exit 0
            ;;
        *) echo "Unknown arg: $arg" >&2; exit 1 ;;
    esac
done

# Load .env if present
if [ -f .env ]; then
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
fi

# Translate DB_DSN (pgsql:host=X;port=Y;dbname=Z) → host/port/dbname
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-vote_app}"

if [ -n "${DB_DSN:-}" ]; then
    # Parse PDO-format DSN
    IFS=';' read -ra DSN_PARTS <<< "${DB_DSN#pgsql:}"
    for part in "${DSN_PARTS[@]}"; do
        case "$part" in
            host=*)   DB_HOST="${part#host=}" ;;
            port=*)   DB_PORT="${part#port=}" ;;
            dbname=*) DB_NAME="${part#dbname=}" ;;
        esac
    done
fi

DB_USER="${DB_USER:-vote_app}"
DB_PASS="${DB_PASS:-}"

if [ -z "$DB_PASS" ]; then
    echo "ERROR: DB_PASS not set in .env or environment." >&2
    echo "Either populate .env or run: PGPASSWORD=... $0" >&2
    exit 1
fi

MIGRATIONS=(
    "database/migrations/20260504_error_events.sql"
    "database/migrations/20260504_next_step_clicks.sql"
    "database/migrations/20260504_invitation_revoke_pre_hmac.sql"
)

echo "──────────────────────────────────────────────────────────────"
echo " AG-VOTE — Apply v2.5 post-archive migrations"
echo "──────────────────────────────────────────────────────────────"
echo " Target:    $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME"
echo " Files:"
for m in "${MIGRATIONS[@]}"; do
    if [ -f "$m" ]; then
        echo "   ✓ $m"
    else
        echo "   ✗ $m  (MISSING)" >&2
        exit 1
    fi
done
echo "──────────────────────────────────────────────────────────────"
echo
echo "⚠ WARNING: 20260504_invitation_revoke_pre_hmac.sql REVOKES all"
echo "  pending/sent invitations whose token_hash predates the v2.6 HMAC"
echo "  algorithm change. Operators must re-issue affected invitations."
echo

if [ "$DRY_RUN" -eq 1 ]; then
    echo "[DRY-RUN] No SQL will be executed."
    exit 0
fi

if [ "$ASSUME_YES" -ne 1 ]; then
    read -r -p "Continue ? [y/N] " reply
    if [[ ! "$reply" =~ ^[yY]$ ]]; then
        echo "Aborted."
        exit 0
    fi
fi

export PGPASSWORD="$DB_PASS"

for m in "${MIGRATIONS[@]}"; do
    echo
    echo "────── Applying: $m ──────"
    if psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" \
            -v ON_ERROR_STOP=1 -f "$m"; then
        echo "✓ $m applied."
    else
        echo "✗ $m FAILED (psql exit $?). Stopping migration chain." >&2
        exit 1
    fi
done

echo
echo "──────────────────────────────────────────────────────────────"
echo " ✓ All 3 migrations applied successfully."
echo "──────────────────────────────────────────────────────────────"
echo
echo " Verify with:"
echo "   psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c \"SELECT COUNT(*) FROM error_events;\""
echo "   psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c \"SELECT COUNT(*) FROM next_step_clicks;\""
echo "   psql -h $DB_HOST -U $DB_USER -d $DB_NAME -c \"SELECT COUNT(*) FROM invitations WHERE revoked_at IS NOT NULL;\""
