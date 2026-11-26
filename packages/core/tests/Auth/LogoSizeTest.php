<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Settings\Settings;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
});

/** A px value from a token such as "2.25rem" (1rem = 16px). */
function tokenPx(string $name): float
{
    $tokens = (string) file_get_contents(__DIR__.'/../../resources/css/tokens.css');
    preg_match('/'.preg_quote($name, '/').':\s*([\d.]+)rem/', $tokens, $m);

    return (float) $m[1] * 16;
}

it('keeps the sidebar logo 32-36px tall, set by one token, with width auto and a capped width', function () {
    app(Settings::class)->set('theme.logo_light', 'branding/light.png');
    $html = $this->actingAs(staff('editor'), 'admin')->get('/admin')->assertOk()->getContent();
    $xp = dom($html);
    $logo = attr($xp, '//aside//a[not(@data-brand-mark)]//img', 'class');

    expect($logo)->toContain('h-(--logo-height)')->toContain('w-auto')->toContain('max-w-(--logo-max-width)')->toContain('object-contain')
        ->and(tokenPx('--logo-height'))->toBeGreaterThanOrEqual(32)->toBeLessThanOrEqual(36)
        ->and(tokenPx('--logo-mark-size'))->toBe(32.0);
});

it('cannot overflow the sidebar with a very wide logo, and a very tall one only gets narrower', function () {
    // Header content width: desktop sidebar 16rem - 2 * 1rem padding = 224px; drawer 18rem - padding - close button (2.5rem) - gap (0.5rem) = 208px.
    $widest = min(224, 208);

    expect(tokenPx('--logo-max-width'))->toBeLessThanOrEqual($widest)
        // The image keeps its aspect ratio (object-contain, width auto): a wide logo scales down inside the cap, a tall one is as high as
        // the token and as narrow as its ratio says; both stay inside the 4rem (64px) header.
        ->and(tokenPx('--logo-height'))->toBeLessThan(64);

    $css = (string) file_get_contents(__DIR__.'/../../resources/dist/codeart.css');
    expect($css)->toContain('--logo-height:2.25rem')->toContain('--logo-max-width:12.5rem')->toContain('--logo-mark-size:2rem')
        ->and($css)->toContain('height:var(--logo-height)')->toContain('max-width:var(--logo-max-width)');
});

it('renders the collapsed brand mark at the mark size, for a favicon and for the letter tile', function () {
    $user = staff('editor');

    $tile = dom($this->actingAs($user, 'admin')->get('/admin')->getContent());
    expect(attr($tile, '//a[@data-brand-mark]//span', 'class'))->toContain('size-(--logo-mark-size)');

    app(Settings::class)->set('theme.favicon', 'branding/fav.png');
    $icon = dom($this->actingAs($user, 'admin')->get('/admin')->getContent());
    expect(attr($icon, '//a[@data-brand-mark]//img', 'class'))->toContain('size-(--logo-mark-size)')->toContain('object-contain');
});
