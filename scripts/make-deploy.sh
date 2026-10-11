#!/usr/bin/env bash
# Assemble an upload-ready deploy package for shared hosting (DirectAdmin).
# Includes ONLY the files you upload. It intentionally EXCLUDES secrets and
# environment state: config.php, config.local.php, log contents, the SQLite
# data/ dir, tests, the Vue source, git and node metadata.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/deploy"
ZIP="$ROOT/chrono-deploy.zip"

rm -rf "$OUT" "$ZIP"
mkdir -p "$OUT/includes" "$OUT/assets/css" "$OUT/assets/js" "$OUT/logs"

# Application code
cp "$ROOT/api.php"                 "$OUT/"
cp "$ROOT/index.html"              "$OUT/"
cp "$ROOT/.htaccess"               "$OUT/"
cp "$ROOT/config.example.php"      "$OUT/"

for f in auth db logger security sessions users; do
    cp "$ROOT/includes/$f.php" "$OUT/includes/"
done
cp "$ROOT/includes/.htaccess"      "$OUT/includes/"

cp "$ROOT/assets/css/style.css"    "$OUT/assets/css/"
cp "$ROOT/assets/js/app.js"        "$OUT/assets/js/"
cp "$ROOT/assets/js/utils.js"      "$OUT/assets/js/"

# Only the log directory's protection file, never log contents.
cp "$ROOT/logs/.htaccess"          "$OUT/logs/"

# Safety net: refuse to ship secret/config files.
if [ -e "$OUT/config.php" ] || [ -e "$OUT/config.local.php" ]; then
    echo "ABORT: a config file with secrets ended up in the package" >&2
    exit 1
fi

if command -v zip >/dev/null 2>&1; then
    ( cd "$OUT" && zip -r -q "$ZIP" . )
    echo "Created: $ZIP"
fi

echo "Package ready at: $OUT"
find "$OUT" -type f | sed "s#$OUT/#  #" | sort
