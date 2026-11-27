<?php

namespace Codeart\Core\Auth\Contracts;

use Illuminate\Support\Collection;

/**
 * What core needs from the app's User model beyond Eloquent. The `AdminUser` trait implements all of it (SoftDeletes,
 * spatie roles, Fortify two-factor):
 * `class User extends Authenticatable implements StaffUser, MustVerifyEmail, HasLocalePreference { use AdminUser; }`.
 * Return types are left open on purpose: the traits that provide these methods differ in how they declare them.
 */
interface StaffUser
{
    /** @return bool */
    public function trashed();

    /** @return bool|null */
    public function restore();

    /** @return Collection<int, string> */
    public function getRoleNames();

    /**
     * @param  mixed  ...$roles
     * @return $this
     */
    public function syncRoles(...$roles);

    /** @return bool */
    public function hasTwoFactorEnabled();

    /** @return void */
    public function sendEmailVerificationNotification();
}
