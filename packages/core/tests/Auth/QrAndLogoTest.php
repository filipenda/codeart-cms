<?php

use Codeart\Core\Auth\TwoFactorQrCode;
use Codeart\Core\Settings\Settings;

require_once __DIR__.'/../Support/dom.php';

/** The 2FA setup screen in its "confirm" state (QR code visible). */
function qrScreen($test): string
{
    $user = staff('editor');
    $test->actingAs($user, 'admin')->withSession(['auth.password_confirmed_at' => time()]);
    $test->from('/admin/two-factor-setup')->post('/admin/user/two-factor-authentication');

    return $test->get('/admin/two-factor-setup')->assertOk()->getContent();
}

it('draws the QR code with the QR tokens, a 4-module quiet zone and no theme colors', function (string $mode) {
    app(Settings::class)->set('theme.mode', $mode);
    $page = qrScreen($this);
    $xp = dom($page);

    expect($page)->toContain('data-theme="'.$mode.'"')
        ->and(attr($xp, '//*[@role="img"]', 'class'))->toContain('bg-qr-bg')->not->toContain('bg-surface')
        ->and(attr($xp, '//*[@role="img"]', 'style'))->toContain('color-scheme: only light');

    $svg = $xp->query('//*[@role="img"]//svg')->item(0);
    [, , $w, $h] = array_map('floatval', explode(' ', $svg->getAttribute('viewbox')));
    $translate = attr($xp, '//*[@role="img"]//svg/g', 'transform');
    $rect = $xp->query('//*[@role="img"]//svg/rect')->item(0);
    $path = $xp->query('//*[@role="img"]//svg/g/path')->item(0);

    expect($w)->toBe($h)
        ->and($translate)->toBe('translate('.TwoFactorQrCode::QUIET_ZONE.','.TwoFactorQrCode::QUIET_ZONE.')')
        ->and(TwoFactorQrCode::QUIET_ZONE)->toBeGreaterThanOrEqual(4)
        ->and($rect->getAttribute('style'))->toBe('fill:var(--color-qr-bg)')
        ->and($path->getAttribute('style'))->toBe('fill:var(--color-qr-fg)')
        ->and($rect->getAttribute('width'))->toBe((string) (int) $w);

    // The modules never reach into the quiet zone: every coordinate stays inside 0..(size - 8).
    preg_match_all('/[ML](\d+) (\d+)/', $path->getAttribute('d'), $m);
    expect(max($m[1]))->toBeLessThanOrEqual($w - 2 * TwoFactorQrCode::QUIET_ZONE)
        ->and(max($m[2]))->toBeLessThanOrEqual($h - 2 * TwoFactorQrCode::QUIET_ZONE)
        ->and($page)->not->toContain('#2d3748')->not->toContain('#ffffff');
})->with(['light', 'dark', 'auto']);

it('has the QR tokens, identical in light, dark and auto, and nowhere else', function () {
    $css = (string) file_get_contents(__DIR__.'/../../resources/css/tokens.css');
    [$light, $dark] = explode('[data-theme="dark"]', $css, 2);
    [$darkOnly, $auto] = explode('prefers-color-scheme: dark', $dark, 2);

    $values = fn (string $block) => [
        preg_match('/--color-qr-fg:\s*([^;]+);/', $block, $fg) ? trim($fg[1]) : null,
        preg_match('/--color-qr-bg:\s*([^;]+);/', $block, $bg) ? trim($bg[1]) : null,
    ];

    expect($values($light))->toBe(['#000000', '#ffffff'])
        ->and($values($darkOnly))->toBe($values($light))
        ->and($values($auto))->toBe($values($light));
});

it('ships the QR utilities in the built CSS', function () {
    $css = (string) file_get_contents(__DIR__.'/../../resources/dist/codeart.css');

    expect($css)->toContain('.bg-qr-bg')->toContain('--color-qr-fg')->toContain('--color-qr-bg');
});

it('refuses an unexpected QR svg instead of showing a broken code', function () {
    TwoFactorQrCode::withQuietZone('<svg></svg>');
})->throws(RuntimeException::class);

// ---------------------------------------------------------------------------------------------------------- logo

it('renders the logo block on every auth screen, with and without uploaded logos', function (bool $uploaded) {
    if ($uploaded) {
        app(Settings::class)->set('theme.logo_light', 'branding/light.png');
        app(Settings::class)->set('theme.logo_dark', 'branding/dark.png');
    }

    $logo = function (string $html) use ($uploaded) {
        $xp = dom($html);

        return $uploaded
            ? count_nodes($xp, '//main//img[contains(@class,"codeart-logo-light")]') === 1 && count_nodes($xp, '//main//img[contains(@class,"codeart-logo-dark")]') === 1
            : count_nodes($xp, '//main//span[contains(@class,"tracking-")]') === 1;
    };

    foreach (['/admin/login', '/admin/forgot-password'] as $url) {
        expect($logo($this->get($url)->assertOk()->getContent()))->toBeTrue($url);
    }

    $this->actingAs(staff('editor'), 'admin')->withSession(['auth.password_confirmed_at' => time()]);
    foreach ([route('password.confirm'), '/admin/two-factor-setup'] as $url) {
        expect($logo($this->get($url)->assertOk()->getContent()))->toBeTrue($url);
    }
    $this->from('/admin/two-factor-setup')->post('/admin/user/two-factor-authentication');
    expect($logo($this->get('/admin/two-factor-setup')->getContent()))->toBeTrue('setup (QR state)');
})->with([false, true]);

it('builds every auth screen on the card layout that carries the logo', function () {
    $card = (string) file_get_contents(__DIR__.'/../../resources/views/components/card.blade.php');
    $layout = (string) file_get_contents(__DIR__.'/../../resources/views/components/auth-layout.blade.php');

    expect($card)->toContain('<x-codeart::logo')->and($layout)->toContain('<x-codeart::card');
    foreach (glob(__DIR__.'/../../resources/views/auth/*.blade.php') as $view) {
        expect(str_contains((string) file_get_contents($view), '<x-codeart::auth-layout'))->toBeTrue(basename($view));
    }
});

it('links uploaded logos root-relative, so they load under any host (not only APP_URL)', function () {
    config(['app.url' => 'http://localhost']);
    fake_disk('public');
    app(Settings::class)->set('theme.logo_light', 'branding/light.png');

    $src = attr(dom($this->get('/admin/login')->getContent()), '//main//img', 'src');

    expect($src)->toBe('/storage/branding/light.png')->not->toContain('localhost');
});
