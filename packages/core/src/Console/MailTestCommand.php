<?php

namespace Codeart\Core\Console;

use Codeart\Core\Auth\Mail\TestMail;
use Codeart\Core\Mail\AuthMailer;
use Illuminate\Console\Command;
use Throwable;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {address}';

    protected $description = 'Send a test message with the current mail settings';

    public function handle(AuthMailer $mailer): int
    {
        $address = (string) $this->argument('address');
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('That is not a valid e-mail address.');

            return self::FAILURE;
        }

        try {
            $mailer->send($address, new TestMail);
        } catch (Throwable $e) {
            $this->components->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Test message sent to {$address} using the [".config('mail.default').'] driver.');

        return self::SUCCESS;
    }
}
