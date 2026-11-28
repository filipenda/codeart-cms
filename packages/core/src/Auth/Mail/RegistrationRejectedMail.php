<?php

namespace Codeart\Core\Auth\Mail;

class RegistrationRejectedMail extends ThemedMail
{
    public function __construct(private readonly string $name, private readonly ?string $reason = null) {}

    protected function key(): string
    {
        return 'registration_rejected';
    }

    protected function replacements(): array
    {
        return ['name' => $this->name, 'reason' => (string) $this->reason];
    }

    protected function paragraphs(): array
    {
        return $this->reason !== null && $this->reason !== '' ? ['intro', 'reason'] : ['intro'];
    }
}
