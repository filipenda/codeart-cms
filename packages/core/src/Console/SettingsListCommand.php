<?php

namespace Codeart\Core\Console;

use Codeart\Core\Settings\Settings;

class SettingsListCommand extends SettingsCommand
{
    protected $signature = 'settings:list {--group= : Only this group (theme, locales, security, ...)}';

    protected $description = 'List settings and their values (secrets are masked)';

    public function handle(Settings $settings): int
    {
        $rows = [];
        $group = $this->option('group');
        foreach ($settings->registry()->all() as $definition) {
            if (is_string($group) && $group !== '' && $definition->group !== $group) {
                continue;
            }
            $rows[] = [
                $definition->key,
                $definition->type->value,
                $this->display($settings, $definition),
                $settings->has($definition->key) ? 'yes' : 'default',
                $settings->isLocked($definition->key) ? 'locked' : '',
            ];
        }

        $this->table(['Key', 'Type', 'Value', 'Stored', 'Lock'], $rows);

        return self::SUCCESS;
    }
}
