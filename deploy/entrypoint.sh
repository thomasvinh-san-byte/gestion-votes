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

# Wait for PostgreSQL to be ready (timeout after 60 seconds)
echo "Attente de PostgreSQL..."
PG_WAIT=0
PG_MAX=60
until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-vote_app}" -q 2>/dev/null; do
  PG_WAIT=$((PG_WAIT + 1))
  if [ "$PG_WAIT" -ge "$PG_MAX" ]; then
    echo "[FATAL] PostgreSQL non disponible apres ${PG_MAX}s. Arrêt."
    exit 1
  fi
  sleep 1
done
echo "PostgreSQL disponible (${PG_WAIT}s)."

# ---------------------------------------------------------------------------
# Database initialization
# ---------------------------------------------------------------------------
export PGPASSWORD="${DB_PASSWORD}"

# Helper: run psql with ON_ERROR_STOP so failures actually fail.
pg() {
  psql -v ON_ERROR_STOP=1 \
    -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" \
    -U "${DB_USERNAME:-vote_app}" -d "${DB_DATABASE:-vote_app}" "$@"
}

TABLE_COUNT=$(pg -tAc \
  "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -lt 5 ]; then
  echo "Base vide, application du schema..."
  pg -f /var/www/database/schema-master.sql

  if [ "${LOAD_DEMO_DATA:-1}" = "1" ]; then
    echo "Chargement des donnees de demo..."
    pg -f /var/www/database/seeds/01_minimal.sql
    pg -f /var/www/database/seeds/02_test_users.sql
    pg -f /var/www/database/seeds/03_demo.sql
  fi

  # Mark all current migrations as already applied (schema-master includes them)
  pg -tAc "CREATE TABLE IF NOT EXISTS applied_migrations (
    name text PRIMARY KEY,
    applied_at timestamptz NOT NULL DEFAULT now()
  );" 2>/dev/null || true
  for f in /var/www/database/migrations/*.sql; do
    [ -f "$f" ] || continue
    MNAME="$(basename "$f")"
    pg -tAc "INSERT INTO applied_migrations (name) VALUES ('${MNAME}') ON CONFLICT DO NOTHING;" 2>/dev/null || true
  done

  echo "Base de donnees initialisee."
else
  echo "Base existante ($TABLE_COUNT tables)."
fi

# ---------------------------------------------------------------------------
# Migration tracking — only run NEW migrations, skip already-applied ones
# ---------------------------------------------------------------------------
pg -tAc "CREATE TABLE IF NOT EXISTS applied_migrations (
  name text PRIMARY KEY,
  applied_at timestamptz NOT NULL DEFAULT now()
);" 2>/dev/null || true

MIGRATION_COUNT=0
for f in /var/www/database/migrations/*.sql; do
  [ -f "$f" ] || continue
  MNAME="$(basename "$f")"
  ALREADY=$(pg -tAc "SELECT 1 FROM applied_migrations WHERE name = '${MNAME}';" 2>/dev/null || echo "")
  if [ "$ALREADY" = "1" ]; then
    continue
  fi
  echo "  migration: $MNAME"
  pg -f "$f" || { echo "[FATAL] Migration failed: $MNAME"; exit 1; }
  pg -tAc "INSERT INTO applied_migrations (name) VALUES ('${MNAME}') ON CONFLICT DO NOTHING;" 2>/dev/null || true
  MIGRATION_COUNT=$((MIGRATION_COUNT + 1))
done
[ "$MIGRATION_COUNT" -gt 0 ] && echo "Migrations appliquees: ${MIGRATION_COUNT}." || echo "Migrations: aucune nouvelle."

unset PGPASSWORD

# ---------------------------------------------------------------------------
# Runtime PHP overrides
# ---------------------------------------------------------------------------
# Force secure cookies when not in plain local dev (demo, staging, prod…).
# php.ini defaults to cookie_secure=0 so local HTTP dev works out of the box.
_ENV="${APP_ENV:-development}"
if [ "$_ENV" != "development" ] && [ "$_ENV" != "dev" ]; then
  echo "session.cookie_secure = 1" > /usr/local/etc/php/conf.d/zz-runtime.ini
  echo "Cookie secure: ON (APP_ENV=${_ENV})"
else
  echo "Cookie secure: OFF (local dev)"
fi

# ---------------------------------------------------------------------------
# Dynamic port binding (Render injects PORT=10000 by default)
# ---------------------------------------------------------------------------
LISTEN_PORT="${PORT:-8080}"
if [ "$LISTEN_PORT" != "8080" ]; then
  sed -i "s/listen 8080/listen ${LISTEN_PORT}/" /etc/nginx/http.d/default.conf
  echo "Nginx port: ${LISTEN_PORT} (from \$PORT)"
else
  echo "Nginx port: 8080 (default)"
fi

echo "=== Demarrage des services ==="
exec "$@"
