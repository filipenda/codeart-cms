<?php

namespace Codeart\Core\Auth\Mail;

class RegistrationReceivedMail extends ThemedMail
{
    public function __construct(private readonly string $name, private readonly bool $needsApproval) {}

    protected function key(): string
    {
        return 'registration_received';
    }

    protected function replacements(): array
    {
        return ['name' => $this->name];
    }

    protected function paragraphs(): array
    {
        return $this->needsApproval ? ['intro', 'approval'] : ['intro'];
    }
}
