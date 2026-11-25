<?php

namespace Codeart\Core\Modules\Actions;

use Codeart\Core\Events\ModuleDisabled;
use Codeart\Core\Modules\ModuleException;
use Codeart\Core\Modules\ModuleRecord;
use Codeart\Core\Modules\ModuleRegistry;

/** Turns a module off. Data, tables and permissions stay: re-enabling brings everything back. */
class DisableModule
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    /** @return bool false when the module was not enabled (nothing done) */
    public function __invoke(string $name): bool
    {
        $record = ModuleRecord::query()->where('name', $name)->first();
        if ($record === null) {
            if ($this->registry->find($name) === null) {
                throw new ModuleException("Module \"{$name}\" is not installed.");
            }

            return false;
        }

        if (! $record->enabled) {
            return false;
        }

        foreach ($this->registry->enabled() as $other) {
            if ($other->manifest->name !== $name && array_key_exists($name, $other->manifest->requires)) {
                throw new ModuleException("Cannot disable \"{$name}\": enabled module \"{$other->manifest->name}\" requires it. Disable \"{$other->manifest->name}\" first.");
            }
        }

        $record->update(['enabled' => false]);
        $this->registry->forgetCache();

        // The package may have been removed from vendor/ already; then there is no manifest to announce.
        if (($module = $this->registry->find($name)) !== null) {
            event(new ModuleDisabled($module->manifest));
        }

        return true;
    }
}
