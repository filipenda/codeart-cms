<?php

use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Theme\Color;
use Codeart\Core\Theme\ThemeSettings;
use Illuminate\Support\Facades\Blade;

function theme(): ThemeSettings
{
    app()->forgetInstance(ThemeSettings::class);

    return app(ThemeSettings::class);
}

it('emits no overrides for the brand defaults', function () {
    expect(theme()->overrides())->toBe(['light' => [], 'dark' => []])
        ->and(theme()->css())->toBe('');
});

it('reads colors from the database settings', function () {
    app(Settings::class)->set('theme.primary', '#1A237E');

    expect(theme()->overrides()['light']['--color-primary'])->toBe('#1a237e')
        ->and(theme()->css())->toContain(':root:root{--color-primary:#1a237e;');
});

it('falls back to the config defaults and follows config changes', function () {
    config(['codeart.theme.primary' => '#ff5722']);

    expect(theme()->overrides()['light']['--color-primary'])->toBe('#ff5722');

    app(Settings::class)->set('theme.primary', '#00bfa5');
    expect(theme()->overrides()['light']['--color-primary'])->toBe('#00bfa5');
});

it('picks readable text and link colors by contrast for any primary', function (string $primary) {
    app(Settings::class)->set('theme.primary', $primary);
    $o = theme()->overrides();
    $min = config('codeart.theme_base.min_contrast');

    expect(Color::contrast($o['light']['--color-on-primary'], $o['light']['--color-primary']))->toBeGreaterThanOrEqual($min)
        ->and(Color::contrast($o['light']['--color-link'], config('codeart.theme_base.surface_light')))->toBeGreaterThanOrEqual($min)
        ->and(Color::contrast($o['dark']['--color-link'], config('codeart.theme_base.surface_dark')))->toBeGreaterThanOrEqual($min);
})->with(['#1a237e', '#ff5722', '#ffeb3b', '#00bfa5', '#880e4f', '#fafafa', '#ffffff', '#050505', '#000000']);

it('uses dark text on a very light primary and light text on a very dark one', function () {
    $settings = app(Settings::class);
    $settings->set('theme.primary', '#fefefe');
    expect(theme()->overrides()['light']['--color-on-primary'])->toBe(config('codeart.theme_base.ink'));

    $settings->set('theme.primary', '#020202');
    expect(theme()->overrides()['light']['--color-on-primary'])->toBe(config('codeart.theme_base.paper'));
});

it('derives the secondary text color too', function () {
    app(Settings::class)->set('theme.secondary', '#101010');

    expect(theme()->overrides()['light']['--color-on-secondary'])->toBe(config('codeart.theme_base.paper'));
});

it('renders the inline style partial with overrides, including the auto-mode dark block', function () {
    app(Settings::class)->set('theme.primary', '#1a237e');
    theme();

    $html = Blade::render("@include('codeart::partials.theme-style')");

    expect($html)->toContain('<style>:root:root{--color-primary:#1a237e;')
        ->toContain('--color-on-primary:#ffffff;')
        ->toContain(':root[data-theme="dark"]{')
        ->toContain('@media (prefers-color-scheme:dark){:root[data-theme="auto"]{--color-primary:#1a237e;');
});

it('renders nothing for default settings', function () {
    theme();

    expect(trim(Blade::render("@include('codeart::partials.theme-style')")))->toBe('');
});

it('caches the generated css and picks up a changed setting', function () {
    app(Settings::class)->set('theme.primary', '#1a237e');
    $first = theme()->css();
    expect(theme()->css())->toBe($first);

    app(Settings::class)->set('theme.primary', '#880e4f');
    expect(theme()->css())->not->toBe($first)->toContain('#880e4f');
});

it('ignores a broken stored color', function () {
    config(['codeart.theme.primary' => 'javascript:alert(1)', 'codeart.theme.secondary' => 'red']);

    expect(theme()->css())->toBe('');
});

it('renders the theme mode in the html attribute: light, dark, auto', function (string $mode) {
    app(Settings::class)->set('theme.mode', $mode);
    theme();

    expect(Blade::render("<html @include('codeart::partials.theme-attributes')>"))->toBe('<html data-theme="'.$mode.'">');
})->with(['light', 'dark', 'auto']);

it('defaults the mode to light and rejects unknown modes', function () {
    expect(theme()->mode())->toBe('light')
        ->and(fn () => app(Settings::class)->set('theme.mode', 'sepia'))->toThrow(InvalidSettingValue::class);
});

it('makes auto mode follow the system preference in the shipped css', function () {
    $tokens = file_get_contents(__DIR__.'/../../../resources/css/tokens.css');
    $built = file_get_contents(__DIR__.'/../../../resources/dist/codeart.css');

    expect($tokens)->toContain('@media (prefers-color-scheme: dark)')->toContain(':root[data-theme="auto"]')
        ->and($built)->toContain('prefers-color-scheme')->toContain('[data-theme=auto]');
});

it('exposes the image paths', function () {
    $settings = app(Settings::class);
    $settings->set('theme.logo_light', 'branding/a.png');

    expect(theme()->logoLight())->toBe('branding/a.png')->and(theme()->logoDark())->toBeNull()->and(theme()->favicon())->toBeNull();
});
