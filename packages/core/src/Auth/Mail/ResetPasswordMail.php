<?php

namespace Codeart\Core\Auth\Mail;

use Codeart\Core\Auth\Admin;
use Illuminate\Contracts\Auth\CanResetPassword;

class ResetPasswordMail extends ThemedMail
{
    public function __construct(private readonly CanResetPassword $user, private readonly string $token) {}

    protected function key(): string
    {
        return 'reset_password';
    }

    protected function replacements(): array
    {
        return ['name' => (string) ($this->user->name ?? ''), 'minutes' => (string) config('auth.passwords.users.expire', 60)];
    }

    protected function paragraphs(): array
    {
        return ['intro', 'expires', 'ignore'];
    }

    protected function actionUrl(): string
    {
        return Admin::screenUrl('password.reset', 'reset-password/'.$this->token, ['email' => $this->user->getEmailForPasswordReset()], ['token' => $this->token]);
    }
}
