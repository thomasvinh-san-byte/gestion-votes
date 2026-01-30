#!/bin/bash
# scripts/setup_db.sh — Installation idempotente de la base AG-Vote
# Usage : bash scripts/setup_db.sh [--no-demo]
# Peut etre relance autant de fois que necessaire sans casser l'existant.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DB_DIR="$PROJECT_DIR/database"
ENV_FILE="$PROJECT_DIR/.env"

SKIP_DEMO=false
[[ "${1:-}" == "--no-demo" ]] && SKIP_DEMO=true

# -------------------------------------------------------------------
# 0. Lire la configuration depuis .env
# -------------------------------------------------------------------
# Le script lit DB_USER, DB_PASS et DB_DSN depuis .env.
# DB_HOST et DB_NAME sont extraits du DSN (pgsql:host=...;dbname=...).
# Valeurs par defaut si .env absent ou variable manquante.

DB_USER="vote_app"
DB_PASS="vote_app_dev_2026"
DB_HOST="localhost"
DB_NAME="vote_app"

if [ -f "$ENV_FILE" ]; then
  while IFS='=' read -r key val; do
    key="${key## }"; key="${key%% }"
    val="${val## }"; val="${val%% }"
    [[ -z "$key" || "$key" == \#* ]] && continue
    case "$key" in
      DB_USER) DB_USER="$val" ;;
      DB_PASS) DB_PASS="$val" ;;
      DB_DSN)
        # Extraire host et dbname du DSN pgsql:host=X;port=Y;dbname=Z
        _host=$(echo "$val" | sed -n 's/.*host=\([^;]*\).*/\1/p')
        _name=$(echo "$val" | sed -n 's/.*dbname=\([^;]*\).*/\1/p')
        [ -n "$_host" ] && DB_HOST="$_host"
        [ -n "$_name" ] && DB_NAME="$_name"
        ;;
    esac
  done < "$ENV_FILE"
  echo "[..] Configuration lue depuis $ENV_FILE"
  echo "     DB_USER=$DB_USER  DB_NAME=$DB_NAME  DB_HOST=$DB_HOST"
else
  echo "[..] Pas de .env, valeurs par defaut (DB_USER=$DB_USER DB_NAME=$DB_NAME)"
fi

# -- Couleurs (si terminal)
if [ -t 1 ]; then
  GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
else
  GREEN=''; YELLOW=''; RED=''; NC=''
fi

info()  { echo -e "${GREEN}[OK]${NC} $1"; }
warn()  { echo -e "${YELLOW}[..] $1${NC}"; }
fail()  { echo -e "${RED}[ERR] $1${NC}" >&2; exit 1; }

# -------------------------------------------------------------------
# 1. Verifier que PostgreSQL est accessible
# -------------------------------------------------------------------
warn "Verification de PostgreSQL..."
if ! pg_isready -q 2>/dev/null; then
  warn "PostgreSQL non demarre, tentative de demarrage..."
  sudo service postgresql start || fail "Impossible de demarrer PostgreSQL"
  sleep 1
  pg_isready -q || fail "PostgreSQL ne repond toujours pas"
fi
info "PostgreSQL est accessible"

# -------------------------------------------------------------------
# 2. Creer le role applicatif (idempotent)
# -------------------------------------------------------------------
warn "Creation du role $DB_USER..."
sudo -u postgres psql -v ON_ERROR_STOP=0 -q <<SQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '$DB_USER') THEN
    CREATE ROLE $DB_USER LOGIN PASSWORD '$DB_PASS';
    RAISE NOTICE 'Role $DB_USER cree';
  ELSE
    RAISE NOTICE 'Role $DB_USER existe deja';
  END IF;
END \$\$;
SQL
info "Role $DB_USER OK"

# -------------------------------------------------------------------
# 3. Creer la base de donnees (idempotent)
# -------------------------------------------------------------------
warn "Creation de la base $DB_NAME..."
if sudo -u postgres psql -lqt | cut -d \| -f 1 | grep -qw "$DB_NAME"; then
  info "Base $DB_NAME existe deja"
else
  sudo -u postgres createdb "$DB_NAME" -O "$DB_USER"
  info "Base $DB_NAME creee"
fi

# -------------------------------------------------------------------
# 4. Appliquer le schema (idempotent, superuser pour CREATE EXTENSION)
# -------------------------------------------------------------------
warn "Application du schema..."
sudo -u postgres psql -d "$DB_NAME" -q -f "$DB_DIR/schema.sql"
info "Schema applique"

# -------------------------------------------------------------------
# 5. Charger les seeds (idempotents, ON CONFLICT)
# -------------------------------------------------------------------
warn "Chargement seed_minimal.sql..."
PGPASSWORD="$DB_PASS" psql -U "$DB_USER" -d "$DB_NAME" -h "$DB_HOST" -q \
  -f "$DB_DIR/seed_minimal.sql"
info "seed_minimal.sql OK"

warn "Chargement test_users.sql..."
PGPASSWORD="$DB_PASS" psql -U "$DB_USER" -d "$DB_NAME" -h "$DB_HOST" -q \
  -f "$DB_DIR/seeds/test_users.sql"
info "test_users.sql OK"

# -------------------------------------------------------------------
# 6. Donnees de demo (optionnel)
# -------------------------------------------------------------------
if [ "$SKIP_DEMO" = false ]; then
  warn "Chargement seed_demo.sql..."
  PGPASSWORD="$DB_PASS" psql -U "$DB_USER" -d "$DB_NAME" -h "$DB_HOST" -q \
    -f "$DB_DIR/seed_demo.sql"
  info "seed_demo.sql OK"
else
  info "Demo skip (--no-demo)"
fi

# -------------------------------------------------------------------
# 7. Verification
# -------------------------------------------------------------------
TABLE_COUNT=$(PGPASSWORD="$DB_PASS" psql -U "$DB_USER" -d "$DB_NAME" -h "$DB_HOST" -tAc \
  "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")
USER_COUNT=$(PGPASSWORD="$DB_PASS" psql -U "$DB_USER" -d "$DB_NAME" -h "$DB_HOST" -tAc \
  "SELECT count(*) FROM users")

echo ""
echo "========================================="
echo "  AG-Vote : base de donnees prete"
echo "========================================="
echo "  Tables  : $TABLE_COUNT"
echo "  Users   : $USER_COUNT"
echo "  Base    : $DB_NAME"
echo "  Role    : $DB_USER"
echo "========================================="
echo ""
echo "Lancer le serveur :"
echo "  php -S 0.0.0.0:8080 -t public"
echo ""
echo "Se connecter :"
echo "  http://localhost:8080/login.html"
echo "  Cle admin : admin-key-2024-secret"
echo ""
echo "Acces depuis un autre poste :"
echo "  1. Remplacer localhost par l'IP de la VM"
echo "     http://<IP>:8080/login.html"
echo "  2. Ajouter cette origine dans .env :"
echo "     CORS_ALLOWED_ORIGINS=http://localhost:8080,http://<IP>:8080"
echo "========================================="
