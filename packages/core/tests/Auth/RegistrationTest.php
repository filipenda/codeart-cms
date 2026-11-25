<?php

use Codeart\Core\Auth\Actions\ApproveUser;
use Codeart\Core\Auth\BotProtection;
use Codeart\Core\Auth\Mail\NewRegistrationPendingMail;
use Codeart\Core\Auth\Mail\RegistrationApprovedMail;
use Codeart\Core\Auth\Mail\RegistrationReceivedMail;
use Codeart\Core\Auth\Mail\VerifyEmailMail;
use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\Support\AdminTestUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

/** Open registration with no waiting time, unless the test sets its own. */
function openRegistration(array $settings = []): void
{
    $store = app(Settings::class);
    $store->setMany(['auth.registration.enabled' => true, 'auth.registration.min_seconds' => 0, ...$settings]);
}

function registration(array $overrides = []): array
{
    return [
        'name' => 'Visitor',
        'email' => 'visitor@example.com',
        'password' => 'Correct-horse-9',
        'password_confirmation' => 'Correct-horse-9',
        app(BotProtection::class)->tokenField() => app(BotProtection::class)->issueToken(),
        app(BotProtection::class)->honeypotField() => '',
        ...$overrides,
    ];
}

it('is closed by default, with safe defaults for everything else', function () {
    $settings = app(Settings::class);

    expect($settings->get('auth.registration.enabled'))->toBeFalse()
        ->and($settings->get('auth.registration.require_email_verification'))->toBeTrue()
        ->and($settings->get('auth.registration.require_approval'))->toBeTrue()
        ->and($settings->get('auth.registration.default_role'))->toBe('member')
        ->and($settings->get('auth.registration.allowed_email_domains'))->toBe([])
        ->and($settings->get('auth.registration.min_seconds'))->toBe(3)
        ->and($settings->get('auth.invitation.expiry_days'))->toBe(7)
        ->and($settings->get('auth.admin_path'))->toBe('admin');

    $this->postJson('/admin/register', registration())->assertNotFound();
    expect(AdminTestUser::query()->count())->toBe(0);
});

it('registers a member who waits for approval', function () {
    Mail::fake();
    openRegistration();

    $this->postJson('/admin/register', registration())->assertStatus(201);

    $user = AdminTestUser::query()->where('email', 'visitor@example.com')->firstOrFail();
    expect($user->hasRole('member'))->toBeTrue()
        ->and($user->is_active)->toBeFalse()
        ->and($user->approved_at)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeFalse();
    Mail::assertSent(VerifyEmailMail::class, 1);
    Mail::assertSent(RegistrationReceivedMail::class, fn ($m) => $m->hasTo('visitor@example.com'));
    expect(Activity::query()->where('event', 'registration.created')->count())->toBe(1);
});

it('activates the user at once when approval is not required', function () {
    Mail::fake();
    openRegistration(['auth.registration.require_approval' => false]);

    $this->postJson('/admin/register', registration())->assertStatus(201);

    $user = AdminTestUser::query()->firstOrFail();
    expect($user->is_active)->toBeTrue()->and($user->approved_at)->not->toBeNull();
    Mail::assertNotSent(NewRegistrationPendingMail::class);
});

it('skips the verification mail when verification is not required', function () {
    Mail::fake();
    openRegistration(['auth.registration.require_email_verification' => false]);

    $this->postJson('/admin/register', registration())->assertStatus(201);

    Mail::assertNotSent(VerifyEmailMail::class);
});

it('uses the configured default role, and never lets that be an admin role', function () {
    Mail::fake();
    openRegistration(['auth.registration.default_role' => 'editor']);
    $this->postJson('/admin/register', registration())->assertStatus(201);
    expect(AdminTestUser::query()->firstOrFail()->hasRole('editor'))->toBeTrue();

    $settings = app(Settings::class);
    expect(fn () => $settings->set('auth.registration.default_role', 'super-admin'))->toThrow(InvalidSettingValue::class)
        ->and(fn () => $settings->set('auth.registration.default_role', 'admin'))->toThrow(InvalidSettingValue::class);
});

it('applies the password policy', function () {
    openRegistration();

    $this->postJson('/admin/register', registration(['password' => 'weak', 'password_confirmation' => 'weak']))->assertStatus(422)->assertJsonValidationErrors('password');
    $this->postJson('/admin/register', registration(['password_confirmation' => 'Different-pass-1']))->assertStatus(422);
    expect(AdminTestUser::query()->count())->toBe(0);
});

it('only accepts the allowed e-mail domains when a list is set', function () {
    Mail::fake();
    openRegistration(['auth.registration.allowed_email_domains' => ['company.rs']]);

    $this->postJson('/admin/register', registration(['email' => 'someone@gmail.com']))->assertStatus(422)->assertJsonValidationErrors('email');
    $this->postJson('/admin/register', registration(['email' => 'someone@evil.com']))->assertStatus(422);
    $this->postJson('/admin/register', registration(['email' => 'Someone@Company.RS']))->assertStatus(201);
});

it('treats a filled honeypot as a bot, with the generic message', function () {
    openRegistration();

    $response = $this->postJson('/admin/register', registration([app(BotProtection::class)->honeypotField() => 'http://spam.example']));

    $response->assertStatus(422)->assertJsonPath('errors.email.0', __('codeart::auth.registration_failed'));
    expect(AdminTestUser::query()->count())->toBe(0);
});

it('refuses forms that are submitted too fast, or without a genuine token', function () {
    Mail::fake();
    openRegistration(['auth.registration.min_seconds' => 5]);
    $token = app(BotProtection::class)->tokenField();

    $this->postJson('/admin/register', registration())->assertStatus(422); // instant
    $this->postJson('/admin/register', registration([$token => 'forged']))->assertStatus(422);
    $this->postJson('/admin/register', registration([$token => null]))->assertStatus(422);
    $slow = registration();
    $this->travel(6)->seconds();
    $this->postJson('/admin/register', $slow)->assertStatus(201);
    expect(AdminTestUser::query()->count())->toBe(1);
});

it('refuses a token that is far too old', function () {
    openRegistration();
    $form = registration();

    $this->travel(2)->days();
    $this->postJson('/admin/register', $form)->assertStatus(422);
});

it('throttles registration per IP', function () {
    openRegistration();

    foreach (range(1, 5) as $i) {
        $this->postJson('/admin/register', registration(['email' => "bot{$i}@example.com", 'password' => 'weak']))->assertStatus(422);
    }
    $this->postJson('/admin/register', registration())->assertStatus(429);
});

it('tells the approvers about a pending registration', function () {
    Mail::fake();
    $admin = staff('admin');
    $superAdmin = staff('super-admin');
    $editor = staff('editor');
    $inactiveAdmin = staff('admin', ['is_active' => false]);
    openRegistration();

    $this->postJson('/admin/register', registration())->assertStatus(201);

    Mail::assertSent(NewRegistrationPendingMail::class, fn ($m) => $m->hasTo($admin->email));
    Mail::assertSent(NewRegistrationPendingMail::class, fn ($m) => $m->hasTo($superAdmin->email));
    Mail::assertNotSent(NewRegistrationPendingMail::class, fn ($m) => $m->hasTo($editor->email) || $m->hasTo($inactiveAdmin->email));
});

it('approves through the service, only for people who may approve', function () {
    Mail::fake();
    openRegistration();
    $this->postJson('/admin/register', registration())->assertStatus(201);
    $applicant = AdminTestUser::query()->where('email', 'visitor@example.com')->firstOrFail();

    expect(fn () => app(ApproveUser::class)($applicant, staff('editor')))->toThrow(AuthorizationException::class);
    expect($applicant->fresh()->is_active)->toBeFalse();

    expect(app(ApproveUser::class)($applicant, staff('admin')))->toBeTrue();
    $applicant = $applicant->fresh();
    expect($applicant->is_active)->toBeTrue()->and($applicant->approved_at)->not->toBeNull()
        ->and(app(ApproveUser::class)($applicant, staff('admin')))->toBeFalse(); // already approved
    Mail::assertSent(RegistrationApprovedMail::class, fn ($m) => $m->hasTo('visitor@example.com'));
    expect(Activity::query()->where('event', 'user.approved')->count())->toBe(1);
});

it('approves from the command line', function () {
    Mail::fake();
    $user = staff('member', ['is_active' => false, 'approved_at' => null]);

    $this->artisan('user:approve', ['email' => $user->email])->assertSuccessful();

    expect($user->fresh()->is_active)->toBeTrue()->and($user->fresh()->approved_at)->not->toBeNull();
    $this->artisan('user:approve', ['email' => 'nobody@example.com'])->assertFailed();
});

it('keeps a pending registration from signing in', function () {
    openRegistration();
    Mail::fake();
    $this->postJson('/admin/register', registration())->assertStatus(201);
    $this->flushSession();
    app('auth')->forgetGuards();

    $this->postJson('/admin/login', ['email' => 'visitor@example.com', 'password' => 'Correct-horse-9'])->assertStatus(422);
});
