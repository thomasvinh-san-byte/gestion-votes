#!/usr/bin/env bash
#
# bin/check-deps.sh — guard against the dual-install Playwright regression.
#
# Source: TEST-V24-03 / D-07 — Plan 03.1 (Phase 3 v2.4).
#
# Failure modes detected:
#   1. @playwright/test present in root package.json devDependencies/dependencies
#   2. tests/e2e/package.json missing @playwright/test (the SOT must keep it)
#
# Exit codes:
#   0 — both invariants hold
#   1 — root package.json regressed
#   2 — tests/e2e/package.json regressed
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ROOT_PKG="${REPO_ROOT}/package.json"
E2E_PKG="${REPO_ROOT}/tests/e2e/package.json"

if [[ ! -f "$ROOT_PKG" ]]; then
  echo "check-deps: root package.json not found at $ROOT_PKG" >&2
  exit 1
fi

if grep -q '"@playwright/test"' "$ROOT_PKG"; then
  echo "FAIL: @playwright/test found in root package.json — should only live in tests/e2e/" >&2
  echo "      Remove it from root devDependencies and rerun 'npm install'." >&2
  exit 1
fi

if [[ ! -f "$E2E_PKG" ]]; then
  echo "check-deps: tests/e2e/package.json not found at $E2E_PKG" >&2
  exit 2
fi

if ! grep -q '"@playwright/test"' "$E2E_PKG"; then
  echo "FAIL: @playwright/test missing from tests/e2e/package.json (SOT regression)" >&2
  exit 2
fi

echo "OK: Playwright SOT confined to tests/e2e/."
