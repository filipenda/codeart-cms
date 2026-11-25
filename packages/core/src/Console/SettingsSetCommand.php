<?php

namespace Codeart\Core\Console;

use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\SettingType;
use InvalidArgumentException;

class SettingsSetCommand extends SettingsCommand
{
    protected $signature = 'settings:set {key} {value? : Omit for secrets to be asked without echo; use "null" to reset to the default} {--locale= : Locale of a translatable setting}';

    protected $description = 'Change a setting (validated; the value is never printed)';

    public function handle(Settings $settings): int
    {
        try {
            $definition = $settings->registry()->definition((string) $this->argument('key'));
            $raw = $this->argument('value');
            if ($raw === null) {
                if (! $definition->secret) {
                    $this->components->error('Give a value (or "null" to reset to the default).');

                    return self::FAILURE;
                }
                $raw = (string) $this->secret('Value (hidden)');
            }

            $value = match (true) {
                $raw === 'null' && ! $definition->secret => null,
                $definition->type === SettingType::Json => $this->decodeJson((string) $raw),
                default => $raw,
            };
            $locale = $this->option('locale');
            $settings->set($definition->key, $value, is_string($locale) ? $locale : null);
        } catch (InvalidArgumentException $e) { // unknown key, invalid value
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Setting [{$definition->key}] updated.");

        return self::SUCCESS;
    }

    private function decodeJson(string $raw): mixed
    {
        $value = json_decode($raw, true);
        if (! is_array($value)) {
            throw new InvalidArgumentException('Give JSON, e.g. \'["sr","en"]\'.');
        }

        return $value;
    }
}
