<?php

namespace Codeart\Core\Tests\Fixtures;

use Codeart\Core\Settings\SettingDefinition;
use Codeart\Core\Settings\SettingsRegistry;
use Codeart\Core\Settings\SettingType;
use Illuminate\Support\ServiceProvider;

class AlphaProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(SettingsRegistry::class)->register(
            new SettingDefinition('alpha.greeting', SettingType::String, 'hello', permission: 'alpha.edit', label: 'Greeting', module: 'alpha'),
        );
    }
}
