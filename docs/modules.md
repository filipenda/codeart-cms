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

## Translations
Translated content (page titles, product names...) uses core's `Translatable` trait. Locales are configured in `config/codeart.php` under `locales` (default, enabled, fallback chain, per locale `label`, `script`, and `manual` or `derived_from` + `transliterator`). This is *content* language only; the admin interface language is a separate setting.

Default setup: `sr` (Serbian Latin, the source), `sr-Cyrl` (generated from `sr`), `en` (by hand). Fallbacks: `sr-Cyrl -> sr`, `en -> sr`; the default locale is always the last resort.

**Making a model translatable** (see `ExampleItem` in the example module):
1. Migration: `<model>s` (no translated columns) and `<model>_translations` with `<model>_id` (FK, cascade), `locale` (string 12), the translated columns, `slug`, `manual_fields` (JSON, nullable), `unique(<model>_id, locale)` and `unique(locale, slug)`.
2. `class Item extends Model { use Translatable; public function translatedAttributes(): array { return ['title', 'slug']; } }`
3. `class ItemTranslation extends Codeart\Core\Translations\Translation {}` (same namespace; override `translationModel()` otherwise).

**API**
```php
$item->setTranslation('sr', ['title' => 'Ljubav i njiva']); // saves; sr-Cyrl is generated, slug created
$item->setTranslation('en', ['title' => 'Love']);           // own slug (unique per locale)
$item->title;                           // current locale (app()->getLocale()), with fallback chain
$item->translated('title', 'en');       // one attribute, one locale
$item->getTranslation('en', false);     // the row, without fallback (null if missing)
$item->hasTranslation('en');            // exact locale only
Item::whereTranslatedSlug('love', 'en')->first();           // find by slug in a locale
Item::uniqueSlug('Ljubav', 'sr');       // free slug (ljubav, ljubav-2, ...)
$item->resetToAutomatic('sr-Cyrl', 'title');                // give a hand-edited value back to the transliterator
```
The model must be saved before `setTranslation()`. Disabled or unknown locales throw. A missing `slug` is generated from `title` (`slugSource()`), is lowercase ASCII (`đ` becomes `dj`), and stays stable when the title changes later.

**Cyrillic rules**
- Saving `sr` regenerates `sr-Cyrl` for every attribute not listed in `manual_fields`. If `sr` does not exist yet, nothing is generated. The `sr-Cyrl` slug is always copied from `sr`.
- Saving `sr-Cyrl` directly: a value that differs from the generated one is added to `manual_fields` (and is never overwritten again); a value equal to it is removed from the list.
- Transliteration is rule based: `lj nj dž` become Љ Њ Џ, `đ` becomes ђ, a plain `dj` stays д+ј. HTML tags and attribute values, script/style, entities, URLs, e-mail addresses and placeholders (`:name`, `%s`, `{name}`, `{{ }}`) are left alone.
- **Known limitation:** ambiguous words cannot be solved by rules: "injekcija" comes out as "ињекција" (correct: "инјекција"). The editor corrects the Cyrillic by hand; that edit is kept (`manual_fields`).
