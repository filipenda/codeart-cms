# Admin UI (shell and dashboard)

Visual spec of the approved mockups (light and dark, same layout). The module-facing API is in `docs/modules.md`, the
Blade components in `docs/components.md`, the reasons in `docs/DECISIONS.md` (section D). Lime is only the default primary:
every color derives from the theme settings and `tokens.css`.

## Shell
- **Sidebar** (left): logo (theme settings), menu, version line "CMS v0.3.0" / "CMS dev" at the bottom (rule below). It follows the theme: light sidebar
  in the light theme, dark in the dark theme (tokens `--sidebar-*`). Active item: filled with the primary color, text by contrast
  (`text-on-primary`), `aria-current="page"`. Sub-items are indented under their group with a dot marker; groups collapse and
  expand (Alpine, chevron).
- **Desktop (>= 1024px):** the hamburger collapses the sidebar to icons only; remembered in `localStorage` (inside try/catch).
  Collapsed: the header shows the brand mark (the favicon from the theme settings; without one, the first letter of the site name
  on a primary tile). Every icon has an `aria-label` and a tooltip (`role="tooltip"`, shown on hover and keyboard focus); the nav
  stops clipping (`overflow-visible`) so tooltips can sit outside the 72px bar. Clicking a group icon expands the sidebar.
- **Mobile (< 1024px):** the sidebar is an off-canvas drawer with a backdrop; closes on Esc, backdrop click and navigation;
  traps focus and restores it.
- **Top bar:** hamburger, global search with a Ctrl+K hint (command palette), user menu. Nothing else.
- **Content:** left-aligned, full width of the content area with page padding, `max-width: var(--content-max)` (1600px, in
  `tokens.css`) on very wide screens; the title moves only by the sidebar width change. **Page header:** breadcrumb, H1, optional description, right-aligned actions slot.
  **Cards:** rounded surface with border, optional header with icon + title.

## Top bar details
- **Search / command palette:** server-rendered JSON of the items the user may see (`SearchRegistry`; core ships the menu provider
  only). Filtering is client side, keyboard navigable (arrows, Enter, Esc), focus trapped and restored.
- **User menu** (dropdown): button with avatar, name and role (`aria-expanded`, `aria-controls`). Panel: header (name, role),
  "Tema" radiogroup (light | dark | auto: per-browser override in `localStorage`, falling back to the `theme.mode` setting; a small
  inline script in `<head>` applies it before paint), "Jezik" with the three enabled languages as fixed endonyms ("Srpski
  (latinica)", "Српски (ћирилица)", "English", never translated, no flags) and a check on the active one (POST `codeart.locale`,
  the same mechanism as the sign-in screens; works without JS), the 2FA link only while it is offered, and "Odjava" (POST + CSRF).
  Keyboard: Down/Up/Home/End move between items, Esc closes and returns focus to the button, tabbing out closes. The panel is at
  most viewport width minus 2rem wide.
- **Version rule:** a tagged release shows `v0.3.0` (also `-rc.N`); anything else (dev-*, *-dev, branch names, unknown) shows `dev`.
  Resolved by `Version::cms()` from Composer's installed data of `codeart/core`; used by the sidebar footer and the status widget.

## Dashboard
Title "Kontrolna tabla", a welcome line with the user's first name. Widgets sit in a 12-column grid: `sm` = 3 columns, `md` = 6,
`lg` = 12 (full row) on desktop; tablets use 6/6/12; phones stack. On desktop `WidgetRows` packs widgets in order into rows of 12;
a row of two or more that sums to less than 12 is scaled up to exactly 12 (6+3 -> 8+4, 3+3+3 -> 4+4+4; rounding to the widest);
a single widget keeps its size. Empty state when the user may see no widgets. No fake statistics.
Core widgets: "Nedavne aktivnosti" (last 8 activity entries, translated at render time from `admin.activity.<event>`; events in `codeart.activity.hidden_events` such as role/permission attach and detach stay in the log but are not shown), "Status sistema" (admin and super-admin), "Brze akcije".

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

## "Moj profil" (`/admin/profile`)
Header: icon tile, title, description. Under it three server-rendered tabs (plain links, no JS needed, horizontal scroll on mobile): "Lični podaci" (`admin.profile`), "Lozinka" (`admin.profile.password.show`), "Dvofaktorska autentifikacija (2FA)" (`admin.profile.two-factor`); `aria-current="page"` marks the open one. No other tabs are rendered (no notifications or sessions until they exist).
Two columns from `lg` (left card 340px, right content), stacked below. Left card: decorative header (CSS gradient `from-primary-soft` + two inline-SVG wave paths filled with `fill-primary` at low opacity: tokens only, no raster, no external file; follows the client's primary color in light, dark and auto), round 112px avatar with a camera button (file input, auto-submits with Alpine, `<noscript>` fallback button), "Ukloni sliku" while a picture exists, name, e-mail, role chip, then "Član od", "Poslednja prijava" (relative, exact time in `title`) and "IP adresa" (`users.last_login_ip`).
Right card "Osnovni podaci": full name (one field), e-mail (read-only with hint), phone (optional: digits, spaces, `+ - ( )`, max 30), interface language, theme, "Odustani" / "Sačuvaj izmene". Password and 2FA tabs keep their rules (current password required, configured password rules; 2FA state as before, a note when 2FA is off for the site).
Avatar: png/jpg/webp by content (never SVG), max size = setting `profile.avatar_max_kb` (default 5120), fitted within 512x512 (never enlarged) by `ImageProcessor`, stored under `avatars/` on the branding disk with a random name; the old file is deleted on replace/remove. Only the signed-in user can change their own picture. Shown through `<x-codeart::avatar>` (top bar) and the table avatar cells as `rounded-full object-cover`; initials otherwise. Columns `users.phone`, `users.avatar_path` come from core's migration `add_phone_and_avatar_to_users_table`.
