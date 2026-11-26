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
    'language' => 'Language',
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

    'activity' => [
        'auth' => [
            'login' => 'Signed in',
            'login_failed' => 'Sign-in failed',
            'logout' => 'Signed out',
            'lockout' => 'Too many sign-in attempts',
        ],
        'registration' => ['created' => 'Registration'],
        'user' => [
            'created' => 'User created',
            'deactivated' => 'User deactivated',
            'approved' => 'Registration approved',
        ],
        'invitation' => ['sent' => 'Invitation sent', 'accepted' => 'Invitation accepted'],
        'password' => [
            'changed' => 'Password changed',
            'reset_requested' => 'Password reset requested',
            'reset_completed' => 'Password reset completed',
        ],
        'email' => ['verified' => 'E-mail address verified'],
        'session' => ['others_revoked' => 'Signed out of other devices'],
        'two_factor' => [
            'setup_started' => 'Two-factor setup started',
            'enabled' => 'Two-factor authentication enabled',
            'disabled' => 'Two-factor authentication disabled',
            'failed' => 'Two-factor code rejected',
            'recovery_code_used' => 'Recovery code used',
            'recovery_codes_regenerated' => 'Recovery codes regenerated',
            'reset' => 'Two-factor authentication reset',
        ],
        'role' => ['attached' => 'Role assigned', 'detached' => 'Role removed'],
        'permission' => ['attached' => 'Permission granted', 'detached' => 'Permission removed'],
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
