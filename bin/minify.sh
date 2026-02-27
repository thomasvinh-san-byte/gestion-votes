#!/bin/sh
# =============================================================================
# Minify CSS/JS assets in-place.
#
# Usage (requires Node.js + npm):
#   npm install -g terser clean-css-cli
#   ./bin/minify.sh [directory]
#
# Default directory: public/assets
# Called automatically in Dockerfile multi-stage build.
# =============================================================================
set -e

DIR="${1:-public/assets}"
JS_COUNT=0
CSS_COUNT=0

echo "Minifying assets in ${DIR} ..."

# JavaScript — terser
for f in $(find "$DIR" -name '*.js' -not -name '*.min.js' -type f); do
  terser "$f" --compress --mangle --output "$f" 2>/dev/null && JS_COUNT=$((JS_COUNT + 1)) || echo "  [WARN] skip: $f"
done

# CSS — clean-css
for f in $(find "$DIR" -name '*.css' -not -name '*.min.css' -type f); do
  cleancss --level 1 -o "$f" "$f" 2>/dev/null && CSS_COUNT=$((CSS_COUNT + 1)) || echo "  [WARN] skip: $f"
done

echo "Done: ${JS_COUNT} JS, ${CSS_COUNT} CSS minified."
