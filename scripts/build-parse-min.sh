#!/usr/bin/env bash
#
# Regenerate assets/ueditor.parse.min.js from assets/ueditor.parse.js.
#
# The minified parser must never be hand-edited: a previous manual edit turned
# the series loop condition into a comparison that was always false, which made
# every chart on any page loading the minified parser render empty.
#
# Usage: bash scripts/build-parse-min.sh
#   TERSER_VERSION=5.51.2   override the pinned terser version
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$REPO_ROOT/assets/ueditor.parse.js"
OUT="$REPO_ROOT/assets/ueditor.parse.min.js"
TERSER_VERSION="${TERSER_VERSION:-5.51.2}"

if [ ! -f "$SRC" ]; then
    echo "source not found: $SRC" >&2
    exit 1
fi

# Keep the global references that ueditor.parse.js and chart.config.js rely on
# (buildChartOption, typeConfig, UE, uParse) intact: mangle inner scopes only.
npx --yes "terser@${TERSER_VERSION}" "$SRC" \
    --compress \
    --mangle \
    --output "$OUT"

node --check "$OUT"

echo "regenerated $OUT ($(wc -c < "$OUT") bytes, terser ${TERSER_VERSION})"
