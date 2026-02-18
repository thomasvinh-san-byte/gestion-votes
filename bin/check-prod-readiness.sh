#!/bin/sh
# =============================================================================
# AG-VOTE — Vérification de préparation production
# =============================================================================
# Usage: ./bin/check-prod-readiness.sh
#
# Vérifie que toutes les variables d'environnement et configurations
# sont correctement définies pour un déploiement en production.
# Code de retour : 0 = prêt, 1 = problèmes détectés.
# =============================================================================

set -e

ERRORS=0
WARNINGS=0

# Colors (if terminal supports them)
if [ -t 1 ]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[0;33m'
    BOLD='\033[1m'
    NC='\033[0m'
else
    RED='' GREEN='' YELLOW='' BOLD='' NC=''
fi

ok()   { printf "${GREEN}  [OK]${NC}  %s\n" "$1"; }
fail() { printf "${RED}[FAIL]${NC}  %s\n" "$1"; ERRORS=$((ERRORS + 1)); }
warn() { printf "${YELLOW}[WARN]${NC}  %s\n" "$1"; WARNINGS=$((WARNINGS + 1)); }

echo ""
echo "${BOLD}=== AG-VOTE : Vérification de préparation production ===${NC}"
echo ""

# ---- Load .env if present ----
if [ -f .env ]; then
    # shellcheck disable=SC1091
    set -a; . ./.env; set +a
fi

# =============================================================================
# 1. APPLICATION
# =============================================================================
echo "${BOLD}1. Application${NC}"

if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "prod" ]; then
    ok "APP_ENV=$APP_ENV"
else
    fail "APP_ENV=$APP_ENV (attendu: production)"
fi

if [ "$APP_DEBUG" = "0" ] || [ -z "$APP_DEBUG" ]; then
    ok "APP_DEBUG=0"
else
    fail "APP_DEBUG=$APP_DEBUG (doit être 0 en prod)"
fi

if [ -z "$APP_SECRET" ]; then
    fail "APP_SECRET non défini"
elif [ "$APP_SECRET" = "dev-secret-do-not-use-in-production-change-me-now-please-64chr" ]; then
    fail "APP_SECRET est la valeur par défaut (générez avec: php -r \"echo bin2hex(random_bytes(32));\")"
elif [ "$APP_SECRET" = "change-me-in-prod" ]; then
    fail "APP_SECRET est le placeholder"
elif [ ${#APP_SECRET} -lt 32 ]; then
    fail "APP_SECRET trop court (${#APP_SECRET} chars, min 32)"
else
    ok "APP_SECRET défini (${#APP_SECRET} chars)"
fi

echo ""

# =============================================================================
# 2. SÉCURITÉ
# =============================================================================
echo "${BOLD}2. Sécurité${NC}"

if [ "$APP_AUTH_ENABLED" = "1" ]; then
    ok "APP_AUTH_ENABLED=1"
else
    fail "APP_AUTH_ENABLED=$APP_AUTH_ENABLED (doit être 1 en prod)"
fi

if [ "$CSRF_ENABLED" = "1" ]; then
    ok "CSRF_ENABLED=1"
else
    fail "CSRF_ENABLED=$CSRF_ENABLED (doit être 1 en prod)"
fi

if [ "$RATE_LIMIT_ENABLED" = "1" ]; then
    ok "RATE_LIMIT_ENABLED=1"
else
    fail "RATE_LIMIT_ENABLED=$RATE_LIMIT_ENABLED (doit être 1 en prod)"
fi

if [ "$LOAD_DEMO_DATA" = "0" ] || [ -z "$LOAD_DEMO_DATA" ]; then
    ok "LOAD_DEMO_DATA=0 (pas de données de test)"
else
    fail "LOAD_DEMO_DATA=$LOAD_DEMO_DATA (doit être 0 en prod !)"
fi

echo ""

# =============================================================================
# 3. BASE DE DONNÉES
# =============================================================================
echo "${BOLD}3. Base de données${NC}"

if [ -z "$DB_DSN" ] && [ -z "$DB_HOST" ]; then
    fail "Aucune connexion DB configurée (DB_DSN ou DB_HOST manquant)"
else
    ok "Connexion DB configurée"
fi

if [ -z "$DB_PASS" ] || [ "$DB_PASS" = "CHANGEZ_CE_MOT_DE_PASSE" ] || [ "$DB_PASS" = "vote_app_dev_2026" ]; then
    fail "DB_PASS est un mot de passe par défaut"
else
    ok "DB_PASS personnalisé"
fi

if echo "$DB_DSN" | grep -q "sslmode=require"; then
    ok "DB_DSN inclut sslmode=require"
else
    warn "DB_DSN sans sslmode=require (recommandé pour les connexions cloud)"
fi

echo ""

# =============================================================================
# 4. CORS
# =============================================================================
echo "${BOLD}4. CORS${NC}"

if echo "$CORS_ALLOWED_ORIGINS" | grep -q "localhost"; then
    warn "CORS_ALLOWED_ORIGINS contient localhost (à retirer en prod)"
elif [ -z "$CORS_ALLOWED_ORIGINS" ]; then
    warn "CORS_ALLOWED_ORIGINS non défini"
else
    ok "CORS_ALLOWED_ORIGINS=$CORS_ALLOWED_ORIGINS"
fi

echo ""

# =============================================================================
# 5. TENANT
# =============================================================================
echo "${BOLD}5. Multi-tenant${NC}"

if [ "$DEFAULT_TENANT_ID" = "aaaaaaaa-1111-2222-3333-444444444444" ] || [ -z "$DEFAULT_TENANT_ID" ]; then
    warn "DEFAULT_TENANT_ID est la valeur par défaut (à personnaliser pour votre orga)"
else
    ok "DEFAULT_TENANT_ID=$DEFAULT_TENANT_ID"
fi

echo ""

# =============================================================================
# RÉSUMÉ
# =============================================================================
echo "${BOLD}=== Résumé ===${NC}"
echo ""

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo "${GREEN}${BOLD}PRÊT POUR LA PRODUCTION${NC}"
    echo ""
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo "${YELLOW}${BOLD}PRESQUE PRÊT${NC} — $WARNINGS avertissement(s) à vérifier"
    echo ""
    exit 0
else
    echo "${RED}${BOLD}PAS PRÊT${NC} — $ERRORS erreur(s) critique(s), $WARNINGS avertissement(s)"
    echo ""
    echo "Pour passer en production :"
    echo "  1. Corrigez les [FAIL] ci-dessus dans votre .env"
    echo "  2. Relancez ce script : ./bin/check-prod-readiness.sh"
    echo ""
    exit 1
fi
