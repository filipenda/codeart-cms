<?php

namespace Codeart\Core\Console;

use Codeart\Core\Settings\SettingDefinition;
use Codeart\Core\Settings\Settings;
use Illuminate\Console\Command;

/** CLI is treated as super-admin (whoever has a shell is trusted). Secrets are always masked in output. */
abstract class SettingsCommand extends Command
{
    protected function display(Settings $settings, SettingDefinition $definition, ?string $locale = null): string
    {
        $value = $settings->masked($definition->key, $locale);

        return match (true) {
            $value === null => '(not set)',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }
}
