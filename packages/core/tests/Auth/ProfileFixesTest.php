<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Admin\Navigation\AdminMenu;
use Codeart\Core\Settings\Settings;
use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'optional');
});

it('lets the dialog title and body wrap and never scroll sideways', function () {
    $html = Blade::render('<x-codeart::modal id="m1" title="A very long title">body</x-codeart::modal>');
    preg_match('/<dialog[^>]*class="([^"]*)"/', $html, $m);
    $dialog = $m[1] ?? '';
    expect($dialog)->toContain('whitespace-normal')->toContain('break-words')->toContain('overflow-x-hidden')->toContain('min-w-0')->toContain('max-w-lg')->toContain('w-[calc(100%-2rem)]')
        ->not->toContain('whitespace-nowrap')->not->toContain('overflow-x-auto')->not->toContain('overflow-x-scroll');
    expect($html)->toMatch('/<h2[^>]*class="[^"]*break-words/')->toMatch('/data-modal-body/');
    expect($html)->not->toMatch('/class="[^"]*whitespace-nowrap/');
});

it('keeps the confirm dialog wrapped even inside a nowrap table cell', function () {
    $html = $this->actingAs(staff('admin'), 'admin')->get('/admin/users')->assertOk()->getContent();
    expect($html)->toContain('data-modal-body');
    preg_match_all('/<dialog[^>]*class="([^"]*)"/', $html, $all);
    foreach ($all[1] as $classes) {
        expect($classes)->toContain('whitespace-normal');
    }
});

it('marks the menu item active by route-name patterns', function () {
    expect(AdminMenu::isActive('admin.profile', 'admin.profile.password.show', ['admin.profile', 'admin.profile.*']))->toBeTrue()
        ->and(AdminMenu::isActive('admin.profile', 'admin.profile.password.show'))->toBeFalse()
        ->and(AdminMenu::isActive('admin.profile', 'admin.dashboard', ['admin.profile.*']))->toBeFalse()
        ->and(AdminMenu::isActive('admin.users.index', 'admin.users.edit'))->toBeTrue()
        ->and(AdminMenu::isActive('admin.users.index', 'admin.users.invitations', ['admin.users.*']))->toBeTrue();
});

it('keeps "Moj profil" active on every profile tab and "Korisnici" on the users routes', function () {
    $me = staff('admin');
    foreach (['/admin/profile', '/admin/profile/password', '/admin/profile/two-factor'] as $path) {
        $xp = dom($this->actingAs($me, 'admin')->get($path)->assertOk()->getContent());
        expect(attr($xp, '//aside//nav//a[@aria-current="page"]', 'href'))->toBe(url('/admin/profile'));
    }
    foreach (['/admin/users', '/admin/users/invitations', '/admin/users/invite'] as $path) {
        $xp = dom($this->actingAs($me, 'admin')->get($path)->assertOk()->getContent());
        expect(attr($xp, '//aside//nav//a[@aria-current="page"]', 'href'))->toBe(url('/admin/users'));
    }
});

it('draws the profile card header from primary tokens: gradient, glows, three waves, ring', function () {
    $html = $this->actingAs(staff('editor'), 'admin')->get('/admin/profile')->getContent();
    preg_match('/<div[^>]*data-profile-banner.*?<\/svg>/s', $html, $m);
    $banner = $m[0] ?? '';
    expect($banner)->toContain('radial-gradient')->toContain('linear-gradient')->toContain('var(--color-primary)')
        ->and(substr_count($banner, '<path'))->toBe(3)
        ->and(substr_count($banner, 'fill-primary'))->toBe(3)
        ->and($banner)->not->toMatch('/#[0-9a-f]{3,8}\b|rgb\(|hsl\(|<img|\.(png|jpe?g|webp|gif|svg)/i')
        ->and($html)->toContain('ring-4');
});

it('gives the tab forms a card header with description and a footer with the buttons', function () {
    $xp = dom($this->actingAs(staff('editor'), 'admin')->get('/admin/profile/password')->getContent());
    expect(count_nodes($xp, '//form[@data-password-form]//header[@data-card-header]//*[@data-card-description]'))->toBe(1)
        ->and(count_nodes($xp, '//form[@data-password-form]//footer[@data-card-footer]//button'))->toBe(1)
        ->and(count_nodes($xp, '//form[@data-password-form]//*[contains(@class,"max-w-xl")]'))->toBe(0);
});
