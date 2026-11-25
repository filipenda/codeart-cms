<?php

namespace Codeart\Core\Console;

use Codeart\Core\Auth\Actions\CreateAdminUser;
use Codeart\Core\Auth\Admin;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

use function Laravel\Prompts\password;

class UserCreateCommand extends Command
{
    protected $signature = 'user:create {name} {email} {role : super-admin, admin, editor or member}';

    protected $description = 'Create a staff user (the password is asked for, never given on the command line). This is how the first super-admin is made.';

    public function handle(CreateAdminUser $create): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('The password is asked for with a hidden prompt; run this command interactively.');

            return self::FAILURE;
        }
        $email = (string) $this->argument('email');
        if (Admin::findByEmail($email) !== null) {
            $this->components->error('A user with this e-mail address already exists.');

            return self::FAILURE;
        }

        $password = password('Password', required: true);
        if ($password !== password('Confirm password', required: true)) {
            $this->components->error('The passwords do not match.');

            return self::FAILURE;
        }

        try {
            $user = $create((string) $this->argument('name'), $email, $password, [(string) $this->argument('role')], null, ['email_verified_at' => now()]);
        } catch (ValidationException $e) {
            $this->components->error($e->validator->errors()->first());

            return self::FAILURE;
        } catch (RoleDoesNotExist) {
            $this->components->error('That role does not exist. Run `php artisan codeart:roles` first.');

            return self::FAILURE;
        }

        $this->components->info("Created {$user->email} with role {$this->argument('role')}.");

        return self::SUCCESS;
    }
}
