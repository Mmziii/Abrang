#!/usr/bin/env bash
# =============================================================================
# Abrang — image import by SKU (wrapper)
#
# Run from the repo root:
#
#   docker compose run --rm wpcli bash /scripts/import-images.sh /import/images
#   docker compose run --rm wpcli bash /scripts/import-images.sh /import/images.zip
#
# The folder or zip must be inside ./import on the host (mounted read-only
# as /import inside the wpcli container). Files are named by SKU:
#   SKU.jpg          = main image
#   SKU-2.jpg, SKU-3.jpg ... = gallery (jpg/jpeg/png/webp, case-insensitive)
#
# Re-running does not duplicate media (match by SKU + filename hash).
# =============================================================================
set -euo pipefail

if [ $# -lt 1 ]; then
  echo "Usage: bash /scripts/import-images.sh <folder-or-zip> [--dry-run]" >&2
  echo "  The folder or zip must be inside /import (host: ./import)." >&2
  exit 2
fi

input="$1"
shift || true

candidates=("$input" "/import/$(basename "$input")")
if [[ "$input" == ./import/* ]]; then
  candidates=("$input" "/import/$(basename "$input")" "/$input")
elif [[ "$input" == import/* ]]; then
  candidates=("$input" "/import/$(basename "$input")" "/$input")
fi

resolved=""
for c in "${candidates[@]}"; do
  if [ -e "$c" ]; then
    resolved="$c"
    break
  fi
done

if [ -z "$resolved" ]; then
  echo "ERROR: مسیر «$input» یافت نشد. پوشه یا فایل فشرده را در ./import کپی کنید." >&2
  echo "  Host: ./import/$(basename "$input") -> Container: /import/$(basename "$input")" >&2
  exit 1
fi

wp eval-file /scripts/import-images.php "$resolved" "$@"
