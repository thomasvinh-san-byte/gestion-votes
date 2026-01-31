#!/bin/bash
# scripts/setup_db.sh — Installation idempotente de la base AG-Vote
# Usage : sudo bash scripts/setup_db.sh [--no-demo]
# Peut etre relance autant de fois que necessaire sans casser l'existant.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DB_DIR="$PROJECT_DIR/database"
ENV_FILE="$PROJECT_DIR/.env"

# Validation des chemins resolus
if [ ! -d "$DB_DIR" ]; then
  echo "[ERR] Repertoire introuvable : $DB_DIR" >&2
  echo "      Verifiez que le script est lance depuis la racine du projet." >&2
  exit 1
fi
if [ ! -f "$DB_DIR/schema.sql" ]; then
  echo "[ERR] Fichier introuvable : $DB_DIR/schema.sql" >&2
  exit 1
fi

SKIP_DEMO=false
[[ "${1:-}" == "--no-demo" ]] && SKIP_DEMO=true

# -------------------------------------------------------------------
# 0. Lire la configuration depuis .env
# -------------------------------------------------------------------
DB_USER="vote_app"
DB_PASS="vote_app_dev_2026"
DB_HOST="localhost"
DB_NAME="vote_app"
DB_PORT="5432"

if [ -f "$ENV_FILE" ]; then
  while IFS='=' read -r key val; do
    key="${key## }"; key="${key%% }"
    val="${val## }"; val="${val%% }"
    [[ -z "$key" || "$key" == \#* ]] && continue
    case "$key" in
      DB_USER) DB_USER="$val" ;;
      DB_PASS) DB_PASS="$val" ;;
      DB_DSN)
        _host=$(echo "$val" | sed -n 's/.*host=\([^;]*\).*/\1/p')
        _name=$(echo "$val" | sed -n 's/.*dbname=\([^;]*\).*/\1/p')
        _port=$(echo "$val" | sed -n 's/.*port=\([^;]*\).*/\1/p')
        [ -n "$_host" ] && DB_HOST="$_host"
        [ -n "$_name" ] && DB_NAME="$_name"
        [ -n "$_port" ] && DB_PORT="$_port"
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
  GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; BLUE='\033[0;34m'; NC='\033[0m'
else
  GREEN=''; YELLOW=''; RED=''; BLUE=''; NC=''
fi

ok()   { echo -e "${GREEN}[OK]${NC} $1"; }
warn() { echo -e "${YELLOW}[..]${NC} $1"; }
fail() { echo -e "${RED}[ERR]${NC} $1" >&2; exit 1; }

# -------------------------------------------------------------------
# Helper : executer du SQL en tant que postgres (pipe stdin, pas -f)
# -------------------------------------------------------------------
pg_exec() { sudo -u postgres psql -d "$DB_NAME" -q "$@"; }

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
ok "PostgreSQL est accessible"

# -------------------------------------------------------------------
# 2. Creer le role applicatif (idempotent)
# -------------------------------------------------------------------
warn "Creation du role $DB_USER..."
sudo -u postgres psql -v ON_ERROR_STOP=0 -q <<SQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '$DB_USER') THEN
    CREATE ROLE $DB_USER LOGIN PASSWORD '$DB_PASS';
    RAISE NOTICE 'Role % cree', '$DB_USER';
  ELSE
    ALTER ROLE $DB_USER WITH PASSWORD '$DB_PASS';
    RAISE NOTICE 'Role % existe deja — mot de passe synchronise', '$DB_USER';
  END IF;
END \$\$;
SQL
ok "Role $DB_USER OK"

# -------------------------------------------------------------------
# 3. Creer la base de donnees (idempotent)
# -------------------------------------------------------------------
warn "Creation de la base $DB_NAME..."
if sudo -u postgres psql -lqt | cut -d \| -f 1 | grep -qw "$DB_NAME"; then
  ok "Base $DB_NAME existe deja"
else
  sudo -u postgres createdb "$DB_NAME" -O "$DB_USER"
  ok "Base $DB_NAME creee"
fi

# -------------------------------------------------------------------
# 4. Appliquer le schema (idempotent via IF NOT EXISTS / CREATE OR REPLACE)
# -------------------------------------------------------------------
warn "Application du schema..."
pg_exec < "$DB_DIR/schema.sql"
ok "Schema applique"

# -------------------------------------------------------------------
# 5. Permissions : s'assurer que vote_app peut acceder aux tables
# -------------------------------------------------------------------
warn "Attribution des permissions a $DB_USER..."
pg_exec <<SQL
GRANT USAGE ON SCHEMA public TO $DB_USER;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO $DB_USER;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO $DB_USER;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO $DB_USER;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO $DB_USER;
SQL
ok "Permissions OK"

# -------------------------------------------------------------------
# 6. Charger les seeds (idempotents, ON CONFLICT)
#    Executes en tant que postgres pour eviter tout probleme de droits.
# -------------------------------------------------------------------
if [ -f "$DB_DIR/seed_minimal.sql" ]; then
  warn "Chargement seed_minimal.sql..."
  pg_exec < "$DB_DIR/seed_minimal.sql"
  ok "seed_minimal.sql OK"
fi

if [ -f "$DB_DIR/seeds/test_users.sql" ]; then
  warn "Chargement test_users.sql..."
  pg_exec < "$DB_DIR/seeds/test_users.sql"
  ok "test_users.sql OK"
fi

# -------------------------------------------------------------------
# 7. Donnees de demo (optionnel)
# -------------------------------------------------------------------
if [ "$SKIP_DEMO" = false ] && [ -f "$DB_DIR/seed_demo.sql" ]; then
  warn "Chargement seed_demo.sql..."
  pg_exec < "$DB_DIR/seed_demo.sql"
  ok "seed_demo.sql OK"
else
  ok "Demo skip (--no-demo ou fichier absent)"
fi

# -------------------------------------------------------------------
# 8. Creer .env si absent
# -------------------------------------------------------------------
if [ ! -f "$ENV_FILE" ] && [ -f "$PROJECT_DIR/.env.example" ]; then
  cp "$PROJECT_DIR/.env.example" "$ENV_FILE"
  sed -i "s|^DB_USER=.*|DB_USER=$DB_USER|" "$ENV_FILE"
  sed -i "s|^DB_PASS=.*|DB_PASS=$DB_PASS|" "$ENV_FILE"
  sed -i "s|^DB_DSN=.*|DB_DSN=pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME|" "$ENV_FILE"
  ok "Fichier .env cree depuis .env.example"
fi

# -------------------------------------------------------------------
# 9. Verification finale
# -------------------------------------------------------------------
warn "Verification..."
TABLE_COUNT=$(sudo -u postgres psql -d "$DB_NAME" -tAc \
  "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")
USER_COUNT=$(sudo -u postgres psql -d "$DB_NAME" -tAc \
  "SELECT count(*) FROM users" 2>/dev/null || echo "0")
MEMBER_COUNT=$(sudo -u postgres psql -d "$DB_NAME" -tAc \
  "SELECT count(*) FROM members" 2>/dev/null || echo "0")
MEETING_COUNT=$(sudo -u postgres psql -d "$DB_NAME" -tAc \
  "SELECT count(*) FROM meetings" 2>/dev/null || echo "0")
ok "Verification terminee"

# -------------------------------------------------------------------
# 10. Resume final
# -------------------------------------------------------------------
echo ""
echo -e "${BLUE}=========================================${NC}"
echo -e "${BLUE}  AG-VOTE — Base de donnees prete${NC}"
echo -e "${BLUE}=========================================${NC}"
echo ""
echo "  Base de donnees"
echo "  ───────────────"
echo "    Base     : $DB_NAME"
echo "    Role     : $DB_USER"
echo "    Host     : $DB_HOST:$DB_PORT"
echo "    Tables   : $TABLE_COUNT"
echo "    Users    : $USER_COUNT"
echo "    Membres  : $MEMBER_COUNT"
echo "    Seances  : $MEETING_COUNT"
echo ""
echo "  Lancer le serveur"
echo "  ─────────────────"
echo "    php -S 0.0.0.0:8080 -t public"
echo ""
echo "  Connexion"
echo "  ─────────"
echo "    URL  : http://localhost:8080/login.html"
echo ""
echo "  Cles API de test (header X-Api-Key)"
echo "  ────────────────────────────────────"
echo "    admin    : admin-key-2024-secret"
echo "    operator : operator-key-2024-secret"
echo "    auditor  : auditor-key-2024-secret"
echo "    viewer   : viewer-key-2024-secret"
echo ""
echo "  Pages principales"
echo "  ─────────────────"
echo "    Admin      : http://localhost:8080/admin.htmx.html"
echo "    Operateur  : http://localhost:8080/operator.htmx.html"
echo "    President  : http://localhost:8080/president.htmx.html"
echo "    Vote       : http://localhost:8080/vote.htmx.html"
echo ""
echo "  Connexion psql"
echo "  ──────────────"
echo "    PGPASSWORD=$DB_PASS psql -U $DB_USER -d $DB_NAME -h $DB_HOST"
echo ""
echo "  Acces distant"
echo "  ─────────────"
echo "    Remplacer localhost par l'IP de la machine."
echo "    Ajouter l'origine dans .env :"
echo "    CORS_ALLOWED_ORIGINS=http://localhost:8080,http://<IP>:8080"
echo ""
echo -e "${BLUE}=========================================${NC}"
echo ""
