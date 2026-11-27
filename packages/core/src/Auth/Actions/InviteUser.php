<?php

namespace Codeart\Core\Auth\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\Mail\InvitationMail;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Auth\UserInvitation;
use Codeart\Core\Mail\AuthMailer;
use Codeart\Core\Settings\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class InviteUser
{
    public function __construct(
        private readonly Settings $settings,
        private readonly AuthMailer $mailer,
        private readonly ActivityLog $log,
    ) {}

    /**
     * @param  list<string>  $roles
     *
     * @throws AuthorizationException|ValidationException
     */
    public function __invoke(string $name, string $email, array $roles, ?Authenticatable $actor = null, ?string $message = null): UserInvitation
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('invite', Admin::userModel());
        }
        if ($roles === []) {
            throw ValidationException::withMessages(['roles' => [__('codeart::auth.role_not_allowed')]]);
        }
        foreach ($roles as $role) {
            if (! RoleRank::canAssign($actor, $role)) {
                throw new AuthorizationException(__('codeart::auth.role_not_allowed'));
            }
            Role::findByName($role, Admin::guardName());
        }
        if (Admin::usersWithTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => [__('codeart::auth.email_taken')]]);
        }

        // One open invitation per address: a new one replaces the old.
        UserInvitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

        $days = (int) $this->settings->get('auth.invitation.expiry_days');
        $token = Str::random(64);
        $invitation = UserInvitation::query()->create([
            'name' => $name,
            'email' => $email,
            'roles' => $roles,
            'message' => $message !== null && trim($message) !== '' ? mb_substr(trim($message), 0, 500) : null,
            'token_hash' => UserInvitation::hashToken($token),
            'invited_by' => $actor?->getAuthIdentifier(),
            'expires_at' => now()->addDays($days),
        ]);

        $url = Admin::signedUrl('admin.invitation.show', $invitation->expires_at, ['token' => $token]);
        $this->mailer->send($email, new InvitationMail($name, $url, $days, $invitation->message));

        $this->log->record('invitation.sent', 'Invitation sent', $invitation, $actor instanceof Model ? $actor : null, ['name' => $name, 'email' => $email, 'roles' => $roles, 'with_message' => $invitation->message !== null], 'users');

        return $invitation;
    }
}
