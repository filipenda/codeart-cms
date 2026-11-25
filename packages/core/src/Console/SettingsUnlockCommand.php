<?php

namespace Codeart\Core\Console;

use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\UnknownSetting;

class SettingsUnlockCommand extends SettingsCommand
{
    protected $signature = 'settings:unlock {key}';

    protected $description = 'Unlock a setting: only a super-admin can change it while locked';

    public function handle(Settings $settings): int
    {
        try {
            $settings->unlock((string) $this->argument('key'));
        } catch (UnknownSetting $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Setting ['.$this->argument('key').'] unlocked.');

        return self::SUCCESS;
    }
}
