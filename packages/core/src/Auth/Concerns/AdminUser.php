<?php

namespace Codeart\Core\Auth\Concerns;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\AdminAccess;
use Codeart\Core\Auth\Mail\ResetPasswordMail;
use Codeart\Core\Auth\Mail\VerifyEmailMail;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Auth\TwoFactorPolicy;
use Codeart\Core\Mail\AuthMailer;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * What the app's User model needs to be a CodeArt staff user (core does not own the model):
 *
 *     class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
 *     {
 *         use AdminUser;
 *     }
 *
 * The table needs core's migration (locale, theme_preference, is_active, approved_at, ...).
 * Mass assignment stays the model's business: core creates users with forceFill.
 */
trait AdminUser
{
    use HasRoles, Notifiable, TwoFactorAuthenticatable;

    public function initializeAdminUser(): void
    {
        $this->mergeCasts([
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ]);
        $this->makeHidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);
    }

    /** Roles and permissions of staff live under the admin guard, whatever guard is asked about. */
    public function guardName(): string
    {
        return Admin::guardName();
    }

    public function canAccessAdmin(): bool
    {
        return app(AdminAccess::class)->denial($this) === null;
    }

    public function isSuperAdmin(): bool
    {
        return RoleRank::isSuperAdmin($this);
    }

    public function isPendingApproval(): bool
    {
        return $this->approved_at === null;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function mustSetUpTwoFactor(): bool
    {
        return app(TwoFactorPolicy::class)->mustSetUp($this);
    }

    public function preferredLocale(): ?string
    {
        return $this->locale ?: null;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill(['email_verified_at' => $this->freshTimestamp()])->save();
    }

    public function getEmailForVerification(): string
    {
        return (string) $this->email;
    }

    public function sendEmailVerificationNotification(): void
    {
        app(AuthMailer::class)->send($this, new VerifyEmailMail($this), deferred: true);
    }

    /** @param  string  $token */
    public function sendPasswordResetNotification($token): void
    {
        app(AuthMailer::class)->send($this, new ResetPasswordMail($this, (string) $token), deferred: true);
    }
}
