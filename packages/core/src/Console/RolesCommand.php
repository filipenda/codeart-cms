<?php

namespace Codeart\Core\Console;

use Codeart\Core\Permissions\CreateDefaultRoles;
use Illuminate\Console\Command;

class RolesCommand extends Command
{
    protected $signature = 'codeart:roles';

    protected $description = 'Create the default roles (super-admin, admin, editor) if missing';

    public function handle(CreateDefaultRoles $create): int
    {
        $created = $create();
        $this->components->info($created === [] ? 'Roles already exist.' : 'Created roles: '.implode(', ', $created).'.');

        return self::SUCCESS;
    }
}
