#!/bin/sh
set -e

echo "=== AG-VOTE : Initialisation ==="

# ---------------------------------------------------------------------------
# Production safety checks
# ---------------------------------------------------------------------------
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "prod" ]; then
  FAIL=0
  if [ "$APP_AUTH_ENABLED" != "1" ]; then
    echo "[FATAL] APP_AUTH_ENABLED doit être 1 en production."
    FAIL=1
  fi
  if [ "$CSRF_ENABLED" != "1" ]; then
    echo "[FATAL] CSRF_ENABLED doit être 1 en production."
    FAIL=1
  fi
  if [ "$RATE_LIMIT_ENABLED" != "1" ]; then
    echo "[FATAL] RATE_LIMIT_ENABLED doit être 1 en production."
    FAIL=1
  fi
  if [ -z "$APP_SECRET" ] || [ "$APP_SECRET" = "change-me-in-prod" ] || [ "$APP_SECRET" = "dev-secret-do-not-use-in-production-change-me-now-please-64chr" ]; then
    echo "[FATAL] APP_SECRET non configuré pour la production."
    echo "        Générer avec: php -r \"echo bin2hex(random_bytes(32));\""
    FAIL=1
  fi
  if [ "$LOAD_DEMO_DATA" = "1" ]; then
    echo "[FATAL] LOAD_DEMO_DATA=1 interdit en production."
    FAIL=1
  fi
  if [ "$FAIL" = "1" ]; then
    echo "[FATAL] Vérifications production échouées. Arrêt."
    exit 1
  fi
  echo "Vérifications production OK."
fi

# ---------------------------------------------------------------------------
# Normalize env vars (Render/cloud sets DB_HOST etc., PHP app needs DB_DSN)
# ---------------------------------------------------------------------------
: "${DB_HOST:=db}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:=vote_app}"
: "${DB_USERNAME:=${DB_USER:-vote_app}}"
: "${DB_PASSWORD:=${DB_PASS}}"

if [ -z "$DB_PASSWORD" ]; then
  echo "[WARN] DB_PASS/DB_PASSWORD non defini. Utilisation du mot de passe par defaut (dev uniquement)."
  DB_PASSWORD="vote_app_dev_2026"
fi

# Expose for PHP (app/config.php reads DB_DSN, DB_USER, DB_PASS)
export DB_USER="${DB_USERNAME}"
export DB_PASS="${DB_PASSWORD}"
if [ -z "$DB_DSN" ]; then
  export DB_DSN="pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}"
fi

# Wait for PostgreSQL to be ready (timeout after 30 seconds)
echo "Attente de PostgreSQL..."
PG_WAIT=0
PG_MAX=30
until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-vote_app}" -q 2>/dev/null; do
  PG_WAIT=$((PG_WAIT + 1))
  if [ "$PG_WAIT" -ge "$PG_MAX" ]; then
    echo "[FATAL] PostgreSQL non disponible apres ${PG_MAX}s. Arrêt."
    exit 1
  fi
  sleep 1
done
echo "PostgreSQL disponible (${PG_WAIT}s)."

# Run schema if tables don't exist
export PGPASSWORD="${DB_PASSWORD}"
PG_CMD="psql -h ${DB_HOST:-db} -p ${DB_PORT:-5432} -U ${DB_USERNAME:-vote_app} -d ${DB_DATABASE:-vote_app}"
TABLE_COUNT=$($PG_CMD -tAc \
  "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -lt 5 ]; then
  echo "Base vide, application du schema..."
  $PG_CMD -f /var/www/database/schema-master.sql

  # Load demo data
  if [ "${LOAD_DEMO_DATA:-1}" = "1" ]; then
    echo "Chargement des donnees de demo..."
    $PG_CMD -f /var/www/database/seeds/01_minimal.sql
    $PG_CMD -f /var/www/database/seeds/02_test_users.sql
    $PG_CMD -f /var/www/database/seeds/03_demo.sql
  fi

  echo "Base de donnees initialisee."
else
  echo "Base existante ($TABLE_COUNT tables)."
fi

# Always apply migrations (they are idempotent — IF NOT EXISTS, etc.)
# This ensures new migrations are applied on redeployment, not only on fresh DBs.
MIGRATION_COUNT=0
for f in /var/www/database/migrations/*.sql; do
  [ -f "$f" ] || continue
  $PG_CMD -f "$f" 2>&1 | head -5
  MIGRATION_COUNT=$((MIGRATION_COUNT + 1))
done
if [ "$MIGRATION_COUNT" -gt 0 ]; then
  echo "Migrations appliquees: $MIGRATION_COUNT fichier(s)."
fi

unset PGPASSWORD

echo "=== Demarrage des services ==="
exec "$@"
