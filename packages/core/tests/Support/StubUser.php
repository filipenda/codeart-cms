<?php

namespace Codeart\Core\Tests\Support;

use Codeart\Core\Auth\Admin;
use Illuminate\Foundation\Auth\User;
use Spatie\Permission\Traits\HasRoles;

/** Core does not own the User model; this is what an app's User looks like once it uses HasRoles. */
class StubUser extends User
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    public function guardName(): string
    {
        return Admin::guardName();
    }
}
