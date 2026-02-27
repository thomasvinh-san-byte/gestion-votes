#!/bin/sh
# =============================================================================
# AG-VOTE — État du stack Docker
# =============================================================================
# Usage: ./bin/status.sh
#
# Affiche : conteneurs, ports, healthchecks, DB, Redis, espace disque.
# =============================================================================
set -e

cd "$(dirname "$0")/.."

# Colors
if [ -t 1 ]; then
  G='\033[0;32m' Y='\033[0;33m' C='\033[0;36m' R='\033[0;31m' B='\033[1m' N='\033[0m'
else
  G='' Y='' C='' R='' B='' N=''
fi

echo "${B}=== AG-VOTE — État du stack ===${N}"
echo ""

# --- Containers -------------------------------------------------------------
echo "${B}Conteneurs :${N}"
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null || docker compose ps
echo ""

# --- Health -----------------------------------------------------------------
echo "${B}Healthchecks :${N}"
for SVC in agvote-app agvote-db agvote-redis; do
  STATUS=$(docker inspect --format='{{.State.Health.Status}}' "$SVC" 2>/dev/null || echo "not running")
  case "$STATUS" in
    healthy)   echo "  ${G}✓${N} $SVC: ${G}healthy${N}" ;;
    unhealthy) echo "  ${R}✗${N} $SVC: ${R}unhealthy${N}" ;;
    starting)  echo "  ${Y}…${N} $SVC: ${Y}starting${N}" ;;
    *)         echo "  ${R}?${N} $SVC: ${R}${STATUS}${N}" ;;
  esac
done
echo ""

# --- App health endpoint ----------------------------------------------------
APP_PORT=$(grep -E '^APP_PORT=' .env 2>/dev/null | cut -d= -f2 || echo "")
PORT="${APP_PORT:-8080}"

echo "${B}API Health :${N}"
HEALTH=$(curl -sf "http://localhost:${PORT}/api/v1/health.php" 2>/dev/null || echo '{"error":"unreachable"}')
echo "  $HEALTH"
echo ""

# --- Database ----------------------------------------------------------------
echo "${B}Base de données :${N}"
TABLE_COUNT=$(docker compose exec -T db psql -U vote_app -d vote_app -tAc \
  "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" 2>/dev/null || echo "?")
USER_COUNT=$(docker compose exec -T db psql -U vote_app -d vote_app -tAc \
  "SELECT count(*) FROM users;" 2>/dev/null || echo "?")
MEETING_COUNT=$(docker compose exec -T db psql -U vote_app -d vote_app -tAc \
  "SELECT count(*) FROM meetings;" 2>/dev/null || echo "?")
echo "  Tables: ${TABLE_COUNT} | Users: ${USER_COUNT} | Meetings: ${MEETING_COUNT}"
echo ""

# --- Redis -------------------------------------------------------------------
echo "${B}Redis :${N}"
REDIS_INFO=$(docker compose exec -T redis redis-cli -a "${REDIS_PASSWORD:-agvote-redis-dev}" --no-auth-warning info server 2>/dev/null | grep -E 'redis_version|connected_clients|used_memory_human' || echo "  (non disponible)")
echo "  $REDIS_INFO"
echo ""

# --- Disk usage --------------------------------------------------------------
echo "${B}Volumes Docker :${N}"
docker system df --format "table {{.Type}}\t{{.TotalCount}}\t{{.Size}}\t{{.Reclaimable}}" 2>/dev/null || echo "  (non disponible)"
echo ""

# --- URL ---------------------------------------------------------------------
echo "${B}URL :${N} ${C}http://localhost:${PORT}${N}"
echo ""
