<?php

namespace Codeart\Core\Auth\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/** Removes a user's two-factor setup (lost phone). The user sets it up again on the next sign-in. */
class ResetTwoFactor
{
    public function __construct(private readonly ActivityLog $log) {}

    public function __invoke(Model $user, ?Authenticatable $actor = null): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->log->record('two_factor.reset', 'Two-factor authentication reset', $user, $actor instanceof Model ? $actor : null, ['via' => $actor === null ? 'command line' : 'admin'], 'users');
    }
}
