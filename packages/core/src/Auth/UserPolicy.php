<?php

namespace Codeart\Core\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Who may do what with staff users (docs/auth.md, DECISIONS US1). A super-admin passes every permission check before
 * this class is asked (Gate::before) but still goes through the self / last-super-admin rules below.
 * Everybody else manages only users STRICTLY below their own rank; nobody blocks, deletes or demotes themselves, and the
 * last super-admin can be neither blocked, deleted nor demoted.
 */
class UserPolicy
{
    /** Abilities a super-admin does NOT pass automatically: the self and last-super-admin rules apply to everybody. */
    public const array GUARDED = ['changeRole', 'block', 'deactivate', 'delete', 'restore', 'forceDelete', 'resetTwoFactor'];

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->can($actor, 'users.view');
    }

    public function view(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.view');
    }

    /** Invite (the only way to add a person). */
    public function invite(Authenticatable $actor): bool
    {
        return $this->can($actor, 'users.create');
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->invite($actor);
    }

    public function update(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.update') && RoleRank::canManage($actor, $target);
    }

    /** Change someone's role: never your own. */
    public function changeRole(Authenticatable $actor, mixed $target): bool
    {
        return $this->update($actor, $target) && ! $this->same($actor, $target) && ! $this->isLastSuperAdmin($target);
    }

    public function block(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.block') && RoleRank::canManage($actor, $target)
            && ! $this->same($actor, $target) && ! $this->isLastSuperAdmin($target);
    }

    public function deactivate(Authenticatable $actor, mixed $target): bool
    {
        return $this->block($actor, $target);
    }

    /** Soft delete. */
    public function delete(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.delete') && RoleRank::canManage($actor, $target)
            && ! $this->same($actor, $target) && ! $this->isLastSuperAdmin($target);
    }

    public function restore(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.delete') && RoleRank::canManage($actor, $target) && ! $this->same($actor, $target);
    }

    /** Permanent delete: only a super-admin (the permission is held by nobody else), never yourself or the last super-admin. */
    public function forceDelete(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.force-delete') && RoleRank::isSuperAdmin($actor)
            && ! $this->same($actor, $target) && ! $this->isLastSuperAdmin($target);
    }

    public function resetTwoFactor(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.reset-2fa') && RoleRank::canManage($actor, $target) && ! $this->same($actor, $target);
    }

    public function approve(Authenticatable $actor, mixed $target): bool
    {
        return $this->can($actor, 'users.approve') && RoleRank::canManage($actor, $target);
    }

    /** True when this user is the only active, undeleted super-admin. */
    public function isLastSuperAdmin(mixed $target): bool
    {
        if (! $target instanceof Model || ! RoleRank::isSuperAdmin($target)) {
            return false;
        }
        $model = $target::class;

        return $model::query()->whereKeyNot($target->getKey())->role(RoleRank::SUPER_ADMIN, Admin::guardName())->where('is_active', true)->doesntExist();
    }

    private function can(Authenticatable $actor, string $permission): bool
    {
        return Gate::forUser($actor)->allows($permission);
    }

    private function same(Authenticatable $actor, mixed $target): bool
    {
        return is_object($target) && method_exists($target, 'getAuthIdentifier') && $actor->getAuthIdentifier() === $target->getAuthIdentifier();
    }
}
