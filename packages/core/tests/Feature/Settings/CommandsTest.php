<?php

use Codeart\Core\Settings\SettingDefinition;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\SettingsRegistry;
use Codeart\Core\Settings\SettingType;

it('lists settings, optionally by group', function () {
    $this->artisan('settings:list')->expectsOutputToContain('theme.primary')->expectsOutputToContain('security.2fa.mode')->assertSuccessful();
    $this->artisan('settings:list --group=locales')->expectsOutputToContain('locales.default')->doesntExpectOutputToContain('theme.primary')->assertSuccessful();
});

it('gets and sets typed values', function () {
    $this->artisan('settings:set security.login.max_attempts 8')->expectsOutputToContain('updated')->assertSuccessful();
    $this->artisan('settings:get security.login.max_attempts')->expectsOutput('8')->assertSuccessful();

    $this->artisan('settings:set locales.enabled \'["sr","en"]\'')->assertSuccessful();
    expect(app(Settings::class)->get('locales.enabled'))->toBe(['sr', 'en']);
    $this->artisan('settings:get locales.enabled')->expectsOutput('["sr","en"]')->assertSuccessful();

    $this->artisan('settings:set security.password.require_symbol true')->assertSuccessful();
    $this->artisan('settings:get security.password.require_symbol')->expectsOutput('true')->assertSuccessful();

    $this->artisan('settings:set security.login.max_attempts null')->assertSuccessful();
    expect(app(Settings::class)->has('security.login.max_attempts'))->toBeFalse();
});

it('reports validation errors and unknown keys without a stack trace', function () {
    $this->artisan('settings:set security.password.min_length 3')->expectsOutputToContain('Invalid value')->assertFailed();
    $this->artisan('settings:set locales.enabled notjson')->expectsOutputToContain('JSON')->assertFailed();
    $this->artisan('settings:set nope.key 1')->expectsOutputToContain('Unknown setting')->assertFailed();
    $this->artisan('settings:get nope.key')->assertFailed();
    $this->artisan('settings:lock nope.key')->assertFailed();
});

it('locks and unlocks', function () {
    $this->artisan('settings:lock theme.primary')->assertSuccessful();
    expect(app(Settings::class)->isLocked('theme.primary'))->toBeTrue();
    $this->artisan('settings:list')->expectsOutputToContain('locked')->assertSuccessful();

    $this->artisan('settings:set theme.primary #123456')->assertSuccessful(); // CLI is super-admin
    $this->artisan('settings:unlock theme.primary')->assertSuccessful();
    expect(app(Settings::class)->isLocked('theme.primary'))->toBeFalse();
});

it('asks for a secret without echoing it and masks it afterwards', function () {
    app(SettingsRegistry::class)->register(
        new SettingDefinition('t.secret', SettingType::Secret, ''),
    );

    $this->artisan('settings:set t.secret')->expectsQuestion('Value (hidden)', 'top-secret-123')->doesntExpectOutputToContain('top-secret-123')->assertSuccessful();

    expect(app(Settings::class)->get('t.secret'))->toBe('top-secret-123');
    $this->artisan('settings:get t.secret')->expectsOutput('********')->assertSuccessful();
});
