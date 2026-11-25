<?php

namespace Codeart\Core\Console;

use Codeart\Core\Modules\ModuleRegistry;
use Illuminate\Console\Command;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List installed CodeArt modules and whether they are enabled';

    public function handle(ModuleRegistry $registry): int
    {
        $records = $registry->records();
        $rows = [];
        foreach ($registry->installed() as $name => $module) {
            $record = $records[$name] ?? null;
            $note = '';
            if ($record !== null && $record->enabled && $record->installed_version !== $module->version) {
                $note = "was {$record->installed_version} when enabled";
            }
            $rows[] = [$name, $module->manifest->package, $module->version, $registry->isEnabled($name) ? 'enabled' : 'disabled', $note];
        }
        foreach ($records as $name => $record) {
            if ($record->enabled && $registry->find($name) === null) {
                $rows[] = [$name, $record->package, '-', 'enabled', 'package missing from vendor/'];
            }
        }

        $this->table(['Module', 'Package', 'Version', 'State', 'Note'], $rows);
        foreach ($registry->problems() as $package => $problem) {
            $this->components->warn("{$package}: {$problem}");
        }

        return self::SUCCESS;
    }
}
