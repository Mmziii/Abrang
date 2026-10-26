#!/usr/bin/env bash
# =============================================================================
# Abrang (آبرنگ) — idempotent WordPress provisioning
#
# Run (from the repo root, PowerShell or any shell):
#
#     docker compose run --rm wpcli bash /scripts/setup.sh
#
# The script runs INSIDE the wpcli container (user 33:33) and configures the
# whole site with WP-CLI. It is safe to run any number of times: every step
# first checks the current state and only acts when something differs, so a
# second run changes nothing and reports "[already done]" for each step.
#
# Decisions worth knowing:
#   * Currency = IRT (تومان), added by the persian-woocommerce plugin,
#     0 decimals, position "right with space" (e.g. «25,000 تومان»).
#   * Persian digits via native PW_Options[persian_price]; separator = «٬».
#     Formatting only: product prices/quantities remain numeric in storage.
#   * "Discourage search engines" (blog_public=0) stays ON for local dev
#     unless WP_ALLOW_SEARCH_ENGINES=1 is set in .env (production only).
# =============================================================================
set -euo pipefail

# --- Configuration (from .env, passed to this container via env_file) --------
WP_URL="${WP_URL:-http://localhost:8080}"
WP_URL="${WP_URL%/}"                                  # strip trailing slash
WP_TITLE="${WP_TITLE:-آبرنگ}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-abrang-admin-1234}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"
WP_ALLOW_SEARCH_ENGINES="${WP_ALLOW_SEARCH_ENGINES:-0}"
WP_DEBUG="${WP_DEBUG:-1}"

DB_HOST="${WORDPRESS_DB_HOST:-db:3306}"
DB_NAME="${MYSQL_DATABASE:-${WORDPRESS_DB_NAME:-abrang}}"
DB_USER="${MYSQL_USER:-${WORDPRESS_DB_USER:-abrang}}"
DB_PASSWORD="${MYSQL_PASSWORD:-${WORDPRESS_DB_PASSWORD:-}}"

SITE_LOCALE="fa_IR"
THEME_SLUG="abrang"
PARENT_THEME_SLUG="twentytwentyfive"

NEEDS_REWRITE_FLUSH=0
FIRST_INSTALL=0

# --- Small logging helpers ------------------------------------------------------
log()  { printf '\n==> %s\n' "$*"; }
ok()   { printf '    [ok] %s\n' "$*"; }
skip() { printf '    [already done] %s\n' "$*"; }
warn() { printf '    [warn] %s\n' "$*" >&2; }
die()  { printf '\nERROR: %s\n' "$*" >&2; exit 1; }

# --- Generic check-before-act helpers ------------------------------------------

# ensure_option KEY VALUE — set a plain string option only if it differs.
ensure_option() {
  local key="$1" value="$2" current
  current="$(wp option get "$key" 2>/dev/null || printf '__missing__')"
  if [ "$current" = "$value" ]; then
    skip "option ${key} = ${value}"
  else
    wp option update "$key" "$value" >/dev/null
    ok "option ${key} set to '${value}'"
  fi
}

# ensure_plugin SLUG — install (if missing) and activate (if inactive).
ensure_plugin() {
  local slug="$1"
  if wp plugin is-installed "$slug"; then
    skip "plugin '${slug}' installed"
  else
    wp plugin install "$slug"
    ok "plugin '${slug}' installed"
  fi
  if wp plugin is-active "$slug"; then
    skip "plugin '${slug}' active"
  else
    wp plugin activate "$slug"
    ok "plugin '${slug}' activated"
  fi
}

# ensure_wc_page SLUG TITLE OPTION_KEY CONTENT — WooCommerce standard pages.
ensure_wc_page() {
  local slug="$1" title="$2" option_key="$3" content="$4" page_id
  page_id="$(wp option get "$option_key" 2>/dev/null || printf '0')"
  if [ -n "$page_id" ] && [ "$page_id" != "0" ] && wp post get "$page_id" >/dev/null 2>&1; then
    skip "WooCommerce page '${slug}' exists (ID ${page_id})"
    return 0
  fi
  page_id="$(wp post list --post_type=page --post_status=any --name="$slug" --format=ids)"
  if [ -z "$page_id" ]; then
    page_id="$(wp post create --post_type=page --post_status=publish \
      --post_title="$title" --post_name="$slug" --post_content="$content" --porcelain)"
    ok "created WooCommerce page '${slug}' (ID ${page_id})"
    NEEDS_REWRITE_FLUSH=1
  else
    ok "found existing page '${slug}' (ID ${page_id})"
  fi
  wp option update "$option_key" "$page_id" >/dev/null
  ok "option ${option_key} -> ${page_id}"
}

# db_ready — exit 0 when MariaDB answers (via PHP mysqli, no WP needed yet).
db_ready() {
  ABRANG_DB_HOST="$DB_HOST" ABRANG_DB_USER="$DB_USER" \
  ABRANG_DB_PASS="$DB_PASSWORD" ABRANG_DB_NAME="$DB_NAME" php -r '
    $parts = explode(":", getenv("ABRANG_DB_HOST"), 2);
    $host  = $parts[0];
    $port  = isset($parts[1]) ? (int) $parts[1] : 3306;
    $m = @new mysqli($host, getenv("ABRANG_DB_USER"), getenv("ABRANG_DB_PASS"), getenv("ABRANG_DB_NAME"), $port);
    exit($m->connect_errno ? 1 : 0);
  ' 2>/dev/null
}

# ===============================================================================
# 0. Wait until the stack is actually ready
# ===============================================================================
cd /var/www/html

log "Waiting for WordPress core files (copied by the 'wordpress' container on first start)"
tries=0
until [ -f wp-includes/version.php ] && [ -f wp-load.php ]; do
  tries=$((tries + 1))
  [ "$tries" -gt 60 ] && die "WordPress core files never appeared. Is the stack up? Run: docker compose up -d"
  sleep 2
done
ok "core files present (WordPress $(wp core version 2>/dev/null || printf '?'))"

log "Waiting for the database"
tries=0
until db_ready; do
  tries=$((tries + 1))
  [ "$tries" -gt 60 ] && die "Database ${DB_HOST} never became reachable. Check: docker compose logs db"
  sleep 2
done
ok "database reachable at ${DB_HOST}"

# wp-config.php is normally created by the wordpress image on first boot.
# Create it ourselves only if it is missing (defensive; keeps us independent
# of container start order).
if [ -f wp-config.php ]; then
  skip "wp-config.php exists"
else
  log "Creating wp-config.php"
  wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" \
    --dbhost="$DB_HOST" --skip-check
  if [ "$WP_DEBUG" = "1" ]; then
    wp config set WP_DEBUG true --raw
  fi
  ok "wp-config.php created"
fi

# ===============================================================================
# 1. WordPress core install (first run only) + default-content cleanup
# ===============================================================================
log "WordPress core"
if wp core is-installed; then
  skip "WordPress already installed"
else
  wp core install --url="$WP_URL" --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" --skip-email
  FIRST_INSTALL=1
  ok "WordPress installed (admin user: ${WP_ADMIN_USER})"
fi

if [ "$FIRST_INSTALL" = "1" ]; then
  log "Removing default content (first install only)"
  # Fresh installs always create: post ID 1 «Hello world!», page ID 2
  # «Sample Page», comment ID 1. (The Privacy Policy draft, ID 3, is kept.)
  for id in 1 2; do
    if wp post get "$id" >/dev/null 2>&1; then
      wp post delete "$id" --force >/dev/null
      ok "deleted default post/page ID ${id}"
    fi
  done
  if wp comment get 1 >/dev/null 2>&1; then
    wp comment delete 1 --force >/dev/null
    ok "deleted default comment ID 1"
  fi
else
  skip "default-content cleanup (first install only)"
fi

# ===============================================================================
# 2. Language: fa_IR
# ===============================================================================
log "Site language (${SITE_LOCALE})"
lang_status="$(wp language core list --fields=language,status --format=csv \
  | awk -F, -v l="$SITE_LOCALE" '$1 == l { print $2 }')"
case "$lang_status" in
  active)
    skip "language ${SITE_LOCALE} installed and active" ;;
  installed)
    wp language core activate "$SITE_LOCALE"
    ok "language ${SITE_LOCALE} activated" ;;
  *)
    wp language core install "$SITE_LOCALE" --activate
    ok "language ${SITE_LOCALE} installed and activated" ;;
esac

# ===============================================================================
# 3. Site settings
# ===============================================================================
log "Site settings"
ensure_option blogname        "$WP_TITLE"
ensure_option siteurl         "$WP_URL"
ensure_option home            "$WP_URL"
ensure_option timezone_string "Asia/Tehran"
ensure_option date_format     "j F Y"    # e.g. «۵ آبان ۱۴۰۴» (Jalali via fa_IR / Persian WooCommerce)
ensure_option time_format     "H:i"
ensure_option start_of_week   "6"        # Saturday — first day of the week in Iran
if [ "$WP_ALLOW_SEARCH_ENGINES" = "1" ]; then
  ensure_option blog_public "1"
else
  ensure_option blog_public "0"          # local dev: keep search engines out
fi

# ===============================================================================
# 4. Permalinks: /%postname%/
# ===============================================================================
log "Permalinks"
current_structure="$(wp option get permalink_structure 2>/dev/null || true)"
if [ "$current_structure" = "/%postname%/" ]; then
  skip "permalink structure is /%postname%/"
else
  wp rewrite structure '/%postname%/' --hard
  NEEDS_REWRITE_FLUSH=1
  ok "permalink structure set to /%postname%/"
fi

# ===============================================================================
# 5. Plugins: WooCommerce + Persian WooCommerce
# ===============================================================================
log "Plugins"
ensure_plugin woocommerce
ensure_plugin persian-woocommerce

# Persian language pack for WooCommerce itself (nice to have, never fatal).
if wp language plugin list woocommerce --fields=language,status --format=csv 2>/dev/null \
     | grep -Eq '^fa_IR,(installed|active)'; then
  skip "WooCommerce fa_IR language pack installed"
elif wp language plugin install woocommerce fa_IR >/dev/null 2>&1; then
  ok "WooCommerce fa_IR language pack installed"
else
  warn "WooCommerce fa_IR language pack could not be installed (non-fatal, continuing)"
fi

# ===============================================================================
# 6. WooCommerce settings: Iran / Toman, onboarding wizard skipped
# ===============================================================================
log "WooCommerce settings (Iran / تومان)"
ensure_option woocommerce_default_country    "IR"
ensure_option woocommerce_currency           "IRT"
ensure_option woocommerce_currency_pos       "right_space"
ensure_option woocommerce_price_num_decimals "0"
ensure_option woocommerce_price_thousand_sep "٬"
wp eval-file /scripts/persian-display.php
ensure_option woocommerce_price_decimal_sep  "."

onboarding="$(wp option get woocommerce_onboarding_profile --format=json 2>/dev/null || printf '')"
case "$onboarding" in
  *'"skipped":true'*|*'"skipped": true'*)
    skip "WooCommerce onboarding wizard already skipped" ;;
  *)
    wp option update woocommerce_onboarding_profile '{"completed":true,"skipped":true}' --format=json >/dev/null
    ok "WooCommerce onboarding wizard marked completed/skipped" ;;
esac

# ===============================================================================
# 7. WooCommerce standard pages (shop / cart / checkout / my account)
# ===============================================================================
log "WooCommerce pages"
ensure_wc_page "shop"       "فروشگاه"        "woocommerce_shop_page_id"     ""
ensure_wc_page "cart"       "سبد خرید"       "woocommerce_cart_page_id"     "<!-- wp:woocommerce/cart /-->"
ensure_wc_page "checkout"   "تسویه حساب"     "woocommerce_checkout_page_id" "<!-- wp:woocommerce/checkout /-->"
ensure_wc_page "my-account" "حساب کاربری من" "woocommerce_myaccount_page_id" "[woocommerce_my_account]"

# ===============================================================================
# 8. Theme: parent ensured, Abrang child theme activated
# ===============================================================================
log "Theme"
if wp theme is-installed "$PARENT_THEME_SLUG"; then
  skip "parent theme '${PARENT_THEME_SLUG}' installed"
else
  wp theme install "$PARENT_THEME_SLUG"
  ok "parent theme '${PARENT_THEME_SLUG}' installed"
fi

if ! wp theme is-installed "$THEME_SLUG"; then
  die "Theme '${THEME_SLUG}' not found in wp-content/themes. Is ./wp-content/themes/abrang mounted? Check docker-compose.yml."
fi

active_theme="$(wp option get stylesheet)"
if [ "$active_theme" = "$THEME_SLUG" ]; then
  skip "theme '${THEME_SLUG}' is active"
else
  wp theme activate "$THEME_SLUG"
  ok "theme '${THEME_SLUG}' activated"
fi

# ===============================================================================
# 9. Rewrite flush (only when something structural changed)
# ===============================================================================
if [ "$NEEDS_REWRITE_FLUSH" = "1" ]; then
  wp rewrite flush --hard
  ok "rewrite rules flushed (.htaccess updated)"
else
  skip "rewrite flush (nothing structural changed)"
fi

wp eval-file /scripts/categories.php
wp eval-file /scripts/pages.php
ensure_option show_on_front "posts"
if [ "${ABRANG_DEMO:-0}" = "1" ]; then bash /scripts/demo.sh; fi

log "Done ✔  Abrang is ready."
printf '    Site:  %s\n'        "$WP_URL"
printf '    Admin: %s/wp-admin (user: %s)\n' "$WP_URL" "$WP_ADMIN_USER"
printf '    Shop:  %s/shop/\n'   "$WP_URL"
