<?php

namespace Codeart\Core\Auth\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\PasswordRules;
use Codeart\Core\Auth\RoleRank;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/** The only place that creates staff users: password policy, role ceiling, roles, log entry. */
class CreateAdminUser
{
    public function __construct(private readonly PasswordRules $passwords, private readonly ActivityLog $log) {}

    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $attributes  extra columns: is_active, approved_at, email_verified_at, locale, invited_at
     *
     * @throws AuthorizationException when the actor may not give one of the roles
     */
    public function __invoke(string $name, string $email, string $password, array $roles, ?Authenticatable $actor = null, array $attributes = []): Model
    {
        Validator::make(['password' => $password], ['password' => $this->passwords->rules()])->validate();

        $guard = Admin::guardName();
        foreach ($roles as $role) {
            if (! RoleRank::canAssign($actor, $role)) {
                throw new AuthorizationException(__('codeart::auth.role_not_allowed'));
            }
            Role::findByName($role, $guard); // throws RoleDoesNotExist for a typo
        }

        $user = DB::transaction(function () use ($name, $email, $password, $roles, $attributes): Model {
            $user = Admin::userModel()::query()->newModelInstance();
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'approved_at' => now(),
                ...$attributes,
            ])->save();
            /** @phpstan-ignore method.notFound */
            $user->syncRoles($roles);

            return $user;
        });

        $this->log->record('user.created', 'User created', $user, $actor instanceof Model ? $actor : null, ['email' => $email, 'roles' => $roles]);

        return $user;
    }
}
