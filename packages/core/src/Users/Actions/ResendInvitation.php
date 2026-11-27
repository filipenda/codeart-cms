<?php

namespace Codeart\Core\Users\Actions;

use Codeart\Core\Auth\Actions\InviteUser;
use Codeart\Core\Auth\UserInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;

/** A fresh token and link for an open invitation (the old link stops working). */
class ResendInvitation
{
    public function __construct(private readonly InviteUser $invite) {}

    /** @throws AuthorizationException */
    public function __invoke(UserInvitation $invitation, Authenticatable $actor): UserInvitation
    {
        abort_if($invitation->accepted_at !== null, 404);

        return ($this->invite)($invitation->name, $invitation->email, $invitation->roles, $actor, $invitation->message);
    }
}
