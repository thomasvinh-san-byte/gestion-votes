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

# Export UID/GID so container runs as host user (non-root, avoids root-owned files)
export UID="${UID:-$(id -u)}"
export GID="${GID:-$(id -g)}"

# Run Playwright in the tests container.
# --rm: remove container after exit
# --profile test: activate the test profile so `tests` service is resolvable
# Override the service command to forward user args; default to chromium project.
echo "[test-e2e] Running Playwright in container (chromium only)..."
exec docker compose --profile test run --rm tests \
  bash -lc "npm install --no-audit --no-fund --silent && npx playwright test --project=chromium $*"
