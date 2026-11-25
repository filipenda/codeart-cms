<?php

namespace Codeart\Core\Console;

use Codeart\Core\Auth\Actions\ApproveUser;
use Codeart\Core\Auth\Admin;
use Illuminate\Console\Command;

class UserApproveCommand extends Command
{
    protected $signature = 'user:approve {email}';

    protected $description = 'Approve a pending registration (activates the account and tells the user)';

    public function handle(ApproveUser $approve): int
    {
        $user = Admin::findByEmail((string) $this->argument('email'));
        if ($user === null) {
            $this->components->error('No such user.');

            return self::FAILURE;
        }

        $this->components->info($approve($user) ? "Approved {$user->email}." : "{$user->email} is already approved.");

        return self::SUCCESS;
    }
}
