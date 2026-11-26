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
52px text input with optional leading `icon`. Used by `field`; `aria-invalid="true"` turns the border to the danger color.

### `password-input`
Password field with show/hide toggle (Alpine switches `type`; without JS the toggle stays hidden and it is a plain password field).

### `otp-input`
Props: `id`, `name` (default `code`), `length` (default 6). The submitted value is ONE text input (`inputmode="numeric"`,
`autocomplete="one-time-code"`, `maxlength`). Alpine (`codeartOtp`) turns it into boxes with auto-advance, paste and backspace
handling and keeps the single input in sync.

### `checkbox`
Props: `id`, `label`; other attributes (`name`, `value`, `checked`) go to the input.

### `button`
Props: `variant` (`primary` default, `secondary`, `ghost`), `type` (`submit` default), `icon` (trailing), `href` (renders a link), `full` (default true).
52px high, 14px radius. As a submit button it shows a spinner after the first submit and ignores further clicks (no double submit);
`pageshow` resets it for the back button.

### `alert`
Props: `type` (`info|success|warning|danger`), `title`, `messages` (list). `danger` = `role="alert"`; the others
`role="status" aria-live="polite"`. Always has an icon, so color is never the only signal.

### `link`, `divider`, `icon`, `logout`
`link` (prop `href`), `divider` (prop `text`: a labeled separator), `icon` (prop `name`: mail, lock, eye, eye-off, arrow-right,
check, alert, globe, shield, chevron-down, user; inline SVG, `aria-hidden`), `logout` (a POST form to `logout` with a ghost button; slot = label).

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
| `admin.user-menu` | Dropdown: name and role, theme radiogroup, language items, 2FA link (when offered), sign out. |
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
