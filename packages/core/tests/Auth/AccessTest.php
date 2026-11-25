<?php

use Codeart\Core\Auth\Mail\VerifyEmailMail;
use Codeart\Core\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => app(Settings::class)->set('security.2fa.mode', 'off'));

it('lets admin and editor into the admin, and super-admin through the Gate', function (string $role) {
    $this->actingAs(staff($role), 'admin')->getJson('/admin/access')->assertNoContent();
})->with(['super-admin', 'admin', 'editor']);

it('keeps the member role out', function () {
    $this->actingAs(staff('member'), 'admin')->getJson('/admin/access')
        ->assertForbidden()->assertJson(['reason' => 'forbidden']);
});

it('keeps a user without any role out', function () {
    $user = staff('editor');
    $user->syncRoles([]);

    $this->actingAs($user->fresh(), 'admin')->getJson('/admin/access')->assertForbidden();
});

it('answers 401 for guests and 403 for signed-in users without access, also for browsers', function () {
    $this->getJson('/admin/access')->assertUnauthorized();
    $this->actingAs(staff('member'), 'admin')->get('/admin/access')->assertForbidden();
});

it('refuses inactive, unapproved and unverified users, with a reason', function (array $attributes, string $reason) {
    $this->actingAs(staff('admin', $attributes), 'admin')->getJson('/admin/access')
        ->assertForbidden()->assertJson(['reason' => $reason]);
})->with([
    'inactive' => [['is_active' => false], 'inactive'],
    'unapproved' => [['approved_at' => null], 'unapproved'],
    'unverified' => [['email_verified_at' => null], 'unverified'],
]);

it('only asks for approval and verification while those are required', function () {
    $settings = app(Settings::class);
    $user = staff('admin', ['approved_at' => null, 'email_verified_at' => null]);
    $this->actingAs($user, 'admin');

    $this->getJson('/admin/access')->assertForbidden();
    $settings->set('auth.registration.require_approval', false);
    $this->getJson('/admin/access')->assertForbidden()->assertJson(['reason' => 'unverified']);
    $settings->set('auth.registration.require_email_verification', false);
    $this->getJson('/admin/access')->assertNoContent();
});

it('never lets an inactive super-admin through the Gate', function () {
    $this->actingAs(staff('super-admin', ['is_active' => false]), 'admin')->getJson('/admin/access')->assertForbidden();
});

it('reports the state to the screens', function () {
    $user = staff('editor', ['locale' => 'sr', 'theme_preference' => 'dark']);

    $this->actingAs($user, 'admin')->getJson('/admin/session')->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.locale', 'sr')
        ->assertJsonPath('user.theme_preference', 'dark')
        ->assertJsonPath('user.roles.0', 'editor')
        ->assertJsonPath('can_access_admin', true)
        ->assertJsonPath('two_factor.available', false)
        ->assertJsonMissingPath('user.password');
    expect(array_keys(staff('editor')->toArray()))->not->toContain('password')->not->toContain('two_factor_secret');
});

it('sends the verification e-mail and verifies through the signed link', function () {
    Mail::fake();
    $user = staff('editor', ['email_verified_at' => null]);
    $this->actingAs($user, 'admin');

    $this->getJson('/admin/access')->assertForbidden()->assertJson(['reason' => 'unverified']);
    $this->postJson('/admin/email/verification-notification')->assertStatus(202);
    Mail::assertSent(VerifyEmailMail::class, fn ($m) => $m->hasTo($user->email));

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->getJson($url)->assertNoContent();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->getJson('/admin/access')->assertNoContent();
});

it('refuses a verification link that is unsigned, tampered or for another user', function () {
    $user = staff('editor', ['email_verified_at' => null]);
    $this->actingAs($user, 'admin');

    $this->getJson("/admin/email/verify/{$user->id}/".sha1($user->email))->assertForbidden(); // not signed
    $wrongHash = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('other@example.com')]);
    $this->getJson($wrongHash)->assertForbidden();
    $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->getJson($expired)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
