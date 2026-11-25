<?php

namespace Codeart\Core\Console;

use Codeart\Core\Auth\Actions\InviteUser;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class UserInviteCommand extends Command
{
    protected $signature = 'user:invite {name} {email} {role}';

    protected $description = 'Invite a staff user by e-mail (the invitation link is single-use)';

    public function handle(InviteUser $invite): int
    {
        try {
            $invitation = $invite((string) $this->argument('name'), (string) $this->argument('email'), [(string) $this->argument('role')]);
        } catch (ValidationException $e) {
            $this->components->error($e->validator->errors()->first());

            return self::FAILURE;
        } catch (RoleDoesNotExist) {
            $this->components->error('That role does not exist. Run `php artisan codeart:roles` first.');

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->components->error('The invitation could not be sent: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Invitation sent to {$invitation->email}; it expires {$invitation->expires_at->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
