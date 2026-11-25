<?php

namespace Codeart\Core\Auth\Mail;

use Codeart\Core\Auth\Admin;

class NewRegistrationPendingMail extends ThemedMail
{
    public function __construct(private readonly string $applicantName, private readonly string $applicantEmail) {}

    protected function key(): string
    {
        return 'registration_pending';
    }

    protected function replacements(): array
    {
        return ['applicant' => $this->applicantName, 'email' => $this->applicantEmail];
    }

    protected function actionUrl(): string
    {
        return Admin::screenUrl('admin.users.pending', 'users/pending');
    }
}
