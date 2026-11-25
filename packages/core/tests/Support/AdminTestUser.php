<?php

namespace Codeart\Core\Tests\Support;

use Codeart\Core\Auth\Concerns\AdminUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User;

/** What an app's User looks like once it uses core's trait. */
class AdminTestUser extends User implements HasLocalePreference, MustVerifyEmail
{
    use AdminUser;

    protected $table = 'users';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
