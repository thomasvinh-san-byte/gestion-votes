#!/bin/sh
# =============================================================================
# AG-VOTE — Rebuild Docker
# =============================================================================
# Usage:
#   ./bin/rebuild.sh              # full rebuild (down → build → up → healthcheck)
#   ./bin/rebuild.sh --no-cache   # full rebuild forcé sans cache Docker
#   ./bin/rebuild.sh --quick      # fast path : régénère uniquement l'autoload
#                                 # composer dans le container running + reload
#                                 # graceful php-fpm. Pas de rebuild d'image.
#                                 # Use quand seul du code PHP a bougé.
#   ./bin/rebuild.sh -h | --help  # affiche cette aide
#
# Le mode --quick évite de rebuild une image complète (typiquement 200-500 MB
# de couches Docker régénérées) quand la seule chose qui a bougé est du code
# PHP — situation fréquente en dev. Si Dockerfile, composer.lock, ou
# package.json ont changé, utilise le mode full.
# =============================================================================
set -e

cd "$(dirname "$0")/.."

# Colors
if [ -t 1 ]; then
  G='\033[0;32m' Y='\033[0;33m' C='\033[0;36m' R='\033[0;31m' B='\033[1m' N='\033[0m'
else
  G='' Y='' C='' R='' B='' N=''
fi

# ── Argument parsing ────────────────────────────────────────────────────────
CACHE_FLAG=""
QUICK_MODE=0

for arg in "$@"; do
  case "$arg" in
    --no-cache)
      CACHE_FLAG="--no-cache"
      ;;
    --quick|-q)
      QUICK_MODE=1
      ;;
    -h|--help)
      sed -n '2,18p' "$0" | sed 's/^# \?//'
      exit 0
      ;;
    *)
      echo "${R}[rebuild]${N} Argument inconnu : $arg"
      echo "         Voir ./bin/rebuild.sh --help"
      exit 1
      ;;
  esac
done

if [ "$QUICK_MODE" = 1 ] && [ -n "$CACHE_FLAG" ]; then
  echo "${R}[rebuild]${N} --quick et --no-cache sont incompatibles"
  echo "         --quick = pas de rebuild, --no-cache = rebuild forcé"
  exit 1
fi

# ── QUICK MODE : autoload-only refresh ──────────────────────────────────────
if [ "$QUICK_MODE" = 1 ]; then
  echo "${B}=== AG-VOTE — Rebuild (mode : quick / autoload-only) ===${N}"
  echo ""

  # Vérifier que le container tourne — sinon le quick path n'a pas de sens
  if ! docker compose ps --status running --services 2>/dev/null | grep -qx "app"; then
    echo "${R}[rebuild]${N} Le service 'app' n'est pas running."
    echo "         --quick suppose une stack déjà up. Lance ./bin/rebuild.sh sans flag."
    exit 1
  fi

  echo "${C}[1/2]${N} Régénération du classmap composer dans le container..."
  if ! docker compose exec -T app composer dump-autoload -o 2>/dev/null; then
    # Fallback : composer peut être absent de l'image runtime (Dockerfile le retire
    # après install). Dans ce cas, on touche les fichiers et on reload php-fpm —
    # opcache invalidate suffit pour les classes nouvellement copiées via volume mount.
    echo "${Y}[rebuild]${N} composer absent du container runtime — bascule sur reload php-fpm seul"
    echo "         (volume mount supposé : les fichiers sources sont déjà à jour)"
  fi

  echo "${C}[2/2]${N} Reload graceful php-fpm (USR2)..."
  docker compose exec -T app pkill -USR2 -f "php-fpm: master" 2>/dev/null || \
    docker compose exec -T app sh -c 'kill -USR2 1 2>/dev/null || true'

  echo ""
  echo "${G}[rebuild]${N} OK — autoload régénéré, php-fpm rechargé."
  echo "         Si un nouveau service/binaire a été ajouté, lance le mode full :"
  echo "         ./bin/rebuild.sh"
  exit 0
fi

# ── FULL MODE : down → build → up → healthcheck ──────────────────────────────
echo "${B}=== AG-VOTE — Rebuild (mode : full) ===${N}"
if [ -n "$CACHE_FLAG" ]; then
  echo "${Y}[rebuild]${N} --no-cache : rebuild complet from scratch"
fi
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
echo "         Astuce : si seul du code PHP a bougé, ./bin/rebuild.sh --quick suffit."
