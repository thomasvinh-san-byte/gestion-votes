#!/usr/bin/env bash
# =============================================================================
# database/setup.sh — Initialisation complète de la base de données AG-VOTE
# =============================================================================
#
# Usage:
#   sudo bash database/setup.sh              # setup complet (user + schema + seeds)
#   sudo bash database/setup.sh --schema     # schéma + migrations uniquement
#   sudo bash database/setup.sh --seed       # seeds uniquement (tous)
#   sudo bash database/setup.sh --seed-only  # alias de --seed
#   sudo bash database/setup.sh --migrate    # migrations uniquement
#   sudo bash database/setup.sh --no-demo    # setup complet SANS demo ni e2e
#   sudo bash database/setup.sh --clean      # nettoie TOUTES les données et re-seed
#   sudo bash database/setup.sh --reset      # SUPPRIME et recrée tout
#
# Les seeds sont chargés depuis database/seeds/ par ordre alphabétique :
#   01_minimal.sql    — tenant, politiques quorum/vote, users RBAC
#   02_test_users.sql — comptes de test avec mots de passe et clés API
#   03_demo.sql       — séance live, membres, motions, bulletins (optionnel)
#   04_e2e.sql        — séance E2E complète (optionnel)
#   05-07_test_*.sql  — jeux de données de recette (optionnel)
#
# Idempotent : peut être relancé autant de fois que nécessaire.
#
# Prérequis:
#   - PostgreSQL 14+ installé et démarré
#   - Exécuté en tant que root ou utilisateur pouvant sudo vers postgres
# =============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Couleurs (désactivées hors terminal)
# ---------------------------------------------------------------------------
if [ -t 1 ]; then
    RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
else
    RED=''; GREEN=''; YELLOW=''; BLUE=''; NC=''
fi

# ---------------------------------------------------------------------------
# Configuration (surchargeable par variables d'env ou .env)
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
SEEDS_DIR="$SCRIPT_DIR/seeds"
ENV_FILE="$PROJECT_DIR/.env"

DB_NAME="${DB_NAME:-vote_app}"
DB_USER="${DB_USER:-vote_app}"
DB_PASS="${DB_PASS:-vote_app_dev_2026}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"

# Lire .env si présent (les variables d'env explicites priment)
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

# ---------------------------------------------------------------------------
# Validation des chemins
# ---------------------------------------------------------------------------
if [ ! -f "$SCRIPT_DIR/schema.sql" ]; then
    echo -e "${RED}[ERR]${NC} Fichier introuvable : $SCRIPT_DIR/schema.sql" >&2
    echo "      Vérifiez que le script est lancé depuis la racine du projet." >&2
    exit 1
fi

if [ ! -d "$SEEDS_DIR" ]; then
    echo -e "${RED}[ERR]${NC} Répertoire introuvable : $SEEDS_DIR" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------
log()  { echo -e "${GREEN}[OK]${NC}   $1"; }
warn() { echo -e "${YELLOW}[..]${NC}   $1"; }
err()  { echo -e "${RED}[ERR]${NC}  $1" >&2; }
info() { echo -e "${BLUE}[INFO]${NC} $1"; }

# ---------------------------------------------------------------------------
# Helper : exécuter du SQL en tant que postgres
# ---------------------------------------------------------------------------
pg_exec() { sudo -u postgres psql -d "$DB_NAME" -q "$@"; }

# =============================================================================
# Vérifications
# =============================================================================
check_postgres() {
    if ! command -v psql &>/dev/null; then
        err "psql non trouvé. Installez PostgreSQL :"
        err "  sudo apt install postgresql postgresql-contrib"
        exit 1
    fi

    if ! pg_isready -q 2>/dev/null; then
        warn "PostgreSQL ne semble pas démarré. Tentative de démarrage..."
        if command -v pg_ctlcluster &>/dev/null; then
            local ver
            ver=$(pg_lsclusters -h | head -1 | awk '{print $1}')
            pg_ctlcluster "$ver" main start 2>/dev/null || true
        elif command -v service &>/dev/null; then
            sudo service postgresql start 2>/dev/null || true
        fi
        sleep 1
        if ! pg_isready -q 2>/dev/null; then
            err "Impossible de démarrer PostgreSQL."
            exit 1
        fi
    fi
    log "PostgreSQL est en cours d'exécution"
}

# =============================================================================
# Création utilisateur + base
# =============================================================================
create_user_and_db() {
    info "Création de l'utilisateur PostgreSQL '$DB_USER'..."

    local user_exists
    user_exists=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" 2>/dev/null || echo "")

    if [ "$user_exists" = "1" ]; then
        sudo -u postgres psql -q -c "ALTER ROLE $DB_USER WITH PASSWORD '$DB_PASS';" 2>/dev/null
        warn "L'utilisateur '$DB_USER' existe déjà — mot de passe synchronisé"
    else
        sudo -u postgres psql -c "CREATE ROLE $DB_USER WITH LOGIN PASSWORD '$DB_PASS' NOSUPERUSER NOCREATEDB NOCREATEROLE;" 2>/dev/null
        log "Utilisateur '$DB_USER' créé"
    fi

    info "Création de la base de données '$DB_NAME'..."

    local db_exists
    db_exists=$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" 2>/dev/null || echo "")

    if [ "$db_exists" = "1" ]; then
        warn "La base '$DB_NAME' existe déjà"
    else
        sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_USER ENCODING 'UTF8';" 2>/dev/null
        log "Base de données '$DB_NAME' créée"
    fi

    # Extensions et permissions
    pg_exec -c "
        CREATE EXTENSION IF NOT EXISTS pgcrypto;
        CREATE EXTENSION IF NOT EXISTS citext;
        GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
        GRANT USAGE ON SCHEMA public TO $DB_USER;
        GRANT ALL PRIVILEGES ON SCHEMA public TO $DB_USER;
        ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO $DB_USER;
        ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO $DB_USER;
    " 2>/dev/null
    log "Extensions et permissions configurées"

    # pg_hba.conf : vérifier/ajouter la règle pour le socket local
    local hba
    hba=$(sudo -u postgres psql -tAc "SHOW hba_file" 2>/dev/null)
    if [ -n "$hba" ] && ! grep -q "^local.*$DB_NAME.*$DB_USER.*scram-sha-256" "$hba" 2>/dev/null; then
        local line_num
        line_num=$(grep -n "^local" "$hba" | head -1 | cut -d: -f1)
        if [ -n "$line_num" ]; then
            sudo sed -i "${line_num}i\\local   $DB_NAME        $DB_USER                                scram-sha-256" "$hba"
            sudo -u postgres psql -c "SELECT pg_reload_conf();" &>/dev/null
            log "Règle pg_hba.conf ajoutée pour '$DB_USER'"
        fi
    fi
}

# =============================================================================
# Schéma
# =============================================================================
apply_schema() {
    info "Application du schéma..."
    pg_exec < "$SCRIPT_DIR/schema.sql" 2>&1 | grep -E "^(ERROR|FATAL)" || true
    log "Schéma appliqué"

    grant_permissions
}

# =============================================================================
# Migrations
# =============================================================================
apply_migrations() {
    if [ ! -d "$SCRIPT_DIR/migrations" ]; then
        warn "Aucun répertoire migrations/ trouvé"
        return
    fi

    info "Application des migrations..."
    for f in "$SCRIPT_DIR"/migrations/*.sql; do
        [ -f "$f" ] || continue
        local fname
        fname=$(basename "$f")
        info "  → $fname"
        pg_exec < "$f" 2>&1 | grep -E "^(ERROR|FATAL)" || true
    done

    grant_permissions
    log "Migrations appliquées"
}

# =============================================================================
# Seeds
# =============================================================================
apply_seeds() {
    local no_demo="${1:-false}"

    info "Application des seeds..."
    for f in "$SEEDS_DIR"/*.sql; do
        [ -f "$f" ] || continue
        local fname
        fname=$(basename "$f")

        # En mode --no-demo, on ne charge que 01 et 02
        if [ "$no_demo" = "true" ]; then
            case "$fname" in
                01_*|02_*) ;;  # toujours chargés
                *) info "  ⊘ $fname (ignoré, mode --no-demo)"; continue ;;
            esac
        fi

        info "  → $fname"
        pg_exec < "$f" 2>&1 | grep -E "^(ERROR|FATAL)" || true
    done

    grant_permissions
    log "Seeds appliqués"
}

# =============================================================================
# Permissions (appelé après chaque étape SQL)
# =============================================================================
grant_permissions() {
    pg_exec -c "
        GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO $DB_USER;
        GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO $DB_USER;
    " 2>/dev/null
}

# =============================================================================
# Configuration .env
# =============================================================================
setup_env() {
    local env_file="$PROJECT_DIR/.env"

    if [ ! -f "$env_file" ]; then
        if [ -f "$PROJECT_DIR/.env.example" ]; then
            cp "$PROJECT_DIR/.env.example" "$env_file"
            info "Fichier .env créé depuis .env.example"
        fi
    fi

    if [ -f "$env_file" ]; then
        sed -i "s|^DB_USER=.*|DB_USER=$DB_USER|" "$env_file"
        sed -i "s|^DB_PASS=.*|DB_PASS=$DB_PASS|" "$env_file"
        sed -i "s|^DB_DSN=.*|DB_DSN=pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME|" "$env_file"
        log "Fichier .env mis à jour"
    fi
}

# =============================================================================
# Nettoyage complet des donnees (conserve le schema)
# =============================================================================
clean_data() {
    info "Nettoyage complet des donnees..."
    pg_exec -c "
        -- Detacher les motions courantes pour eviter les FK
        UPDATE meetings SET current_motion_id = NULL;
        -- Supprimer dans l'ordre inverse des dependances
        DELETE FROM ballots;
        DELETE FROM attendances;
        DELETE FROM proxies;
        DELETE FROM motions;
        DELETE FROM agendas;
        DELETE FROM meeting_roles;
        DELETE FROM meetings;
        DELETE FROM members;
        DELETE FROM auth_failures;
        DELETE FROM audit_events;
        DELETE FROM users;
        DELETE FROM vote_policies;
        DELETE FROM quorum_policies;
        DELETE FROM tenants;
    " 2>&1 | grep -E "^(ERROR|FATAL)" || true
    log "Donnees nettoyees"
}

# =============================================================================
# Reset (destructif)
# =============================================================================
reset_db() {
    warn "ATTENTION : cette opération va SUPPRIMER la base '$DB_NAME' et la recréer."
    read -p "Êtes-vous sûr ? (oui/non) " confirm
    if [ "$confirm" != "oui" ]; then
        info "Annulé."
        exit 0
    fi

    sudo -u postgres psql -c "DROP DATABASE IF EXISTS $DB_NAME;" 2>/dev/null
    sudo -u postgres psql -c "DROP ROLE IF EXISTS $DB_USER;" 2>/dev/null
    log "Base et utilisateur supprimés"

    create_user_and_db
    apply_schema
    apply_migrations
    apply_seeds "false"
    setup_env
}

# =============================================================================
# Vérification finale
# =============================================================================
verify() {
    info "Vérification de la connexion..."

    local table_count user_count member_count meeting_count
    table_count=$(pg_exec -tAc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'" 2>/dev/null || echo "0")
    user_count=$(pg_exec -tAc "SELECT count(*) FROM users" 2>/dev/null || echo "0")
    member_count=$(pg_exec -tAc "SELECT count(*) FROM members" 2>/dev/null || echo "0")
    meeting_count=$(pg_exec -tAc "SELECT count(*) FROM meetings" 2>/dev/null || echo "0")

    if [ "$table_count" -gt 0 ] 2>/dev/null; then
        log "Connexion OK — $table_count tables trouvées"
    else
        err "Échec de connexion ou aucune table trouvée"
        exit 1
    fi

    # Résumé final
    echo ""
    echo -e "${BLUE}=========================================${NC}"
    echo -e "${BLUE}  AG-VOTE — Base de données prête${NC}"
    echo -e "${BLUE}=========================================${NC}"
    echo ""
    echo "  Base de données"
    echo "  ───────────────"
    echo "    Base     : $DB_NAME"
    echo "    Rôle     : $DB_USER"
    echo "    Host     : $DB_HOST:$DB_PORT"
    echo "    Tables   : $table_count"
    echo "    Users    : $user_count"
    echo "    Membres  : $member_count"
    echo "    Séances  : $meeting_count"
    echo ""
    echo "  Lancer le serveur"
    echo "  ─────────────────"
    echo "    php -S 0.0.0.0:8080 -t public"
    echo ""
    echo "  Connexion"
    echo "  ─────────"
    echo "    URL  : http://localhost:8080/login.html"
    echo ""
    echo "  Identifiants de test (email / mot de passe)"
    echo "  ────────────────────────────────────────────"
    echo "    admin     : admin@ag-vote.local     / Admin2026!"
    echo "    operator  : operator@ag-vote.local  / Operator2026!"
    echo "    president : president@ag-vote.local / President2026!"
    echo "    votant    : votant@ag-vote.local    / Votant2026!"
    echo "    auditor   : auditor@ag-vote.local   / Auditor2026!"
    echo "    viewer    : viewer@ag-vote.local    / Viewer2026!"
    echo ""
    echo "  Pages principales"
    echo "  ─────────────────"
    echo "    Admin      : http://localhost:8080/admin.htmx.html"
    echo "    Opérateur  : http://localhost:8080/operator.htmx.html"
    echo "    Président  : http://localhost:8080/president.htmx.html"
    echo "    Vote       : http://localhost:8080/vote.htmx.html"
    echo ""
    echo "  Connexion psql"
    echo "  ──────────────"
    echo "    PGPASSWORD=$DB_PASS psql -U $DB_USER -d $DB_NAME -h $DB_HOST"
    echo ""
    echo "  Accès distant"
    echo "  ─────────────"
    echo "    Remplacer localhost par l'IP de la machine."
    echo "    Ajouter l'origine dans .env :"
    echo "    CORS_ALLOWED_ORIGINS=http://localhost:8080,http://<IP>:8080"
    echo ""
    echo -e "${BLUE}=========================================${NC}"
    echo ""
}

# =============================================================================
# Main
# =============================================================================
main() {
    echo ""
    echo "========================================="
    echo "  AG-VOTE — Setup base de données"
    echo "========================================="
    echo ""

    check_postgres

    case "${1:-}" in
        --schema)
            apply_schema
            apply_migrations
            ;;
        --seed|--seed-only)
            apply_seeds "false"
            ;;
        --clean)
            clean_data
            apply_seeds "false"
            grant_permissions
            ;;
        --migrate)
            apply_migrations
            ;;
        --no-demo)
            create_user_and_db
            apply_schema
            apply_migrations
            apply_seeds "true"
            setup_env
            ;;
        --reset)
            reset_db
            ;;
        *)
            create_user_and_db
            apply_schema
            apply_migrations
            apply_seeds "false"
            setup_env
            ;;
    esac

    verify
}

main "$@"
