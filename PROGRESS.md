# PROGRESS — Prompt 1 of 5: Infrastructure + theme skeleton

Branch: `arena/ff7b29a9-abrang` · One PR for this whole prompt · Owner merges himself.

> Working protocol: after EACH stage → run its checks → tick the box below with a
> one-line note → commit + push. On "ادامه بده" / "continue": read this file and
> `git log`, do NOT redo ticked stages, continue from the first unticked stage.

Note on checks: the agent sandbox has no Docker/PHP, so runtime checks of
Stages 2–4 are executed by GitHub Actions CI (Stage 5) on every push, plus
local static checks (bash -n, JSON validation) in the sandbox. CI must be
green before this prompt is finished.

## Stages

- [x] **Stage 1 — Repo skeleton** — `.gitignore`, `.gitattributes`, `.env.example`, `README.md` (skeleton). Check: dummy core/uploads/dump/.env files verified ignored via `git check-ignore`; theme path stays tracked.
  - `.gitignore` (.env, WP core, uploads, DB dumps, node_modules, OS junk)
  - `.gitattributes` forcing LF for *.sh, *.php, *.json, *.yml, *.css, *.html, *.md
  - `.env.example` (all variables, placeholders only)
  - `README.md` skeleton (filled in Stage 6)

- [x] **Stage 2 — Docker Compose dev environment** — `docker-compose.yml` (db mariadb:11 + healthcheck, wordpress php8.3-apache, wpcli cli-php8.3 user 33:33 behind `tools` profile, theme bind-mounted, scripts mounted ro at /scripts). Check: YAML parsed + structure asserted locally; runtime check (`up -d` + HTTP 200) runs in Stage 5 CI (no Docker in agent sandbox).
  - `db` mariadb:11 + named volume + healthcheck; `wordpress` php8.3-apache :8080, waits for healthy db; `wpcli` wordpress:cli-php8.3 sharing volumes, user 33:33
  - Theme dir bind-mounted live; core + uploads in named volumes; config from `.env`
  - Check: `docker compose up -d` up, http://localhost:8080 responds (via CI)

- [x] **Stage 3 — Idempotent provisioning: `scripts/setup.sh`** — `scripts/setup.sh` (bash, `set -euo pipefail`, check-before-act everywhere, `[already done]` logs). Thousand separator chosen: `,` per Persian WooCommerce docs/examples (e.g. «25,000 تومان»), documented in script header. Check: `bash -n`/`sh -n` pass, LF-only verified; run-twice runtime check in Stage 5 CI.
  - First install only: core install from .env + delete default content
  - fa_IR language, Asia/Tehran, Persian date/time, `/%postname%/`, blog_public via variable (default discouraged locally)
  - woocommerce + persian-woocommerce; IR, IRT, 0 decimals, thousand sep documented, right_space, wizard skipped, WC pages ensured
  - Activate abrang theme (parent twentytwentyfive ensured)
  - Check: run twice on fresh stack; 2nd run = "already done" everywhere, exit 0 (via CI)

- [x] **Stage 4 — Child block theme `wp-content/themes/abrang`** — `style.css`, `functions.php` (WooCommerce support + child textdomain only), `theme.json` v3 (7 brand palette slugs, Vazirmatn fontFace 400/500/700), `parts/header.html` + `parts/footer.html`, `screenshot.png` placeholder, `assets/fonts/` (Vazirmatn woff2 ×3 + `OFL.txt`, from official rastikerdar/vazirmatn repo, v33.003 line). Check: JSON valid + palette/font wiring asserted, woff2 magic verified, block comments balanced; `php -l`, `dir="rtl"`/`lang="fa-IR"` and local font loading verified in Stage 5 CI. No custom `templates/` — parent's templates are inherited, child parts override by slug.
  - Parent twentytwentyfive; style.css, functions.php, theme.json v3, parts/, screenshot.png
  - Vazirmatn self-hosted woff2 (400/500/700) + OFL license, via theme.json fontFace; no CDNs
  - Palette: paper, ink, indigo, coral, teal, ochre, mist; body/headings/links/buttons defaults
  - Placeholder header (آبرنگ + navigation) & footer (copyright) parts, RTL-correct
  - Check: no PHP warnings, `dir="rtl"` + `lang="fa-IR"`, font from local path, `php -l` clean (via CI)

- [x] **Stage 5 — CI `.github/workflows/ci.yml`** — `.github/workflows/ci.yml`. Check: GREEN on PR #1 on first run (run 40229688552, 2m13s) — fresh stack up, setup.sh ×2 (2nd run all `[already done]`), home 200 + `dir="rtl"` + `lang="fa-IR"`, Vazirmatn served from local theme path (no Google Fonts), /shop/ /cart/ /my-account/ 200, WP-CLI: theme abrang (parent twentytwentyfive), WPLANG fa_IR, both plugins active, currency IRT, 0 decimals, country IR, `/%postname%/`, default posts gone, `php -l` clean. This also completes the runtime checks of Stages 2–4.
  - .env from example → up → wait healthy → setup.sh ×2 → curl/WP-CLI smoke tests → php -l; logs on failure
  - Check: CI green on the PR

- [x] **Stage 6 — Documentation (non-technical Windows user)** — `README.md` (full Persian PowerShell walkthrough: prerequisites → clone → .env → up → setup → open site/wp-admin → stop/restart/reset + troubleshooting: port 8080 busy, Docker stopped, CRLF, forgotten password) and `docs/ARCHITECTURE.md` (git vs volumes/DB vs .env, idempotency, why). Check: every command matches the ones proven in CI; admin credentials in README match `.env.example`.
  - `README.md` full PowerShell walkthrough + troubleshooting
  - `docs/ARCHITECTURE.md` git vs database/volumes, and why

## Not done

_(empty for now — anything impossible gets a dated reason here instead of being skipped silently)_

## Report

_(added at the end of this prompt: what was built, how it was tested, known limitations, exact commands for the owner)_
