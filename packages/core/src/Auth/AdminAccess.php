<?php

namespace Codeart\Core\Auth;

use Codeart\Core\Settings\Settings;

/** Decides whether a signed-in user may use the admin area, and says why not. */
class AdminAccess
{
    public const string PERMISSION = 'admin.access';

    public const string INACTIVE = 'inactive';

    public const string UNAPPROVED = 'unapproved';

    public const string UNVERIFIED = 'unverified';

    public const string FORBIDDEN = 'forbidden';

    public const string TWO_FACTOR = 'two_factor_required';

    public function __construct(private readonly Settings $settings, private readonly TwoFactorPolicy $twoFactor) {}

    /** null = allowed; otherwise one of the reason constants. Checked in this order. */
    public function denial(mixed $user): ?string
    {
        if (! is_object($user)) {
            return self::FORBIDDEN;
        }
        if (! $user->is_active) {
            return self::INACTIVE;
        }
        if ($this->settings->get('auth.registration.require_approval') && $user->approved_at === null) {
            return self::UNAPPROVED;
        }
        if ($this->settings->get('auth.registration.require_email_verification') && $user->email_verified_at === null) {
            return self::UNVERIFIED;
        }
        // Gate: super-admin passes through Gate::before, everybody else needs the permission.
        if (! method_exists($user, 'can') || ! $user->can(self::PERMISSION)) {
            return self::FORBIDDEN;
        }
        if ($this->twoFactor->mustSetUp($user)) {
            return self::TWO_FACTOR;
        }

        return null;
    }
}
