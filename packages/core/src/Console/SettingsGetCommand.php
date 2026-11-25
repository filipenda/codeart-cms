<?php

namespace Codeart\Core\Console;

use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\UnknownSetting;

class SettingsGetCommand extends SettingsCommand
{
    protected $signature = 'settings:get {key} {--locale= : Locale of a translatable setting}';

    protected $description = 'Show one setting (secrets are masked)';

    public function handle(Settings $settings): int
    {
        try {
            $definition = $settings->registry()->definition((string) $this->argument('key'));
            $locale = $this->option('locale');
            $this->line($this->display($settings, $definition, is_string($locale) ? $locale : null));
        } catch (UnknownSetting|\InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
