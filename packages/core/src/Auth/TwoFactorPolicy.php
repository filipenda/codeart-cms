<?php

namespace Codeart\Core\Auth;

use Codeart\Core\Settings\Settings;

/** security.2fa.mode / security.2fa.required_roles in one place. */
class TwoFactorPolicy
{
    public function __construct(private readonly Settings $settings) {}

    public function mode(): string
    {
        return (string) $this->settings->get('security.2fa.mode');
    }

    /** Is two-factor offered to users at all? */
    public function available(): bool
    {
        return $this->mode() !== 'off';
    }

    /** Is two-factor mandatory for this user's roles (so they may not switch it off)? */
    public function requiredFor(mixed $user): bool
    {
        if ($this->mode() !== 'required' || ! is_object($user) || ! method_exists($user, 'hasAnyRole')) {
            return false;
        }
        $roles = array_values(array_filter((array) $this->settings->get('security.2fa.required_roles'), 'is_string'));

        return $roles !== [] && $user->hasAnyRole($roles);
    }

    /** Required for this user's roles, and not set up yet? */
    public function mustSetUp(mixed $user): bool
    {
        if ($this->mode() !== 'required' || ! is_object($user) || ! method_exists($user, 'hasAnyRole')) {
            return false;
        }
        $roles = array_values(array_filter((array) $this->settings->get('security.2fa.required_roles'), 'is_string'));
        if ($roles === [] || ! $user->hasAnyRole($roles)) {
            return false;
        }

        return ! (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled());
    }
}
