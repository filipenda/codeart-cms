<?php

namespace Codeart\Core\Auth\Mail;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\URL;

class VerifyEmailMail extends ThemedMail
{
    public function __construct(private readonly MustVerifyEmail $user) {}

    protected function key(): string
    {
        return 'verify_email';
    }

    protected function replacements(): array
    {
        return ['name' => (string) ($this->user->name ?? '')];
    }

    protected function paragraphs(): array
    {
        return ['intro', 'ignore'];
    }

    protected function actionUrl(): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $this->user->getKey(), 'hash' => sha1($this->user->getEmailForVerification())],
        );
    }
}
