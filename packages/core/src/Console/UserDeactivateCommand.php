<?php

namespace Codeart\Core\Console;

use Codeart\Core\Auth\Actions\DeactivateUser;
use Codeart\Core\Auth\Admin;
use Illuminate\Console\Command;

class UserDeactivateCommand extends Command
{
    protected $signature = 'user:deactivate {email}';

    protected $description = 'Deactivate a user: no more sign-in, all sessions ended';

    public function handle(DeactivateUser $deactivate): int
    {
        $user = Admin::findByEmail((string) $this->argument('email'));
        if ($user === null) {
            $this->components->error('No such user.');

            return self::FAILURE;
        }
        $deactivate($user);
        $this->components->info("Deactivated {$user->email}.");

        return self::SUCCESS;
    }
}
