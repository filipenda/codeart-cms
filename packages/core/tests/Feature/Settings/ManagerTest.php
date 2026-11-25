<?php

use Codeart\Core\Modules\Actions\EnableModule;
use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\SettingLocked;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\SettingsManager;
use Codeart\Core\Settings\UnknownSetting;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function manager(): SettingsManager
{
    return app(SettingsManager::class);
}

it('grants settings.appearance to admin only', function () {
    expect(Role::findByName('admin')->hasPermissionTo('settings.appearance'))->toBeTrue()
        ->and(Role::findByName('editor')->hasPermissionTo('settings.appearance'))->toBeFalse();
});

it('lets super-admin and admin change appearance, not editor', function () {
    manager()->update(userWith('super-admin'), 'theme.primary', '#112233');
    manager()->update(userWith('admin'), 'theme.secondary', '#445566');

    expect(app(Settings::class)->get('theme.primary'))->toBe('#112233')
        ->and(app(Settings::class)->get('theme.secondary'))->toBe('#445566');

    expect(fn () => manager()->update(userWith('editor'), 'theme.primary', '#000000'))->toThrow(AuthorizationException::class)
        ->and(fn () => manager()->update(null, 'theme.primary', '#000000'))->toThrow(AuthorizationException::class)
        ->and(app(Settings::class)->get('theme.primary'))->toBe('#112233');
});

it('lets admin change locale content settings', function () {
    manager()->update(userWith('admin'), 'locales.enabled', ['sr', 'en']);

    expect(app(Settings::class)->get('locales.enabled'))->toBe(['sr', 'en']);
});

it('keeps security settings super-admin only', function (string $key, mixed $value) {
    expect(fn () => manager()->update(userWith('admin'), $key, $value))->toThrow(AuthorizationException::class)
        ->and(fn () => manager()->update(userWith('editor'), $key, $value))->toThrow(AuthorizationException::class);

    manager()->update(userWith('super-admin'), $key, $value);
    expect(app(Settings::class)->get($key))->toBe($value);
})->with([
    ['security.password.min_length', 12],
    ['security.2fa.mode', 'required'],
    ['security.login.max_attempts', 3],
]);

it('validates through the manager and rejects unknown keys', function () {
    expect(fn () => manager()->update(userWith('super-admin'), 'security.password.min_length', 6))->toThrow(InvalidSettingValue::class)
        ->and(fn () => manager()->update(userWith('super-admin'), 'nope.x', 1))->toThrow(UnknownSetting::class);
});

it('locks and unlocks: only super-admin changes a locked setting', function () {
    $super = userWith('super-admin');
    $admin = userWith('admin');

    manager()->lock($super, 'theme.primary');
    expect(app(Settings::class)->isLocked('theme.primary'))->toBeTrue()
        ->and(fn () => manager()->update($admin, 'theme.primary', '#010101'))->toThrow(SettingLocked::class)
        ->and(manager()->canUpdate($admin, 'theme.primary'))->toBeFalse()
        ->and(manager()->canUpdate($admin, 'theme.secondary'))->toBeTrue();

    manager()->update($super, 'theme.primary', '#020202'); // super-admin may
    expect(app(Settings::class)->get('theme.primary'))->toBe('#020202');

    manager()->unlock($super, 'theme.primary');
    manager()->update($admin, 'theme.primary', '#030303');
    expect(app(Settings::class)->get('theme.primary'))->toBe('#030303')->and(app(Settings::class)->isLocked('theme.primary'))->toBeFalse();
});

it('lets only super-admin lock or unlock', function () {
    expect(fn () => manager()->lock(userWith('admin'), 'theme.primary'))->toThrow(AuthorizationException::class)
        ->and(fn () => manager()->unlock(userWith('editor'), 'theme.primary'))->toThrow(AuthorizationException::class)
        ->and(fn () => manager()->lock(userWith('super-admin'), 'nope.x'))->toThrow(UnknownSetting::class);
});

it('does not lock out the raw layer (CLI, seeders)', function () {
    app(Settings::class)->lock('theme.primary');
    app(Settings::class)->set('theme.primary', '#0a0a0a');

    expect(app(Settings::class)->get('theme.primary'))->toBe('#0a0a0a');
});

it('lets a module permission govern its own setting', function () {
    useModules(['alpha' => '1.0.0']);
    app(EnableModule::class)('alpha');
    $admin = userWith('admin');

    expect(fn () => manager()->update($admin, 'alpha.greeting', 'x'))->toThrow(AuthorizationException::class);

    $admin->givePermissionTo(Permission::findOrCreate('alpha.edit', 'web'));
    manager()->update($admin->fresh(), 'alpha.greeting', 'x');
    expect(app(Settings::class)->get('alpha.greeting'))->toBe('x');
});
