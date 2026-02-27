#!/bin/sh
# =============================================================================
# AG-VOTE — Démarrage rapide Docker dev
# =============================================================================
# Usage: ./bin/dev.sh
#
# Ce script :
#   1. Crée .env à partir de .env.example si absent
#   2. Lance docker compose up -d
#   3. Attend que le healthcheck passe
#   4. Affiche l'URL et les identifiants de test
# =============================================================================
set -e

cd "$(dirname "$0")/.."

# Colors
if [ -t 1 ]; then
  G='\033[0;32m' Y='\033[0;33m' C='\033[0;36m' R='\033[0;31m' B='\033[1m' N='\033[0m'
else
  G='' Y='' C='' R='' B='' N=''
fi

echo "${B}=== AG-VOTE — Démarrage dev ===${N}"
echo ""

# --- .env -------------------------------------------------------------------
if [ ! -f .env ]; then
  echo "${Y}[env]${N} .env absent → copie depuis .env.example"
  cp .env.example .env
  echo "${G}[env]${N} .env créé. Adaptez si besoin, puis relancez."
else
  echo "${G}[env]${N} .env trouvé."
fi

# --- Docker Compose ---------------------------------------------------------
echo ""
echo "${C}[docker]${N} Lancement des services..."
docker compose up -d

# --- Wait for healthcheck ---------------------------------------------------
echo ""
echo "${C}[health]${N} Attente du healthcheck (max 90s)..."

WAIT=0
MAX=90
APP_PORT=$(grep -E '^APP_PORT=' .env 2>/dev/null | cut -d= -f2 || echo "")
PORT="${APP_PORT:-8080}"

while [ "$WAIT" -lt "$MAX" ]; do
  STATUS=$(docker inspect --format='{{.State.Health.Status}}' agvote-app 2>/dev/null || echo "missing")
  case "$STATUS" in
    healthy)
      echo "${G}[health]${N} App healthy (${WAIT}s)."
      break
      ;;
    unhealthy)
      echo "${R}[health]${N} UNHEALTHY après ${WAIT}s."
      echo "  → docker compose logs app"
      exit 1
      ;;
    *)
      printf "."
      sleep 2
      WAIT=$((WAIT + 2))
      ;;
  esac
done

if [ "$WAIT" -ge "$MAX" ]; then
  echo ""
  echo "${R}[health]${N} Timeout (${MAX}s). Vérifiez les logs :"
  echo "  → docker compose logs app"
  exit 1
fi

# --- Summary ----------------------------------------------------------------
echo ""
echo "${B}┌──────────────────────────────────────────────┐${N}"
echo "${B}│  AG-VOTE est prêt !                          │${N}"
echo "${B}├──────────────────────────────────────────────┤${N}"
echo "${B}│${N}  URL: ${C}http://localhost:${PORT}${N}"
echo "${B}│${N}"
echo "${B}│${N}  Comptes de test :"
echo "${B}│${N}  ${G}admin${N}     admin@ag-vote.local    / Admin2026!"
echo "${B}│${N}  ${G}operator${N}  operator@ag-vote.local / Operator2026!"
echo "${B}│${N}"
echo "${B}│${N}  Commandes utiles :"
echo "${B}│${N}  ${C}./bin/logs.sh${N}     Suivre les logs"
echo "${B}│${N}  ${C}./bin/status.sh${N}   État du stack"
echo "${B}│${N}  ${C}./bin/test.sh${N}     Lancer les tests"
echo "${B}│${N}  ${C}./bin/rebuild.sh${N}  Rebuild complet"
echo "${B}└──────────────────────────────────────────────┘${N}"
