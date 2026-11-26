<?php

/* Admin texts (shell, menu, dashboard). sr-Cyrl is generated: php artisan lang:transliterate */
return [
    'menu' => [
        'dashboard' => 'Dashboard',
        'main' => 'Main menu',
        'toggle' => 'Open or close the menu',
        'close' => 'Close menu',
        'expand_group' => 'Show or hide sub-items',
    ],
    'version' => 'CMS :version',
    'breadcrumb' => 'Breadcrumb',
    'home' => 'Home',

    'search' => [
        'placeholder' => 'Search…',
        'title' => 'Quick search',
        'label' => 'Search pages',
        'hint' => 'Arrow keys to choose, Enter to open, Esc to close.',
        'empty' => 'No results.',
        'results' => ':count results',
    ],
    'theme' => [
        'label' => 'Theme',
        'light' => 'Light',
        'dark' => 'Dark',
        'auto' => 'Automatic',
    ],
    'language' => 'Panel language',
    'user' => [
        'menu' => 'User menu',
        'security' => 'Two-step verification',
        'logout' => 'Sign out',
        'roles' => [
            'super-admin' => 'Super administrator',
            'admin' => 'Administrator',
            'editor' => 'Editor',
            'member' => 'Member',
        ],
        'no_role' => 'No role',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'welcome' => 'Welcome back, :name.',
        'welcome_anonymous' => 'Welcome back.',
        'empty_title' => 'Nothing to show yet',
        'empty_text' => 'There are no dashboard widgets available for your account right now.',
    ],
    'widgets' => [
        'recent_activity' => 'Recent activity',
        'system_status' => 'System status',
        'quick_actions' => 'Quick actions',
        'no_activity' => 'No activity recorded yet.',
        'by' => 'By :name',
        'system' => 'System',
        'check' => [
            'version' => 'CMS version',
            'php' => 'PHP',
            'database' => 'Database',
            'storage' => 'Storage',
        ],
        'ok' => 'OK',
        'fail' => 'Problem',
        'error_title' => 'This panel is unavailable right now',
        'error_text' => 'Something went wrong. The rest of the dashboard works and the error was logged.',
    ],
];
