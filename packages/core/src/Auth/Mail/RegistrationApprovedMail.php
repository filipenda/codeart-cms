<?php

namespace Codeart\Core\Auth\Mail;

use Codeart\Core\Auth\Admin;

class RegistrationApprovedMail extends ThemedMail
{
    public function __construct(private readonly string $name) {}

    protected function key(): string
    {
        return 'registration_approved';
    }

    protected function replacements(): array
    {
        return ['name' => $this->name];
    }

    protected function actionUrl(): string
    {
        return Admin::screenUrl('login', 'login');
    }
}
