# Installer (developer view)

One service, two doors: the web wizard (`/install`) and `php artisan codeart:install` both call `Codeart\Core\Install\InstallerService`. Client packages are built by `tools/build-package.php`.

## Parts (`packages/core/src/Install`)
- `InstallPaths`: base path, `.env`, marker, key file. All from `config('codeart.install.*')` (null = the real application folder), so tests run in a temporary directory.
- `InstallState`: **installed = the marker file `storage/app/installed` exists** (one file check per request). `enabled()` = `config('codeart.install.enabled')`, which defaults to "the file `install.key` exists in the application folder": only the client ZIP has it, so the dev app and core's tests never see the installer.
- `InstallKey`: reads `install.key`, compares with `hash_equals`, throttles through `RateLimiter` (5 wrong tries per IP, then 10 minutes). The wizard refuses everything while the file is missing. The key file is deleted by the last run step.
- `Requirements` + `Probe`: the list (PHP >= 8.4.1, `pdo_mysql mbstring openssl intl fileinfo zip ctype json tokenizer xml curl`, `gd` or `imagick`, writable `storage/`, `bootstrap/cache/`, `.env` or its folder, `vendor/autoload.php` + `bootstrap/app.php` present, `memory_limit` >= 128M, and the non-blocking warning "debug on"). Tests replace `Probe` to pretend any server.
- `DatabaseTester`: plain PDO, maps MySQL error numbers to `credentials | unknown_database | unreachable | privileges | other` (texts `install.db_error.*`), counts existing tables, proves it can `CREATE TABLE`.
- `EnvWriter`: merges keys into `.env`, atomic write, mode 0600, quotes awkward values, never logs.
- `InstallerService::run()`: steps `migrate, roles, settings, admin, modules, storage, finish`, each returns a `StepResult`; the first failure stops the run. Every step is safe to repeat (migrate is incremental, roles are `findOrCreate`, settings overwrite, the admin is skipped when that e-mail already is a super-admin, `EnableModule` returns false when already on). `finish` switches `SESSION_DRIVER` to `database`, writes the marker and deletes `install.key` and `storage/app/installer.key`.
- `Http/*`, `routes/install.php`, `resources/views/install`, `components/install/layout`, lang `install.php` (sr, en, generated sr-Cyrl).

## Not installed yet
`InstallServiceProvider` (registered by core before auth) sets, while the marker is missing and the installer is enabled: session driver `file` (encrypted), cache `file`, queue `sync`, no debug, and a temporary `APP_KEY` kept in `storage/app/installer.key` (it becomes the real `APP_KEY`, so the wizard session survives writing `.env`). A global middleware sends every request except `/install*` and `/up` to `/install`. Installed: `/install*` answers 404; nothing but the CLI with `--force` can run it again. An emptied database does not reopen it (the marker decides).

## CLI
`php artisan codeart:install` with flags or `CODEART_INSTALL_<NAME>` environment variables (`DB_PASSWORD`, `ADMIN_PASSWORD`, ...); missing values are asked for when the terminal is interactive.
`--name --url --timezone --locales=sr,sr-Cyrl,en --default-locale --admin-path --db-host --db-port --db-name --db-user --db-password --first-name --last-name --email --admin-password --generate-password --modules=a,b|all --confirm-existing-db --force`.
Prefer environment variables for secrets (a flag stays in the shell history). `--generate-password` prints the password once. No install key is needed on the command line. A non-empty database needs `--confirm-existing-db`. `--force` removes the marker and installs again.
The CLI cannot know where `public_html` is, so `storage:link` may only warn there; link `public_html/storage` to `storage/app/public` by hand.

## Build the client ZIP
`php tools/build-package.php [--out=build] [--version=1.2.3]` (needs PHP with `zip`, Composer; no Node). Copies `apps/starter` + `packages/*` into a staging folder, `composer install --no-dev --optimize-autoloader` (local packages are copied into `vendor`, not linked), strips tests/docs from `vendor`, publishes the built assets, creates the empty storage structure, a random `install.key`, `VERSION`, and zips two folders:

- `cms/` the application (goes OUTSIDE the web root)
- `public_html/` index.php, .htaccess, assets (goes to `public_html`)

`public_html/index.php` has ONE marked setting, `$applicationPath` (default `__DIR__.'/../cms'`). It also calls `usePublicPath(__DIR__)` so `storage:link` and asset paths point to `public_html`. The output ends with the ZIP path and its SHA-256. `build/` is git-ignored; never commit a ZIP. There is no `.env` in it.

## Tests
- `packages/core/tests/Install/*`: key gate, each requirement red/green, database error mapping (wrong password, unknown database, unreachable, missing privileges), `.env` writer, the whole wizard through the HTTP kernel, the CLI, retry after a failed migration, the lock after install. `InstallTestCase` makes a temporary directory and a throwaway database (`codeart_inst_*`) per test; it never touches the dev `.env` or the dev database.
- `apps/starter/tests/Build/PackageBuildTest.php`: builds the ZIP, checks its content, boots the unpacked application from another directory and with the application folder moved (path setting). Needs Composer's cache or network.
- Rule: tests and checks never touch the dev `.env` or the dev database for install tests (CLAUDE.md).
