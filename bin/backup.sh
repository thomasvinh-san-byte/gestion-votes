#!/usr/bin/env bash
# =============================================================================
# AG-VOTE — Database & uploads backup script
# =============================================================================
#
# Creates a timestamped PostgreSQL dump and archives uploaded files.
# Rotates old backups to keep only the last N days.
#
# Usage:
#   ./bin/backup.sh                    # Default: 7-day rotation
#   ./bin/backup.sh --retention=14     # Keep 14 days
#   ./bin/backup.sh --output=/backups  # Custom backup directory
#   ./bin/backup.sh --dry-run          # Show what would be done
#
# Environment variables (or .env):
#   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
#   BACKUP_DIR (default: /var/backups/ag-vote)
#   BACKUP_RETENTION_DAYS (default: 7)
#
# Recommended cron entry:
#   0 2 * * * /var/www/bin/backup.sh >> /var/log/ag-vote/backup.log 2>&1
# =============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Load .env if available
if [ -f "${PROJECT_ROOT}/.env" ]; then
    set -a
    # shellcheck disable=SC1091
    source "${PROJECT_ROOT}/.env"
    set +a
fi

# Database settings
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-agvote}"
DB_USER="${DB_USER:-agvote}"
# DB_PASSWORD should be set via PGPASSWORD or .pgpass

# Backup settings
BACKUP_DIR="${BACKUP_DIR:-/var/backups/ag-vote}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"
UPLOAD_DIR="${PROJECT_ROOT}/storage/uploads"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DRY_RUN=false

# ---------------------------------------------------------------------------
# Parse arguments
# ---------------------------------------------------------------------------

for arg in "$@"; do
    case "$arg" in
        --retention=*) RETENTION_DAYS="${arg#*=}" ;;
        --output=*)    BACKUP_DIR="${arg#*=}" ;;
        --dry-run)     DRY_RUN=true ;;
        --help|-h)
            head -25 "$0" | tail -18
            exit 0
            ;;
        *)
            echo "Unknown argument: $arg" >&2
            exit 1
            ;;
    esac
done

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

die() {
    log "ERROR: $*" >&2
    exit 1
}

# ---------------------------------------------------------------------------
# Preflight checks
# ---------------------------------------------------------------------------

command -v pg_dump >/dev/null 2>&1 || die "pg_dump not found. Install postgresql-client."

if [ "$DRY_RUN" = true ]; then
    log "DRY RUN — no changes will be made"
    log "  Database: ${DB_HOST}:${DB_PORT}/${DB_NAME} (user: ${DB_USER})"
    log "  Backup dir: ${BACKUP_DIR}"
    log "  Retention: ${RETENTION_DAYS} days"
    log "  Upload dir: ${UPLOAD_DIR}"
    exit 0
fi

# Create backup directory
mkdir -p "${BACKUP_DIR}/db" "${BACKUP_DIR}/uploads"

# ---------------------------------------------------------------------------
# 1. Database dump
# ---------------------------------------------------------------------------

DB_BACKUP_FILE="${BACKUP_DIR}/db/agvote_${TIMESTAMP}.sql.gz"

log "Starting PostgreSQL dump → ${DB_BACKUP_FILE}"

export PGPASSWORD="${DB_PASSWORD:-}"

pg_dump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USER" \
    --dbname="$DB_NAME" \
    --format=custom \
    --compress=6 \
    --no-owner \
    --no-privileges \
    --verbose \
    2>&1 | gzip > "$DB_BACKUP_FILE" \
    || die "pg_dump failed"

DB_SIZE=$(du -sh "$DB_BACKUP_FILE" | cut -f1)
log "Database dump complete: ${DB_SIZE}"

# ---------------------------------------------------------------------------
# 2. Uploads archive
# ---------------------------------------------------------------------------

if [ -d "$UPLOAD_DIR" ] && [ "$(ls -A "$UPLOAD_DIR" 2>/dev/null)" ]; then
    UPLOAD_BACKUP_FILE="${BACKUP_DIR}/uploads/uploads_${TIMESTAMP}.tar.gz"

    log "Archiving uploads → ${UPLOAD_BACKUP_FILE}"

    tar -czf "$UPLOAD_BACKUP_FILE" \
        -C "$(dirname "$UPLOAD_DIR")" \
        "$(basename "$UPLOAD_DIR")" \
        2>&1 || die "Upload archive failed"

    UPLOAD_SIZE=$(du -sh "$UPLOAD_BACKUP_FILE" | cut -f1)
    log "Uploads archive complete: ${UPLOAD_SIZE}"
else
    log "No uploads to archive (${UPLOAD_DIR} is empty or missing)"
fi

# ---------------------------------------------------------------------------
# 3. Rotation — remove backups older than retention period
# ---------------------------------------------------------------------------

log "Rotating backups older than ${RETENTION_DAYS} days"

DELETED_DB=$(find "${BACKUP_DIR}/db" -name "agvote_*.sql.gz" -mtime "+${RETENTION_DAYS}" -delete -print | wc -l)
DELETED_UPLOADS=$(find "${BACKUP_DIR}/uploads" -name "uploads_*.tar.gz" -mtime "+${RETENTION_DAYS}" -delete -print | wc -l)

log "Rotation complete: ${DELETED_DB} DB dumps + ${DELETED_UPLOADS} upload archives removed"

# ---------------------------------------------------------------------------
# 4. Summary
# ---------------------------------------------------------------------------

TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
BACKUP_COUNT=$(find "${BACKUP_DIR}" -name "*.gz" | wc -l)

log "Backup complete!"
log "  Total backups: ${BACKUP_COUNT} files (${TOTAL_SIZE})"
log "  Latest DB: ${DB_BACKUP_FILE}"
log "  Retention: ${RETENTION_DAYS} days"
