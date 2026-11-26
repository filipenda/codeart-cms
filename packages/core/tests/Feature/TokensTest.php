<?php

/*
 * Front-end is verified by measuring CSS text, never by screenshots.
 */

function css(string $file): string
{
    return (string) file_get_contents(__DIR__.'/../../resources/'.$file);
}

it('defines every semantic token for light and dark', function (string $token) {
    $tokens = css('css/tokens.css');
    [$light, $dark] = explode('[data-theme="dark"]', $tokens);

    expect($light)->toContain($token.':');
    if (! str_starts_with($token, '--radius') && ! str_starts_with($token, '--space') && ! str_starts_with($token, '--font')) {
        expect($dark)->toContain($token.':');
    }
})->with([
    '--color-primary', '--color-primary-hover', '--color-on-primary', '--color-secondary',
    '--color-surface', '--color-surface-alt', '--color-text', '--color-text-muted', '--color-border', '--color-link',
    '--color-success', '--color-success-bg', '--color-success-text',
    '--color-warning', '--color-warning-bg', '--color-warning-text',
    '--color-danger', '--color-danger-bg', '--color-danger-text',
    '--color-info', '--color-info-bg', '--color-info-text',
    '--sidebar-bg', '--sidebar-text', '--sidebar-muted', '--sidebar-hover', '--sidebar-border', '--color-topbar', '--color-surface-sunken', '--color-neutral', '--color-neutral-bg', '--color-neutral-text', '--radius-md', '--space-md', '--font-sans',
]);

it('uses the CodeArt brand defaults', function () {
    $tokens = css('css/tokens.css');

    expect($tokens)->toContain('--color-primary: #c9f31d')
        ->toContain('--color-on-primary: #22201f')
        ->toContain('--color-secondary: #a0bb47')
        ->toContain('--sidebar-bg: #ffffff')
        ->toContain('--color-link: #4a6210');
});

it('ships built css that maps utilities to variables and has no palette', function () {
    $built = css('dist/codeart.css');

    expect($built)->toContain('.bg-primary{background-color:var(--color-primary)}')
        ->toContain('.text-on-primary{color:var(--color-on-primary)}')
        ->not->toContain('--color-lime-')
        ->not->toContain('--color-slate-');
});
