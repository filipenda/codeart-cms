<?php

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
    | Theme settings. Later these come from the database settings screen.
    | Colors equal to the brand defaults produce no overrides: tokens.css is used.
    */
    'theme' => [
        'primary' => '#C9F31D',
        'secondary' => '#A0BB47',
        'default_mode' => 'light', // light | dark
        'logo' => null,
    ],

    'theme_defaults' => [
        'primary' => '#C9F31D',
        'secondary' => '#A0BB47',
    ],
];
