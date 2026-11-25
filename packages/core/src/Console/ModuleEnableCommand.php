<?php

namespace Codeart\Core\Console;

use Codeart\Core\Modules\Actions\EnableModule;
use Codeart\Core\Modules\ModuleException;
use Illuminate\Console\Command;

class ModuleEnableCommand extends Command
{
    protected $signature = 'module:enable {name : Module name, e.g. example}';

    protected $description = 'Enable a module: run its migrations, sync its permissions';

    public function handle(EnableModule $enable): int
    {
        $name = (string) $this->argument('name');
        try {
            $changed = $enable($name);
        } catch (ModuleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $changed
            ? $this->components->info("Module \"{$name}\" enabled.")
            : $this->components->warn("Module \"{$name}\" was already enabled.");

        return self::SUCCESS;
    }
}
