<?php

namespace Codeart\Core\Users\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\Contracts\StaffUser;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Settings\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Edit name, e-mail and role of a user. Only these three fields are ever read from the input (no mass assignment of
 * anything else). A new e-mail address is unverified until confirmed; a role change needs `changeRole` and an
 * assignable role. Old and new values go to the log (never secrets).
 */
class UpdateUser
{
    use ManagesUsers;

    public function __construct(private readonly ActivityLog $log, private readonly Settings $settings) {}

    /**
     * @param  array<string, mixed>  $input
     * @return list<string> what changed
     *
     * @throws AuthorizationException
     */
    public function __invoke(Model $user, array $input, Authenticatable $actor): array
    {
        Gate::forUser($actor)->authorize('update', $user);
        $table = $user->getTable();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique($table, 'email')->ignore($user->getKey())],
        ];
        $changeRole = array_key_exists('role', $input);
        if ($changeRole) {
            $rules['role'] = ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', Admin::guardName())];
        }
        $data = Validator::make($input, $rules, [], __('codeart::auth.attributes'))->validate();

        $changes = [];
        DB::transaction(function () use ($user, $data, $changeRole, $actor, &$changes): void {
            $attributes = ['name' => (string) $data['name']];
            $oldEmail = (string) $user->getAttribute('email');
            if ($data['email'] !== $oldEmail) {
                $attributes += ['email' => (string) $data['email'], 'email_verified_at' => null];
            }
            $old = ['name' => $user->getAttribute('name'), 'email' => $oldEmail];
            $user->forceFill($attributes)->save();
            if ($old['name'] !== $user->getAttribute('name')) {
                $changes[] = 'name';
                $this->logUser($this->log, 'user.updated', 'User updated', $user, $actor, ['field' => 'name', 'old' => $old['name'], 'new' => $user->getAttribute('name')]);
            }
            if ($old['email'] !== $user->getAttribute('email')) {
                $changes[] = 'email';
                $this->logUser($this->log, 'user.email_changed', 'User e-mail changed', $user, $actor, ['old' => $old['email'], 'new' => $user->getAttribute('email')]);
                if ($this->settings->get('auth.registration.require_email_verification') && $user instanceof StaffUser) {
                    Admin::staff($user)->sendEmailVerificationNotification();
                }
            }
            if ($changeRole && $this->changeRole($user, (string) $data['role'], $actor)) {
                $changes[] = 'role';
            }
        });

        return $changes;
    }

    private function changeRole(Model $user, string $role, Authenticatable $actor): bool
    {
        /** @var Collection<int, string> $current */
        $current = Admin::staff($user)->getRoleNames();
        if ($current->count() === 1 && $current->first() === $role) {
            return false;
        }
        Gate::forUser($actor)->authorize('changeRole', $user);
        if (! RoleRank::canAssign($actor, $role)) {
            throw new AuthorizationException(__('codeart::auth.role_not_allowed'));
        }
        Role::findByName($role, Admin::guardName());
        Admin::staff($user)->syncRoles([$role]);
        $this->logUser($this->log, 'user.role_changed', 'User role changed', $user, $actor, ['old' => $current->values()->all(), 'new' => [$role]]);

        return true;
    }
}
