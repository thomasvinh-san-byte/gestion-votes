#!/bin/sh
# =============================================================================
# AG-VOTE — Rebuild complet Docker
# =============================================================================
# Usage: ./bin/rebuild.sh [--no-cache]
#
# Arrête, rebuild et relance tous les services.
# --no-cache : force un rebuild sans cache Docker (images from scratch).
# =============================================================================
set -e

cd "$(dirname "$0")/.."

# Colors
if [ -t 1 ]; then
  G='\033[0;32m' Y='\033[0;33m' C='\033[0;36m' R='\033[0;31m' B='\033[1m' N='\033[0m'
else
  G='' Y='' C='' R='' B='' N=''
fi

CACHE_FLAG=""
if [ "$1" = "--no-cache" ]; then
  CACHE_FLAG="--no-cache"
  echo "${Y}[rebuild]${N} Mode --no-cache : rebuild complet from scratch"
fi

echo "${B}=== AG-VOTE — Rebuild ===${N}"
echo ""

# Stop
echo "${C}[1/4]${N} Arrêt des services..."
docker compose down --remove-orphans

# Build
echo "${C}[2/4]${N} Build de l'image..."
docker compose build $CACHE_FLAG

# Start
echo "${C}[3/4]${N} Démarrage des services..."
docker compose up -d

# Wait for healthy
echo "${C}[4/4]${N} Attente du healthcheck..."
WAIT=0
MAX=90
while [ "$WAIT" -lt "$MAX" ]; do
  STATUS=$(docker inspect --format='{{.State.Health.Status}}' agvote-app 2>/dev/null || echo "missing")
  case "$STATUS" in
    healthy) echo "${G}[rebuild]${N} OK — healthy (${WAIT}s)."; break ;;
    unhealthy) echo "${R}[rebuild]${N} UNHEALTHY."; docker compose logs --tail=30 app; exit 1 ;;
    *) printf "."; sleep 2; WAIT=$((WAIT + 2)) ;;
  esac
done

if [ "$WAIT" -ge "$MAX" ]; then
  echo ""
  echo "${R}[rebuild]${N} Timeout (${MAX}s)."
  docker compose logs --tail=30 app
  exit 1
fi

echo ""
echo "${G}[rebuild]${N} Stack rebuilt et healthy. → http://localhost:${APP_PORT:-8080}"
