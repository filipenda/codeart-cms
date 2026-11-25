<?php

/*
 * Fallback texts for Laravel's own keys (auth.*, passwords.*, validation.*), used only when the app
 * has no translation of its own. Laravel 13 ships no lang files, so without these users would see raw keys.
 */
return [
    'auth' => [
        'failed' => 'These credentials do not match our records.',
        'throttle' => 'Too many attempts. Please try again in :seconds seconds.',
    ],
    'passwords' => [
        'reset' => 'Your password has been reset.',
        'sent' => 'If the e-mail address is registered, a reset link has been sent.',
        'throttled' => 'If the e-mail address is registered, a reset link has been sent.',
        'token' => 'This password reset link is invalid or expired.',
        'user' => 'This password reset link is invalid or expired.',
    ],
    'validation' => [
        'required' => 'The :attribute field is required.',
        'string' => 'The :attribute must be text.',
        'email' => 'The :attribute must be a valid e-mail address.',
        'unique' => 'The :attribute has already been taken.',
        'confirmed' => 'The :attribute confirmation does not match.',
        'current_password' => 'The password is incorrect.',
        'max' => [
            'string' => 'The :attribute must not be longer than :max characters.',
        ],
        'min' => [
            'string' => 'The :attribute must be at least :min characters.',
        ],
        'password' => [
            'letters' => 'The :attribute must contain at least one letter.',
            'mixed' => 'The :attribute must contain at least one uppercase and one lowercase letter.',
            'numbers' => 'The :attribute must contain at least one number.',
            'symbols' => 'The :attribute must contain at least one symbol.',
            'uncompromised' => 'The :attribute appeared in a data leak. Choose another one.',
        ],
    ],
];
