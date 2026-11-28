<?php

namespace Codeart\Core\Auth\Listeners;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\Mail\NewRegistrationPendingMail;
use Codeart\Core\Auth\Mail\RegistrationReceivedMail;
use Codeart\Core\Auth\UserSessions;
use Codeart\Core\Mail\AuthMailer;
use Codeart\Core\Privacy\IpPrivacy;
use Codeart\Core\Settings\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Turns Laravel, Fortify and spatie events into activity-log entries, and runs the registration side effects.
 * Only the admin guard is logged. Passwords, tokens, codes and secrets are never passed on.
 */
class AuthEventSubscriber
{
    public function __construct(
        private readonly ActivityLog $log,
        private readonly Settings $settings,
        private readonly AuthMailer $mailer,
        private readonly UserSessions $sessions,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, $this->login(...));
        $events->listen(Failed::class, $this->failed(...));
        $events->listen(Logout::class, $this->logout(...));
        $events->listen(Lockout::class, $this->lockout(...));
        $events->listen(PasswordResetLinkSent::class, fn ($e) => $this->userEvent('password.reset_requested', 'Password reset requested', $e));
        $events->listen(PasswordReset::class, $this->passwordReset(...));
        $events->listen(Verified::class, fn ($e) => $this->userEvent('email.verified', 'E-mail address verified', $e));
        $events->listen(Registered::class, $this->registered(...));

        // Fortify and spatie classes are looked up by name so a version without one of them cannot break boot.
        $map = [
            'Laravel\Fortify\Events\PasswordUpdatedViaController' => ['password.changed', 'Password changed'],
            'Laravel\Fortify\Events\TwoFactorAuthenticationEnabled' => ['two_factor.setup_started', 'Two-factor setup started'],
            'Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed' => ['two_factor.enabled', 'Two-factor authentication enabled'],
            'Laravel\Fortify\Events\TwoFactorAuthenticationDisabled' => ['two_factor.disabled', 'Two-factor authentication disabled'],
            'Laravel\Fortify\Events\TwoFactorAuthenticationFailed' => ['two_factor.failed', 'Two-factor code rejected'],
            'Laravel\Fortify\Events\RecoveryCodeReplaced' => ['two_factor.recovery_code_used', 'Recovery code used'],
            'Laravel\Fortify\Events\RecoveryCodesGenerated' => ['two_factor.recovery_codes_regenerated', 'Recovery codes regenerated'],
        ];
        foreach ($map as $class => [$event, $description]) {
            if (class_exists($class)) {
                $events->listen($class, fn ($e) => $this->userEvent($event, $description, $e));
            }
        }

        foreach (['RoleAttachedEvent' => 'attached', 'RoleDetachedEvent' => 'detached', 'PermissionAttachedEvent' => 'attached', 'PermissionDetachedEvent' => 'detached'] as $name => $verb) {
            $class = 'Spatie\Permission\Events\\'.$name;
            if (class_exists($class)) {
                $kind = str_starts_with($name, 'Role') ? 'role' : 'permission';
                $events->listen($class, fn ($e) => $this->permissionChange($kind, $verb, $e));
            }
        }
    }

    private function onAdminGuard(string $guard): bool
    {
        return $guard === Admin::guardName();
    }

    private function login(Login $event): void
    {
        if (! $this->onAdminGuard($event->guard) || ! $event->user instanceof Model) {
            return;
        }
        $event->user->forceFill(['last_login_at' => now(), 'last_login_ip' => app(IpPrivacy::class)->store(request()->ip())])->saveQuietly();
        $this->log->record('auth.login', 'Signed in', $event->user, $event->user, ['remember' => $event->remember]);
    }

    private function failed(Failed $event): void
    {
        if (! $this->onAdminGuard($event->guard)) {
            return;
        }
        $this->log->record('auth.login_failed', 'Sign-in failed', $event->user instanceof Model ? $event->user : null, null, ['email' => $this->safeEmail($event->credentials['email'] ?? null)]);
    }

    private function logout(Logout $event): void
    {
        if ($this->onAdminGuard($event->guard) && $event->user instanceof Model) {
            $this->log->record('auth.logout', 'Signed out', $event->user, $event->user);
        }
    }

    private function lockout(Lockout $event): void
    {
        $this->log->record('auth.lockout', 'Too many sign-in attempts', null, null, ['email' => $this->safeEmail($event->request->input('email'))]);
    }

    private function passwordReset(PasswordReset $event): void
    {
        $this->userEvent('password.reset_completed', 'Password reset completed', $event);
    }

    private function registered(Registered $event): void
    {
        $user = $event->user;
        if (! $user instanceof Model) {
            return;
        }
        $needsApproval = (bool) $this->settings->get('auth.registration.require_approval');
        $this->log->record('registration.created', 'Registration', $user, $user, ['email' => $user->email, 'needs_approval' => $needsApproval], 'users');

        if ($this->settings->get('auth.registration.require_email_verification') && $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
        $this->mailer->send($user, new RegistrationReceivedMail((string) $user->name, $needsApproval), deferred: true);

        if ($needsApproval) {
            foreach ($this->approvers() as $approver) {
                $this->mailer->send($approver, new NewRegistrationPendingMail((string) $user->name, (string) $user->email), deferred: true);
            }
        }
    }

    /**
     * Active staff who may approve registrations: users.approve through a role, plus every super-admin.
     *
     * @return Collection<array-key, Model>
     */
    private function approvers(): Collection
    {
        $model = Admin::userModel();
        $found = $model::query()->role('super-admin')->get();
        try {
            $found = $found->merge($model::query()->permission('users.approve')->get());
        } catch (Throwable) {
            // the permission does not exist yet (roles not seeded): super-admins only
        }

        return $found->unique(fn (Model $user) => $user->getKey())->filter(fn (Model $user): bool => (bool) $user->is_active && $user->approved_at !== null);
    }

    private function userEvent(string $event, string $description, object $e): void
    {
        $user = $e->user ?? null;
        if ($user instanceof Model) {
            $this->log->record($event, $description, $user, $user);
            if ($event === 'password.reset_completed') {
                $this->sessions->revokeAll($user->getKey());
            }
        }
    }

    private function permissionChange(string $kind, string $verb, object $e): void
    {
        $model = $e->model ?? null;
        $items = $e->rolesOrIds ?? $e->permissionsOrIds ?? [];
        $names = [];
        foreach (is_iterable($items) ? $items : [$items] as $item) {
            if (is_object($item) && isset($item->name)) {
                $names[] = (string) $item->name;
            } else {
                $names[] = is_int($item) || ctype_digit((string) $item) ? (string) (Role::query()->find($item)->name ?? $item) : (string) $item;
            }
        }

        $this->log->record("{$kind}.{$verb}", ucfirst($kind)." {$verb}", $model instanceof Model ? $model : null, null, [$kind.'s' => $names], 'users');
    }

    /** Only a well-formed address goes into the log (people sometimes type their password into the e-mail field). */
    private function safeEmail(mixed $value): ?string
    {
        return is_string($value) && Validator::make(['e' => $value], ['e' => 'email'])->passes() ? mb_substr($value, 0, 190) : null;
    }
}
