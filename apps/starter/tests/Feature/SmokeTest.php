<?php

use Codeart\Core\CoreServiceProvider;
use Codeart\Core\Theme\ThemeSettings;

it('boots the app and loads the core service provider', function () {
    expect(app()->getProvider(CoreServiceProvider::class))->toBeInstanceOf(CoreServiceProvider::class)
        ->and(app(ThemeSettings::class))->toBeInstanceOf(ThemeSettings::class)
        ->and(config('codeart.theme.primary'))->not->toBeNull();
});

it('serves the home page with the core stylesheet', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('vendor/codeart/codeart.css', false)
        ->assertSee('data-theme="light"', false);
});
