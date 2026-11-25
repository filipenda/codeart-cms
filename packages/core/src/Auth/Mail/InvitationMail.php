<?php

namespace Codeart\Core\Auth\Mail;

class InvitationMail extends ThemedMail
{
    public function __construct(private readonly string $name, private readonly string $url, private readonly int $days) {}

    protected function key(): string
    {
        return 'invitation';
    }

    protected function replacements(): array
    {
        return ['name' => $this->name, 'days' => (string) $this->days];
    }

    protected function paragraphs(): array
    {
        return ['intro', 'expires'];
    }

    protected function actionUrl(): string
    {
        return $this->url;
    }
}
