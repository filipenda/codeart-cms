<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Auth\Mail\InvitationMail;
use Codeart\Core\Auth\UserInvitation;
use Codeart\Core\Confirmation\ConfirmationTokens;
use Codeart\Core\Confirmation\RequireConfirmation;
use Codeart\Core\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    Mail::fake();
});

it('lets an admin invite an editor with a message, without showing or logging the token', function () {
    $admin = staff('admin');

    $this->actingAs($admin, 'admin')->post('/admin/users/invite', ['name' => 'Nova Osoba', 'email' => 'nova@example.com', 'role' => 'editor', 'message' => 'Dobrodošla u tim'])
        ->assertRedirect('/admin/users/invitations');

    $invitation = UserInvitation::query()->firstOrFail();
    expect($invitation->roles)->toBe(['editor'])->and($invitation->message)->toBe('Dobrodošla u tim');
    Mail::assertSent(InvitationMail::class, fn (InvitationMail $m) => str_contains($m->render(), 'Dobrodošla u tim') && $m->hasTo('nova@example.com'));
    $log = Activity::query()->where('event', 'invitation.sent')->first();
    expect($log->properties['email'])->toBe('nova@example.com')->and($log->toJson())->not->toContain($invitation->token_hash);
    $html = $this->actingAs($admin, 'admin')->get('/admin/users/invitations')->assertOk()->getContent();
    expect($html)->toContain('nova@example.com')->not->toContain($invitation->token_hash);
});

it('offers only roles below the actor and refuses others, also by direct POST', function () {
    $admin = staff('admin');
    $xp = dom($this->actingAs($admin, 'admin')->get('/admin/users/invite')->assertOk()->getContent());

    expect(count_nodes($xp, '//select[@name="role"]/option[@value="editor"]'))->toBe(1)
        ->and(count_nodes($xp, '//select[@name="role"]/option[@value="admin"]'))->toBe(0)
        ->and(count_nodes($xp, '//select[@name="role"]/option[@value="super-admin"]'))->toBe(0);
    foreach (['admin', 'super-admin'] as $role) {
        $this->actingAs($admin, 'admin')->post('/admin/users/invite', ['name' => 'X', 'email' => "{$role}@example.com", 'role' => $role])->assertSessionHasErrors('role');
    }
    expect(UserInvitation::query()->count())->toBe(0)
        ->and(count_nodes(dom($this->actingAs(staff('super-admin'), 'admin')->get('/admin/users/invite')->getContent()), '//select[@name="role"]/option[@value="admin"]'))->toBe(1);
});

it('denies the invitation screens and routes to editors and members', function () {
    $invitation = UserInvitation::query()->create(['name' => 'A', 'email' => 'a@example.com', 'roles' => ['member'], 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDay()]);
    foreach (['editor', 'member'] as $role) {
        $actor = staff($role);
        $this->actingAs($actor, 'admin')->get('/admin/users/invitations')->assertForbidden();
        $this->actingAs($actor, 'admin')->get('/admin/users/invite')->assertForbidden();
        $this->actingAs($actor, 'admin')->post('/admin/users/invite', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'member'])->assertForbidden();
        $this->actingAs($actor, 'admin')->post("/admin/users/invitations/{$invitation->id}/resend")->assertForbidden();
    }
    expect(UserInvitation::query()->count())->toBe(1);
});

it('resends with a new token and revokes with confirmation, logging both', function () {
    $admin = staff('admin');
    $invitation = UserInvitation::query()->create(['name' => 'A', 'email' => 'a@example.com', 'roles' => ['member'], 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDay()]);

    $this->actingAs($admin, 'admin')->post("/admin/users/invitations/{$invitation->id}/resend")->assertRedirect();
    $new = UserInvitation::query()->firstOrFail();
    expect($new->token_hash)->not->toBe(str_repeat('a', 64))->and(UserInvitation::query()->count())->toBe(1);
    Mail::assertSent(InvitationMail::class, 1);

    $path = "/admin/users/invitations/{$new->id}";
    $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($admin, 'admin')->delete($path)->assertRedirect(); // no token
    expect(UserInvitation::query()->count())->toBe(1);
    $token = app(ConfirmationTokens::class)->issue($admin, RequireConfirmation::scope('DELETE', $path));
    $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($admin, 'admin')->delete($path, ['confirmation' => $token])->assertRedirect();
    expect(UserInvitation::query()->count())->toBe(0)
        ->and(Activity::query()->where('event', 'invitation.revoked')->first()->properties['email'])->toBe('a@example.com');
});

it('does not let an admin resend or revoke an invitation for a role above theirs', function () {
    $admin = staff('admin');
    $invitation = UserInvitation::query()->create(['name' => 'A', 'email' => 'a@example.com', 'roles' => ['admin'], 'token_hash' => str_repeat('b', 64), 'expires_at' => now()->addDay()]);
    $path = "/admin/users/invitations/{$invitation->id}";
    $token = app(ConfirmationTokens::class)->issue($admin, RequireConfirmation::scope('DELETE', $path));

    $this->actingAs($admin, 'admin')->post("{$path}/resend")->assertForbidden();
    $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($admin, 'admin')->delete($path, ['confirmation' => $token])->assertForbidden();
    expect(UserInvitation::query()->count())->toBe(1);
});

it('throttles sending invitations', function () {
    $admin = staff('admin');
    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($admin, 'admin')->post('/admin/users/invite', ['name' => 'X', 'email' => "p{$i}@example.com", 'role' => 'member'])->assertRedirect();
    }
    $this->actingAs($admin, 'admin')->post('/admin/users/invite', ['name' => 'X', 'email' => 'last@example.com', 'role' => 'member'])->assertStatus(429);
});

it('finds invitations by translated state and role', function () {
    $admin = staff('admin');
    UserInvitation::query()->create(['name' => 'Old', 'email' => 'old@example.com', 'roles' => ['member'], 'token_hash' => str_repeat('c', 64), 'expires_at' => now()->subDay()]);
    UserInvitation::query()->create(['name' => 'Fresh', 'email' => 'fresh@example.com', 'roles' => ['editor'], 'token_hash' => str_repeat('d', 64), 'expires_at' => now()->addDay()]);
    $rows = '//div[@data-table="user-invitations"]//tbody/tr';
    $get = fn (string $q) => dom($this->actingAs($admin, 'admin')->get('/admin/users/invitations'.$q)->getContent());

    expect(count_nodes($get('?q=Isteklo'), $rows))->toBe(1)->and(count_nodes($get('?q=Urednik'), $rows))->toBe(1);
});

it('shows invite and quick action to those who may invite', function () {
    $xp = dom($this->actingAs(staff('admin'), 'admin')->get('/admin')->getContent());
    expect(count_nodes($xp, '//a[@href="'.url('/admin/users/invite').'"]'))->toBeGreaterThan(0);
    $xp = dom($this->actingAs(staff('editor'), 'admin')->get('/admin')->getContent());
    expect(count_nodes($xp, '//a[@href="'.url('/admin/users/invite').'"]'))->toBe(0);
});
