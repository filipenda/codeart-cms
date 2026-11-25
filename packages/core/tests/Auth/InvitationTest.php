<?php

use Codeart\Core\Auth\Actions\InviteUser;
use Codeart\Core\Auth\Mail\InvitationMail;
use Codeart\Core\Auth\UserInvitation;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\Support\AdminTestUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** Invite and return the signed link from the mail that went out. */
function invite(?AdminTestUser $by, string $email = 'new@example.com', array $roles = ['editor'], string $name = 'New Person'): string
{
    Mail::fake();
    app(InviteUser::class)($name, $email, $roles, $by);
    $html = null;
    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use (&$html) {
        $html = $mail->render();

        return true;
    });
    preg_match('/href="([^"]+invitation[^"]+)"/', (string) $html, $m);

    return html_entity_decode($m[1]);
}

const NEW_PASSWORD = ['password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'];

it('invites by e-mail with a signed single-use link and stores only a hash of the token', function () {
    $url = invite(staff('admin'));

    $invitation = UserInvitation::query()->firstOrFail();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $token = basename((string) parse_url($url, PHP_URL_PATH));
    expect($invitation->token_hash)->toBe(hash('sha256', $token))->not->toBe($token)
        ->and($invitation->roles)->toBe(['editor'])
        ->and($invitation->email)->toBe('new@example.com')
        ->and($invitation->expires_at->diffInDays(now(), true))->toBeGreaterThan(6.9)->toBeLessThan(7.1)
        ->and($query)->toHaveKeys(['signature', 'expires'])
        ->and(json_encode($invitation->toArray()))->not->toContain($token);

    $this->getJson($url)->assertOk()->assertJson(['name' => 'New Person', 'email' => 'new@example.com']);
});

it('sets the password, verifies the e-mail and activates the user when accepted', function () {
    $url = invite(staff('admin'));

    $this->postJson($url, NEW_PASSWORD)->assertOk();

    $user = AdminTestUser::query()->where('email', 'new@example.com')->firstOrFail();
    expect(Hash::check('Brand-new-pass-7', $user->password))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->approved_at)->not->toBeNull()
        ->and($user->invited_at)->not->toBeNull()
        ->and($user->hasRole('editor'))->toBeTrue()
        ->and(UserInvitation::query()->first()->accepted_at)->not->toBeNull();
    $this->postJson('/admin/login', ['email' => 'new@example.com', 'password' => 'Brand-new-pass-7'])->assertOk();
});

it('cannot be used twice', function () {
    $url = invite(staff('admin'));
    $this->postJson($url, NEW_PASSWORD)->assertOk();

    $this->postJson($url, ['password' => 'Another-pass-99', 'password_confirmation' => 'Another-pass-99'])->assertStatus(410);
    $this->getJson($url)->assertStatus(410);
    expect(AdminTestUser::query()->where('email', 'new@example.com')->count())->toBe(1);
});

it('expires after auth.invitation.expiry_days', function () {
    app(Settings::class)->set('auth.invitation.expiry_days', 2);
    $url = invite(staff('admin'));

    $this->travel(1)->days();
    $this->getJson($url)->assertOk();
    $this->travel(2)->days();
    $this->getJson($url)->assertStatus(403)->assertJsonMissing(['email' => 'new@example.com']); // the signature expires with it
});

it('is refused when the database says it expired even if the link is still signed', function () {
    $url = invite(staff('admin'));
    UserInvitation::query()->update(['expires_at' => now()->subMinute()]);

    $this->getJson($url)->assertStatus(410);
    $this->postJson($url, NEW_PASSWORD)->assertStatus(410);
    expect(AdminTestUser::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

it('is refused when the link was tampered with', function () {
    $url = invite(staff('admin'));
    $token = basename((string) parse_url($url, PHP_URL_PATH));

    $this->getJson(str_replace($token, strrev($token), $url))->assertForbidden();
    $this->getJson(preg_replace('/expires=\d+/', 'expires='.(now()->addYear()->timestamp), $url))->assertForbidden();
    $this->getJson(preg_replace('/signature=\w+/', 'signature=abc', $url))->assertForbidden();
    $this->postJson((string) strtok($url, '?'), NEW_PASSWORD)->assertForbidden(); // no signature at all
    expect(AdminTestUser::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

it('applies the password policy when accepting', function () {
    $url = invite(staff('admin'));

    $this->postJson($url, ['password' => 'weak', 'password_confirmation' => 'weak'])->assertStatus(422)->assertJsonValidationErrors('password');
    $this->postJson($url, ['password' => 'Brand-new-pass-7', 'password_confirmation' => 'different'])->assertStatus(422);
    expect(AdminTestUser::query()->where('email', 'new@example.com')->exists())->toBeFalse();
    $this->postJson($url, NEW_PASSWORD)->assertOk(); // the link survived the failed tries
});

it('replaces an open invitation when the same address is invited again', function () {
    $first = invite(staff('admin'));
    $second = invite(staff('admin'));

    $this->getJson($first)->assertStatus(410);
    $this->getJson($second)->assertOk();
    expect(UserInvitation::query()->count())->toBe(1);
});

it('does not invite someone who already has an account', function () {
    $existing = staff('editor');

    expect(fn () => invite(staff('admin'), $existing->email))->toThrow(ValidationException::class);
});

it('lets an admin invite up to their own rank and never a super-admin', function (string $role, bool $allowed) {
    $admin = staff('admin');

    if ($allowed) {
        invite($admin, roles: [$role]);
        expect(UserInvitation::query()->count())->toBe(1);
    } else {
        expect(fn () => invite($admin, roles: [$role]))->toThrow(AuthorizationException::class);
        expect(UserInvitation::query()->count())->toBe(0);
    }
})->with([
    'member' => ['member', true],
    'editor' => ['editor', true],
    'admin' => ['admin', true],
    'super-admin' => ['super-admin', false],
]);

it('lets a super-admin invite a super-admin', function () {
    invite(staff('super-admin'), roles: ['super-admin']);

    expect(UserInvitation::query()->count())->toBe(1);
});

it('does not let users without users.invite invite anyone', function (string $role) {
    expect(fn () => invite(staff($role), roles: ['member']))->toThrow(AuthorizationException::class);
})->with(['editor', 'member']);

it('refuses to accept when the inviter lost the rank to hand out that role', function () {
    $inviter = staff('admin');
    $url = invite($inviter, roles: ['admin']);
    $inviter->syncRoles(['editor']);

    $this->postJson($url, NEW_PASSWORD)->assertStatus(410);
    expect(AdminTestUser::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

it('logs the invitation and its acceptance without the token', function () {
    $url = invite(staff('admin'));
    $this->postJson($url, NEW_PASSWORD)->assertOk();

    $token = basename((string) parse_url($url, PHP_URL_PATH));
    expect(Activity::query()->whereIn('event', ['invitation.sent', 'invitation.accepted'])->orderBy('id')->pluck('event')->all())->toEqual(['invitation.sent', 'invitation.accepted'])
        ->and(Activity::query()->get()->toJson())->not->toContain($token)->not->toContain('Brand-new-pass-7');
});
