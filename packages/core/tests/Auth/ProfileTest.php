<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Settings\Settings;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
});

it('is available to every user who may enter the admin, with menu entry and user-menu link', function (string $role) {
    $html = $this->actingAs(staff($role), 'admin')->get('/admin/profile')->assertOk()->getContent();
    $xp = dom($html);

    expect(count_nodes($xp, '//form[@data-profile-form]'))->toBe(1)
        ->and(count_nodes($xp, '//a[@data-profile-link]'))->toBe(1)
        ->and(count_nodes($xp, '//nav//a[@href="'.url('/admin/profile').'"]'))->toBeGreaterThan(0);
})->with(['super-admin', 'admin', 'editor']);

it('keeps the profile away from guests and from users who may not enter the admin', function () {
    $this->get('/admin/profile')->assertRedirect();
    $this->actingAs(staff('member'), 'admin')->get('/admin/profile')->assertStatus(403);
});

it('changes only name, language and theme, and only of the signed-in user (mass assignment safe)', function () {
    $me = staff('editor', ['name' => 'Old', 'email' => 'me@example.com']);
    $other = staff('member', ['name' => 'Other One']);

    $this->actingAs($me, 'admin')->put('/admin/profile', [
        'name' => 'New Name', 'locale' => 'en', 'theme_preference' => 'dark',
        'email' => 'evil@example.com', 'role' => 'super-admin', 'is_active' => 0, 'id' => $other->id, 'approved_at' => null,
    ])->assertRedirect('/admin/profile');

    $fresh = $me->fresh();
    expect($fresh->name)->toBe('New Name')->and($fresh->locale)->toBe('en')->and($fresh->theme_preference)->toBe('dark')
        ->and($fresh->email)->toBe('me@example.com')->and($fresh->hasRole('editor'))->toBeTrue()->and($fresh->hasRole('super-admin'))->toBeFalse()
        ->and($fresh->is_active)->toBeTrue()->and($fresh->approved_at)->not->toBeNull()
        ->and($other->fresh()->name)->toBe('Other One');
    $log = Activity::query()->where('event', 'profile.updated')->first();
    expect($log->properties['old']['name'])->toBe('Old')->and($log->properties['new']['name'])->toBe('New Name');
});

it('validates the profile', function () {
    $me = staff('editor');
    $this->actingAs($me, 'admin')->put('/admin/profile', ['name' => '', 'locale' => 'xx', 'theme_preference' => 'neon'])->assertSessionHasErrors(['name', 'locale', 'theme_preference']);
});

it('saves the theme from the user menu and starts the next page with it', function () {
    $me = staff('editor');
    $this->actingAs($me, 'admin')->postJson('/admin/profile/theme', ['theme' => 'dark'])->assertOk()->assertJson(['theme' => 'dark']);
    $this->actingAs($me, 'admin')->postJson('/admin/profile/theme', ['theme' => 'neon'])->assertStatus(422);

    $xp = dom($this->actingAs($me->fresh(), 'admin')->get('/admin')->getContent());
    expect($me->fresh()->theme_preference)->toBe('dark')
        ->and(attr($xp, '//html', 'data-theme'))->toBe('dark')
        ->and(attr($xp, '//body', 'data-theme-user'))->toBe('dark')
        ->and(attr($xp, '//body', 'data-theme-url'))->toBe(url('/admin/profile/theme'));
    // nothing saved: the site default applies and the browser's own memory stays in charge
    $fresh = dom($this->actingAs(staff('editor'), 'admin')->get('/admin')->getContent());
    expect(attr($fresh, '//body', 'data-theme-user'))->toBeNull();
});

it('changes the password only with the current one and the configured rules', function () {
    $me = staff('editor', ['password' => 'Secret-pass-123']);

    $this->actingAs($me, 'admin')->put('/admin/profile/password', ['current_password' => 'wrong', 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'])->assertSessionHasErrorsIn('updatePassword', 'current_password');
    $this->actingAs($me, 'admin')->put('/admin/profile/password', ['current_password' => 'Secret-pass-123', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrorsIn('updatePassword', 'password');
    expect(Hash::check('Secret-pass-123', $me->fresh()->password))->toBeTrue();

    $this->actingAs($me, 'admin')->put('/admin/profile/password', ['current_password' => 'Secret-pass-123', 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'])->assertRedirect('/admin/profile/password');
    expect(Hash::check('Brand-new-pass-7', $me->fresh()->password))->toBeTrue()
        ->and(Activity::query()->where('event', 'password.changed')->count())->toBe(1)
        ->and(Activity::query()->get()->toJson())->not->toContain('Brand-new-pass-7')->not->toContain('Secret-pass-123');
});

it('shows the two-factor state and lets people switch it off only where it is not required', function () {
    $settings = app(Settings::class);
    $on = ['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()];

    $settings->set('security.2fa.mode', 'off');
    expect(count_nodes(dom($this->actingAs(staff('editor'), 'admin')->get('/admin/profile/two-factor')->getContent()), '//*[@data-two-factor]'))->toBe(0);

    $settings->set('security.2fa.mode', 'optional');
    $xp = dom($this->actingAs(staff('editor'), 'admin')->get('/admin/profile/two-factor')->getContent());
    expect(count_nodes($xp, '//*[@data-two-factor]//a[contains(@href,"two-factor-setup")]'))->toBe(1)->and(count_nodes($xp, '//button[@data-disable]'))->toBe(0);

    $xp = dom($this->actingAs(staff('editor', $on), 'admin')->get('/admin/profile/two-factor')->getContent());
    expect(count_nodes($xp, '//button[@data-regenerate]'))->toBe(1)->and(count_nodes($xp, '//button[@data-disable]'))->toBe(1);

    $settings->set('security.2fa.mode', 'required');
    $settings->set('security.2fa.required_roles', ['admin']);
    $xp = dom($this->actingAs(staff('admin', $on), 'admin')->get('/admin/profile/two-factor')->getContent());
    expect(count_nodes($xp, '//button[@data-disable]'))->toBe(0)->and(count_nodes($xp, '//*[@data-required-note]'))->toBe(1);
});

it('has the profile texts in sr, en and sr-Cyrl', function () {
    foreach (['sr' => 'Moj profil', 'en' => 'My profile', 'sr-Cyrl' => 'Мој профил'] as $locale => $text) {
        expect(__('codeart::profile.title', [], $locale))->toBe($text);
    }
});
