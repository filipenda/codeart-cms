<?php

namespace Codeart\Core\Console;

use Codeart\Core\Modules\Actions\DisableModule;
use Codeart\Core\Modules\ModuleException;
use Illuminate\Console\Command;

class ModuleDisableCommand extends Command
{
    protected $signature = 'module:disable {name : Module name, e.g. example}';

    protected $description = 'Disable a module (data, tables and permissions are kept)';

    public function handle(DisableModule $disable): int
    {
        $name = (string) $this->argument('name');
        try {
            $changed = $disable($name);
        } catch (ModuleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $changed
            ? $this->components->info("Module \"{$name}\" disabled. Its data was not touched.")
            : $this->components->warn("Module \"{$name}\" was not enabled.");

        return self::SUCCESS;
    }
}
