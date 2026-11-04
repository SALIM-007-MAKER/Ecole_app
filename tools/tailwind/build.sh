#!/bin/sh
# Compile les deux feuilles Tailwind (binaire standalone v3.4.17 requis : voir README.md). Linux/macOS : adapter le nom du binaire.
cd "$(dirname "$0")/../.." || exit 1
BIN="${TAILWIND_BIN:-tools/tailwind/tailwindcss}"
[ -x "$BIN" ] || { echo "Binaire absent : voir tools/tailwind/README.md"; exit 2; }
TW_PREFLIGHT=0 "$BIN" -c tools/tailwind/tailwind.config.js -i tools/tailwind/input.css -o public/assets/vendor/tailwind/tailwind.css --minify || exit 1
TW_PREFLIGHT=1 "$BIN" -c tools/tailwind/tailwind.config.js -i tools/tailwind/input.css -o public/assets/vendor/tailwind/tailwind-preflight.css --minify || exit 1
echo OK
