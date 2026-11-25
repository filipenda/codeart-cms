<?php

namespace Codeart\Core\Settings;

use Codeart\Core\Modules\ModuleRegistry;
use LogicException;

/**
 * All known settings. Core registers its own; a module registers its in its service provider,
 * with `module: '<name>'`. Module definitions are visible only while that module is enabled.
 */
class SettingsRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    public function register(SettingDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            if (isset($this->definitions[$definition->key])) {
                throw new LogicException("Setting [{$definition->key}] is already registered.");
            }
            $this->definitions[$definition->key] = $definition;
        }
    }

    public function find(string $key): ?SettingDefinition
    {
        $definition = $this->definitions[$key] ?? null;

        return $definition !== null && $this->visible($definition) ? $definition : null;
    }

    /** @throws UnknownSetting */
    public function definition(string $key): SettingDefinition
    {
        return $this->find($key) ?? throw new UnknownSetting($key);
    }

    /** @return array<string, SettingDefinition> */
    public function all(): array
    {
        $all = array_filter($this->definitions, $this->visible(...));
        ksort($all);

        return $all;
    }

    /** @return array<string, SettingDefinition> */
    public function group(string $group): array
    {
        return array_filter($this->all(), fn (SettingDefinition $d): bool => $d->group === $group);
    }

    private function visible(SettingDefinition $definition): bool
    {
        return $definition->module === null || app(ModuleRegistry::class)->isEnabled($definition->module);
    }
}
