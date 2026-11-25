<?php

namespace Codeart\Core\Auth\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Mail\RegistrationApprovedMail;
use Codeart\Core\Mail\AuthMailer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ApproveUser
{
    public function __construct(private readonly AuthMailer $mailer, private readonly ActivityLog $log) {}

    /**
     * A null approver is the command line. Returns false when the user was already approved.
     *
     * @throws AuthorizationException
     */
    public function __invoke(Model $user, ?Authenticatable $approver = null): bool
    {
        if ($approver !== null) {
            Gate::forUser($approver)->authorize('approve', $user);
        }
        if ($user->approved_at !== null && $user->is_active) {
            return false;
        }

        $user->forceFill(['approved_at' => now(), 'is_active' => true])->save();
        $this->mailer->send($user, new RegistrationApprovedMail((string) $user->name));
        $this->log->record('user.approved', 'Registration approved', $user, $approver instanceof Model ? $approver : null, ['email' => $user->email], 'users');

        return true;
    }
}
