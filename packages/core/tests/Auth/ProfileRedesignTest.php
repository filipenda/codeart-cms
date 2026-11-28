<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Profile\Avatar;
use Codeart\Core\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    Storage::fake('public');
});

function png_upload(string $name = 'me.png', int $w = 900, int $h = 600): UploadedFile
{
    $im = imagecreatetruecolor($w, $h);
    ob_start();
    imagepng($im);
    $bytes = (string) ob_get_clean();
    $path = tempnam(sys_get_temp_dir(), 'av').'.png';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'image/png', null, true);
}

it('renders exactly three server-side tabs that route to their own pages', function () {
    $me = staff('editor');
    $routes = ['personal' => '/admin/profile', 'password' => '/admin/profile/password', 'two-factor' => '/admin/profile/two-factor'];
    foreach ($routes as $key => $path) {
        $xp = dom($this->actingAs($me, 'admin')->get($path)->assertOk()->getContent());
        $links = $xp->query('//nav[@data-profile-tabs]//a');
        expect($links->length)->toBe(3)
            ->and(attr($xp, '//nav[@data-profile-tabs]//a[@aria-current="page"]', 'data-tab'))->toBe($key)
            ->and(count_nodes($xp, '//nav[@data-profile-tabs]//a[@data-tab="'.$key.'"]'))->toBe(1);
        foreach (['personal', 'password', 'two-factor'] as $k) {
            expect(attr($xp, '//nav[@data-profile-tabs]//a[@data-tab="'.$k.'"]', 'href'))->toBe(url($routes[$k]));
        }
        expect(count_nodes($xp, '//aside[@data-profile-card]'))->toBe(1);
    }
    $xp = dom($this->actingAs($me, 'admin')->get('/admin/profile')->getContent());
    $tabs = text_of($xp, '//nav[@data-profile-tabs]');
    expect($tabs)->not->toContain('Obaveštenja')->not->toContain('Sesije')->not->toContain('Notifications')->not->toContain('Sessions')
        ->and(count_nodes($xp, '//*[@role="tab"]'))->toBe(0);
});

it('shows the profile card with role, member since, last sign-in and IP', function () {
    $me = staff('editor', ['name' => 'Mila Petrović', 'last_login_at' => now()->subHours(2), 'last_login_ip' => '203.0.113.7']);
    $xp = dom($this->actingAs($me, 'admin')->get('/admin/profile')->getContent());

    expect(text_of($xp, '//*[@data-profile-name]'))->toBe('Mila Petrović')
        ->and(text_of($xp, '//*[@data-last-ip]'))->toBe('203.0.113.7')
        ->and(text_of($xp, '//*[@data-member-since]'))->toBe($me->created_at->translatedFormat('d.m.Y.'))
        ->and(text_of($xp, '//*[@data-last-sign-in]'))->not->toBe('—')
        ->and(count_nodes($xp, '//*[@data-profile-role]'))->toBe(1)
        ->and(text_of($xp, '//*[@data-avatar-initials]'))->toBe('MP');
});

it('keeps the e-mail read-only and validates the phone', function () {
    $me = staff('editor', ['email' => 'me@example.com']);
    $xp = dom($this->actingAs($me, 'admin')->get('/admin/profile')->getContent());
    expect(attr($xp, '//input[@id="profile-email"]', 'readonly'))->not->toBeNull()->and(attr($xp, '//input[@id="profile-email"]', 'name'))->toBeNull();

    $this->actingAs($me, 'admin')->put('/admin/profile', ['name' => 'Ok', 'email' => 'evil@example.com', 'phone' => '+381 (11) 123-4567'])->assertRedirect('/admin/profile');
    expect($me->fresh()->phone)->toBe('+381 (11) 123-4567')->and($me->fresh()->email)->toBe('me@example.com');

    foreach (['abc', '12<script>', str_repeat('1', 31)] as $bad) {
        $this->actingAs($me, 'admin')->put('/admin/profile', ['name' => 'Ok', 'phone' => $bad])->assertSessionHasErrors('phone');
    }
    $this->actingAs($me, 'admin')->put('/admin/profile', ['name' => 'Ok', 'phone' => ''])->assertSessionHasNoErrors();
    expect($me->fresh()->phone)->toBeNull();
});

it('uploads an avatar fitted to 512, replaces the old file and removes it on request', function () {
    $me = staff('editor');
    $this->actingAs($me, 'admin')->post('/admin/profile/avatar', ['avatar' => png_upload()])->assertRedirect('/admin/profile');
    $first = $me->fresh()->avatar_path;
    expect($first)->toStartWith('avatars/')->and($first)->toEndWith('.png');
    Storage::disk('public')->assertExists($first);
    [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($first));
    expect(max($w, $h))->toBe(512);

    $this->actingAs($me->fresh(), 'admin')->post('/admin/profile/avatar', ['avatar' => png_upload('b.png', 100, 100)])->assertRedirect('/admin/profile');
    $second = $me->fresh()->avatar_path;
    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
    [$w] = getimagesizefromstring(Storage::disk('public')->get($second));
    expect($w)->toBe(100); // never enlarged

    $this->actingAs($me->fresh(), 'admin')->delete('/admin/profile/avatar')->assertRedirect('/admin/profile');
    expect($me->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

it('rejects svg, wrong content, disguised files and oversize pictures', function () {
    $me = staff('editor');
    $svg = UploadedFile::fake()->createWithContent('a.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    $fake = UploadedFile::fake()->createWithContent('a.png', 'not an image at all');
    $svgAsPng = UploadedFile::fake()->createWithContent('b.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    foreach ([$svg, $fake, $svgAsPng] as $file) {
        $this->actingAs($me, 'admin')->post('/admin/profile/avatar', ['avatar' => $file])->assertSessionHasErrors('avatar');
    }
    expect($me->fresh()->avatar_path)->toBeNull()->and(Storage::disk('public')->allFiles())->toBe([]);

    app(Settings::class)->set('profile.avatar_max_kb', 100);
    $big = png_upload('big.png', 1500, 1500);
    file_put_contents($big->getPathname(), file_get_contents($big->getPathname()).str_repeat("\0", 200 * 1024));
    $this->actingAs($me, 'admin')->post('/admin/profile/avatar', ['avatar' => new UploadedFile($big->getPathname(), 'big.png', 'image/png', null, true)])->assertSessionHasErrors('avatar');
    expect(app(Settings::class)->get('profile.avatar_max_kb'))->toBe(100);
});

it('has a 5 MB default limit', function () {
    expect(app(Settings::class)->get('profile.avatar_max_kb'))->toBe(5120);
});

it('only ever changes the signed-in user\'s avatar', function () {
    $me = staff('editor');
    $other = staff('admin', ['avatar_path' => 'avatars/other.png']);
    Storage::disk('public')->put('avatars/other.png', 'x');
    $this->actingAs($me, 'admin')->post('/admin/profile/avatar', ['avatar' => png_upload(), 'id' => $other->id, 'user_id' => $other->id])->assertRedirect();
    $this->actingAs($me->fresh(), 'admin')->delete('/admin/profile/avatar')->assertRedirect();
    expect($other->fresh()->avatar_path)->toBe('avatars/other.png');
    Storage::disk('public')->assertExists('avatars/other.png');
});

it('shows the avatar in the top bar, the users list and the profile card, as round object-cover images', function () {
    $admin = staff('super-admin');
    $this->actingAs($admin, 'admin')->post('/admin/profile/avatar', ['avatar' => png_upload()]);
    $url = Avatar::url($admin->fresh());
    expect($url)->toStartWith('/storage/avatars/');

    $profile = dom($this->actingAs($admin->fresh(), 'admin')->get('/admin/profile')->getContent());
    expect(count_nodes($profile, '//header//img[@data-avatar-image][@src="'.$url.'"][contains(@class,"rounded-full")][contains(@class,"object-cover")]'))->toBe(1)
        ->and(count_nodes($profile, '//aside[@data-profile-card]//img[@src="'.$url.'"][contains(@class,"rounded-full")][contains(@class,"object-cover")]'))->toBe(1)
        ->and(count_nodes($profile, '//button[@data-avatar-remove]'))->toBe(1);

    $list = dom($this->actingAs($admin->fresh(), 'admin')->get('/admin/users')->getContent());
    expect(count_nodes($list, '//td//img[@src="'.$url.'"][contains(@class,"rounded-full")][contains(@class,"object-cover")]'))->toBeGreaterThan(0);
});

it('edits only the signed-in user\'s own row', function () {
    $me = staff('editor', ['name' => 'Me']);
    $other = staff('editor', ['name' => 'Other']);
    $this->actingAs($me, 'admin')->put('/admin/profile', ['name' => 'Changed', 'phone' => '123', 'id' => $other->id])->assertRedirect();
    expect($other->fresh()->name)->toBe('Other')->and($other->fresh()->phone)->toBeNull();
});

it('builds the decorative card header from tokens only, without raster images', function () {
    $html = $this->actingAs(staff('editor'), 'admin')->get('/admin/profile')->getContent();
    preg_match('/<div[^>]*data-profile-banner.*?<\/svg>\s*<\/div>/s', $html, $m);
    $banner = $m[0] ?? '';
    expect($banner)->not->toBe('')->toContain('from-primary-soft')->toContain('fill-primary')
        ->not->toMatch('/#[0-9a-f]{3,8}\b|rgb\(|hsl\(|<img|url\(|\.(png|jpe?g|webp|gif|svg)/i');
});

it('has the profile texts in sr, en and generated sr-Cyrl', function () {
    $keys = ['tab_personal', 'tab_password', 'tab_two_factor', 'member_since', 'last_sign_in', 'ip_address', 'full_name', 'phone', 'cancel', 'save', 'avatar_remove', 'avatar_type'];
    foreach ($keys as $key) {
        foreach (['sr', 'en', 'sr-Cyrl'] as $locale) {
            expect(__('codeart::profile.'.$key, [], $locale))->not->toBe('codeart::profile.'.$key);
        }
    }
    expect(__('codeart::profile.tab_personal', [], 'sr'))->toBe('Lični podaci')->and(__('codeart::profile.tab_personal', [], 'sr-Cyrl'))->toBe('Лични подаци')
        ->and(__('codeart::profile.member_since', [], 'en'))->toBe('Member since');
});
