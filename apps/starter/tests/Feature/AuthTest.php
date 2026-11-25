<?php

use App\Models\User;
use Codeart\Core\Permissions\CreateDefaultRoles;
use Codeart\Core\Settings\Settings;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    app(CreateDefaultRoles::class)();
    app(Settings::class)->flush();
});

it('uses the admin guard and the staff user model', function () {
    expect(config('auth.defaults.guard'))->toBe('admin')
        ->and(config('auth.guards.admin.provider'))->toBe('users')
        ->and(Route::has('login.store'))->toBeTrue()
        ->and(Route::has('admin.session'))->toBeTrue()
        ->and(Route::has('admin.invitation.show'))->toBeTrue();
});

it('signs an editor in and lets them into the admin', function () {
    $editor = User::factory()->create(['password' => 'Secret-pass-123']);
    $editor->assignRole('editor');

    $this->postJson('/admin/login', ['email' => $editor->email, 'password' => 'Secret-pass-123'])->assertOk();
    $this->getJson('/admin/access')->assertNoContent();
    expect($editor->fresh()->canAccessAdmin())->toBeTrue()->and($editor->fresh()->last_login_at)->not->toBeNull();
});

it('asks an admin for two-factor setup before anything else', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin, 'admin')->getJson('/admin/access')->assertForbidden()->assertJson(['reason' => 'two_factor_required']);
});

it('keeps members out of the admin', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $this->actingAs($member, 'admin')->getJson('/admin/access')->assertForbidden()->assertJson(['reason' => 'forbidden']);
});
