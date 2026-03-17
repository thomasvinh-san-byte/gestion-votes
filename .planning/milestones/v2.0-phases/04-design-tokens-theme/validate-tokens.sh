#!/usr/bin/env bash
# validate-tokens.sh
# Validates that CSS custom properties in design-system.css match wireframe v3.19.2 reference values.
#
# Usage:
#   bash validate-tokens.sh           # Quick mode: checks 10 key tokens
#   bash validate-tokens.sh --full    # Full mode: checks all defined tokens
#
# Exit: 0 if all pass, 1 if any fail.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
CSS_FILE="$REPO_ROOT/public/assets/css/design-system.css"

if [[ ! -f "$CSS_FILE" ]]; then
  echo "ERROR: CSS file not found: $CSS_FILE" >&2
  exit 1
fi

PASS=0
FAIL=0
ERRORS=()

# ─────────────────────────────────────────────────────────────────────────────
# Helper: extract the value of a CSS custom property from a specific block.
# Arguments:
#   $1 - Token name (e.g., --color-bg)
#   $2 - Block selector: "root" or "dark"
# Returns the trimmed value string, or empty string if not found.
# ─────────────────────────────────────────────────────────────────────────────
get_token_value() {
  local token="$1"
  local block="$2"
  local value=""

  if [[ "$block" == "root" ]]; then
    # Extract :root block and find the token value
    value=$(awk '
      /^:root[[:space:]]*\{/ { in_block=1; depth=1; next }
      in_block {
        for(i=1;i<=length($0);i++){
          c=substr($0,i,1)
          if(c=="{") depth++
          else if(c=="}") { depth--; if(depth==0){ in_block=0; next } }
        }
        print
      }
    ' "$CSS_FILE" | grep -E "^\s*${token}\s*:" | head -1 \
      | sed 's|/\*.*\*/||g' \
      | sed "s/.*${token}:[[:space:]]*//" \
      | sed 's/[[:space:]]*;.*//' \
      | xargs)
  else
    # Extract [data-theme="dark"] block and find the token value
    value=$(awk '
      /\[data-theme="dark"\][[:space:]]*\{/ { in_block=1; depth=1; next }
      in_block {
        for(i=1;i<=length($0);i++){
          c=substr($0,i,1)
          if(c=="{") depth++
          else if(c=="}") { depth--; if(depth==0){ in_block=0; next } }
        }
        print
      }
    ' "$CSS_FILE" | grep -E "^\s*${token}\s*:" | head -1 \
      | sed 's|/\*.*\*/||g' \
      | sed "s/.*${token}:[[:space:]]*//" \
      | sed 's/[[:space:]]*;.*//' \
      | xargs)
  fi

  echo "$value"
}

# ─────────────────────────────────────────────────────────────────────────────
# Helper: check a token against an expected exact value (case-insensitive hex).
# ─────────────────────────────────────────────────────────────────────────────
check_exact() {
  local token="$1"
  local expected="$2"
  local block="${3:-root}"
  local actual
  actual=$(get_token_value "$token" "$block")

  # Normalize: lowercase both for comparison
  local actual_lower expected_lower
  actual_lower=$(echo "$actual" | tr '[:upper:]' '[:lower:]')
  expected_lower=$(echo "$expected" | tr '[:upper:]' '[:lower:]')

  # Normalize rgba spaces: remove all spaces inside rgba() for comparison
  actual_norm=$(echo "$actual_lower" | sed 's/rgba([[:space:]]*/rgba(/g; s/,[[:space:]]*/,/g; s/[[:space:]]*)/)/g')
  expected_norm=$(echo "$expected_lower" | sed 's/rgba([[:space:]]*/rgba(/g; s/,[[:space:]]*/,/g; s/[[:space:]]*)/)/g')

  if [[ "$actual_norm" == "$expected_norm" ]]; then
    echo "  PASS  $token"
    ((PASS++)) || true
  else
    echo "  FAIL  $token"
    echo "        expected: $expected"
    echo "        actual:   $actual"
    ((FAIL++)) || true
    ERRORS+=("[$block] $token: expected '$expected', got '$actual'")
  fi
}

# ─────────────────────────────────────────────────────────────────────────────
# Helper: check that a token's value CONTAINS a substring (for rgba patterns).
# ─────────────────────────────────────────────────────────────────────────────
check_contains() {
  local token="$1"
  local expected_fragment="$2"
  local block="${3:-root}"
  local actual
  actual=$(get_token_value "$token" "$block")

  # Normalize rgba spaces in actual value for comparison
  local actual_norm
  actual_norm=$(echo "$actual" | sed 's/rgba([[:space:]]*/rgba(/g; s/,[[:space:]]*/,/g; s/[[:space:]]*)/)/g' | tr '[:upper:]' '[:lower:]')
  local frag_norm
  frag_norm=$(echo "$expected_fragment" | sed 's/rgba([[:space:]]*/rgba(/g; s/,[[:space:]]*/,/g; s/[[:space:]]*)/)/g' | tr '[:upper:]' '[:lower:]')

  if echo "$actual_norm" | grep -q "$frag_norm"; then
    echo "  PASS  $token (contains '$expected_fragment')"
    ((PASS++)) || true
  else
    echo "  FAIL  $token"
    echo "        expected to contain: $expected_fragment"
    echo "        actual: $actual"
    ((FAIL++)) || true
    ERRORS+=("[$block] $token: expected to contain '$expected_fragment', got '$actual'")
  fi
}

# ─────────────────────────────────────────────────────────────────────────────
# QUICK MODE — 10 key tokens
# ─────────────────────────────────────────────────────────────────────────────
run_quick() {
  echo ""
  echo "══ QUICK MODE — 10 key tokens ══════════════════════════════════════════"
  echo ""
  echo "── Light theme (:root) ─────────────────────────────────────────────────"
  check_exact    "--color-bg"       "#EDECE6"
  check_exact    "--color-surface"  "#FAFAF7"
  check_exact    "--color-primary"  "#1650E0"
  check_exact    "--color-danger"   "#C42828"
  check_exact    "--color-text"     "#52504A"
  check_contains "--shadow-xs"      "rgba(21,21,16,"
  check_contains "--radius-lg"      "0.625rem"
  check_contains "--font-sans"      "Bricolage Grotesque"
  check_exact    "--sidebar-bg"     "#0C1018"
  check_exact    "--tag-bg"         "#E5E3D8"
}

# ─────────────────────────────────────────────────────────────────────────────
# FULL MODE — all defined tokens
# ─────────────────────────────────────────────────────────────────────────────
run_full() {
  echo ""
  echo "══ FULL MODE — all tokens ═══════════════════════════════════════════════"
  echo ""

  echo "── Surface elevation (:root) ────────────────────────────────────────────"
  check_exact    "--color-bg"             "#EDECE6"
  check_exact    "--color-surface"        "#FAFAF7"
  check_exact    "--color-surface-alt"    "#E5E3D8"
  check_exact    "--color-surface-raised" "#FFFFFF"
  check_contains "--color-glass"          "rgba(250,250,247,"

  echo ""
  echo "── Borders (:root) ──────────────────────────────────────────────────────"
  check_exact    "--color-border"         "#CDC9BB"
  check_exact    "--color-border-subtle"  "#DEDAD0"
  check_exact    "--color-border-dash"    "#BCB7A5"

  echo ""
  echo "── Primary/Accent (:root) ────────────────────────────────────────────────"
  check_exact    "--color-primary"        "#1650E0"
  check_exact    "--color-primary-hover"  "#1140C0"
  check_exact    "--color-primary-active" "#0C30A0"
  check_exact    "--color-primary-subtle" "#EBF0FF"
  check_contains "--color-primary-glow"   "rgba(22,80,224,"

  echo ""
  echo "── Typography colors (:root) ────────────────────────────────────────────"
  check_exact    "--color-text-dark"      "#151510"
  check_exact    "--color-text"           "#52504A"
  check_exact    "--color-text-muted"     "#857F72"
  check_exact    "--color-text-light"     "#B5B0A0"

  echo ""
  echo "── Semantic colors (:root) ──────────────────────────────────────────────"
  check_exact    "--color-danger"         "#C42828"
  check_exact    "--color-danger-subtle"  "#FEF1F0"
  check_exact    "--color-danger-border"  "#F4BFBF"
  check_exact    "--color-success"        "#0B7A40"
  check_exact    "--color-success-subtle" "#EDFAF2"
  check_exact    "--color-success-border" "#A3E8C1"
  check_exact    "--color-warning"        "#B56700"
  check_exact    "--color-warning-subtle" "#FFF7E8"
  check_exact    "--color-warning-border" "#F5D490"
  check_exact    "--color-purple"         "#5038C0"
  check_exact    "--color-purple-subtle"  "#EEEAFF"
  check_exact    "--color-purple-border"  "#C4B8F8"

  echo ""
  echo "── Sidebar (:root) ──────────────────────────────────────────────────────"
  check_exact    "--sidebar-bg"           "#0C1018"
  check_contains "--sidebar-hover"        "rgba(255,255,255,"
  check_contains "--sidebar-active"       "rgba(22,80,224,"
  check_contains "--sidebar-border"       "rgba(255,255,255,"
  check_contains "--sidebar-text"         "rgba(255,255,255,"

  echo ""
  echo "── Tags (:root) ─────────────────────────────────────────────────────────"
  check_exact    "--tag-bg"               "#E5E3D8"
  check_exact    "--tag-text"             "#6B6860"

  echo ""
  echo "── Shadows (:root) ──────────────────────────────────────────────────────"
  check_contains "--shadow-xs"            "rgba(21,21,16,"
  check_contains "--shadow-sm"            "rgba(21,21,16,"
  check_contains "--shadow-md"            "rgba(21,21,16,"
  check_contains "--shadow-lg"            "rgba(21,21,16,"
  check_contains "--shadow-focus"         "rgba(22,80,224,"

  echo ""
  echo "── Geometry (:root) ─────────────────────────────────────────────────────"
  # Wireframe defines: sm=6px, default=8px, lg=10px, full=999px.
  # Codebase uses rem equivalents: 0.375rem (6px), 0.5rem (8px), 0.625rem (10px).
  # Accept both px and rem representations (either is correct).
  check_contains "--radius-sm"            "0.375rem"
  check_contains "--radius"              "0.5rem"
  check_contains "--radius-lg"           "0.625rem"
  check_contains "--radius-full"         "999px"

  echo ""
  echo "── Typography (:root) ───────────────────────────────────────────────────"
  check_contains "--font-sans"           "Bricolage Grotesque"
  check_contains "--font-display"        "Fraunces"
  check_contains "--font-mono"           "JetBrains Mono"

  echo ""
  echo "── Dark theme ([data-theme=\"dark\"]) ─────────────────────────────────────"
  check_exact    "--color-bg"            "#0B0F1A"            "dark"
  check_exact    "--color-surface"       "#141820"            "dark"
  check_exact    "--color-surface-alt"   "#1B2030"            "dark"
  check_exact    "--color-surface-raised" "#1E2438"           "dark"
  check_exact    "--color-primary"       "#3D7EF8"            "dark"
  check_exact    "--color-danger"        "#E85454"            "dark"
  check_exact    "--color-success"       "#2DC87A"            "dark"
  check_exact    "--color-warning"       "#EDA030"            "dark"
  check_exact    "--color-purple"        "#8C72F8"            "dark"
  check_exact    "--color-text"          "#7A8499"            "dark"
  check_exact    "--color-text-dark"     "#ECF0FA"            "dark"
  check_exact    "--color-border"        "#252C3C"            "dark"
  check_exact    "--sidebar-bg"          "#080B10"            "dark"
  check_contains "--shadow-xs"           "rgba(0,0,0,"        "dark"
  check_contains "--shadow-md"           "rgba(0,0,0,"        "dark"
  check_contains "--shadow-focus"        "var(--color-surface)" "dark"
}

# ─────────────────────────────────────────────────────────────────────────────
# Main
# ─────────────────────────────────────────────────────────────────────────────
FULL_MODE=false
for arg in "$@"; do
  if [[ "$arg" == "--full" ]]; then
    FULL_MODE=true
  fi
done

echo "validate-tokens.sh — AG-Vote design token validation"
echo "CSS file: $CSS_FILE"

if $FULL_MODE; then
  run_full
else
  run_quick
fi

echo ""
echo "────────────────────────────────────────────────────────────────────────"
echo "Results: $PASS passed, $FAIL failed"

if [[ $FAIL -gt 0 ]]; then
  echo ""
  echo "FAILURES:"
  for err in "${ERRORS[@]}"; do
    echo "  - $err"
  done
  echo ""
  exit 1
fi

echo ""
echo "All token checks PASSED."
exit 0
