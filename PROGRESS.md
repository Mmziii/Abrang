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

- [ ] **Stage 1 — Repo skeleton**
  - `.gitignore` (.env, WP core, uploads, DB dumps, node_modules, OS junk)
  - `.gitattributes` forcing LF for *.sh, *.php, *.json, *.yml, *.css, *.html, *.md
  - `.env.example` (all variables, placeholders only)
  - `README.md` skeleton (filled in Stage 6)

- [ ] **Stage 2 — Docker Compose dev environment**
  - `db` mariadb:11 + named volume + healthcheck; `wordpress` php8.3-apache :8080, waits for healthy db; `wpcli` wordpress:cli-php8.3 sharing volumes, user 33:33
  - Theme dir bind-mounted live; core + uploads in named volumes; config from `.env`
  - Check: `docker compose up -d` up, http://localhost:8080 responds (via CI)

- [ ] **Stage 3 — Idempotent provisioning: `scripts/setup.sh`**
  - First install only: core install from .env + delete default content
  - fa_IR language, Asia/Tehran, Persian date/time, `/%postname%/`, blog_public via variable (default discouraged locally)
  - woocommerce + persian-woocommerce; IR, IRT, 0 decimals, thousand sep documented, right_space, wizard skipped, WC pages ensured
  - Activate abrang theme (parent twentytwentyfive ensured)
  - Check: run twice on fresh stack; 2nd run = "already done" everywhere, exit 0 (via CI)

- [ ] **Stage 4 — Child block theme `wp-content/themes/abrang`**
  - Parent twentytwentyfive; style.css, functions.php, theme.json v3, parts/, screenshot.png
  - Vazirmatn self-hosted woff2 (400/500/700) + OFL license, via theme.json fontFace; no CDNs
  - Palette: paper, ink, indigo, coral, teal, ochre, mist; body/headings/links/buttons defaults
  - Placeholder header (آبرنگ + navigation) & footer (copyright) parts, RTL-correct
  - Check: no PHP warnings, `dir="rtl"` + `lang="fa-IR"`, font from local path, `php -l` clean (via CI)

- [ ] **Stage 5 — CI `.github/workflows/ci.yml`**
  - .env from example → up → wait healthy → setup.sh ×2 → curl/WP-CLI smoke tests → php -l; logs on failure
  - Check: CI green on the PR

- [ ] **Stage 6 — Documentation (non-technical Windows user)**
  - `README.md` full PowerShell walkthrough + troubleshooting
  - `docs/ARCHITECTURE.md` git vs database/volumes, and why

## Not done

_(empty for now — anything impossible gets a dated reason here instead of being skipped silently)_

## Report

_(added at the end of this prompt: what was built, how it was tested, known limitations, exact commands for the owner)_
