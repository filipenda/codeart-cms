<?php

namespace Codeart\Core\Users\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class UnblockUser
{
    use ManagesUsers;

    public function __construct(private readonly ActivityLog $log) {}

    public function __invoke(Model $user, ?Authenticatable $actor = null): void
    {
        $this->authorizeFor($actor, 'block', $user);
        $user->forceFill(['is_active' => true])->save();
        $this->logUser($this->log, 'user.unblocked', 'User unblocked', $user, $actor);
    }
}
