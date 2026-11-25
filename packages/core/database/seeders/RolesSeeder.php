<?php

namespace Codeart\Core\Database\Seeders;

use Codeart\Core\Permissions\CreateDefaultRoles;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        (new CreateDefaultRoles)();
    }
}
