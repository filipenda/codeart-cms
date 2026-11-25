# Architecture

## Shape
One monorepo, many Composer packages: `codeart/core`, `codeart/module-<name>`, and a thin `apps/starter`. Each client gets their own installation (own server, own database). Developed together, released as split packages later.
*Why:* a single codebase to maintain, with per-client installs that can enable only the modules they need.

## Hosting constraints drive everything
Targets are cPanel shared hosting, sometimes SSH, sometimes FTP only. Therefore: no Node on servers (assets are pre-built and shipped in packages), no queue workers (database queue + cron scheduler), no required daemons, file/database cache only.

## Layering
Controller / Livewire component -> Action (one operation) -> Eloquent model. Services only when actions share logic. No repository layer over Eloquent; interface + adapter only for external systems.
*Why:* least ceremony; Eloquent is already the data layer.

## Modules
Modules never call each other. They talk through events and contracts defined in core, so any module can be removed without breaking others. Only a super-admin can enable/disable modules.

## Identifiers and tables
Auto-increment ID everywhere; a ULID column only for externally exposed records. Each module prefixes its tables (`pages_*`).

## Design tokens
`tokens.css` is the single source of truth for colors, radius, spacing, fonts (light on `:root`, dark on `[data-theme="dark"]`). Tailwind v4 (CSS-first) maps its theme to these variables and the default palette is removed, so raw palette utilities do not exist. `ThemeSettings` returns overrides (from config now, DB later), derives hover/link shades from a client primary and picks dark/white text by WCAG contrast; a Blade partial prints them as a cached inline `<style>`. `check:colors` enforces the rule in CI.

## Quality
Pint, Larastan (level raised gradually), Pest; one root `composer check`; CI on PHP 8.4 (minimum) and latest stable, with a MariaDB 12.3 service. Tests run on MariaDB (never SQLite) locally and in CI. Stack: Laravel 13, Pest 5, Larastan 3, Pint, Testbench 11, Tailwind 4.3.

## Hosting requirements
For client servers (cPanel, SSH or FTP-only):
- **PHP 8.4.1 or newer** (the product is tested on 8.4 and the latest stable PHP).
- Required PHP extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `intl`, `mbstring`, `openssl`, `pcre`, `pdo` with `pdo_mysql`, `session`, `tokenizer`, `xml`, `xmlwriter`.
- Database: MariaDB or MySQL (production). Developed and tested on MariaDB 12.3 LTS; MariaDB 10.11+ / MySQL 8+ are the supported floor but only 12.3 is exercised by tests.
- Cron able to run `php artisan schedule:run` every minute (database queue is processed by the scheduler). No Node, no daemons, no root access.
