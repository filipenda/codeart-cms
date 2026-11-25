<?php

namespace Codeart\Core\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may do what with staff users. A super-admin passes every check before this class is asked (Gate::before).
 * Nobody else may touch a super-admin, or anybody above their own rank (see RoleRank).
 */
class UserPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        return $this->can($actor, 'users.view');
    }

    public function view(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.view');
    }

    public function invite(Authenticatable $actor): bool
    {
        return $this->can($actor, 'users.invite');
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->can($actor, 'users.manage');
    }

    public function update(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.manage') && RoleRank::canTouch($actor, $target);
    }

    public function deactivate(Authenticatable $actor, mixed $target): bool
    {
        return $this->update($actor, $target) && ! $this->same($actor, $target);
    }

    public function delete(Authenticatable $actor, mixed $target): bool
    {
        return $this->deactivate($actor, $target);
    }

    public function approve(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.approve') && RoleRank::canTouch($actor, $target);
    }

    private function can(Authenticatable $actor, string $permission): bool
    {
        return method_exists($actor, 'checkPermissionTo') && $actor->checkPermissionTo($permission);
    }

    private function same(Authenticatable $actor, mixed $target): bool
    {
        return is_object($target) && method_exists($target, 'getAuthIdentifier') && $actor->getAuthIdentifier() === $target->getAuthIdentifier();
    }
}
