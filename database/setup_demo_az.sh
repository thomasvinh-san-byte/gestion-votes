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
# ARCHITECTURE :
#   Phase A (admin) : CREATE ROLE / CREATE DATABASE / extensions
#                     → sudo -u postgres psql (seul endroit qui l'utilise)
#   Phase B (app)   : schema, migrations, seeds
#                     → PGPASSWORD=... psql -U vote_app (user applicatif)
#                     → pas de probleme de permissions fichier
#                     → tables creees en tant que vote_app (pas besoin de OWNER TO)
#
# Ce script charge UNIQUEMENT ce qui est necessaire pour la demo A-Z :
#   - schema-master.sql (toutes les tables + migrations consolidees)
#   - migrations/*.sql (idempotent, no-op si schema recent)
#   - 01_minimal.sql (tenant, policies, users RBAC de base)
#   - 02_test_users.sql (comptes admin/operator/president/auditor/viewer/votant)
#   - 08_demo_az.sql (seance scheduled + 10 membres + 2 resolutions)
#
# IMPORTANT:
#   - ON_ERROR_STOP=1 partout : au moindre ERROR SQL, le script s'arrete.
#   - set -euo pipefail : au moindre echec bash, le script s'arrete.
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

# =============================================================================
# Phase A : admin postgres (CREATE ROLE, CREATE DATABASE, extensions)
# =============================================================================
pg_admin() {
  sudo -u postgres psql -X -v ON_ERROR_STOP=1 -q "$@"
}

# =============================================================================
# Phase B : user applicatif (schema, migrations, seeds)
#   → pas de sudo -u postgres → pas de Permission denied sur les fichiers
#   → les tables sont creees par vote_app → pas besoin de ALTER TABLE OWNER
# =============================================================================
pg_app() {
  PGPASSWORD="$DB_PASS" psql -X -v ON_ERROR_STOP=1 -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -q "$@"
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

# Phase A : bootstrap (admin postgres uniquement)
create_user_and_db() {
  info "Phase A : creation user '$DB_USER' + base '$DB_NAME'..."

  local user_exists db_exists
  user_exists=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" 2>/dev/null || echo "")
  if [ "$user_exists" = "1" ]; then
    pg_admin -c "ALTER ROLE $DB_USER WITH PASSWORD '$DB_PASS';"
  else
    pg_admin -c "CREATE ROLE $DB_USER WITH LOGIN PASSWORD '$DB_PASS' NOSUPERUSER NOCREATEDB NOCREATEROLE;"
  fi

  db_exists=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" 2>/dev/null || echo "")
  if [ "$db_exists" != "1" ]; then
    pg_admin -c "CREATE DATABASE $DB_NAME OWNER $DB_USER ENCODING 'UTF8';"
  fi

  # Extensions + privileges (doit etre fait en superuser)
  pg_admin -d "$DB_NAME" -c "
    CREATE EXTENSION IF NOT EXISTS pgcrypto;
    CREATE EXTENSION IF NOT EXISTS citext;
    GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
    GRANT USAGE ON SCHEMA public TO $DB_USER;
    GRANT ALL PRIVILEGES ON SCHEMA public TO $DB_USER;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO $DB_USER;
    ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO $DB_USER;
  "

  # Transferer ownership du schema public a vote_app pour qu'il puisse creer des tables
  pg_admin -d "$DB_NAME" -c "ALTER SCHEMA public OWNER TO $DB_USER;"

  log "Phase A terminee — user + base prets"
}

# Phase B : schema (user applicatif)
apply_schema() {
  info "Phase B : schema..."
  pg_app -f "$SCRIPT_DIR/schema-master.sql"
  log "Schema applique"
}

# Phase B : migrations (user applicatif)
apply_migrations() {
  info "Phase B : migrations..."
  shopt -s nullglob
  for f in "$SCRIPT_DIR"/migrations/*.sql; do
    info "  Migration: $(basename "$f")"
    pg_app -f "$f"
  done
  shopt -u nullglob
  log "Migrations appliquees"
}

# Phase B : seeds demo (user applicatif)
apply_demo_seeds() {
  info "Phase B : seed 01_minimal.sql (tenant + policies + users)..."
  pg_app -f "$SCRIPT_DIR/seeds/01_minimal.sql"

  info "Phase B : seed 02_test_users.sql (comptes admin/operator/president/auditor/viewer/votant)..."
  pg_app -f "$SCRIPT_DIR/seeds/02_test_users.sql"

  info "Phase B : seed 08_demo_az.sql (seance + 10 membres + 2 resolutions)..."
  pg_app -f "$SCRIPT_DIR/seeds/08_demo_az.sql"

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
    # Demo mode : CSRF off pour simplifier les tests curl
    grep -q '^CSRF_ENABLED=' "$ENV_FILE" && sed -i "s|^CSRF_ENABLED=.*|CSRF_ENABLED=0|" "$ENV_FILE" || echo "CSRF_ENABLED=0" >> "$ENV_FILE"
    log ".env mis a jour (CSRF_ENABLED=0 pour demo)"
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
  tc=$(pg_app -tAc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'" 2>/dev/null || echo "0")
  uc=$(pg_app -tAc "SELECT count(*) FROM users" 2>/dev/null || echo "0")
  mc=$(pg_app -tAc "SELECT count(*) FROM members" 2>/dev/null || echo "0")
  mtc=$(pg_app -tAc "SELECT count(*) FROM meetings" 2>/dev/null || echo "0")

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
  echo "  Smoke test :"
  echo "    bash scripts/smoke_test.sh"
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
