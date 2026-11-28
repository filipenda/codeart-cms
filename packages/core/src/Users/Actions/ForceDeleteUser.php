<?php

namespace Codeart\Core\Users\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\UserSessions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/** Permanent delete (super-admin only). The log entry keeps name and email as a snapshot in its properties. */
class ForceDeleteUser
{
    use ManagesUsers;

    public function __construct(private readonly ActivityLog $log, private readonly UserSessions $sessions) {}

    public function __invoke(Model $user, ?Authenticatable $actor = null): void
    {
        $this->authorizeFor($actor, 'forceDelete', $user);
        $this->logUser($this->log, 'user.force_deleted', 'User permanently deleted', $user, $actor);
        $this->sessions->revokeAll($user->getKey());
        $user->forceDelete();
    }
}
