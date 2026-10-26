#!/usr/bin/env bash
# =============================================================================
# Abrang — bulk product import wrapper
#
# Run from the repo root (PowerShell or any shell):
#
#   docker compose run --rm wpcli bash /scripts/import-products.sh /import/products.csv
#   docker compose run --rm wpcli bash /scripts/import-products.sh /import/products.xlsx --dry-run
#   docker compose run --rm wpcli bash /scripts/import-products.sh /import/products.csv --skip-invalid
#
# The import file must be placed in ./import on the host (mounted read-only
# as /import inside the wpcli container — see docker-compose.yml).
# The script is idempotent: products are matched by SKU and updated, never
# duplicated. All rows are validated first; on any error nothing is imported
# unless --skip-invalid is given. See docs/products-template.csv for columns.
# =============================================================================
set -euo pipefail

if [ $# -lt 1 ]; then
  echo "Usage: bash /scripts/import-products.sh <file.csv|file.xlsx> [--dry-run] [--skip-invalid]" >&2
  echo "  The file must be inside /import (host: ./import)." >&2
  exit 2
fi

file="$1"
shift || true

# Resolve the file inside the container. The host folder ./import is mounted
# as /import (read-only). Accept host-style ./import/... as well.
candidates=("$file" "/import/$(basename "$file")")
if [[ "$file" == ./import/* ]]; then
  candidates=("$file" "/import/$(basename "$file")" "/$file")
elif [[ "$file" == import/* ]]; then
  candidates=("$file" "/import/$(basename "$file")" "/$file")
fi

resolved=""
for c in "${candidates[@]}"; do
  if [ -f "$c" ]; then
    resolved="$c"
    break
  fi
done

if [ -z "$resolved" ]; then
  echo "ERROR: فایل «$file» یافت نشد. آن را در پوشهٔ ./import کپی کنید و دوباره تلاش کنید." >&2
  echo "  Host: ./import/$(basename "$file") -> Container: /import/$(basename "$file")" >&2
  exit 1
fi

# Pass through any extra flags (--dry-run, --skip-invalid) to the PHP importer.
wp eval-file /scripts/import-products.php "$resolved" "$@"
