# apps/starter

Thin Laravel 12 app that only wires `codeart/core` (and later modules) together via Composer `path` repositories.

- Keep it thin: no CMS logic here. Logic goes into core or a module package.
- Public site views belong to the client and must never be dictated by the CMS; they use only semantic color utilities.
- Config: DB queue + scheduler via cron, no Node on servers (assets are published from core: `php artisan vendor:publish --tag=codeart-assets`).
- Commands (inside `apps/starter`): `composer check`, `composer test`.
