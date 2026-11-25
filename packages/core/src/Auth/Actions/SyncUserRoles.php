<?php

namespace Codeart\Core\Auth\Actions;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\RoleRank;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/** Change a user's roles. The role changes themselves are logged from spatie's events. */
class SyncUserRoles
{
    /**
     * @param  list<string>  $roles
     *
     * @throws AuthorizationException
     */
    public function __invoke(Model $user, array $roles, Authenticatable $actor): void
    {
        Gate::forUser($actor)->authorize('update', $user);

        /** @var Collection<int, string> $current */
        $current = $user->getRoleNames();
        // Nothing the actor may not hand out can be given, and nothing they may not hand out can be taken away.
        foreach (array_unique([...$roles, ...$current->all()]) as $role) {
            $changes = in_array($role, $roles, true) !== $current->contains($role);
            if ($changes && ! RoleRank::canAssign($actor, $role)) {
                throw new AuthorizationException(__('codeart::auth.role_not_allowed'));
            }
        }
        foreach ($roles as $role) {
            Role::findByName($role, Admin::guardName());
        }

        $user->syncRoles($roles);
    }
}
