# Blade components (`<x-codeart::...>`)

Anonymous Blade components in `packages/core/resources/views/components`. They use only semantic Tailwind utilities
(`bg-primary`, `text-text-muted`, `rounded-control` ...), so a theme change in `tokens.css` or the theme settings restyles them.
**No color literal anywhere** (`composer check:colors`). Every component works without JavaScript; Alpine only enhances.

Load the assets once per page with `<x-codeart::assets />` (the auth layout does it). To change a component in an app:
`php artisan vendor:publish --tag=codeart-views` copies `components/` and `auth/` to `resources/views/vendor/codeart/`,
and the copy wins.

## Tokens added for these components (`tokens.css`, light, dark and auto)

| Token | Use |
|---|---|
| `--color-primary-soft` | `color-mix` of the primary color (22% light, 14% dark): background shapes (`fill-primary-soft`) |
| `--shadow-card` | the very soft card shadow (`shadow-card`) |
| `--radius-card` 24px, `--radius-control` 14px | `rounded-card`, `rounded-control` |
| `--color-focus` | focus ring color (= `--color-link`, which is contrast-checked against the surface) |

## Layout and structure

### `auth-layout`
Page shell: `<html>` (lang from the app locale, `data-theme` from the settings), head, assets, decorative SVG shapes,
the language pill (top right), a centered `card`, flash status. Props: `title` (page title).
Slots: default, and the empty named slot `social` (rendered below a divider only when given; a later task fills it).
```blade
<x-codeart::auth-layout :title="__('codeart::ui.forgot_title')">
    ...content...
    <x-slot:social> ...buttons... </x-slot:social>
</x-codeart::auth-layout>
```
`session('status')` is shown as a success alert: Fortify's keys come from `codeart::ui.status.*`, finished sentences are shown as they are.

### `card`
White card, max 480px, 40px padding (full width and 24px on phones), 24px radius, logo on top.

### `heading`
Props: `title`, `text` (optional muted sentence). Centered 32px bold `h1`.

### `tabs`
Props: `tabs` (`[['key' => 'login', 'label' => '...', 'href' => '...']]`), `active`, `label` (accessible name).
One named slot per key holds the panel. Without JS the tabs are links to the page of each tab (the server renders the
active panel); with Alpine they switch in place. A single tab renders no tab list.
```blade
<x-codeart::tabs :tabs="$tabs" active="login"><x-slot:login>...</x-slot:login><x-slot:register>...</x-slot:register></x-codeart::tabs>
```

### `logo`
Prop: `class` (default `h-12 w-auto`). Reads `theme.logo_light` / `theme.logo_dark`: the light logo on light, the dark one on dark
(CSS by `data-theme`, also for `auto`). One logo set = used for both; none = the text wordmark CODEART.

### `language-dropdown`
A `<details>` pill with the ENABLED locales (`Locales::name()`: Srpski, Српски, English), posting to route `codeart.locale`.
Hidden when only one locale is enabled. Works without JS.

### `assets`
Prints the `<link>` and `<script defer>` for `vendor/codeart/codeart.{css,js}` with `?v=<content hash>` (`Codeart\Core\Theme\Assets`).

## Form components

### `field`
Label + control + hint + error, wired with `for`/`id` and `aria-describedby`, `aria-invalid` on errors.
Props: `name`, `label`, `type` (`text|email|password|otp|...`; `password` and `otp` pick those controls), `icon`, `id` (default `field-{name}`),
`hint`, `bag` (a `MessageBag`; default the request's `$errors`; use it when one page holds two forms), `value` (default `old(name)`).
Other attributes (`autocomplete`, `required`, `autofocus`, `readonly`) go to the control.
```blade
<x-codeart::field name="email" type="email" icon="mail" :label="__('codeart::ui.email')" autocomplete="username" required />
```

### `input`
Props: `icon` (start), `icon-end`, `type`. 52px text input; `aria-invalid="true"` turns the border to the danger color; `disabled`/`readonly` styled. Used by `field`.

### `password-input`
Password field with show/hide toggle (Alpine switches `type`; without JS the toggle stays hidden and it is a plain password field).

### `otp-input`
Props: `id`, `name` (default `code`), `length` (default 6). The submitted value is ONE text input (`inputmode="numeric"`,
`autocomplete="one-time-code"`, `maxlength`). Alpine (`codeartOtp`) turns it into boxes with auto-advance, paste and backspace
handling and keeps the single input in sync.

### `field-shell`
Frame shared by `field`, `textarea` and `select`. Props: `id`, `label`, `hint`, `error`, `required` (adds an `aria-hidden` `*`), `max` + `count` (character counter `data-counter`, live with Alpine, server value without JS).

### `textarea`, `select`
Same contract as `field`. `textarea` props: `name`, `label`, `id`, `hint`, `bag`, `value`, `rows` (4); `maxlength` gives the counter. `select` props: `name`, `label`, `id`, `hint`, `bag`, `value`, `options` (value => label) or `<option>` in the slot, `placeholder`. Value defaults to `old(name)`.
```blade
<x-codeart::textarea name="bio" label="Bio" maxlength="80" hint="Optional" />
<x-codeart::select name="plan" label="Plan" :options="['free' => 'Free', 'pro' => 'Pro']" placeholder="Choose..." required />
```

### `checkbox`, `radio`, `switch`
Props: `id`, `label`, `hint`, `bag`, `checked` (`radio` also `value`). The plain attribute `name` ties them to `old()` (after a failed submit the sent state wins) and, for checkbox and switch, to the error bag. `switch` is a real checkbox with `role="switch"`, styled by CSS only.
```blade
<x-codeart::checkbox id="terms" name="terms" label="I accept" />
<x-codeart::radio id="plan-pro" name="plan" value="pro" label="Pro" />
<x-codeart::switch id="active" name="active" label="Active" :checked="true" />
```

### `button`
Props: `variant` (`primary` default, `secondary`, `ghost`, `danger`), `size` (`sm` 36px, `md` 44px, `lg` 52px default), `type` (`submit` default), `icon` (trailing), `icon-left`, `href` (renders a link), `full` (default true), `loading` (spinner, `aria-busy`, no clicks), `disabled`. As a submit button it shows a spinner after the first submit and ignores further clicks (no double submit); `pageshow` resets it. A disabled link gets `aria-disabled` and `tabindex=-1`.
```blade
<x-codeart::button variant="danger" size="md" :full="false" icon-left="trash-2">Delete</x-codeart::button>
```

### `badge`
Props: `tone` (`primary|success|warning|danger|neutral`), `dot` (true). The label is the meaning; the dot is decoration.
```blade
<x-codeart::badge tone="success">Published</x-codeart::badge>
```

### `alert`
Props: `type` (`info|success|warning|danger`), `title`, `messages` (list), `dismissible` (close button, needs JS). `danger` = `role="alert"`; the others `role="status" aria-live="polite"`. Always has an icon, so color is never the only signal.
```blade
<x-codeart::alert type="warning" title="Careful" :dismissible="true">Text</x-codeart::alert>
```

### `modal`
Native `<dialog>`. Props: `id`, `title`, `size` (`sm|md|lg`). Slots: `title` (markup, overrides the prop), default = body, `footer`. Open with any element that has `data-modal-open="id"`; `data-modal-close` closes; Esc and backdrop click close; focus is trapped and restored by the browser. Opening needs JS.
```blade
<x-codeart::button type="button" data-modal-open="m1">Open</x-codeart::button>
<x-codeart::modal id="m1" title="Title">Body <x-slot:footer>...</x-slot:footer></x-codeart::modal>
```

### `confirm`
Destructive action with a SERVER-side confirmation (TB11). Props: `action`, `method` (POST; others are spoofed), `title`, `text`, `confirm`, `cancel`, `button` (label if the slot is empty), `variant` (danger), `size` (md), `icon`. The default slot is the trigger label.
It renders two forms: the trigger posts to core's confirmation step (`codeart.confirm`) with the signed target, never to the target itself. Without JS the answer is a confirmation page (`codeart::admin.confirm`: text, Confirm, Cancel); with JS the trigger asks the step for a token (JSON), opens the dialog, and the dialog's own form posts the real request with `confirmation=<token>`. The target route MUST use the `codeart.confirmed` middleware, which refuses a request without a valid, unused token (10 minutes, bound to user, method and path).
```blade
<x-codeart::confirm :action="route('pages.destroy', $page)" method="DELETE" title="Delete page?" text="This cannot be undone.">Delete</x-codeart::confirm>
Route::delete('pages/{page}', [PageController::class, 'destroy'])->middleware('codeart.confirmed')->name('pages.destroy');
```

### `toasts`
Once per page (the admin layout has it). Shows `session('toast')` (string, `['type' => 'success', 'message' => '...']` or a list), `session('status')` (success) and `session('error')` (danger) in an `aria-live` stack and listens to the window event `codeart:toast`. Auto-dismiss 5 s (danger 8 s); without JS the flashes are alerts.
```blade
return back()->with('toast', ['type' => 'success', 'message' => __('Saved')]);
<button x-data x-on:click="$dispatch('codeart:toast', { message: 'Copied', type: 'info' })">Copy</button>
```

### `empty-state`
Props: `title`, `text`, `icon` (inbox). The default slot holds the action.
```blade
<x-codeart::empty-state title="No pages yet" text="Create the first one."><x-codeart::button href="...">New page</x-codeart::button></x-codeart::empty-state>
```

### `dropdown`
The one dropdown (the user menu uses it). Props: `label` (aria-label of the button), `id` (panel id), `align` (`end|start`), `width` (`w-56`), `trigger-class`. Slot `trigger` = button content, default = panel; mark items `data-menu-item` for arrow-key navigation (Esc closes and refocuses, tabbing out closes).
```blade
<x-codeart::dropdown label="Actions"><x-slot:trigger>Actions</x-slot:trigger><a data-menu-item href="...">Edit</a></x-codeart::dropdown>
```

### Showcase
`/admin/_ui` (route `codeart.ui-showcase`) lists every component in all states for review in light/dark. Local environment only (404 elsewhere), super-admin only (403), no menu item.

### `link`, `divider`, `icon`, `logout`
`link` (prop `href`), `divider` (prop `text`: a labeled separator), `icon` (prop `name`: mail, lock, eye, eye-off, arrow-right,
check, alert, globe, shield, chevron-down, user; inline SVG, `aria-hidden`), `logout` (a POST form to `logout` with a ghost button; slot = label).

## Data table (`<x-codeart::table>` and `DataTable`)

Server-side lists for tables of any size. A module defines the table in PHP, the controller loads one page, the component draws it.
Everything is plain HTML (GET form, links, POST bulk form); Alpine (`codeartTable`) only adds debounced search, auto-submit on filter
change and the selection counter. Strings: `codeart::table.*` (sr, en, generated sr-Cyrl). Rules and reasons: `docs/DECISIONS.md` section TB.

```php
// definition (registered once in the module provider: app(TableRegistry::class)->register('pages', PagesTable::class))
final class PagesTable implements TableDefinition
{
    public function table(): DataTable
    {
        return DataTable::make('pages', Page::query())
            ->columns(
                Column::titleSubtitle('title', 'Naslov')->using(fn (Page $p) => ['title' => $p->title, 'subtitle' => $p->slug, 'url' => route('pages.edit', $p)]),
                Column::status('status', 'Status', ['draft' => ['Nacrt', 'neutral'], 'published' => ['Objavljeno', 'success']]),
                Column::date('updated_at', 'Izmenjeno'),
            )
            ->filters(Filter::status('status', 'Status', ['draft' => 'Nacrt', 'published' => 'Objavljeno']), Filter::dateRange('updated_at', 'Izmenjeno'))
            ->with('author')                                   // eager loads: the query count must not grow with the rows
            ->defaultSort('updated_at', 'desc')
            ->permission('pages.view')
            ->rowActions(RowAction::edit(fn (Page $p) => route('pages.edit', $p), 'pages.edit'), RowAction::delete(fn (Page $p) => route('pages.destroy', $p), 'pages.delete'))
            ->bulkActions(BulkAction::make('publish', 'Objavi', fn (Page $p) => $p->publish(), 'pages.publish', 'Objaviti izabrane stranice?'))
            ->export('pages');
    }
}

// controller
public function index(Request $request, PagesTable $pages)
{
    $result = $pages->table()->resolve($request, $request->user('admin'));
    return $result->redirectTo ? redirect($result->redirectTo) : view('pages::index', ['result' => $result]);
}
// view
<x-codeart::table :result="$result" label="Stranice" />
```

**Query string** (the whole state, so every list is linkable): `q`, `sort`, `dir`, `filter[key]` (`filter[key][from|to]` for ranges), `per_page`, `page`.
Invalid values are ignored or clamped: `per_page` 1..100 (default 25, nonsense = default), `page` >= 1, unknown sort keys and filters dropped, and a page past
the last redirects to the last page (`TableResult::$redirectTo`). A request loads exactly one page and the exact total (`count(*)`).

**Columns** (`Column::text|number|date|boolean|status|badge|avatar|titleSubtitle|thumbnail|languageChips`): key, label, DB expression (default the key; a plain
identifier is quoted and qualified, anything else is your own raw SQL). All columns sort, search and filter unless `->sortable(false)`, `->searchable(false)` or
`->filterable(false)`. `->using(fn ($row) => ...)` shapes the cell value (avatar `['name', 'subtitle', 'image']`, title-subtitle `['title', 'subtitle', 'url']`, language chips
`['sr' => true, 'en' => false]`, thumbnail a URL), `->exportUsing()` the CSV value, `->options([...])` the label and tone of status and badge cells.
The base query must return one row per model (no GROUP BY). Sorting uses only the declared expressions, with the primary key as a stable tie-breaker.

**Search** is "contains" over the searchable columns, `%`, `_` and `!` are literal (`LIKE ? ESCAPE '!'`), words are AND-ed (each word in any column).
**Filters**: declared `Filter::select|status|boolean|dateRange|numberRange|text` (own control in the toolbar, `->using(fn ($q, $v) => ...)` for a custom constraint); every other
column has an implicit filter of its type, reachable through the URL and the "Više filtera" panel.

**Permissions**: `->permission()` on the table (list, export, bulk), on each `RowAction` and `BulkAction`. They are hidden when missing and, for bulk and export, checked again
on the server (403). A row action only links to a module route; that route must authorize again.

**Destructive actions**: `BulkAction::destructive(key, label, handle, permission, confirmText)` (or `destructive: true`) never runs without a server-issued confirmation token (TB11): the first request returns the confirmation page (action, affected rows, active filter for "all matching"), JSON for the dialog. Row actions with a non-GET method go through `<x-codeart::confirm>` and their routes need `codeart.confirmed`. Other bulk actions with a confirm text only open the dialog.

**Bulk actions and export** are served by core's `POST admin/tables/{key}/bulk` and `GET admin/tables/{key}/export`, so the table must be registered in `TableRegistry` (the key
is the `DataTable` id). Bulk: `BulkActionRunner`, `chunkById` (500), the handler returns true/false/null (done/failed/skipped) per row; "select all results" sends the filter, never ids.
Over `codeart.tables.bulk.queue_limit` (1000) rows it queues `RunBulkActionJob` when a real queue driver is configured; otherwise it runs in chunks up to `hard_cap` (10000) and
refuses above with "narrow the filter". Export: `CsvExporter`, streamed, UTF-8 BOM, current filter and sort, formula-looking cells prefixed with `'`, `export.max_rows` 500000.

Benchmark: `php artisan codeart:bench:table` (MariaDB, creates and drops a temporary table, 1,000,000 rows by default).

| Class | Purpose |
|---|---|
| `DataTable` | definition, `resolve(Request, ?user): TableResult`, `filteredQuery`, `orderedQuery`, `eachOrdered` |
| `Column`, `Filter`, `RowAction`, `BulkAction` | the parts |
| `TableState` | the parsed query string (clamped) |
| `TableResult` | page rows, total, `url()`, `hiddenFields()` for the view |
| `TableRegistry`, `TableDefinition` | key => definition, so bulk and export can rebuild a table |
| `BulkActionRunner`, `RunBulkActionJob`, `CsvExporter`, `TableController` | actions and export |

Sub-components: `table.cell` (one renderer per column type), `table.filter`, `table.select` (compact 44px select), `table.pagination`. The showcase page `/admin/_ui` has a demo backed by the activity log.

### Translated cells are searched by what the user sees

Anything a column displays through a translation (an event key, a status, a role) must also be searchable by the
translated text. Declare it on the column: `Column::searchTranslated(fn (string $locale): array => [rawValue => text])`
(or `searchByOptions()` when `options()` already holds the texts). The search term is matched against the texts in the
current locale plus `sr`, `sr-Cyrl` and `en`; every raw value whose text contains the word is searched (`IN (...)`)
together with the raw value itself. Other toolbar details: "Više filtera" is a compact toggle button in the toolbar row
(the panel is visible without JavaScript); a cell of type `avatar` with `['system' => true, 'name' => 'Sistem']` shows
a server icon instead of initials; date filter inputs carry `lang` of the panel locale.

## JavaScript (`resources/js/app.js`, built to `resources/dist/codeart.js`)
Alpine 3 plus two components: `codeartOtp(length)` and `codeartRecoveryCodes(filename)` (copy and download .txt).
Rebuild both bundles after changing views, CSS or JS: `ddev exec "cd packages/core && npm run build"` and commit `resources/dist`.

## Admin shell (`<x-codeart::admin.*>`, `resources/views/components/admin`)
Spec: `docs/admin-ui.md`. Only semantic utilities; the sidebar and top bar follow the theme.

| Component | Purpose |
|---|---|
| `admin.layout` | Page shell. Prop `title`. Skip link, `aside > nav` (sidebar), `header` (top bar), `main#main`, command palette. Builds the menu and search items for the signed-in user. Alpine `codeartShell` holds drawer, collapse, theme and palette state. |
| `admin.sidebar`, `admin.menu-node` | Logo, menu tree (`NavNode`s), version line. Groups collapse; `aria-current="page"` on the active link. |
| `admin.topbar` | Hamburger, search button (Ctrl+K), `admin.user-menu`. |
| `admin.user-menu` | Built on `dropdown`: name and role, theme radiogroup, language items, 2FA link (when offered), sign out. |
| `admin.command-palette` | Prop `items` (list of `{label, url, icon, group}`); rendered as JSON, filtered in the browser, arrow keys, Enter, Esc, focus trap. |
| `admin.page-header` | Props `title`, `description`, `breadcrumbs` (`[['label' => 'Pages', 'url' => '...'], ['label' => 'Edit']]`); named slot `actions` aligned to the end. |
| `admin.card` | Rounded surface with border. Props `title`, `icon` (header shown when `title` is set). |

```blade
<x-codeart::admin.layout :title="__('pages::menu.list')">
    <x-codeart::admin.page-header :title="__('pages::menu.list')" :breadcrumbs="[['label' => 'CMS', 'url' => route('admin.dashboard')], ['label' => 'Pages']]">
        <x-slot:actions><x-codeart::button href="{{ route('pages.create') }}" :full="false">New</x-codeart::button></x-slot:actions>
    </x-codeart::admin.page-header>
    <x-codeart::admin.card title="Latest" icon="file-text">...</x-codeart::admin.card>
</x-codeart::admin.layout>
```
`icon` now also knows every Lucide icon listed in `scripts/icons.txt`. Widget views get `$widget`, `$data`, `$user`; core's live in `views/admin/widgets`.
