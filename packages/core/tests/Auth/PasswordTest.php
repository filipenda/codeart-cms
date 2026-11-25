<?php

use Codeart\Core\Auth\Mail\ResetPasswordMail;
use Codeart\Core\Auth\PasswordRules;
use Codeart\Core\Settings\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

function passes(string $password): bool
{
    return Validator::make(['password' => $password], ['password' => app(PasswordRules::class)->rules()])->passes();
}

it('builds the rule from the settings', function () {
    expect(passes('Short1a'))->toBeFalse()          // under 10
        ->and(passes('alllowercase123'))->toBeFalse() // no upper case
        ->and(passes('NoNumbersHereAtAll'))->toBeFalse()
        ->and(passes('Correct-horse-9'))->toBeTrue();

    $settings = app(Settings::class);
    $settings->set('security.password.min_length', 14);
    $settings->set('security.password.require_symbol', true);
    $settings->set('security.password.require_mixed_case', false);
    $settings->set('security.password.require_number', false);

    expect(passes('Correct-horse-9'))->toBeTrue(); // 15 characters and a symbol
    expect(passes('shortsymbol!'))->toBeFalse()
        ->and(passes('longenoughbutnosymbol'))->toBeFalse()
        ->and(passes('long enough with symbol!'))->toBeTrue();
});

it('rejects a breached password', function () {
    $sha1 = strtoupper(sha1('Correct-horse-9'));
    fakeHttp(['api.pwnedpasswords.com/*' => Http::response(substr($sha1, 5).':9001', 200)]);

    expect(passes('Correct-horse-9'))->toBeFalse();

    app(Settings::class)->set('security.password.check_breached', false);
    expect(passes('Correct-horse-9'))->toBeTrue();
});

it('lets the user through when the breach service is unreachable or broken', function () {
    fakeHttp(['api.pwnedpasswords.com/*' => fn () => throw new ConnectionException('offline')]);
    expect(passes('Correct-horse-9'))->toBeTrue();

    fakeHttp(['api.pwnedpasswords.com/*' => Http::response('boom', 500)]);
    expect(passes('Correct-horse-9'))->toBeTrue();

    fakeHttp(['api.pwnedpasswords.com/*' => fn () => throw new RuntimeException('anything')]);
    expect(passes('Correct-horse-9'))->toBeTrue();
});

it('answers a password reset request the same way for known and unknown addresses', function () {
    Mail::fake();
    $user = staff('editor');

    $known = $this->postJson('/admin/forgot-password', ['email' => $user->email]);
    $unknown = $this->postJson('/admin/forgot-password', ['email' => 'nobody@example.com']);
    $again = $this->postJson('/admin/forgot-password', ['email' => $user->email]); // throttled by the broker

    expect($known->status())->toBe(200)
        ->and($unknown->status())->toBe(200)
        ->and($again->status())->toBe(200)
        ->and($known->json())->toEqual($unknown->json())->toEqual($again->json());
    Mail::assertSent(ResetPasswordMail::class, 1);
    Mail::assertSent(ResetPasswordMail::class, fn ($m) => $m->hasTo($user->email));
});

it('resets the password with a valid token and ends the user\'s sessions', function () {
    $user = staff('editor');
    DB::table('sessions')->insert(['id' => 'old', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $token = Password::broker()->createToken($user);

    $this->postJson('/admin/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'])->assertOk();

    expect(Hash::check('Brand-new-pass-7', $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'old')->exists())->toBeFalse();
    // the token is single-use
    $this->postJson('/admin/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Another-new-pass-8', 'password_confirmation' => 'Another-new-pass-8'])->assertStatus(422);
});

it('does not say whether the address or the token was wrong on reset', function () {
    $user = staff('editor');
    $payload = ['password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'];

    $badToken = $this->postJson('/admin/reset-password', [...$payload, 'token' => 'nope', 'email' => $user->email]);
    $badEmail = $this->postJson('/admin/reset-password', [...$payload, 'token' => 'nope', 'email' => 'nobody@example.com']);

    expect($badToken->status())->toBe(422)->and($badEmail->status())->toBe(422)
        ->and($badToken->json())->toEqual($badEmail->json());
});

it('applies the password policy when resetting and changing a password', function () {
    $user = staff('editor');
    $token = Password::broker()->createToken($user);

    $this->postJson('/admin/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'weak', 'password_confirmation' => 'weak'])
        ->assertStatus(422)->assertJsonValidationErrors('password');

    $this->actingAs($user, 'admin');
    $this->putJson('/admin/user/password', ['current_password' => 'wrong-pass-1', 'password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7'])
        ->assertStatus(422)->assertJsonValidationErrors('current_password');
    $this->putJson('/admin/user/password', ['current_password' => 'Secret-pass-123', 'password' => 'weak', 'password_confirmation' => 'weak'])
        ->assertStatus(422)->assertJsonValidationErrors('password');
});
