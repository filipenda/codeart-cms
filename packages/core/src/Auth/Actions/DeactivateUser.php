<?php

namespace Codeart\Core\Auth\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\UserSessions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DeactivateUser
{
    public function __construct(private readonly UserSessions $sessions, private readonly ActivityLog $log) {}

    /** @throws AuthorizationException */
    public function __invoke(Model $user, ?Authenticatable $actor = null): void
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('deactivate', $user);
            if ($actor->getAuthIdentifier() === $user->getKey()) {
                throw new AuthorizationException('You cannot deactivate yourself.');
            }
        }

        $user->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();
        $this->sessions->revokeAll($user->getKey());
        $this->log->record('user.deactivated', 'User deactivated', $user, $actor instanceof Model ? $actor : null, ['name' => $user->name, 'email' => $user->email], 'users');
    }
}
