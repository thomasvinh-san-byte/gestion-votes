#!/bin/sh
set -e

echo "=== AG-VOTE : Initialisation ==="

# ---------------------------------------------------------------------------
# Production safety check
# ---------------------------------------------------------------------------
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "prod" ]; then
  if [ "$APP_AUTH_ENABLED" != "1" ]; then
    echo "[FATAL] APP_AUTH_ENABLED doit être 1 en production. Arrêt."
    exit 1
  fi
  if [ -z "$APP_SECRET" ] || [ "$APP_SECRET" = "change-me-in-prod" ] || [ "$APP_SECRET" = "dev-secret-do-not-use-in-production-change-me-now-please-64chr" ]; then
    echo "[FATAL] APP_SECRET non configuré pour la production. Arrêt."
    echo "        Générer avec: php -r \"echo bin2hex(random_bytes(32));\""
    exit 1
  fi
  if [ "$LOAD_DEMO_DATA" = "1" ]; then
    echo "[FATAL] LOAD_DEMO_DATA=1 interdit en production. Arrêt."
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
: "${DB_PASSWORD:=${DB_PASS:-vote_app_dev_2026}}"

# Expose for PHP (app/config.php reads DB_DSN, DB_USER, DB_PASS)
export DB_USER="${DB_USERNAME}"
export DB_PASS="${DB_PASSWORD}"
if [ -z "$DB_DSN" ]; then
  export DB_DSN="pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}"
fi

# Wait for PostgreSQL to be ready
echo "Attente de PostgreSQL..."
until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-vote_app}" -q 2>/dev/null; do
  sleep 1
done
echo "PostgreSQL disponible."

# Run schema if tables don't exist
export PGPASSWORD="${DB_PASSWORD:-vote_app_dev_2026}"
TABLE_COUNT=$(psql -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-vote_app}" -d "${DB_DATABASE:-vote_app}" -tAc \
  "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -lt 5 ]; then
  echo "Base vide, application du schema..."
  psql -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-vote_app}" -d "${DB_DATABASE:-vote_app}" \
    -f /var/www/database/schema-master.sql

  # Apply latest migration
  for f in /var/www/database/migrations/20*.sql; do
    [ -f "$f" ] && psql -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-vote_app}" -d "${DB_DATABASE:-vote_app}" -f "$f"
  done

  # Load demo data
  if [ "${LOAD_DEMO_DATA:-1}" = "1" ]; then
    echo "Chargement des donnees de demo..."
    psql -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-vote_app}" -d "${DB_DATABASE:-vote_app}" \
      -f /var/www/database/seeds/01_minimal.sql
    psql -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-vote_app}" -d "${DB_DATABASE:-vote_app}" \
      -f /var/www/database/seeds/02_test_users.sql
    psql -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-vote_app}" -d "${DB_DATABASE:-vote_app}" \
      -f /var/www/database/seeds/03_demo.sql
  fi

  echo "Base de donnees initialisee."
else
  echo "Base existante ($TABLE_COUNT tables), schema deja applique."
fi

unset PGPASSWORD

echo "=== Demarrage des services ==="
exec "$@"
