<?php

namespace Codeart\Core\Users\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\RoleRank;
use Codeart\Core\Auth\UserInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class RevokeInvitation
{
    public function __construct(private readonly ActivityLog $log) {}

    /** @throws AuthorizationException */
    public function __invoke(UserInvitation $invitation, Authenticatable $actor): void
    {
        Gate::forUser($actor)->authorize('invite', Admin::userModel());
        foreach ($invitation->roles as $role) {
            if (! RoleRank::canAssign($actor, $role)) {
                throw new AuthorizationException(__('codeart::auth.role_not_allowed'));
            }
        }
        $this->log->record('invitation.revoked', 'Invitation revoked', $invitation, $actor instanceof Model ? $actor : null, ['name' => $invitation->name, 'email' => $invitation->email, 'roles' => $invitation->roles], 'users');
        $invitation->delete();
    }
}
