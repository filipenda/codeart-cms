# CodeArt CMS

Modular, reusable Laravel CMS: one shared codebase (core + modules) installed separately on many client servers (cPanel; sometimes SSH, sometimes FTP only). The CMS never dictates how the client's public website looks. Only a super-admin can enable/disable modules. Multilingual (sr Latin source, auto-Cyrillic with override, other languages by hand) is planned, not built yet.

## Structure (monorepo, released later as split packages)
- `packages/core`      `codeart/core`: Laravel package, auto-discovery, tokens, theme, contracts, events
- `packages/modules/`  future `codeart/module-<name>`
- `apps/starter`       thin Laravel 12 app requiring core via Composer `path` repositories
- `tools/`             repo tooling (`check-colors.php`); `docs/` decisions and task briefs

Env: PHP >= 8.2 (composer `platform` 8.2.0, CI also on latest), Laravel 12, DDEV. No Node on production: assets are built locally and shipped inside packages. No queue workers: database queue + scheduler via cron.

## Commands
- `ddev start`, then `ddev composer setup` (first time)
- `ddev composer check` = check:colors + Pint (test mode) + Larastan + Pest for core and starter
- `ddev composer check:colors` alone; inside a package: `composer check`, `composer test`
- Rebuild CSS: `ddev exec "cd packages/core && npm run build"` (commit `resources/dist`)

## Code conventions
- Thin controllers/Livewire components -> Action classes (one class = one operation) -> Eloquent models.
- Service classes only when several actions share logic. No generic repository layer over Eloquent; interface + adapter ONLY for external systems (ERP etc.).
- Modules never call each other directly: they communicate through events and contracts (interfaces) defined in core.
- Primary key: normal auto-increment ID. Add a ULID column only where an ID is exposed externally (API, public URLs, ERP sync).
- Table prefix per module (e.g. `pages_*`).
- Admin UI (later): Blade + Livewire + Alpine + Tailwind. No React in admin.
- Everything must work on shared hosting over SSH or FTP only: no daemons, no Node, no required root access.

## Color rule (strict)
Every color lives in ONE place: `packages/core/resources/css/tokens.css` (plus brand defaults in `packages/core/config/codeart.php`).
- Views, components, CSS and PHP use only semantic utilities (`bg-primary`, `text-on-primary`, `text-text-muted` ...).
- No hex/rgb/hsl and no raw Tailwind palette colors (`bg-lime-400`, `bg-white`) anywhere else. `composer check:colors` enforces it (tests dirs are not scanned).
- Client colors come from `ThemeSettings` (derives shades, picks readable text by WCAG contrast).

## Git rules
- `main` is always releasable; short-lived branches `feat/`, `fix/`, `chore/`; Conventional Commits; PRs are squash-merged.
- Never push or touch `main` unless asked. CI is informational (no branch protection), see CONTRIBUTING.md.

## Verification rule
Never take screenshots. Verify front-end work by measuring: rendered HTML, CSS text, computed/derived values and contrast ratios in Pest tests.
Do not read `vendor/` or `node_modules/`.

## Docs
- `docs/ARCHITECTURE.md`: decisions and why. `docs/DECISIONS.md`: running log; add an entry for every choice made on the user's behalf.
- `docs/tasks/_template.md`: task brief template for new sessions.
