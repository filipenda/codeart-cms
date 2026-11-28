<?php

use Codeart\Core\Translations\SerbianLatinToCyrillic;

/*
 * Core configuration. This file (with tokens.css) is the only place
 * where raw color values may live. See docs/ARCHITECTURE.md.
 */

return [

    /*
    | Brand-neutral base values used to derive readable shades.
    | Not editable by clients.
    */
    'theme_base' => [
        'ink' => '#22201f',
        'paper' => '#ffffff',
        'surface_light' => '#ffffff',
        'surface_dark' => '#1c1a1a',
        'min_contrast' => 4.5,
    ],

    /*
    | Defaults of the appearance settings (theme.*). The database settings override them.
    */
    'theme' => [
        'primary' => '#C9F31D',
        'secondary' => '#A0BB47',
        'mode' => 'light', // light | dark | auto (follow the visitor's system)
        'logo_light' => null,
        'logo_dark' => null,
        'favicon' => null,
    ],

    /*
    | What tokens.css already defines. A color equal to these produces no CSS override.
    */
    'theme_defaults' => [
        'primary' => '#C9F31D',
        'secondary' => '#A0BB47',
    ],

    /*
    | Image processing (the single ImageProcessor service). `driver`: gd (default) or imagick; imagick is used only
    | when the extension exists, otherwise gd. `trim_alpha_tolerance` (0-255): opacity at or below it counts as empty
    | when transparent logo margins are trimmed, so faint shadows do not block the trim.
    */
    'images' => [
        'driver' => 'gd',
        'trim_alpha_tolerance' => 8,
    ],

    /*
    | Logo/favicon uploads (png, jpg, webp only).
    */
    'branding' => [
        'disk' => 'public',
        'directory' => 'branding',
        'max_kb' => 2048,
        'max_dimension' => 4096,
    ],

    /*
    | Profile pictures (same disk as the branding images; png, jpg, webp only, fitted within 512x512).
    */
    'avatars' => [
        'directory' => 'avatars',
    ],

    /*
    | Content languages (not the admin interface language).
    | `default`, `enabled` and `fallbacks` are only the DEFAULTS of the locales.* settings (the database wins);
    | `available` (what each language is) stays here.
    | `sr` is the source; `sr-Cyrl` is generated from it by the transliterator unless a field was edited by hand.
    | Add a language: an entry in `available` (a `manual` one, or `derived_from` + `transliterator`)
    | and its code in `enabled`. `fallbacks` = what to show when a translation is missing.
    */
    'locales' => [
        'default' => 'sr',
        'enabled' => ['sr', 'sr-Cyrl', 'en'],
        'fallbacks' => [
            'sr-Cyrl' => ['sr'],
            'en' => ['sr'],
        ],
        'available' => [
            'sr' => ['label' => 'Srpski (latinica)', 'name' => 'Srpski', 'script' => 'Latn', 'manual' => true],
            'sr-Cyrl' => [
                'label' => 'Српски (ћирилица)',
                'name' => 'Српски',
                'script' => 'Cyrl',
                'derived_from' => 'sr',
                'transliterator' => SerbianLatinToCyrillic::class,
            ],
            'en' => ['label' => 'English', 'name' => 'English', 'script' => 'Latn', 'manual' => true],
        ],
    ],

    /*
    | Activity events that stay in the log but are left out of the dashboard's "Recent activity" widget
    | (technical sync noise). Use the event name as written to the log.
    */
    'activity' => [
        'hidden_events' => ['role.attached', 'role.detached', 'permission.attached', 'permission.detached'],
    ],

    /*
    | Admin authentication (see docs/auth.md). Behaviour is controlled by the auth.* / security.* settings;
    | these keys are wiring only.
    */
    'auth' => [
        // Name of the staff guard (and of the spatie guard roles/permissions live under).
        'guard' => 'admin',
        // true = Fortify's GET screens (login, reset, verify, ...) use core's Blade views (`codeart::auth.*`).
        // false = headless: Fortify registers no GET (screen) routes.
        'views' => true,
        // Cookie that remembers the visitor's language on the sign-in screens (the session holds it too).
        'locale_cookie' => 'codeart_locale',
        // Higher rank = more power. Nobody may assign or modify a role above their own; roles not listed
        // rank 98, i.e. only a super-admin can hand them out.
        'role_rank' => ['super-admin' => 100, 'admin' => 50, 'editor' => 10, 'member' => 0],
        // Routes reachable while two-factor setup is still required (besides the Fortify two-factor routes).
        'two_factor_exempt_routes' => ['logout', 'admin.session', 'password.confirm', 'password.confirm.store', 'password.confirmation'],
    ],

    /*
    | Data tables (docs/components.md). A list always loads one page (default below, hard maximum 100).
    | Bulk actions work in chunks; above `queue_limit` rows they are queued when a real queue driver is configured,
    | otherwise they run in chunks inside the request up to `hard_cap` and are refused above it ("narrow the filter").
    */
    'tables' => [
        'default_per_page' => 25,
        'bulk' => ['chunk' => 500, 'queue_limit' => 1000, 'hard_cap' => 10000],
        'export' => ['chunk' => 1000, 'max_rows' => 500000],
    ],
];
