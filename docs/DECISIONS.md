# Decisions log

Running log. Add an entry for every choice made on the owner's behalf. Format: date, decision, why.

## 2026-11-25 (foundation)

1. **Laravel 12, not 13.** Laravel 13 requires PHP 8.3+; the minimum is PHP 8.2, so Laravel 12 (`^12.0`) is the latest compatible. Revisit when the PHP floor moves.
2. **Pest 3 / Testbench 10 / Larastan 3.** Pest 4 needs PHP 8.3+; these are the newest versions that support PHP 8.2.
3. **Composer `platform` php = 8.2.0** in root, core and starter, so lock files resolve for 8.2 and still install on newer PHP.
4. **DDEV runs PHP 8.2** (the minimum); the "latest PHP" is covered by the CI matrix (`8.2`, `latest`) because DDEV runs one version at a time. DB: MariaDB 10.11 (common on cPanel).
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
