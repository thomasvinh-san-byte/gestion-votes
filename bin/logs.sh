#!/bin/sh
# =============================================================================
# AG-VOTE — Suivi des logs Docker
# =============================================================================
# Usage:
#   ./bin/logs.sh          Tous les services (follow)
#   ./bin/logs.sh app      Logs app uniquement
#   ./bin/logs.sh db       Logs PostgreSQL
#   ./bin/logs.sh redis    Logs Redis
#   ./bin/logs.sh err      Filtrer erreurs/warnings uniquement
#   ./bin/logs.sh last     30 dernières lignes (pas de follow)
# =============================================================================
set -e

cd "$(dirname "$0")/.."

case "${1:-all}" in
  app)
    echo "=== Logs: app (Ctrl+C pour quitter) ==="
    docker compose logs -f --tail=50 app
    ;;
  db)
    echo "=== Logs: PostgreSQL (Ctrl+C pour quitter) ==="
    docker compose logs -f --tail=50 db
    ;;
  redis)
    echo "=== Logs: Redis (Ctrl+C pour quitter) ==="
    docker compose logs -f --tail=50 redis
    ;;
  err|error|errors)
    echo "=== Logs: erreurs/warnings ==="
    docker compose logs --tail=200 app 2>&1 | grep -iE 'error|fatal|warn|exception|fail|panic' || echo "(aucune erreur trouvée)"
    ;;
  last)
    echo "=== 30 dernières lignes ==="
    docker compose logs --tail=30
    ;;
  all|*)
    echo "=== Logs: tous les services (Ctrl+C pour quitter) ==="
    docker compose logs -f --tail=30
    ;;
esac
