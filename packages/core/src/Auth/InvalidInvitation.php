<?php

namespace Codeart\Core\Auth;

use RuntimeException;

/** Unknown, expired, already used (or no longer allowed) invitation; the reason is deliberately not exposed. */
class InvalidInvitation extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('codeart::auth.invitation_invalid'));
    }
}
