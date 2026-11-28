<?php

namespace Codeart\Core\Users\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Mail\RegistrationRejectedMail;
use Codeart\Core\Mail\AuthMailer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/** Odbij: only for a registration that is still waiting; the account is deleted (soft) and the person gets a mail with the optional reason. */
class RejectUser
{
    use ManagesUsers;

    public function __construct(private readonly ActivityLog $log, private readonly AuthMailer $mailer) {}

    public function __invoke(Model $user, ?string $reason, ?Authenticatable $actor = null): bool
    {
        $this->authorizeFor($actor, 'approve', $user);
        if ($user->getAttribute('approved_at') !== null) {
            return false;
        }
        $reason = $reason !== null ? mb_substr(trim($reason), 0, 500) : null;
        $this->mailer->send($user, new RegistrationRejectedMail((string) $user->getAttribute('name'), $reason));
        $this->logUser($this->log, 'user.rejected', 'Registration rejected', $user, $actor, ['reason' => $reason]);
        $user->delete();

        return true;
    }
}
