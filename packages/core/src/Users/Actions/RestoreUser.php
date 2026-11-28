<?php

namespace Codeart\Core\Users\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class RestoreUser
{
    use ManagesUsers;

    public function __construct(private readonly ActivityLog $log) {}

    public function __invoke(Model $user, ?Authenticatable $actor = null): void
    {
        $this->authorizeFor($actor, 'restore', $user);
        Admin::staff($user)->restore();
        $this->logUser($this->log, 'user.restored', 'User restored', $user, $actor);
    }
}
