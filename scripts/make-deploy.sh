#!/usr/bin/env bash
# Assemble an upload-ready deploy package for shared hosting (DirectAdmin),
# using the built Vue 3 SPA as the frontend plus the PHP backend.
#
# It includes ONLY the files you upload and intentionally EXCLUDES secrets and
# environment state: config.php, config.local.php, log contents, the SQLite
# data/ dir, tests, and the Vue source (only the compiled output ships).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/deploy"
ZIP="$ROOT/chrono-deploy.zip"

# 1) Build the Vue SPA (needs Node.js locally — not on the host).
if [ ! -d "$ROOT/frontend/node_modules" ]; then
    npm --prefix "$ROOT/frontend" install
fi
npm --prefix "$ROOT/frontend" run build

rm -rf "$OUT" "$ZIP"
mkdir -p "$OUT/includes" "$OUT/logs"

# 2) PHP backend (unchanged on the host side).
cp "$ROOT/api.php"            "$OUT/"
cp "$ROOT/.htaccess"          "$OUT/"
cp "$ROOT/config.example.php" "$OUT/"
for f in auth db logger security sessions users; do
    cp "$ROOT/includes/$f.php" "$OUT/includes/"
done
cp "$ROOT/includes/.htaccess" "$OUT/includes/"
cp "$ROOT/logs/.htaccess"     "$OUT/logs/"

# 3) Built Vue frontend (replaces the vanilla index.html + assets).
cp "$ROOT/frontend/dist/index.html" "$OUT/"
cp -r "$ROOT/frontend/dist/assets"  "$OUT/assets"

# Safety net: refuse to ship secret/config files, and never ship generated
# logs or an initial-password file (e.g. if the package dir was served locally).
find "$OUT/logs" -type f ! -name '.htaccess' -delete 2>/dev/null || true
if [ -e "$OUT/config.php" ] || [ -e "$OUT/config.local.php" ] || [ -e "$OUT/logs/INITIAL_ADMIN_PASSWORD.txt" ]; then
    echo "ABORT: a secret/config file ended up in the package" >&2
    exit 1
fi

if command -v zip >/dev/null 2>&1; then
    ( cd "$OUT" && zip -r -q "$ZIP" . )
    echo "Created: $ZIP"
fi

echo "Package ready at: $OUT"
find "$OUT" -type f | sed "s#$OUT/#  #" | sort
