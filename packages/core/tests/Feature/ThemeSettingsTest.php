<?php

use Codeart\Core\Theme\Color;
use Codeart\Core\Theme\ThemeSettings;
use Illuminate\Support\Facades\Blade;

it('emits no overrides for the brand defaults', function () {
    $settings = app(ThemeSettings::class);

    expect($settings->overrides())->toBe(['light' => [], 'dark' => []])
        ->and($settings->css())->toBe('');
});

it('derives readable shades from any client primary', function (string $primary) {
    $settings = new ThemeSettings(
        ['primary' => $primary],
        config('codeart.theme_base'),
        config('codeart.theme_defaults'),
    );
    $o = $settings->overrides();
    $min = config('codeart.theme_base.min_contrast');

    expect(Color::contrast($o['light']['--color-on-primary'], $o['light']['--color-primary']))->toBeGreaterThanOrEqual($min)
        ->and(Color::contrast($o['light']['--color-link'], config('codeart.theme_base.surface_light')))->toBeGreaterThanOrEqual($min)
        ->and(Color::contrast($o['dark']['--color-link'], config('codeart.theme_base.surface_dark')))->toBeGreaterThanOrEqual($min);
})->with(['#1a237e', '#ff5722', '#ffeb3b', '#00bfa5', '#880e4f', '#fafafa']);

it('ignores invalid colors', function () {
    $settings = new ThemeSettings(['primary' => 'javascript:alert(1)', 'secondary' => 'red'], config('codeart.theme_base'));

    expect($settings->css())->toBe('');
});

it('renders the inline style partial with overrides', function () {
    config(['codeart.theme.primary' => '#1a237e']);
    app()->forgetInstance(ThemeSettings::class);

    $html = Blade::render("@include('codeart::partials.theme-style')");

    expect($html)->toContain('<style>:root:root{--color-primary:#1a237e;')
        ->toContain('--color-on-primary:#ffffff;')
        ->toContain(':root[data-theme="dark"]{');
});

it('renders nothing for default settings', function () {
    expect(trim(Blade::render("@include('codeart::partials.theme-style')")))->toBe('');
});

it('caches the generated css per settings hash', function () {
    $settings = new ThemeSettings(['primary' => '#1a237e'], config('codeart.theme_base'), config('codeart.theme_defaults'));
    $first = $settings->css();

    expect($settings->css())->toBe($first);
});

it('exposes default mode and logo', function () {
    $s = new ThemeSettings(['default_mode' => 'dark', 'logo' => 'logo.svg'], []);

    expect($s->defaultMode())->toBe('dark')->and($s->logo())->toBe('logo.svg');
});
