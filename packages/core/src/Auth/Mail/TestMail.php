<?php

namespace Codeart\Core\Auth\Mail;

class TestMail extends ThemedMail
{
    protected function key(): string
    {
        return 'test';
    }
}
