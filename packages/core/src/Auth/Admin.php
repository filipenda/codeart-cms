<?php

namespace Codeart\Core\Auth;

use Codeart\Core\Auth\Contracts\StaffUser;
use Codeart\Core\Settings\Settings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/** Small facts about the staff area: guard name, base path, the user model. */
final class Admin
{
    public static function guardName(): string
    {
        return (string) config('codeart.auth.guard', 'admin');
    }

    public static function guard(): StatefulGuard
    {
        /** @var StatefulGuard */
        return Auth::guard(self::guardName());
    }

    /** URL prefix of every admin and sign-in route, without slashes at the ends. */
    public static function path(): string
    {
        $path = trim((string) app(Settings::class)->get('auth.admin_path'), '/');

        return $path === '' ? 'admin' : $path;
    }

    /** @return class-string<Model> */
    public static function userModel(): string
    {
        $model = config('auth.providers.users.model');
        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new RuntimeException('auth.providers.users.model must be an Eloquent model.');
        }

        return $model;
    }

    /** The model as a staff user (soft deletes, roles, two-factor); the app's User must implement StaffUser. */
    public static function staff(Model $user): Model&StaffUser
    {
        return $user instanceof StaffUser ? $user : throw new RuntimeException($user::class.' must implement '.StaffUser::class.' (use the AdminUser trait).');
    }

    /** @return Builder<Model> every user, deleted ones included */
    public static function usersWithTrashed(): Builder
    {
        return self::userModel()::query()->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function findByEmail(string $email): ?Model
    {
        return self::userModel()::query()->where('email', $email)->first();
    }

    public static function currentUser(): ?Authenticatable
    {
        return self::guard()->user();
    }

    /** A named screen route when it exists, else the documented fallback path under the admin prefix. */
    /**
     * @param  array<string, string>  $query
     * @param  array<string, string>  $parameters  path parameters of the named route (e.g. the reset token)
     */
    public static function screenUrl(string $routeName, string $fallbackPath, array $query = [], array $parameters = []): string
    {
        if (Route::has($routeName)) {
            return route($routeName, $parameters + $query);
        }
        $url = url(self::path().'/'.ltrim($fallbackPath, '/'));

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * Signed, expiring URL.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function signedUrl(string $routeName, \DateTimeInterface $expires, array $parameters): string
    {
        return URL::temporarySignedRoute($routeName, $expires, $parameters);
    }
}
