# packages/core

Composer package `codeart/core` (Laravel package, auto-discovered via `CoreServiceProvider`).

- Namespace `Codeart\Core\`. Tests use Orchestra Testbench + Pest (`tests/`).
- Owns: design tokens, `ThemeSettings`, shared contracts and events that modules use to talk to each other.
- Colors: only in `resources/css/tokens.css` and the defaults in `config/codeart.php`. Never anywhere else.
- Built CSS lives in `resources/dist/` and is committed (no Node on servers). Rebuild: `ddev exec "cd packages/core && npm run build"`.
- Commands (inside `packages/core`): `composer check`, `composer test`.
- Verify front-end work with HTML/CSS assertions in tests, never screenshots.
