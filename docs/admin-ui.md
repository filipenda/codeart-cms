# Admin UI (shell and dashboard)

Visual spec of the approved mockups (light and dark, same layout). The module-facing API is in `docs/modules.md`, the
Blade components in `docs/components.md`, the reasons in `docs/DECISIONS.md` (section D). Lime is only the default primary:
every color derives from the theme settings and `tokens.css`.

## Shell
- **Sidebar** (left): logo (theme settings), menu, version line "CMS v{version}" at the bottom. It follows the theme: light sidebar
  in the light theme, dark in the dark theme (tokens `--sidebar-*`). Active item: filled with the primary color, text by contrast
  (`text-on-primary`), `aria-current="page"`. Sub-items are indented under their group with a dot marker; groups collapse and
  expand (Alpine, chevron).
- **Desktop (>= 1024px):** the hamburger collapses the sidebar to icons only; remembered in `localStorage` (inside try/catch).
- **Mobile (< 1024px):** the sidebar is an off-canvas drawer with a backdrop; closes on Esc, backdrop click and navigation;
  traps focus and restores it.
- **Top bar:** hamburger, global search with a Ctrl+K hint (command palette), light/dark/auto toggle, panel language switcher,
  user menu.
- **Content:** max width and page padding. **Page header:** breadcrumb, H1, optional description, right-aligned actions slot.
  **Cards:** rounded surface with border, optional header with icon + title.

## Top bar details
- **Search / command palette:** server-rendered JSON of the items the user may see (`SearchRegistry`; core ships the menu provider
  only). Filtering is client side, keyboard navigable (arrows, Enter, Esc), focus trapped and restored.
- **Theme toggle:** light | dark | auto. Per-browser override in `localStorage`, falling back to the `theme.mode` setting. A small
  inline script in `<head>` applies it before paint (no flash).
- **Panel language:** sr, en, sr-Cyrl (the admin UI language, not the content language). Same mechanism as the sign-in screens
  (POST `codeart.locale`). Shown as plain code badges (SR, EN, СР), no flags, no emoji.
- **User menu:** avatar with initials, full name, highest role; dropdown with logout (POST + CSRF) and a link to the 2FA page only
  for users who still can and need to manage it (otherwise omitted).

## Dashboard
Title "Kontrolna tabla", a welcome line with the user's first name. Widgets sit in a 12-column grid: `sm` = 3 columns, `md` = 6,
`lg` = 12 (full row) on desktop; everything stacks on mobile. Empty state when the user may see no widgets. No fake statistics.
Core widgets: "Nedavne aktivnosti" (last 8 activity entries), "Status sistema" (admin and super-admin), "Brze akcije".

## Tokens
Only `tokens.css` holds colors (light, dark, auto blocks): `--sidebar-bg|text|muted|hover|border`, `--color-topbar`,
`--color-surface` / `-alt` / `-sunken`, `--color-border`, `--color-text-muted`, status `success|warning|danger|info|neutral`
each with `-bg` and `-text`. `composer check:colors` enforces it.

## Typography and icons
Inter (variable), self-hosted: `@fontsource-variable/inter` files (latin, latin-ext, cyrillic, cyrillic-ext) are copied to
`resources/dist/fonts` by `npm run build:fonts`; no CDN. Icons: Lucide as inline SVG (`lucide-static` at build time; only the
names in `scripts/icons.txt` are shipped, in `resources/icons/lucide.json`), rendered by `<x-codeart::icon name="...">`.

## Accessibility
Skip-to-content link, landmarks (`header`, `nav`, `main`, `aside`), `aria-current`, visible focus ring (`--color-focus`),
drawer and palette trap focus and restore it, everything works with the keyboard. Icons are `aria-hidden`.
