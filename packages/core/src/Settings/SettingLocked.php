<?php

namespace Codeart\Core\Settings;

use Illuminate\Auth\Access\AuthorizationException;

class SettingLocked extends AuthorizationException
{
    public function __construct(string $key)
    {
        parent::__construct("Setting [{$key}] is locked; only a super-admin can change it.");
    }
}
