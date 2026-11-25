<?php

namespace Codeart\Core\Console;

use Codeart\Core\Auth\Actions\ResetTwoFactor;
use Codeart\Core\Auth\Admin;
use Illuminate\Console\Command;

class UserReset2faCommand extends Command
{
    protected $signature = 'user:reset-2fa {email}';

    protected $description = 'Remove a user\'s two-factor setup (lost phone); the event is written to the activity log';

    public function handle(ResetTwoFactor $reset): int
    {
        $user = Admin::findByEmail((string) $this->argument('email'));
        if ($user === null) {
            $this->components->error('No such user.');

            return self::FAILURE;
        }
        $reset($user);
        $this->components->info("Two-factor authentication reset for {$user->email}.");

        return self::SUCCESS;
    }
}
