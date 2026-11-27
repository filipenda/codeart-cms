<?php

namespace Codeart\Core\Users\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\UserSessions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** "Obriši": soft delete. The person disappears from the list and cannot sign in; a restore brings everything back. */
class DeleteUser
{
    use ManagesUsers;

    public function __construct(private readonly ActivityLog $log, private readonly UserSessions $sessions) {}

    public function __invoke(Model $user, ?Authenticatable $actor = null): void
    {
        $this->authorizeFor($actor, 'delete', $user);
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $this->sessions->revokeAll($user->getKey());
        $this->logUser($this->log, 'user.deleted', 'User deleted', $user, $actor);
        $user->delete();
    }
}
