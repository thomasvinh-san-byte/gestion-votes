#!/usr/bin/env bash
# =============================================================================
# scripts/install-deps.sh — Installation des dépendances système AG-VOTE
# =============================================================================
#
# Installe les paquets Ubuntu/Debian nécessaires au projet :
#   - PHP 8.x + extensions (pdo_pgsql, mbstring, xml, zip, gd, curl)
#   - PostgreSQL + contrib
#   - Outils : git, unzip, curl, composer
#
# Usage :
#   sudo bash scripts/install-deps.sh
#
# Ce script n'effectue AUCUNE configuration de base de données.
# Pour initialiser la BDD, utiliser ensuite : sudo bash database/setup.sh
# =============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Vérifications
# ---------------------------------------------------------------------------
if [[ $EUID -ne 0 ]]; then
    echo "[ERR] Ce script doit être lancé avec sudo ou en root."
    echo "      Usage : sudo bash scripts/install-deps.sh"
    exit 1
fi

# ---------------------------------------------------------------------------
# Couleurs
# ---------------------------------------------------------------------------
if [ -t 1 ]; then
    GREEN='\033[0;32m'; BLUE='\033[0;34m'; NC='\033[0m'
else
    GREEN=''; BLUE=''; NC=''
fi
ok()   { echo -e "${GREEN}[OK]${NC} $1"; }
info() { echo -e "${BLUE}[..]${NC} $1"; }

# ---------------------------------------------------------------------------
# Installation
# ---------------------------------------------------------------------------
echo ""
echo "========================================="
echo "  AG-VOTE — Installation des dépendances"
echo "========================================="
echo ""

info "Mise à jour des dépôts APT..."
apt-get update -y

info "Installation des outils de base..."
apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl

info "Installation de PHP et des extensions..."
apt-get install -y --no-install-recommends \
    php \
    php-cli \
    php-pgsql \
    php-mbstring \
    php-xml \
    php-zip \
    php-gd \
    php-curl

info "Installation de PostgreSQL..."
apt-get install -y --no-install-recommends \
    postgresql \
    postgresql-contrib

info "Installation de Composer..."
if ! command -v composer >/dev/null 2>&1; then
    apt-get install -y composer
    ok "Composer installé"
else
    ok "Composer déjà présent"
fi

# ---------------------------------------------------------------------------
# Nettoyage
# ---------------------------------------------------------------------------
apt-get autoremove -y 2>/dev/null || true
apt-get clean 2>/dev/null || true

# ---------------------------------------------------------------------------
# Vérification
# ---------------------------------------------------------------------------
echo ""
echo -e "${BLUE}=========================================${NC}"
echo -e "${BLUE}  Dépendances système installées${NC}"
echo -e "${BLUE}=========================================${NC}"
echo ""
echo "  Versions détectées"
echo "  ──────────────────"
echo "    PHP        : $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo 'non détecté')"
echo "    PostgreSQL : $(psql --version 2>/dev/null | head -1 || echo 'non détecté')"
echo "    Composer   : $(composer --version 2>/dev/null | head -1 || echo 'non détecté')"
echo "    Git        : $(git --version 2>/dev/null || echo 'non détecté')"
echo ""
echo "  Étapes suivantes"
echo "  ────────────────"
echo "    1. Installer les dépendances PHP :"
echo "       composer install"
echo ""
echo "    2. Initialiser la base de données :"
echo "       sudo bash database/setup.sh"
echo ""
echo "    3. Lancer le serveur de développement :"
echo "       php -S 0.0.0.0:8080 -t public"
echo ""
echo -e "${BLUE}=========================================${NC}"
echo ""
