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
- **Menu**: `module.json` `menu` entries become flat sidebar links; richer menus (groups, children) are registered from the provider, see "Admin menu, dashboard, search". `MenuRegistry::items($user)` still returns the manifest entries the user may see.
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

## Admin menu, dashboard, search
Register everything from your provider's `boot()` (it only runs while the module is enabled, so a disabled module disappears by itself).
Every admin route of a module must use `['web', 'auth:admin', 'admin.access']`. Layout: `<x-codeart::admin.layout>`, see `docs/components.md`.
```php
use Codeart\Core\Admin\Dashboard\{QuickAction, QuickActions, Widget, WidgetData, WidgetRegistry};
use Codeart\Core\Admin\Navigation\{AdminMenu, NavItem};
use Codeart\Core\Admin\Search\{SearchProvider, SearchRegistry, SearchResult};

public function boot(): void
{
    // Menu: a group (no route) with children; labels may be translation keys.
    $this->app->make(AdminMenu::class)->add(
        new NavItem('pages', 'pages::menu.pages', 'file-text', order: 20, children: [
            new NavItem('pages.list', 'pages::menu.list', 'list', 'pages.index', 'pages.view', 10),
            new NavItem('pages.new', 'pages::menu.new', 'plus', 'pages.create', 'pages.edit', 20),
        ]),
    );

    // Dashboard widget: a Blade view plus a data class. size: sm (3/12), md (6/12), lg (12/12) on desktop.
    $this->app->make(WidgetRegistry::class)->add(
        new Widget('pages.drafts', 'pages::widgets.drafts', 'file-text', 'pages::widgets.drafts', DraftsWidget::class,
            permission: 'pages.view', size: 'sm', order: 40, module: 'pages'),
    );

    // Quick action: collected into the "Quick actions" widget.
    $this->app->make(QuickActions::class)->add(new QuickAction('pages.new', 'pages::menu.new', 'plus', 'pages.create', 'pages.edit', 10));

    // Command palette results.
    $this->app->make(SearchRegistry::class)->add(PagesSearch::class);
}

class DraftsWidget implements WidgetData
{
    public function data(?Authenticatable $user): ?array   // null = leave the widget out
    {
        return ['count' => Page::where('status', 'draft')->count()];   // the view gets $widget, $data, $user
    }
}

class PagesSearch implements SearchProvider
{
    public function results(?Authenticatable $user): array   // short, cheap, only what the user may open
    {
        return [new SearchResult('New page', route('pages.create'), 'plus', 'Pages')];
    }
}
```
- **NavItem**: `key` (unique), `label`, `icon` (Lucide name, see below), `route` (null = group), `permission` (Gate ability; null = everyone who may enter), `order`, `parent`, `children`, `module`. Hidden: items the user may not see, items whose route does not exist, groups without a visible child. Active: the item's route, or any route of its section when the item's route ends in `.index`. The Dashboard item belongs to core. A "Modules" item (permission `manage-modules`, super-admin only) comes later.
- **Widget**: a throwing provider or view is logged and shown as a small error card; the rest of the dashboard is unaffected. Sizes and order as in the example. No made-up numbers: show real data or no widget.
- **Icons**: the Lucide names in `packages/core/scripts/icons.txt` (add yours there and run `npm run build:icons` in core), or `app(Codeart\Core\Admin\Icons::class)->register('my-icon', '<path d="..."/>')` (24x24, stroke style). An unknown name renders nothing.
- **Core registers**: menu item `dashboard`; widgets `core.recent-activity` (permission `users.view`), `core.system-status` (`system.status`, admin and super-admin), `core.quick-actions`; the menu search provider.

## Settings
Settings live in the `settings` table (typed, cached as one entry, secrets encrypted). A module registers its definitions in its service provider; they exist only while the module is enabled (stored values survive a disable). Keys must start with `<module>.`.
```php
use Codeart\Core\Settings\{SettingDefinition, SettingsRegistry, SettingType};

public function boot(): void
{
    $this->app->make(SettingsRegistry::class)->register(
        new SettingDefinition(
            key: 'pages.per_page', type: SettingType::Int, default: 12,
            rules: ['min:1', 'max:100'],
            permission: 'pages.edit',          // who may change it; null = super-admin only
            label: 'Pages per page', description: 'Shown on list screens.',
            module: 'pages',
        ),
    );
}
```
Types: `string int bool color enum json image secret`. Other options: `translatable: true` (one value per locale), `options: [...]` (enum), `itemRules` (rules per list item), `check: fn ($value, $read) => ?string` (cross-field rule; `$read('other.key')` gives the value after the same write), `default` may be a Closure (config-backed).
```php
$settings = app(Codeart\Core\Settings\Settings::class);
$settings->get('pages.per_page');            // typed, default if unset, never throws for a missing DB
$settings->set('pages.title', 'Naslov', 'sr');  // validates; null = back to the default
$settings->setMany([...]);                   // one validated write (cross-field rules see the final state)
$settings->masked('x.api_key');              // for display: secrets become ********
app(SettingsManager::class)->update($user, $key, $value);  // checks permission + lock (use this for people)
```
`Settings::set` is the raw layer (CLI, seeders, internal code): no permission or lock check. Unknown keys always throw. Never put a secret in a log or exception message; use `masked()`.

**Who may change what:** a definition's `permission` (core: `settings.appearance` for `theme.*` and `locales.*`, granted to `admin`); `null` = `super-admin` only (security settings). `super-admin` can lock/unlock any key; a locked key is changeable by `super-admin` only. CLI: `settings:list|get|set|lock|unlock` (CLI = super-admin; secrets masked).

**Appearance:** `theme.primary|secondary|mode|logo_light|logo_dark|favicon`. `theme.mode` is `light|dark|auto`; `<html @include('codeart::partials.theme-attributes')>` prints `data-theme="..."`, `auto` follows the visitor's system preference through a `prefers-color-scheme` block in tokens.css. Logos/favicon are uploaded with `BrandingImageUploader` (png, jpg, webp by real content, read by the `ImageProcessor`; transparent margins of PNG/WebP are trimmed and the original kept, see DECISIONS I1-I4; size and dimension limits in `codeart.branding`; SVG refused).

**Locales:** `locales.default|enabled|fallbacks` (config values are only the defaults; `available` stays in config). The default must be enabled; a derived locale (`sr-Cyrl`) needs its source (`sr`) enabled.

**Security settings** (`security.*`: 2FA mode and roles, password rules, session lifetime, login attempts, log retention) are defined and validated but not enforced until login exists.
