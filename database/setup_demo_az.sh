#!/usr/bin/env bash
# =============================================================================
# database/setup_demo_az.sh — Setup rapide pour demo A-Z (1 seul script)
# =============================================================================
#
# Usage:
#   sudo bash database/setup_demo_az.sh               # setup complet demo A-Z
#   sudo bash database/setup_demo_az.sh --seed-only   # seeds seulement (schema deja en place)
#   sudo bash database/setup_demo_az.sh --reset       # DETRUIT et recree tout
#
# Ce script charge UNIQUEMENT ce qui est necessaire pour la demo A-Z :
#   - schema.sql (toutes les tables)
#   - migrations 001-004 (idempotent, no-op si schema recent)
#   - 01_minimal.sql (tenant, policies, users RBAC de base)
#   - 02_test_users.sql (comptes admin/operator/president/auditor/viewer/votant)
#   - 08_demo_az.sql (seance scheduled + 10 membres + 2 resolutions)
#
# IMPORTANT:
#   - Les seeds sont executes avec ON_ERROR_STOP=1 : au moindre ERROR SQL, le script s'arrete.
#   - Ne "masque" plus les erreurs via grep|true (ancien comportement).
#
# A la fin :
#   Meeting ID : deadbeef-0001-4a00-8000-000000000001
# =============================================================================

set -euo pipefail

# Couleurs
if [ -t 1 ]; then
  RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
else
  RED=''; GREEN=''; YELLOW=''; BLUE=''; NC=''
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env"

DB_NAME="${DB_NAME:-vote_app}"
DB_USER="${DB_USER:-vote_app}"
DB_PASS="${DB_PASS:-vote_app_dev_2026}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"

MEETING_ID_DEMO_AZ="deadbeef-0001-4a00-8000-000000000001"

log()  { echo -e "${GREEN}[OK]${NC}   $1"; }
warn() { echo -e "${YELLOW}[..]${NC}   $1"; }
err()  { echo -e "${RED}[ERR]${NC}  $1" >&2; }
info() { echo -e "${BLUE}[>>]${NC}  $1"; }

# Lire .env si present
if [ -f "$ENV_FILE" ]; then
  while IFS='=' read -r key val; do
    key="${key## }"; key="${key%% }"
    val="${val## }"; val="${val%% }"
    [[ -z "$key" || "$key" == \#* ]] && continue
    case "$key" in
      DB_USER) DB_USER="${DB_USER:-$val}" ;;
      DB_PASS) DB_PASS="${DB_PASS:-$val}" ;;
      DB_DSN)
        _host=$(echo "$val" | sed -n 's/.*host=\([^;]*\).*/\1/p')
        _name=$(echo "$val" | sed -n 's/.*dbname=\([^;]*\).*/\1/p')
        _port=$(echo "$val" | sed -n 's/.*port=\([^;]*\).*/\1/p')
        [ -n "${_host:-}" ] && DB_HOST="${DB_HOST:-$_host}"
        [ -n "${_name:-}" ] && DB_NAME="${DB_NAME:-$_name}"
        [ -n "${_port:-}" ] && DB_PORT="${DB_PORT:-$_port}"
        ;;
    esac
  done < "$ENV_FILE"
fi

# psql helper : stop immediately on SQL errors
pg_exec() {
  sudo -u postgres psql -X -v ON_ERROR_STOP=1 -d "$DB_NAME" -q "$@"
}

check_postgres() {
  if ! command -v psql &>/dev/null; then
    err "psql non trouve. Installez PostgreSQL."
    exit 1
  fi
  if ! pg_isready -q 2>/dev/null; then
    warn "PostgreSQL ne semble pas demarre. Tentative..."
    if command -v service &>/dev/null; then
      sudo service postgresql start 2>/dev/null || true
    fi
    sleep 1
    if ! pg_isready -q 2>/dev/null; then
      err "Impossible de demarrer PostgreSQL."
      exit 1
    fi
  fi
  log "PostgreSQL OK"
}

create_user_and_db() {
  info "Creation user '$DB_USER' + base '$DB_NAME'..."

  local user_exists db_exists
  user_exists=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" 2>/dev/null || echo "")
  if [ "$user_exists" = "1" ]; then
    sudo -u postgres psql -q -c "ALTER ROLE $DB_USER WITH PASSWORD '$DB_PASS';" 2>/dev/null
  else
    sudo -u postgres psql -c "CREATE ROLE $DB_USER WITH LOGIN PASSWORD '$DB_PASS' NOSUPERUSER NOCREATEDB NOCREATEROLE;" 2>/dev/null
  fi

  db_exists=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" 2>/dev/null || echo "")
  if [ "$db_exists" != "1" ]; then
    sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER ENCODING 'UTF8';" 2>/dev/null
  fi

  pg_exec -c "
    CREATE EXTENSION IF NOT EXISTS pgcrypto;
    CREATE EXTENSION IF NOT EXISTS citext;
    GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
    GRANT USAGE ON SCHEMA public TO $DB_USER;
    GRANT ALL PRIVILEGES ON SCHEMA public TO $DB_USER;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO $DB_USER;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO $DB_USER;
  " >/dev/null
  log "User + base prets"
}

grant_perms() {
  pg_exec -c "
    GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO $DB_USER;
    GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO $DB_USER;
    -- Transfer ownership so vote_app can ALTER tables (needed for ensureSchema calls)
    DO \$\$ DECLARE r RECORD; BEGIN
      FOR r IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
        EXECUTE 'ALTER TABLE public.' || quote_ident(r.tablename) || ' OWNER TO $DB_USER';
      END LOOP;
    END \$\$;
  " >/dev/null
}

apply_schema() {
  info "Schema..."
  pg_exec -f "$SCRIPT_DIR/schema.sql" >/dev/null
  grant_perms
  log "Schema applique"
}

apply_migrations() {
  info "Migrations..."
  shopt -s nullglob
  for f in "$SCRIPT_DIR"/migrations/*.sql; do
    info "  Migration: $(basename "$f")"
    pg_exec -f "$f" >/dev/null
  done
  shopt -u nullglob
  grant_perms
  log "Migrations appliquees"
}

apply_demo_seeds() {
  info "Seed 01_minimal.sql (tenant + policies + users)..."
  pg_exec -f "$SCRIPT_DIR/seeds/01_minimal.sql" >/dev/null

  info "Seed 02_test_users.sql (comptes admin/operator/president/auditor/viewer/votant)..."
  pg_exec -f "$SCRIPT_DIR/seeds/02_test_users.sql" >/dev/null

  info "Seed 08_demo_az.sql (seance + 10 membres + 2 resolutions)..."
  pg_exec -f "$SCRIPT_DIR/seeds/08_demo_az.sql" >/dev/null

  grant_perms
  log "Seeds demo A-Z charges"
}

setup_env() {
  if [ ! -f "$ENV_FILE" ] && [ -f "$PROJECT_DIR/.env.example" ]; then
    cp "$PROJECT_DIR/.env.example" "$ENV_FILE"
  fi
  if [ -f "$ENV_FILE" ]; then
    # robust: replace line if exists, otherwise append
    grep -q '^DB_USER=' "$ENV_FILE" && sed -i "s|^DB_USER=.*|DB_USER=$DB_USER|" "$ENV_FILE" || echo "DB_USER=$DB_USER" >> "$ENV_FILE"
    grep -q '^DB_PASS=' "$ENV_FILE" && sed -i "s|^DB_PASS=.*|DB_PASS=$DB_PASS|" "$ENV_FILE" || echo "DB_PASS=$DB_PASS" >> "$ENV_FILE"
    grep -q '^DB_DSN='  "$ENV_FILE" && sed -i "s|^DB_DSN=.*|DB_DSN=pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME|" "$ENV_FILE" || echo "DB_DSN=pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME" >> "$ENV_FILE"
    log ".env mis a jour"
  fi
}

run_composer() {
  if [ -f "$PROJECT_DIR/composer.json" ]; then
    if [ ! -f "$PROJECT_DIR/vendor/autoload.php" ]; then
      info "composer install (autoloader PSR-4)..."
      if command -v composer &>/dev/null; then
        (cd "$PROJECT_DIR" && composer install --no-interaction --quiet 2>/dev/null) && log "composer install OK" || warn "composer install echoue (fallback autoloader actif)"
      else
        warn "composer non trouve — fallback autoloader PSR-4 sera utilise"
      fi
    fi
  fi
}

verify() {
  local tc uc mc mtc
  tc=$(pg_exec -tAc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'" 2>/dev/null || echo "0")
  uc=$(pg_exec -tAc "SELECT count(*) FROM users" 2>/dev/null || echo "0")
  mc=$(pg_exec -tAc "SELECT count(*) FROM members" 2>/dev/null || echo "0")
  mtc=$(pg_exec -tAc "SELECT count(*) FROM meetings" 2>/dev/null || echo "0")

  echo ""
  echo -e "${BLUE}============================================${NC}"
  echo -e "${BLUE}  AG-VOTE — Demo A-Z prete !${NC}"
  echo -e "${BLUE}============================================${NC}"
  echo ""
  echo "  Base : $DB_NAME | Tables: $tc | Users: $uc | Membres: $mc | Seances: $mtc"
  echo ""
  echo "  Lancer le serveur :"
  echo "    php -S 0.0.0.0:8080 -t public"
  echo ""
  echo "  Connexions :"
  echo "    admin     : admin@ag-vote.local     / Admin2026!"
  echo "    operator  : operator@ag-vote.local  / Operator2026!"
  echo "    president : president@ag-vote.local / President2026!"
  echo "    auditor   : auditor@ag-vote.local   / Auditor2026!"
  echo "    viewer    : viewer@ag-vote.local    / Viewer2026!"
  echo ""
  echo "  Pages :"
  echo "    Login      : http://localhost:8080/login.html"
  echo "    Admin      : http://localhost:8080/admin.htmx.html"
  echo "    Operator   : http://localhost:8080/operator.htmx.html"
  echo "    President  : http://localhost:8080/president.htmx.html"
  echo ""
  echo "  Meeting demo A-Z :"
  echo "    ID     : $MEETING_ID_DEMO_AZ"
  echo "    Statut : scheduled"
  echo ""
  echo "  psql :"
  echo "    PGPASSWORD=$DB_PASS psql -U $DB_USER -d $DB_NAME -h $DB_HOST"
  echo ""
  echo -e "${BLUE}============================================${NC}"
  echo ""
}

reset_db() {
  warn "ATTENTION : SUPPRESSION de la base '$DB_NAME'."
  read -p "Confirmer (oui/non) ? " confirm
  if [ "${confirm:-}" != "oui" ]; then
    info "Annule."
    exit 0
  fi
  sudo -u postgres psql -c "DROP DATABASE IF EXISTS $DB_NAME;" >/dev/null 2>&1 || true
  sudo -u postgres psql -c "DROP ROLE IF EXISTS $DB_USER;" >/dev/null 2>&1 || true
  log "Base supprimee"
}

main() {
  echo ""
  echo "========================================="
  echo "  AG-VOTE — Setup Demo A-Z"
  echo "========================================="
  echo ""

  check_postgres

  case "${1:-}" in
    --seed-only)
      apply_demo_seeds
      ;;
    --reset)
      reset_db
      create_user_and_db
      apply_schema
      apply_migrations
      apply_demo_seeds
      run_composer
      setup_env
      ;;
    *)
      create_user_and_db
      apply_schema
      apply_migrations
      apply_demo_seeds
      run_composer
      setup_env
      ;;
  esac

  verify
}

main "$@"
