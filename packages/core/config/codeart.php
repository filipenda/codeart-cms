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
    | Logo/favicon uploads (png, jpg, webp only).
    */
    'branding' => [
        'disk' => 'public',
        'directory' => 'branding',
        'max_kb' => 2048,
        'max_dimension' => 4096,
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
            'sr' => ['label' => 'Srpski (latinica)', 'script' => 'Latn', 'manual' => true],
            'sr-Cyrl' => [
                'label' => 'Српски (ћирилица)',
                'script' => 'Cyrl',
                'derived_from' => 'sr',
                'transliterator' => SerbianLatinToCyrillic::class,
            ],
            'en' => ['label' => 'English', 'script' => 'Latn', 'manual' => true],
        ],
    ],
];
