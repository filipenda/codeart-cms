# Decisions log

Running log. Add an entry for every choice made on the owner's behalf. Format: date, decision, why.

## 2026-11-25 (foundation)

1. ~~SUPERSEDED by U1-U6 (2026-11-25 upgrade).~~ **Laravel 12, not 13.** Laravel 13 requires PHP 8.3+; the minimum is PHP 8.2, so Laravel 12 (`^12.0`) is the latest compatible. Revisit when the PHP floor moves.
2. ~~SUPERSEDED by U1-U6 (2026-11-25 upgrade).~~ **Pest 3 / Testbench 10 / Larastan 3.** Pest 4 needs PHP 8.3+; these are the newest versions that support PHP 8.2.
3. ~~SUPERSEDED by U1-U6 (2026-11-25 upgrade).~~ **Composer `platform` php = 8.2.0** in root, core and starter, so lock files resolve for 8.2 and still install on newer PHP.
4. ~~SUPERSEDED by U1-U6 (2026-11-25 upgrade).~~ **DDEV runs PHP 8.2** (the minimum); the "latest PHP" is covered by the CI matrix (`8.2`, `latest`) because DDEV runs one version at a time. DB: MariaDB 10.11 (common on cPanel).
5. **DDEV docroot `apps/starter/public`**, project root = repo root so the path repositories resolve.
6. **Root `composer.json` has no dependencies**, only scripts. Each package/app owns its vendor and its own `composer check`; root `composer check` runs `check:colors`, core, starter. `composer setup` installs both.
7. **Larastan starts at level 6**, to be raised gradually towards 9.
8. **Pint default (Laravel preset)**, no custom config for now.
9. **Core has no committed `composer.lock`** (library); starter lock is committed. Root/core locks are git-ignored.
10. **Starter has no Node tooling** (`package.json`, Vite removed). The only front-end build lives in `packages/core` (Tailwind CLI v4), and the built `resources/dist/codeart.css` is committed and published with `php artisan vendor:publish --tag=codeart-assets` (to `public/vendor/codeart`, git-ignored in starter). Works for SSH; for FTP-only, run publish locally and upload the folder.
11. **Tailwind**: default palette removed (`--color-*: initial`); semantic utilities are safelisted via `@source inline(...)` so they exist in the built CSS. Tailwind's `@theme inline` emits self-referencing vars inside `@layer theme`; unlayered `tokens.css` always wins, so this is harmless (documented in `app.css`).
12. **Theme overrides are only emitted for colors that differ from the brand default.** Brand primary then uses the hand-tuned values in tokens.css (e.g. text `#22201f`) instead of computed ones.
13. **Override selectors are `:root:root{...}` and `:root[data-theme="dark"]{...}`** (instead of plain `:root`) so inline overrides beat tokens.css regardless of stylesheet load order. Primary shades apply to both themes; the link color is derived separately for light (contrast vs. light surface) and dark (vs. dark surface) with a 4.5:1 minimum.
14. **Theme CSS cache** uses `Cache::rememberForever` keyed by a hash of the settings, so changing settings changes the key (no invalidation needed). Works with the file cache on shared hosting. Old keys are harmless leftovers.
15. **`check:colors` is plain PHP** (`tools/check-colors.php`, no dependencies). Scans php/css/js/ts/vue/html/svg; skips `vendor`, `node_modules`, `storage`, `dist`, `build`, `cache`, `tests`. Allowed files: `tokens.css` and `config/codeart.php`. Tests dirs are skipped because tests need color fixtures. It also flags `bg-white`/`bg-black` as raw palette colors, and functional colors (`rgb(`, `hsl(`, `oklch(` ...). The `.blade.php` and docs `.md` files: Blade is scanned via `php`; Markdown is not scanned.
16. **Core's color-checker test is monorepo-only** and skips itself when `/tools` is missing (after a package split).
17. **Starter welcome page** replaced by a minimal page that includes the core stylesheet and theme partial (the default Laravel page is full of raw colors).
18. **Module/package namespace** `Codeart\Core\`; config file `config/codeart.php`; view namespace `codeart::`.
19. **CI** installs with `composer setup` then runs `composer check`; informational only (no branch protection on the free plan).
20. **Git**: `main` has one initial commit (README, .gitignore); all work on `chore/foundation`; nothing pushed.

## 2026-11-25 (upgrade to latest stable)

Versions verified from packagist.org, php.net and mariadb.org on this date.

- ~~SUPERSEDED by V1-V7 (PHP 8.4 upgrade).~~ **U1. Laravel 13.44 (`^13.0`); PHP minimum 8.3.** Laravel 13 requires `^8.3`, so the minimum is 8.3 and Composer `platform` is `8.3.0` in root, core and starter. The earlier "PHP 8.2 minimum" was a mistake.
- ~~SUPERSEDED by V1-V7 (PHP 8.4 upgrade).~~ **U2. Pest 4.7 (not 5), pest-plugin-laravel 4.1, Testbench 11.3.** Pest 5 (5.3.7) requires PHP ^8.4, which would break the 8.3 minimum and the CI matrix. Pest 4 is the newest compatible. Move to Pest 5 when the PHP minimum reaches 8.4.
- ~~SUPERSEDED by V1-V7 (PHP 8.4 upgrade).~~ **U3. Larastan 3.15 (latest, supports Laravel 13 / PHP 8.2+), Pint 1.32, Tinker 3.2, PHPUnit 12.5 (via Pest).** Larastan level stays 6; passes with no code changes.
- ~~SUPERSEDED by V1-V7 (PHP 8.4 upgrade).~~ **U4. DDEV runs PHP 8.5** (the newest PHP that DDEV 1.25.4 supports; PHP 8.6.0 was released 2026-11-19 but DDEV cannot select it yet). The CI `latest` entry covers newer PHP; matrix is `8.3` and `latest`.
- ~~SUPERSEDED by V1-V7 (PHP 8.4 upgrade).~~ **U5. MariaDB 12.3 (newest LTS, supported until 2029-06)** in DDEV. The old 10.11 volume was empty (the app uses SQLite) and was reset (with DDEV snapshot). Hosting requirement stays MariaDB 10.11+ so cPanel servers remain supported.
- **U6. Tailwind CSS 4.3.5** (`tailwindcss` and `@tailwindcss/cli`, `^4.3.5`). Rebuilt; `resources/dist/codeart.css` came out byte-identical, so no change to commit there. Livewire is not required yet, so it is not added.
- ~~SUPERSEDED by V1-V7 (PHP 8.4 upgrade).~~ **U7. Hosting requirements** documented in `docs/ARCHITECTURE.md` (PHP 8.3+, extension list, DB, cron). Extension list follows Laravel's server requirements plus `intl`/`pdo_mysql`.

## 2026-11-25 (PHP 8.4 minimum, MariaDB tests)

Versions verified on Packagist and via `composer show`. U6 (Tailwind 4.3.5) still stands.

- **V1. PHP minimum 8.4; Composer `platform` and `require` php = 8.4.1** (root, core, starter). Not 8.4.0: Symfony 8 (needed by Pest 5) requires PHP >= 8.4.1, so a platform of 8.4.0 cannot resolve. Hosting requirement is "PHP 8.4.1 or newer".
- **V2. Pest 5.3.7 + pest-plugin-laravel 5.0.1**, Testbench 11.3.0, Larastan 3.15.0 (newest, Laravel 13 ready), Pint 1.32.3, Laravel 13.44.1, Tinker 3.2. Pest 4 constraints replaced by `^5.0`.
- **V3. Tests and dev run on MariaDB, not SQLite.** Production databases are MariaDB/MySQL, so SQLite is out of the default path: `phpunit.xml` and Testbench use the `mariadb` driver, database `test`, host `db` (DDEV), user root. `.env.example` and `config/database.php` default to `mariadb`; the sqlite `touch` was removed from composer scripts. Starter Feature tests use `RefreshDatabase`. Each app has a test asserting the driver is MariaDB.
- **V4. Test database `test` is created by a DDEV `post-start` hook** (root/root). Dev data in database `db` is never touched by tests.
- **V5. CI starts a `mariadb:12.3` service** and passes `DB_HOST=127.0.0.1`, `DB_DATABASE=test`, root/root; PHP extension `pdo_mysql` replaces `pdo_sqlite`. Matrix: PHP `8.4` and `latest`.
- **V6. DDEV stays on PHP 8.5** (newest PHP that DDEV 1.25.4 supports) and MariaDB 12.3 (newest LTS). The CI matrix (`8.4` + `latest`) covers PHP versions newer than DDEV supports (e.g. 8.6).
- **V7. MariaDB floor** for client servers stays 10.11+ / MySQL 8+, but only 12.3 is exercised by tests (untested on older versions).
