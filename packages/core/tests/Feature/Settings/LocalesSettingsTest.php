<?php

use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Translations\Locales;

it('defaults to the config values', function () {
    $locales = app(Locales::class);

    expect($locales->default())->toBe('sr')
        ->and($locales->enabled())->toBe(['sr', 'sr-Cyrl', 'en'])
        ->and($locales->chain('en'))->toBe(['en', 'sr']);
});

it('reads default, enabled and fallbacks from the database', function () {
    $settings = app(Settings::class);
    $settings->setMany(['locales.enabled' => ['en', 'sr'], 'locales.default' => 'en', 'locales.fallbacks' => ['sr' => ['en']]]);
    $locales = app(Locales::class);

    expect($locales->default())->toBe('en')
        ->and($locales->enabled())->toBe(['en', 'sr'])
        ->and($locales->isEnabled('sr-Cyrl'))->toBeFalse()
        ->and($locales->chain('sr'))->toBe(['sr', 'en']);
});

it('uses the config as default when changed', function () {
    config(['codeart.locales.enabled' => ['sr', 'en']]);

    expect(app(Locales::class)->enabled())->toBe(['sr', 'en']);
});

it('requires the default to be among the enabled languages', function () {
    $settings = app(Settings::class);

    expect(fn () => $settings->set('locales.default', 'de'))->toThrow(InvalidSettingValue::class)
        ->and(fn () => $settings->set('locales.default', 'en') ?: $settings->set('locales.enabled', ['sr', 'sr-Cyrl']))->toThrow(InvalidSettingValue::class);
});

it('checks the default against the final state when both change together', function () {
    $settings = app(Settings::class);
    $settings->setMany(['locales.enabled' => ['en'], 'locales.default' => 'en']);

    expect($settings->get('locales.default'))->toBe('en')
        ->and(fn () => $settings->setMany(['locales.enabled' => ['sr'], 'locales.default' => 'en']))->toThrow(InvalidSettingValue::class)
        ->and($settings->get('locales.enabled'))->toBe(['en']); // the failed write changed nothing
});

it('enables sr-Cyrl only together with sr', function () {
    $settings = app(Settings::class);

    expect(fn () => $settings->setMany(['locales.enabled' => ['en', 'sr-Cyrl'], 'locales.default' => 'en']))->toThrow(InvalidSettingValue::class, 'sr must be enabled');
    $settings->set('locales.enabled', ['sr', 'sr-Cyrl']);
    expect($settings->get('locales.enabled'))->toBe(['sr', 'sr-Cyrl']);

    // and sr cannot be dropped while sr-Cyrl stays
    expect(fn () => $settings->setMany(['locales.enabled' => ['en', 'sr-Cyrl'], 'locales.default' => 'en']))->toThrow(InvalidSettingValue::class);
});

it('rejects unknown languages and empty lists', function (mixed $value) {
    expect(fn () => app(Settings::class)->set('locales.enabled', $value))->toThrow(InvalidSettingValue::class);
})->with([[['sr', 'xx']], [[]], [['sr', 5]], [['a' => 'sr']]]);

it('validates fallbacks', function () {
    $settings = app(Settings::class);

    expect(fn () => $settings->set('locales.fallbacks', ['xx' => ['sr']]))->toThrow(InvalidSettingValue::class)
        ->and(fn () => $settings->set('locales.fallbacks', ['en' => 'sr']))->toThrow(InvalidSettingValue::class);
    $settings->set('locales.fallbacks', ['en' => ['sr']]);
    expect($settings->get('locales.fallbacks'))->toBe(['en' => ['sr']]);
});
