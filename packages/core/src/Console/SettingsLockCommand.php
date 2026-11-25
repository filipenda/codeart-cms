<?php

namespace Codeart\Core\Console;

use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\UnknownSetting;

class SettingsLockCommand extends SettingsCommand
{
    protected $signature = 'settings:lock {key}';

    protected $description = 'Lock a setting: only a super-admin can change it while locked';

    public function handle(Settings $settings): int
    {
        try {
            $settings->lock((string) $this->argument('key'));
        } catch (UnknownSetting $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Setting ['.$this->argument('key').'] locked.');

        return self::SUCCESS;
    }
}
