#!/bin/sh
# =============================================================================
# AG-VOTE — Lancement des tests
# =============================================================================
# Usage:
#   ./bin/test.sh                  Tous les tests
#   ./bin/test.sh Unit             Un dossier de tests
#   ./bin/test.sh --filter=Ballot  Filtrer par nom
#   ./bin/test.sh ci               Mode CI (coverage + strict)
# =============================================================================
set -e

cd "$(dirname "$0")/.."

# Install deps if needed
if [ ! -f vendor/bin/phpunit ]; then
  echo "[test] composer install..."
  composer install --no-interaction --no-progress --quiet
fi

# Colors
if [ -t 1 ]; then
  G='\033[0;32m' R='\033[0;31m' B='\033[1m' N='\033[0m'
else
  G='' R='' B='' N=''
fi

echo "${B}=== AG-VOTE — Tests ===${N}"
echo ""

if [ "$1" = "ci" ]; then
  # CI mode: coverage + fail on deprecation
  shift
  echo "${B}[CI mode]${N} Coverage + strict"
  php vendor/bin/phpunit \
    --display-deprecations \
    "$@"
elif [ -n "$1" ] && [ -d "tests/$1" ]; then
  # Run a specific test directory
  DIR="$1"
  shift
  echo "Dossier: tests/${DIR}"
  php vendor/bin/phpunit --no-coverage "tests/${DIR}" "$@"
else
  # Default: all tests, no coverage (fast)
  php vendor/bin/phpunit --no-coverage "$@"
fi

EXIT=$?

echo ""
if [ "$EXIT" -eq 0 ]; then
  echo "${G}[test]${N} Tous les tests passent."
else
  echo "${R}[test]${N} Échecs détectés (exit code: ${EXIT})."
fi

exit $EXIT
