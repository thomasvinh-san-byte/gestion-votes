#!/usr/bin/env bash
# Repair GitHub remote state post-v2.5 archive.
#
# Sandbox proxy refuses non-fast-forward ops (tag push + branch delete) with
# HTTP 403. This script does both from a normal git clone.
#
# Usage:
#   bin/repair-github-state.sh             # interactive (asks confirmation per group)
#   bin/repair-github-state.sh --yes       # non-interactive
#   bin/repair-github-state.sh --dry-run   # show what would run
#
# Idempotent : skips tags/branches that are already in the desired state.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

DRY_RUN=0
ASSUME_YES=0
for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=1 ;;
        --yes|-y)  ASSUME_YES=1 ;;
        --help|-h)
            head -n 12 "$0" | grep -E "^# " | sed 's/^# //'
            exit 0
            ;;
        *) echo "Unknown arg: $arg" >&2; exit 1 ;;
    esac
done

run() {
    if [ "$DRY_RUN" -eq 1 ]; then
        echo "[DRY-RUN] $*"
    else
        echo "→ $*"
        "$@"
    fi
}

confirm() {
    if [ "$ASSUME_YES" -eq 1 ] || [ "$DRY_RUN" -eq 1 ]; then
        return 0
    fi
    read -r -p "$1 [y/N] " reply
    [[ "$reply" =~ ^[yY]$ ]]
}

echo "──────────────────────────────────────────────────────────────"
echo " AG-VOTE — Repair GitHub remote state"
echo "──────────────────────────────────────────────────────────────"

# Sanity: must be run on a real remote (not the sandbox proxy)
ORIGIN_URL=$(git remote get-url origin)
if [[ "$ORIGIN_URL" =~ 127\.0\.0\.1 ]] || [[ "$ORIGIN_URL" =~ local_proxy ]]; then
    echo "ERROR: origin still points at the sandbox proxy ($ORIGIN_URL)." >&2
    echo "Run this from a normal git clone of github.com/thomasvinh-san-byte/gestion-votes." >&2
    exit 1
fi

run git fetch origin --tags --prune

# ────────────────────────────────────────────────────────────────────
# Group 1 : Push retroactive tags v2.2 / v2.3 / v2.4
# ────────────────────────────────────────────────────────────────────

declare -A TAGS=(
    [v2.4]=7c75f5e
    [v2.3]=2cca533
    [v2.2]=3bab9a4
)

echo
echo "────── Group 1 : Tags v2.2 / v2.3 / v2.4 ──────"
TAGS_TO_PUSH=()
for tag in "${!TAGS[@]}"; do
    target="${TAGS[$tag]}"
    if git ls-remote --tags origin "refs/tags/${tag}" | grep -q .; then
        echo "  ✓ $tag already on remote — skip"
    else
        # Create local tag if missing
        if ! git rev-parse "refs/tags/${tag}" >/dev/null 2>&1; then
            run git tag -a "$tag" "$target" -m "$tag — tagged retroactively post-v2.5"
        fi
        TAGS_TO_PUSH+=("$tag")
    fi
done

if [ ${#TAGS_TO_PUSH[@]} -gt 0 ]; then
    echo "  Tags à pousser : ${TAGS_TO_PUSH[*]}"
    if confirm "Push ${#TAGS_TO_PUSH[@]} tag(s) ?"; then
        run git push origin "${TAGS_TO_PUSH[@]}"
    fi
fi

# ────────────────────────────────────────────────────────────────────
# Group 2 : Delete 5 stale branches (PRs already merged)
# ────────────────────────────────────────────────────────────────────

STALE_BRANCHES=(
    chore/v25-cleanup-dead-v24-artifacts
    claude/gsd-ux-review-YG5K0
    feat/v2.2-design-tokens
    feat/v2.3-cockpit-operateur
    feat/v2.4-cockpit-polish
)

echo
echo "────── Group 2 : Stale branches (PRs merged) ──────"
BRANCHES_TO_DELETE=()
for br in "${STALE_BRANCHES[@]}"; do
    if git ls-remote --heads origin "$br" | grep -q .; then
        BRANCHES_TO_DELETE+=("$br")
        echo "  • $br"
    else
        echo "  ✓ $br already deleted — skip"
    fi
done

if [ ${#BRANCHES_TO_DELETE[@]} -gt 0 ]; then
    if confirm "Delete ${#BRANCHES_TO_DELETE[@]} branche(s) sur le remote ?"; then
        run git push origin --delete "${BRANCHES_TO_DELETE[@]}"
    fi
fi

# Local cleanup
echo
echo "────── Group 3 : Local cleanup ──────"
LOCAL_BRANCHES_TO_DELETE=()
for br in "${STALE_BRANCHES[@]}"; do
    if git rev-parse --verify "$br" >/dev/null 2>&1; then
        LOCAL_BRANCHES_TO_DELETE+=("$br")
    fi
done

if [ ${#LOCAL_BRANCHES_TO_DELETE[@]} -gt 0 ]; then
    if confirm "Delete ${#LOCAL_BRANCHES_TO_DELETE[@]} branche(s) locale(s) ?"; then
        run git branch -D "${LOCAL_BRANCHES_TO_DELETE[@]}" || true
    fi
fi

run git remote prune origin

# ────────────────────────────────────────────────────────────────────
# Verify
# ────────────────────────────────────────────────────────────────────

echo
echo "──────────────────────────────────────────────────────────────"
echo " ✓ GitHub state repair complete"
echo "──────────────────────────────────────────────────────────────"
echo
echo " Remote branches :"
git ls-remote --heads origin | awk '{print "   " $2}'
echo
echo " Remote tags (sorted) :"
git ls-remote --tags origin | awk '{print $2}' | sort -V | sed 's/^/   /'
