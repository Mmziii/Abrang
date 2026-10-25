# Prompt 1 (done)

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

Nothing from this prompt's scope is left undone. Two honest clarifications instead:

- Vazirmatn woff2 files were taken from the official `rastikerdar/vazirmatn` GitHub repo
  (`fonts/webfonts`, v33.003 line) via `git clone`, not from a GitHub *Release asset* file,
  because release-asset download hosts are not reachable from the agent sandbox.
  The files are the project's own published webfonts; `OFL.txt` is included next to them.
- The agent sandbox has no Docker/PHP, so no runtime check could run locally.
  All runtime checks (compose up, setup.sh ×2, php -l, RTL/font/shop smoke tests)
  were executed by GitHub Actions CI — green (see Report). Local checks that did run:
  `bash -n`, YAML/JSON parsing + structure assertions, `git check-ignore` proofs, woff2 magic bytes.

## Report

### What was built (Prompt 1/5 — infrastructure + theme skeleton)

- **Repo skeleton:** `.gitignore` (core/uploads/dumps/.env/node_modules ignored; only our theme tracked under wp-content), `.gitattributes` forcing LF for sh/php/json/yml/css/html/md (+ binaries marked), `.env.example` (all variables, local-only placeholders).
- **`docker-compose.yml`:** `db` = mariadb:11 (utf8mb4, named volume `db_data`, `healthcheck.sh` healthcheck); `wordpress` = wordpress:php8.3-apache on `${WP_PORT:-8080}`, `depends_on: service_healthy`, core+uploads in named volume `wp_data`, theme bind-mounted live from the repo; `wpcli` = wordpress:cli-php8.3, **user 33:33** (matches Apache www-data; the cli image's own www-data uid differs), behind profile `tools` (not started by `up`), sharing both volumes + `./scripts` mounted read-only at `/scripts`. All config via `.env` (missing passwords fail with a helpful message).
- **`scripts/setup.sh`** (runs in wpcli, `set -euo pipefail`, check-before-act, `[already done]` logs): waits for core files + DB → creates wp-config only if missing → first install only: `wp core install` from .env + deletes Hello World/Sample Page/default comment (keeps Privacy page) → fa_IR install+activate → title/URL, Asia/Tehran, `j F Y` / `H:i`, week starts Saturday, `blog_public` from `WP_ALLOW_SEARCH_ENGINES` (default 0 = discouraged locally) → permalinks `/%postname%/` → installs+activates `woocommerce` then `persian-woocommerce` (latest stable from wordpress.org) + WooCommerce fa_IR pack (non-fatal) → WooCommerce: country IR, currency **IRT (تومان)**, 0 decimals, thousand separator `,` (per Persian WooCommerce docs/examples; e.g. «25,000 تومان»), decimal sep `.`, position `right_space`, onboarding skipped (`woocommerce_onboarding_profile` completed+skipped) → ensures shop/cart/checkout/my-account pages (Persian titles; cart/checkout use WooCommerce blocks) → ensures parent `twentytwentyfive`, activates `abrang` (hard-fails if theme not mounted) → rewrite flush only when something structural changed.
- **Theme `wp-content/themes/abrang`** (child of twentytwentyfive): `style.css` (Text Domain: abrang), minimal `functions.php` (`load_child_theme_textdomain`, `add_theme_support('woocommerce')`, child stylesheet), `theme.json` v3 with the 7 brand tokens (paper/ink/indigo/coral/teal/ochre/mist — slugs stable), body/heading/link/button defaults, **self-hosted Vazirmatn woff2 400/500/700** in `assets/fonts/` (+ `OFL.txt`) registered via `fontFace`, placeholder `parts/header.html` (site title + navigation) and `parts/footer.html` (copyright), placeholder `screenshot.png`. No `templates/` — parent templates inherited, child parts override by slug. No external fonts/CDNs.
- **CI `.github/workflows/ci.yml`** (push + PR): static checks (bash -n, theme.json parse, `php -l` on all theme PHP) → `.env` from example → compose up → wait HTTP → **setup.sh twice** (2nd run asserted all-`[already done]`) → HTTP smoke (home 200, `dir="rtl"`, `lang="fa-IR"`, Vazirmatn referenced + served locally, no Google Fonts, no Fatal error, /shop/ /cart/ /my-account/ 200) → WP-CLI smoke (theme abrang + parent, WPLANG fa_IR, both plugins active, IRT, 0 decimals, IR, `/%postname%/`, default posts = 0) → container logs dumped on failure, `down -v` always.
- **Docs:** `README.md` — Persian, non-technical, exact PowerShell commands with expected results (prereqs → clone → .env → up → setup → site/wp-admin → stop/restart/update/full reset) + troubleshooting (Docker stopped, port 8080 busy → 8081 in .env, CRLF `$'\r'`, forgotten admin password). `docs/ARCHITECTURE.md` — what lives in git vs `wp_data`/`db_data` volumes vs `.env`, and why.

### How it was tested

- **CI green on PR #1**: run `40229688552` (push, 2m13s) and `40229689571` (pull_request, 1m44s), Oct 25 2026 — full fresh-stack build incl. plugin downloads from wordpress.org, setup.sh ×2, all smoke assertions above.
- Locally in the sandbox: `git check-ignore` proof with dummy core/upload/dump files; YAML+JSON parse with structure/palette/font-wiring assertions; `bash -n` + LF-only check on setup.sh; woff2 magic bytes; block-comment balance; screenshot visual check.
- Verified against upstream sources before writing code: official `wordpress:cli` image ships bash and passes `bash`/`wp` through its entrypoint, but its www-data uid ≠ 33 (hence `user: "33:33"`); `persian-woocommerce` on wordpress.org is maintained (v10.x in 2026) and provides IRT.

### Known limitations

- Floating image tags (`wordpress:php8.3-apache`, `mariadb:11`, latest plugins) — a future upstream major could require setup.sh tweaks; CI on every PR will catch it. (Pinning can be revisited in the deployment prompt.)
- `woocommerce_price_thousand_sep=","` renders «25,000 تومان». If the design prompt prefers the Persian separator «٬», it's a one-line change in setup.sh + existing stores keep the old value until setup re-runs (by design, it will update it).
- Updating `.env` passwords after first install does NOT change the already-created DB/admin credentials (WordPress/MariaDB keep the originals in the volumes) — documented in README; full reset re-creates from `.env`.
- The theme is a skeleton: it inherits Twenty Twenty-Five's templates/look (with our palette + Vazirmatn + header/footer parts). Real design, products, payment, shipping, SEO, deployment = prompts 2–5.
- Local-only: `http://localhost:8080`, search engines discouraged by default, placeholder passwords — production hardening belongs to the deployment prompt.

### Exact commands for the owner (PowerShell)

Prereqs: install Docker Desktop + Git, open Docker Desktop until "Engine running".

```powershell
git clone https://github.com/Mmziii/Abrang.git
cd Abrang
Copy-Item .env.example .env
docker compose up -d
docker compose run --rm wpcli bash /scripts/setup.sh
```

Then open http://localhost:8080 and http://localhost:8080/wp-admin (user `admin`, password `abrang-admin-1234`).
Stop with `docker compose down`; full reset with `docker compose down -v` then the last two commands again.
Full step-by-step with expected results: see README.md.


# Prompt 2

- [x] Stage 1 — Product category tree — scripts/categories.{json,php}, setup.sh; JSON and bash syntax pass; run-twice/tree runtime checks delegated to CI (no local Docker/PHP).
- [x] Stage 2 — Demo content and pages — scripts/demo*, pages.php, setup.sh, .env.example; bash syntax and generated PNG signatures checked; lifecycle runtime in CI. Images are original geometric placeholders, CC0.
- [x] Stage 3 — Header — parts/header.html, wordmark.svg, theme.json, style.css; inline slug navigation and JSON checked; browser checks in CI.
- [x] Stage 4 — Footer — parts/footer.html, style.css; seven slug links and no fake badges verified; mobile rendering checked in CI.
- [x] Stage 5 — Home page — templates/front-page.html, six patterns, functions.php, wash.svg, style.css, setup.sh; six pattern references and shell syntax checked; runtime grids and empty-sale behavior covered by CI.
- [x] Stage 6 — Tests, visual artifacts, docs — CI, tests/storefront.cjs, check-content.php, compose, README, ARCHITECTURE; YAML/JSON/JS/shell syntax passed locally; runtime and screenshots passed CI 40234712666 and PR run 40234717895; extra exact-count/empty-sale assertions added for final CI.


## Not done (Prompt 2)

- No local Docker/PHP runtime or manual screenshot inspection: sandbox lacks Docker/PHP and blocks the Azure artifact download host. CI generated and uploaded all three screenshots successfully; owner can review them on PR #2. Browser assertions passed at 360, 390 and 1440px.
- Out of scope as requested: custom product/archive templates, checkout/payment/shipping configuration, actual legal text, SEO and deployment. Parent/WooCommerce default templates remain in use there.

## Report (Prompt 2)

### ساخته شد
- ۵ دستهٔ اصلی و ۱۵ زیردسته با slug ثابت؛ نصب تکرارشونده بدون تغییر دسته‌های موجود.
- ۱۲ محصول ساختگی با قیمت تومان، تخفیف و موجودی متفاوت، تصاویر هندسی اصلی CC0، حذف امن با متای اختصاصی؛ هفت برگهٔ اطلاعاتی واقعی با متن موقت.
- سربرگ و پابرگ بلوکی، جستجوی محصول، حساب کاربری، سبد خرید، منوی کشویی و منوی موبایل؛ لوگوی موقت قابل جایگزینی و جایگاه خالی نماد اعتماد.
- صفحهٔ نخست با شش الگوی قابل استفادهٔ مجدد: معرفی، دسته‌ها، تازه‌ترین محصولات، مخاطبان، تخفیف‌ها و ویژگی‌های اعتماد؛ رنگ تیرهٔ مرجانی برای خوانایی دکمه‌ها.
- راهنمای فارسی PowerShell، هشدار اولویت قالب‌های ذخیره‌شده در دیتابیس و روش بازنشانی آنها.

### آزمون
- CIهای 40234712666 و 40234717895 سبز: نصب تازه، setup دوبار، درخت دقیق دسته‌ها، HTTP برگه‌ها و دسته‌ها، تست‌های قبلی، PHP lint و debug.log، حذف دوباره و بازسازی دوبارهٔ نمونه‌ها.
- Playwright: صفحهٔ کامل ۳۹۰ و ۱۴۴۰ پیکسل و منوی باز موبایل؛ بدون اسکرول افقی در ۳۶۰ پیکسل. تصاویر در artifact به نام `screenshots` در Checks مربوط به PR شمارهٔ ۲ قرار دارند.
- بررسی محلی: syntax شل و JavaScript، JSON و YAML. اجرای واقعی در CI بود، نه محیط محلی عامل.

### محدودیت‌ها
- مشخصات تماس، شبکه‌های اجتماعی، وعدهٔ ارسال، لوگو و متن‌های معرفی نمونه‌اند؛ پیش از انتشار باید مالک تأیید/جایگزین کند. متن حقوقی واقعی در این مرحله نوشته نشده است.
- دانلود artifact برای بازبینی دستی عامل به‌علت محدودیت شبکه ممکن نبود؛ بازبینی بصری نهایی با مالک است. قالب‌های ذخیره‌شده در Site Editor ممکن است فایل‌های جدید را بپوشانند.
- روی سایت واقعی ABRANG_DEMO باید صفر یا غایب باشد. خاموش کردن پرچم، داده‌های آزمایشی قبلی را حذف نمی‌کند.

### دستورهای مالک (پس از ادغام PR شمارهٔ ۲؛ داخل پوشهٔ پروژه در PowerShell)
```powershell
git pull
docker compose up -d
docker compose run --rm wpcli bash /scripts/setup.sh
```
برای نمونه‌های محلی در تنظیمات قدیمی، بدون ویرایش فایل:
```powershell
docker compose run --rm -e ABRANG_DEMO=1 wpcli bash /scripts/setup.sh
```
برای پاک کردن محصولات و تصاویر آزمایشی (برگه‌ها و محصولات واقعی حفظ می‌شوند):
```powershell
docker compose run --rm wpcli bash /scripts/demo-remove.sh
```
برای بازسازی نمونه‌ها، فقط در محیط محلی:
```powershell
docker compose run --rm wpcli bash /scripts/demo.sh
```
سایت: http://localhost:8080 — تصاویر بررسی: PR #2 → Checks → CI → Artifacts → screenshots.

# Prompt 2 — fixes

- [x] Fix 1 — theme.json lowercase vazirmatn token and all references; tests/storefront.cjs checks body/headings/buttons and loaded font at 390/1440. JSON/JS syntax pass; runtime in final CI.
- [x] Fix 2 — style.css, assets/storefront.js, functions.php, browser tests: opaque viewport modal, inert background/scroll lock, collapsed accessible accordion toggles; JS syntax passes, native focus/Escape retained; runtime in CI.
- [x] Fix 3 — header part/grid CSS and storefront.js: hamburger, live-font wordmark, account/cart on one row; full-width 44px accessible magnifier search below. JS syntax passes; row geometry/search assertions added.
- [x] Fix 4 — shared collection card CSS, product image/button render filters, theme tokens, patterns and original 800×800 fixtures + generator. PNG dimensions, JS/JSON pass; image square checks added; native product context supplies badges/secondary state.
- [x] Fix 5 — setup.sh/persian-display.php enable native PW_Options[persian_price] idempotently (upstream class-price.php verified); Persian separator, display-only rendered-date text filters and scoped dynamic text-node conversion. JS/shell pass; price and untouched input/attribute assertions added.
- [x] Fix 6 — audience/trust patterns now have backpack/palette/briefcase and truck/shield-check/lock/headset SVGs, consistent 56px/2.5px rounded strokes across all three sections (including category tiles); checked seven unique paths and no category reuse.
- [x] Fix 7 — theme spacing presets (64/40/24px), home/template CSS and full-width mist trust band, tile hover/focus/reduced-motion states. JSON/YAML/JS/shell checks pass; final runtime verification recorded below.


## Review-fixes verification

All seven item groups committed on the same branch/PR #2. Added browser checks for
loaded/computed Vazirmatn, opaque viewport menu and inert hero, collapsed/expanded
accordion, one mobile header row and full-width search, square product images,
Persian price text and untouched numeric input values/attributes, card button overflow.
First full review CI verified mobile (font, menu, digits, geometry), then caught desktop overflow in the new full-width trust band. Diagnostics traced the 24px overflow to inherited WordPress global padding versus the custom gutter. Trust band now uses a centered viewport-width bleed independent of padding; overflow diagnostics retained. Verified PR CI 40241275296 GREEN (3m23s): all previous and new browser assertions passed; regenerated screenshots artifact includes home-390.png, home-1440.png and mobile-menu-open.png.

Existing local demo images can be refreshed (only marked demo products/media are deleted):
```powershell
git pull
docker compose run --rm wpcli bash /scripts/demo-remove.sh
docker compose run --rm -e ABRANG_DEMO=1 wpcli bash /scripts/setup.sh
```
Do not run demo generation on production. Site Editor overrides still require reset.


## Prompt 2 — fixes report

- Font token is lowercase throughout; all home headings/buttons/body report Vazirmatn and the font is loaded.
- Mobile navigation is an opaque paper viewport modal, locks background scroll, makes home/footer inert and provides collapsed keyboard-accessible accordions; native focus trap/Escape remain intact.
- Mobile header has one hamburger/wordmark/account/cart row and full-width search below. Search keeps its accessible label and uses a magnifier and Persian placeholder; wordmark is live Vazirmatn text over a subtle wash.
- Shared collection cards have white rounded surfaces, square product illustrations (five original 800px PNGs), two-line titles, aligned bottom buttons, price hierarchy and sale/stock pills. Indigo/paper button contrast 8.13:1; ink/coral sale pill 4.81:1; muted price/white 5.78:1.
- Native Persian WooCommerce price setting and Persian separator are idempotently configured. Scoped display-only text conversion covers dynamic prices/counts and dates; browser tests prove numeric field values/attributes stay unchanged. Date localization targets rendered text only, not global wp_date/date_i18n, preserving machine-readable datetime attributes; an explicit datetime preservation assertion was added.
- Audience/trust icons are distinct backpack/palette/briefcase and truck/shield-check/lock/headset SVGs. Section spacing uses 64/40px presets, a separate mist trust band, hover/focus states and reduced-motion support.
- CI 40241275296 passed including all prior smoke/lifecycle checks and new computed-font, modal/accordion, geometry, square-image, digit/input-safety and overflow assertions. Three refreshed screenshots uploaded as `screenshots`. Agent cannot manually inspect artifact images because the sandbox blocks the artifact storage host; owner review remains available in PR #2 → Checks → CI → Artifacts.
- No branch/PR changes, no production configuration or later-prompt work. Owner applies the PowerShell commands above after merging; resetting Site Editor overrides may be necessary.
