<?php

namespace Codeart\Core\Testing;

use Codeart\Core\Auth\Concerns\AdminUser;
use Codeart\Core\Auth\Contracts\StaffUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User;

/** What an app's User looks like once it uses core's trait: the user of generated module tests. */
class StaffTestUser extends User implements HasLocalePreference, MustVerifyEmail, StaffUser
{
    use AdminUser;

    protected $table = 'users';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
