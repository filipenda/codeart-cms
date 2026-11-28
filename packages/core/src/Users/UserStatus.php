<?php

namespace Codeart\Core\Users;

use Illuminate\Database\Eloquent\Model;

/** The one status a user shows in lists and on the edit screen. Order matters: the first match wins. */
final class UserStatus
{
    public const array ALL = ['deleted' => 'neutral', 'pending' => 'warning', 'blocked' => 'danger', 'unverified' => 'info', 'active' => 'success'];

    public static function of(Model $user): string
    {
        return match (true) {
            $user->getAttribute('deleted_at') !== null => 'deleted',
            $user->getAttribute('approved_at') === null => 'pending',
            ! $user->getAttribute('is_active') => 'blocked',
            $user->getAttribute('email_verified_at') === null => 'unverified',
            default => 'active',
        };
    }

    /** The same rule as SQL, for sorting, searching and filtering. */
    public static function sql(string $table): string
    {
        return "CASE WHEN {$table}.deleted_at IS NOT NULL THEN 'deleted' WHEN {$table}.approved_at IS NULL THEN 'pending' WHEN {$table}.is_active = 0 THEN 'blocked' WHEN {$table}.email_verified_at IS NULL THEN 'unverified' ELSE 'active' END";
    }

    public static function label(string $status, ?string $locale = null): string
    {
        return (string) __('codeart::users.status.'.$status, [], $locale);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function options(?string $locale = null): array
    {
        $out = [];
        foreach (self::ALL as $status => $tone) {
            $out[$status] = [self::label($status, $locale), $tone];
        }

        return $out;
    }
}
