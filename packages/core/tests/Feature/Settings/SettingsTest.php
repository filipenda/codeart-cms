<?php

use Codeart\Core\Modules\Actions\DisableModule;
use Codeart\Core\Modules\Actions\EnableModule;
use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\SettingDefinition;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\SettingsRegistry;
use Codeart\Core\Settings\SettingType;
use Codeart\Core\Settings\UnknownSetting;
use Codeart\Core\Translations\Locales;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

function settings(): Settings
{
    return app(Settings::class);
}

/** A throwaway definition set for the typing tests. */
function defineTypes(): void
{
    app(SettingsRegistry::class)->register(
        new SettingDefinition('t.string', SettingType::String, 'dflt', ['max:5']),
        new SettingDefinition('t.int', SettingType::Int, 3, ['min:1', 'max:10']),
        new SettingDefinition('t.bool', SettingType::Bool, false),
        new SettingDefinition('t.color', SettingType::Color, '#ffffff'),
        new SettingDefinition('t.enum', SettingType::Enum, 'a', options: ['a', 'b']),
        new SettingDefinition('t.json', SettingType::Json, [], itemRules: ['string']),
        new SettingDefinition('t.image', SettingType::Image),
        new SettingDefinition('t.secret', SettingType::Secret, ''),
        new SettingDefinition('t.title', SettingType::String, 'none', translatable: true),
    );
}

it('returns defaults, then stored typed values', function () {
    defineTypes();
    expect(settings()->get('t.int'))->toBe(3)->and(settings()->has('t.int'))->toBeFalse();

    settings()->set('t.string', 'abc');
    settings()->set('t.int', '7'); // CLI style string
    settings()->set('t.bool', 'true');
    settings()->set('t.color', '#ABCDEF');
    settings()->set('t.enum', 'b');
    settings()->set('t.json', ['x', 'y']);
    settings()->set('t.image', 'branding/a.png');

    expect(settings()->get('t.string'))->toBe('abc')
        ->and(settings()->get('t.int'))->toBe(7)
        ->and(settings()->get('t.bool'))->toBeTrue()
        ->and(settings()->get('t.color'))->toBe('#abcdef')
        ->and(settings()->get('t.enum'))->toBe('b')
        ->and(settings()->get('t.json'))->toBe(['x', 'y'])
        ->and(settings()->get('t.image'))->toBe('branding/a.png')
        ->and(settings()->has('t.int'))->toBeTrue();
});

it('rejects values of the wrong type or breaking the rules', function (string $key, mixed $value) {
    defineTypes();

    expect(fn () => settings()->set($key, $value))->toThrow(InvalidSettingValue::class);
    expect(settings()->has($key))->toBeFalse();
})->with([
    'string too long' => ['t.string', 'abcdefg'],
    'string not a string' => ['t.string', 12],
    'int not numeric' => ['t.int', 'ten'],
    'int below min' => ['t.int', 0],
    'int above max' => ['t.int', 11],
    'int empty string' => ['t.int', ''],
    'bool garbage' => ['t.bool', 'maybe'],
    'color named' => ['t.color', 'red'],
    'color injection' => ['t.color', 'url(javascript:x)'],
    'enum unknown' => ['t.enum', 'c'],
    'json scalar' => ['t.json', 'x'],
    'json bad item' => ['t.json', [['nested']]],
    'image traversal' => ['t.image', '../../.env'],
    'image absolute' => ['t.image', '/etc/passwd'],
]);

it('rejects unknown keys for reads and writes', function () {
    expect(fn () => settings()->get('nope.nothing'))->toThrow(UnknownSetting::class)
        ->and(fn () => settings()->set('nope.nothing', 1))->toThrow(UnknownSetting::class)
        ->and(fn () => settings()->forget('nope.nothing'))->toThrow(UnknownSetting::class)
        ->and(fn () => settings()->isLocked('nope.nothing'))->toThrow(UnknownSetting::class);
});

it('resets to the default when forgotten or set to null', function () {
    defineTypes();
    settings()->set('t.int', 9);
    settings()->forget('t.int');
    expect(settings()->get('t.int'))->toBe(3);

    settings()->set('t.int', 9);
    settings()->set('t.int', null);
    expect(settings()->get('t.int'))->toBe(3)->and(DB::table('settings')->where('key', 't.int')->count())->toBe(0);
});

it('keeps translatable values per locale with fallback to the default locale', function () {
    defineTypes();
    settings()->set('t.title', 'Naslov', 'sr');
    settings()->set('t.title', 'Title', 'en');

    expect(settings()->get('t.title', 'en'))->toBe('Title')
        ->and(settings()->get('t.title', 'sr'))->toBe('Naslov')
        ->and(settings()->get('t.title', 'sr-Cyrl'))->toBe('Naslov') // not stored: default locale
        ->and(settings()->has('t.title', 'sr-Cyrl'))->toBeFalse();

    app()->setLocale('en');
    expect(settings()->get('t.title'))->toBe('Title');

    settings()->forget('t.title', 'en');
    expect(settings()->get('t.title', 'en'))->toBe('Naslov');
});

it('refuses a locale for a non-translatable setting', function () {
    defineTypes();

    expect(fn () => settings()->set('t.int', 1, 'en'))->toThrow(InvalidArgumentException::class);
});

it('stores a non-null locale marker so the unique index works', function () {
    defineTypes();
    settings()->set('t.int', 4);
    settings()->set('t.int', 5);

    expect(DB::table('settings')->where('key', 't.int')->pluck('locale')->all())->toBe(['*']);
});

it('reads from one cached entry and invalidates it on write', function () {
    defineTypes();
    settings()->set('t.int', 4);
    expect(settings()->get('t.int'))->toBe(4)->and(Cache::has(Settings::CACHE_KEY))->toBeTrue();

    DB::enableQueryLog();
    settings()->get('t.int');
    settings()->get('t.string');
    expect(DB::getQueryLog())->toBe([]); // no queries: served from cache

    settings()->set('t.int', 6);
    expect(Cache::has(Settings::CACHE_KEY))->toBeFalse()->and(settings()->get('t.int'))->toBe(6);

    // another process changed the table + cleared the cache: a fresh service sees it
    DB::table('settings')->where('key', 't.int')->update(['value' => '8']);
    Cache::forget(Settings::CACHE_KEY);
    expect((new Settings(app(SettingsRegistry::class)))->get('t.int'))->toBe(8);
});

it('works with the plain file cache (no tags)', function () {
    config(['cache.default' => 'file']);
    Cache::forget(Settings::CACHE_KEY);
    defineTypes();

    settings()->set('t.int', 5);
    expect(settings()->get('t.int'))->toBe(5)->and(Cache::get(Settings::CACHE_KEY))->toBeArray();

    settings()->flush();
    expect(Cache::get(Settings::CACHE_KEY))->toBeNull();
});

it('falls back to defaults while the database is not ready', function () {
    defineTypes();
    Schema::dropIfExists('settings_locks');
    Schema::dropIfExists('settings');
    settings()->flush();

    expect(settings()->get('t.int'))->toBe(3)
        ->and(settings()->get('theme.primary'))->toBe(config('codeart.theme.primary'))
        ->and(settings()->isLocked('t.int'))->toBeFalse()
        ->and(Cache::has(Settings::CACHE_KEY))->toBeFalse() // nothing cached, so the next request retries
        ->and(app(Locales::class)->enabled())->toBe(['sr', 'sr-Cyrl', 'en']);
});

it('reads the group', function () {
    settings()->set('security.login.max_attempts', 9);

    expect(settings()->group('security'))->toHaveKey('security.login.max_attempts', 9)->toHaveKey('security.session.lifetime_minutes', 120)
        ->and(settings()->group('theme'))->toHaveKeys(['theme.primary', 'theme.mode']);
});

it('exposes module definitions only while the module is enabled', function () {
    useModules(['alpha' => '1.0.0']);
    $registry = app(SettingsRegistry::class);

    expect($registry->find('alpha.greeting'))->toBeNull(); // installed, not enabled: provider not booted

    app(EnableModule::class)('alpha');
    expect($registry->find('alpha.greeting'))->not->toBeNull()
        ->and(settings()->get('alpha.greeting'))->toBe('hello');

    settings()->set('alpha.greeting', 'hi');
    app(DisableModule::class)('alpha');
    expect($registry->find('alpha.greeting'))->toBeNull()
        ->and(array_keys($registry->group('alpha')))->toBe([])
        ->and(fn () => settings()->get('alpha.greeting'))->toThrow(UnknownSetting::class);

    app(EnableModule::class)('alpha'); // stored value survives a disable/enable cycle
    expect(settings()->get('alpha.greeting'))->toBe('hi');
});

it('forces module keys into the module namespace and unique keys', function () {
    expect(fn () => new SettingDefinition('other.key', SettingType::String, module: 'alpha'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new SettingDefinition('Bad Key', SettingType::String))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(SettingsRegistry::class)->register(new SettingDefinition('theme.primary', SettingType::String)))->toThrow(LogicException::class);
});

it('encrypts secrets at rest, decrypts on read and never exposes them', function () {
    defineTypes();
    settings()->set('t.secret', 'hunter2-api-key');

    $stored = (string) DB::table('settings')->where('key', 't.secret')->value('value');
    $cached = json_encode(Cache::get(Settings::CACHE_KEY));

    expect($stored)->not->toContain('hunter2')
        ->and($cached)->not->toContain('hunter2')
        ->and(settings()->get('t.secret'))->toBe('hunter2-api-key')
        ->and(settings()->masked('t.secret'))->toBe('********')
        ->and(settings()->group('t'))->toBeArray();

    $this->artisan('settings:list')->doesntExpectOutputToContain('hunter2')->expectsOutputToContain('********')->assertSuccessful();
    $this->artisan('settings:get t.secret')->expectsOutput('********')->assertSuccessful();
});

it('never leaks a secret in validation errors', function () {
    app(SettingsRegistry::class)->register(
        new SettingDefinition('t.token', SettingType::Secret, '', ['min:30']),
    );

    try {
        settings()->set('t.token', 'short-secret-value');
        $this->fail('should have been rejected');
    } catch (InvalidSettingValue $e) {
        expect($e->getMessage())->not->toContain('short-secret-value');
    }
    expect(settings()->masked('t.token'))->toBeNull();
});

it('treats an undecryptable secret as unset', function () {
    defineTypes();
    settings()->set('t.secret', 'abc');
    config(['app.key' => 'base64:'.base64_encode(str_repeat('z', 32))]);
    app()->forgetInstance('encrypter');
    Facade::clearResolvedInstance('encrypter');
    settings()->flush();

    expect(settings()->get('t.secret'))->toBe('');
});
