#!/usr/bin/env bash
# =============================================================================
# bin/test-e2e.sh — Run Playwright E2E tests in the `tests` Docker service
# =============================================================================
# Usage:
#   ./bin/test-e2e.sh                         # full chromium suite
#   ./bin/test-e2e.sh --grep @smoke           # filter by tag
#   ./bin/test-e2e.sh specs/login.spec.js     # single spec
#   ./bin/test-e2e.sh --list                  # list tests without running
#
# Requirements:
#   - docker compose stack running: docker compose up -d
#   - tests service defined in docker-compose.yml (profile: test)
#
# Exit code reflects playwright test result.
# HTML report written to tests/e2e/playwright-report/ (host-visible via bind mount).
# =============================================================================
set -euo pipefail

# Resolve repo root (script lives in bin/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

# Ensure app is up before running tests. If not, start non-test services.
if ! docker compose ps --services --filter "status=running" | grep -qx app; then
  echo "[test-e2e] app service not running — starting stack..."
  docker compose up -d app db redis
fi

# Note: UID is readonly in bash — capture host uid/gid in separate vars
DOCKER_UID="$(id -u)"
DOCKER_GID="$(id -g)"

# Derive compose project network and node_modules volume names from the project directory name.
# Matches docker compose default: lowercase dirname with special chars replaced by underscores.
PROJECT_NAME="$(basename "$REPO_ROOT" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9_-]/_/g')"
NETWORK="${PROJECT_NAME}_backend"
NM_VOLUME="${PROJECT_NAME}_tests-node-modules"

# Ensure the node_modules volume is writable by the host user.
# This is a one-time fix needed when the volume was first created by root
# (e.g., via `docker compose run` before this script was in use).
docker run --rm \
  --volume "${NM_VOLUME}:/nm" \
  mcr.microsoft.com/playwright:v1.59.1-jammy \
  bash -c "chown -R ${DOCKER_UID}:${DOCKER_GID} /nm 2>/dev/null || true" 2>/dev/null

# Run Playwright via docker run directly.
# Using docker run (not docker compose run) avoids output-buffering issues where
# docker compose run swallows container stdout in non-TTY environments.
# --rm: remove container after exit
# --network: join the compose backend network so `app` hostname resolves
# Forwards all user args to `npx playwright test --project=chromium`
echo "[test-e2e] Running Playwright in container (chromium only)..."
exec docker run --rm \
  --network "$NETWORK" \
  --volume "${REPO_ROOT}:/work" \
  --volume "${NM_VOLUME}:/work/tests/e2e/node_modules" \
  --workdir /work/tests/e2e \
  --user "${DOCKER_UID}:${DOCKER_GID}" \
  -e IN_DOCKER=true \
  -e CI="${CI:-}" \
  -e BASE_URL=http://agvote:8080 \
  -e REDIS_HOST=redis \
  -e REDIS_PORT=6379 \
  -e REDIS_PASSWORD="${REDIS_PASSWORD:-agvote-redis-dev}" \
  mcr.microsoft.com/playwright:v1.59.1-jammy \
  bash -lc "npm install --no-audit --no-fund --silent && npx playwright test --project=chromium $*"
