# Writing a module

A module is a Composer package that CodeArt core can switch on and off. Look at `packages/modules/example` for a working one.

## Package layout
```
codeart/module-pages/
  composer.json        "type": "codeart-module", requires codeart/core
  module.json          the manifest (below)
  src/PagesServiceProvider.php
  database/migrations/
  routes/web.php       optional, loaded for you
  resources/views/     optional, namespace = module name: view('pages::index')
```
Naming: package `codeart/module-<name>`, namespace `Codeart\<Name>\`, module name = short lowercase slug (`pages`).

## module.json
| Field | Required | Meaning |
|---|---|---|
| `name` | yes | slug, lowercase letters/digits/dashes. Used everywhere (permissions, tables, views). |
| `package` | yes | Composer name; must equal the installed package. |
| `title`, `description` | yes | Plain strings for now (translations come later). |
| `provider` | yes | Service provider class. Registered only while the module is enabled. |
| `requires` | no | `{"pages": "^1.0"}`: other modules (by module name) that must be enabled first. |
| `permissions` | no | Strings that must start with `<name>.`, e.g. `pages.view`. |
| `menu` | no | List of `{label, route, permission, icon, order}`. `permission` must be one of the module's own (or `null` = everyone). |
| `migrations` | no | Relative path, normally `database/migrations`. |
| `license` | no | Unused for now. |

There is **no `version` field**. The version is read from Composer's installed metadata, so it always matches what is installed. Unknown fields are rejected (typos fail loudly).

## What core does for you
- **Discovery**: reads `vendor/composer/installed.php` (no shell needed; works after an FTP upload).
- **Enable** (`php artisan module:enable pages`): checks `requires` (installed, enabled, version constraint; `dev-*` installs skip the version check), runs the module's migrations, creates its permissions, boots it, fires `ModuleEnabled`.
- **Disable** (`module:disable pages`): refused while another enabled module requires it. Never deletes data, tables or permissions. Fires `ModuleDisabled`.
- **Boot** (every request, only enabled modules): registers the provider, `routes/web.php` (with the `web` middleware), `resources/views` and the migrations path. The enabled list is cached; with the database not ready (install time) nothing is booted and nothing crashes.
- **Menu**: `MenuRegistry::items($user)` returns the menu items of enabled modules the user may see, sorted by `order`.
- `module:list` shows what is installed and enabled.

Only a super-admin may manage modules (Gate `manage-modules`); for now the commands are CLI-only.

## Rules
- **Table prefix**: every table starts with the module name, `pages_*`. Never touch another module's tables.
- **Permissions**: `<module>.<action>`. Protect routes with `->middleware('can:pages.view')`. The `super-admin` role passes every check.
- **No direct calls between modules.** Never `use` another module's classes. Talk through events and contracts (interfaces) that live in core. If you need something from another module, add a contract/event to core first.
- **Migrations** must be safe to run once; they are tracked by Laravel's `migrations` table, so enabling again never repeats them.
- **Listening to enable/disable**: `Event::listen(ModuleEnabled::class, ...)` in your provider. Check `$event->module->name` if you only care about your own module (your listener also hears other modules being enabled).
- **Colors**: only semantic utilities (`bg-primary`...), see CLAUDE.md. Public site output is the client's, not the CMS's.

## Roles and permissions in the app
Core uses `spatie/laravel-permission` and creates the roles `super-admin`, `admin`, `editor` (`php artisan codeart:roles`, or call `Codeart\Core\Database\Seeders\RolesSeeder`). Core does **not** own the User model: the app adds the trait.
```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```
Core ships the permission tables migration. Do not publish Spatie's own migration (it would create the same tables twice).
