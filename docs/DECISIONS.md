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

## 2026-11-25 (module registry and permissions)

Versions verified on Packagist: `spatie/laravel-permission` 8.5.0 (requires illuminate `^12.0|^13.0`, so Laravel 13 is supported), `composer/semver` 3.6.0.

- **M1. `spatie/laravel-permission` ^8.5 and `composer/semver` ^3.6 are `require`d by core.** Semver is needed at runtime to check module `requires` constraints (production has no dev tools).
- **M2. Core owns the permission tables migration** (copy of Spatie's schema, teams off, table names from `config/permission.php`) and the `modules` migration; both load through `loadMigrationsFrom`. Apps must not publish Spatie's own migration. Core registers Spatie's service provider itself.
- **M3. Manifest rules beyond the brief:** unknown fields are rejected; `name` is a lowercase slug; `package` must be `vendor/name`; permissions must start with `<name>.`; a menu item's `permission` must be one of the module's own (or `null` = visible to everyone); `migrations` is optional and must be a relative path without `..`; `description` is required. `requires`, `permissions`, `menu`, `migrations`, `license` are optional.
- **M4. Discovery never throws for one bad module:** invalid manifests, package/name mismatches and duplicate names are collected as "problems" and shown by `module:list`; other modules keep working.
- **M5. Conventions inside a module package:** routes in `routes/web.php` (loaded with the `web` middleware), views in `resources/views` (namespace = module name), migrations at the manifest's path. Core loads them only for enabled modules. API/console routes are not covered yet. Routes are skipped if the app's route cache exists (an app that caches routes must re-run `route:cache` after enabling/disabling).
- **M6. Enabled-list cache** is one forever-key (`codeart.modules.enabled`) in the default cache store, forgotten on enable/disable, never written while the database is not ready. If the cache store itself fails (e.g. database cache table missing at install), it falls back to the database, then to "nothing enabled".
- **M7. Module boot happens in an `$app->booted()` callback** of core's provider, so every other provider is registered first. Enabling at runtime boots the module immediately (so its own `ModuleEnabled` listener receives the event it caused).
- **M8. Enable/disable are idempotent:** enabling an enabled module (or disabling a disabled one) does nothing and the command says so; it is not an error. Re-enabling never repeats migrations (Laravel's `migrations` table) and never duplicates permissions.
- **M9. Version constraints on `requires` are checked against the installed version, but `dev-*` / `*-dev` installs (path repositories, the monorepo) skip the check**, because they have no comparable version.
- **M10. Permissions are created for the default guard (`auth.defaults.guard`) and not assigned to any role.** `super-admin` passes via `Gate::before`; `admin` and `editor` get module permissions explicitly (future admin screen). Disabling never removes permissions.
- **M11. `disable` of a module whose package vanished from `vendor/`** still flips the record to disabled (so a site can recover) but fires no `ModuleDisabled` event (no manifest to announce). `module:list` flags such modules.
- **M12. `module:enable`/`disable` are CLI-only and have no authorization check;** whoever has a shell is trusted. The `manage-modules` Gate (super-admin only, also via `Gate::define` so `admin` is denied explicitly) is for the future admin screen.
- **M13. Roles come from an action `CreateDefaultRoles`,** exposed as `php artisan codeart:roles` and `RolesSeeder` (not wired into the app's `DatabaseSeeder`; the app calls it where it wants).
- **M14. Example module** lives in `packages/modules/example`; the starter pulls it with a second path repository (`../../packages/modules/*`) and `codeart/module-example: @dev`. Its routes return JSON (no views, so no colors involved). Its listener seeds one row when the module is enabled.
- **M15. Tests:** core tests use fixture modules (`tests/Fixtures/modules`) through a fake discovery, the array cache, and `migrate:fresh` before each module test (enabling runs DDL, so transaction-based `RefreshDatabase` cannot be used). The starter's example-module tests live in `tests/Modules` for the same reason. Test stub user = `Tests\Support\StubUser` with `HasRoles`; the starter's real `User` got the trait.
- **M16. Release rules** documented in CONTRIBUTING.md (full), CLAUDE.md (short) and ARCHITECTURE.md; no CI/release automation added (not asked).
