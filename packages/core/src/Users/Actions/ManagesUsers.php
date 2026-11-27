<?php

namespace Codeart\Core\Users\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Shared by the user actions: authorize through UserPolicy, log with a snapshot of name and email (they survive a permanent delete). */
trait ManagesUsers
{
    protected function authorizeFor(?Authenticatable $actor, string $ability, Model $user): void
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize($ability, $user);
        }
    }

    /** @param array<string, mixed> $extra */
    protected function logUser(ActivityLog $log, string $event, string $description, Model $user, ?Authenticatable $actor, array $extra = []): void
    {
        $log->record($event, $description, $user, $actor instanceof Model ? $actor : null, ['name' => $user->getAttribute('name'), 'email' => $user->getAttribute('email'), ...$extra], 'users');
    }
}
