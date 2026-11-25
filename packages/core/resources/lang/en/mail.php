<?php

return [
    'button_help' => 'If the button does not work, copy this link into your browser:',

    'invitation' => [
        'subject' => 'You are invited to :app',
        'heading' => 'Welcome to :app',
        'intro' => 'Hello :name, you have been invited to join :app. Set your password to activate your account.',
        'expires' => 'This invitation can be used once and expires in :days days.',
        'action' => 'Accept the invitation',
    ],
    'reset_password' => [
        'subject' => 'Reset your password',
        'heading' => 'Password reset',
        'intro' => 'Hello :name, we received a request to reset the password of your :app account.',
        'expires' => 'This link expires in :minutes minutes.',
        'ignore' => 'If you did not ask for this, you can ignore this message; your password stays unchanged.',
        'action' => 'Choose a new password',
    ],
    'verify_email' => [
        'subject' => 'Confirm your e-mail address',
        'heading' => 'Confirm your e-mail address',
        'intro' => 'Hello :name, please confirm your e-mail address for :app.',
        'ignore' => 'If you did not create an account, you can ignore this message.',
        'action' => 'Confirm e-mail address',
    ],
    'registration_received' => [
        'subject' => 'We received your registration',
        'heading' => 'Thank you for registering',
        'intro' => 'Hello :name, we received your registration for :app.',
        'approval' => 'An administrator will review it. You will get an e-mail when your account is approved.',
    ],
    'registration_approved' => [
        'subject' => 'Your account is approved',
        'heading' => 'Your account is approved',
        'intro' => 'Hello :name, your account for :app has been approved. You can sign in now.',
        'action' => 'Sign in',
    ],
    'registration_pending' => [
        'subject' => 'New registration waiting for approval',
        'heading' => 'A registration needs approval',
        'intro' => ':applicant (:email) registered on :app and waits for approval.',
        'action' => 'Review registrations',
    ],
    'test' => [
        'subject' => 'Test message from :app',
        'heading' => 'E-mail works',
        'intro' => 'This is a test message from :app. If you can read it, your mail settings are correct.',
    ],
];
