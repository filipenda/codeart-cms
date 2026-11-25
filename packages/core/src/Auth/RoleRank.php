<?php

namespace Codeart\Core\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

/** Who may hand out which role: nobody above their own rank; super-admin only by a super-admin. */
final class RoleRank
{
    public const string SUPER_ADMIN = 'super-admin';

    private const int UNKNOWN = 98;

    public static function of(string $role): int
    {
        return (int) (config('codeart.auth.role_rank', [])[$role] ?? self::UNKNOWN);
    }

    /** Highest rank among the user's roles; -1 for a user without roles. */
    public static function ofUser(mixed $user): int
    {
        if (! is_object($user) || ! method_exists($user, 'getRoleNames')) {
            return -1;
        }

        /** @var iterable<int, string> $names */
        $names = $user->getRoleNames();
        $ranks = [];
        foreach ($names as $role) {
            $ranks[] = self::of($role);
        }

        return $ranks === [] ? -1 : max($ranks);
    }

    public static function isSuperAdmin(mixed $user): bool
    {
        return is_object($user) && method_exists($user, 'hasRole') && $user->hasRole(self::SUPER_ADMIN);
    }

    /** May this actor give (or keep) this role on someone? A null actor is the command line, which has shell access. */
    public static function canAssign(?Authenticatable $actor, string $role): bool
    {
        if ($actor === null || self::isSuperAdmin($actor)) {
            return true;
        }

        return $role !== self::SUPER_ADMIN && self::of($role) <= self::ofUser($actor);
    }

    /** May this actor touch (change, deactivate, reset) this user at all? */
    public static function canTouch(?Authenticatable $actor, mixed $target): bool
    {
        if ($actor === null || self::isSuperAdmin($actor)) {
            return true;
        }

        return ! self::isSuperAdmin($target) && self::ofUser($target) <= self::ofUser($actor);
    }
}
