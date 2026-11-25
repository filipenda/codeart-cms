<?php

use Codeart\Core\Auth\Actions\InviteUser;
use Codeart\Core\Auth\BotProtection;
use Codeart\Core\Auth\Mail\InvitationMail;
use Codeart\Core\Auth\UserInvitation;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\Support\AdminTestUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

require_once __DIR__.'/../Support/dom.php';

function openRegistrationScreen(array $settings = []): void
{
    app(Settings::class)->setMany(['auth.registration.enabled' => true, 'auth.registration.min_seconds' => 0, ...$settings]);
}

/** A signed invitation link for a fresh invitation. */
function invitationLink(): string
{
    Mail::fake();
    app(InviteUser::class)('New Person', 'new@example.com', ['editor'], staff('admin'));
    $html = '';
    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use (&$html) {
        $html = $mail->render();

        return true;
    });
    preg_match('/href="([^"]+invitation[^"]+)"/', $html, $m);

    return html_entity_decode($m[1]);
}

// ---------------------------------------------------------------------------------------------------------------- login

it('renders the login screen with a working no-JS form', function () {
    $this->get('/admin/login')->assertOk()->assertHeader('content-type', 'text/html; charset=utf-8');
    $xp = dom($this->get('/admin/login')->getContent());

    $form = '//form[@action="'.route('login.store').'"]';
    expect(attr($xp, $form, 'method'))->toBe('POST')
        ->and(count_nodes($xp, $form.'//input[@name="_token"][@type="hidden"]'))->toBe(1)
        ->and(attr($xp, $form.'//input[@name="email"]', 'autocomplete'))->toBe('username')
        ->and(attr($xp, $form.'//input[@name="email"]', 'type'))->toBe('email')
        ->and(attr($xp, $form.'//input[@name="password"]', 'autocomplete'))->toBe('current-password')
        ->and(attr($xp, $form.'//input[@name="password"]', 'type'))->toBe('password')
        ->and(attr($xp, $form.'//input[@name="remember"]', 'type'))->toBe('checkbox')
        ->and(attr($xp, '//a[@href="'.route('password.request').'"]', 'href'))->not->toBeNull()
        ->and(unlabelled_controls($xp))->toBe([]);
});

it('keeps the password show/hide toggle out of the page until JS runs', function () {
    $xp = dom($this->get('/admin/login')->getContent());

    // a plain password field, and a toggle that is hidden in the HTML (Alpine un-hides it)
    expect(attr($xp, '//input[@name="password"]', 'type'))->toBe('password')
        ->and(count_nodes($xp, '//button[@type="button"][@hidden][@aria-pressed]'))->toBe(1);
});

it('shows the registration tab and link only when registration is on', function () {
    $xp = dom($this->get('/admin/login')->getContent());
    expect(count_nodes($xp, '//*[@role="tab"]'))->toBe(0)
        ->and(count_nodes($xp, '//form[@action="'.route('register.store').'"]'))->toBe(0)
        ->and(count_nodes($xp, '//input[@name="company_website"]'))->toBe(0)
        ->and(count_nodes($xp, '//a[@href="'.route('register').'"]'))->toBe(0);
    $this->get('/admin/register')->assertNotFound();

    openRegistrationScreen();
    $xp = dom($this->get('/admin/login')->getContent());
    expect(count_nodes($xp, '//*[@role="tab"]'))->toBe(2)
        ->and(attr($xp, '//a[@id="tab-login"]', 'aria-selected'))->toBe('true')
        ->and(attr($xp, '//a[@id="tab-register"]', 'aria-selected'))->toBe('false')
        ->and(count_nodes($xp, '//a[@href="'.route('register').'"]'))->toBeGreaterThanOrEqual(2); // tab + "Nemate nalog?" link
});

it('renders the registration form with the honeypot, a signed timestamp and the right autocomplete values', function () {
    openRegistrationScreen();
    $bots = app(BotProtection::class);
    $response = $this->get('/admin/register')->assertOk();
    $xp = dom($response->getContent());

    $form = '//form[@action="'.route('register.store').'"]';
    expect(attr($xp, $form, 'method'))->toBe('POST')
        ->and(count_nodes($xp, $form.'//input[@name="_token"]'))->toBe(1)
        ->and(count_nodes($xp, $form.'//input[@name="'.$bots->honeypotField().'"][@tabindex="-1"][@autocomplete="off"]'))->toBe(1)
        ->and(attr($xp, $form.'//input[@name="'.$bots->honeypotField().'"]', 'value'))->toBe('')
        ->and(attr($xp, $form.'//input[@name="name"]', 'autocomplete'))->toBe('name')
        ->and(attr($xp, $form.'//input[@name="email"]', 'autocomplete'))->toBe('username')
        ->and(attr($xp, $form.'//input[@name="password"]', 'autocomplete'))->toBe('new-password')
        ->and(attr($xp, $form.'//input[@name="password_confirmation"]', 'autocomplete'))->toBe('new-password')
        ->and(unlabelled_controls($xp))->toBe([]);

    // the register tab is the visible one here; the token is a genuine encrypted timestamp
    expect(attr($xp, '//a[@id="tab-register"]', 'aria-selected'))->toBe('true')
        ->and(attr($xp, '//*[@id="panel-register"]', 'hidden'))->toBeNull()
        ->and(attr($xp, '//*[@id="panel-login"]', 'hidden'))->not->toBeNull();
    $token = attr($xp, $form.'//input[@name="'.$bots->tokenField().'"]', 'value');
    expect(abs((int) Crypt::decryptString((string) $token) - now()->getTimestamp()))->toBeLessThan(5);
});

it('gives every field and its error the aria wiring after a failed sign-in', function () {
    $this->from('/admin/login')->post('/admin/login', ['email' => 'nobody@example.com', 'password' => 'x'])->assertRedirect('/admin/login');
    $response = $this->get('/admin/login')->assertOk();
    $xp = dom($response->getContent());

    $describedBy = attr($xp, '//input[@id="login-email"]', 'aria-describedby');
    expect(attr($xp, '//input[@id="login-email"]', 'aria-invalid'))->toBe('true')
        ->and($describedBy)->toBe('login-email-error')
        ->and(text_of($xp, '//*[@id="login-email-error"]'))->not->toBe('')
        ->and(attr($xp, '//input[@id="login-password"]', 'aria-invalid'))->toBeNull()
        ->and(count_nodes($xp, '//*[@role="alert"]'))->toBe(1);
});

it('keeps old input and stays on the registration tab after a failed registration', function () {
    openRegistrationScreen();
    $this->from('/admin/register')->post('/admin/register', ['form' => 'register', 'name' => 'Visitor', 'email' => 'not-an-email', 'password' => 'x', 'password_confirmation' => 'y',
        'form_token' => app(BotProtection::class)->issueToken(), 'company_website' => ''])->assertRedirect('/admin/register');
    $xp = dom($this->get('/admin/register')->getContent());

    expect(attr($xp, '//input[@id="register-name"]', 'value'))->toBe('Visitor')
        ->and(attr($xp, '//input[@id="register-email"]', 'aria-invalid'))->toBe('true')
        ->and(count_nodes($xp, '//*[@id="panel-login"]//*[@aria-invalid="true"]'))->toBe(0)
        ->and(attr($xp, '//a[@id="tab-register"]', 'aria-selected'))->toBe('true');
});

it('tells the remaining lockout time', function (string $locale, string $needle) {
    app()->setFallbackLocale('xx'); // the test skeleton ships English lang files; a real app has none
    app(Settings::class)->setMany(['security.login.max_attempts' => 2]);
    $this->withSession(['codeart.locale' => $locale]);
    $user = staff('editor');

    foreach (range(1, 3) as $_) {
        $this->from('/admin/login')->post('/admin/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }
    $text = text_of(dom($this->get('/admin/login')->getContent()), '//*[@id="login-email-error"]');

    expect($text)->toContain($needle)->and($text)->toMatch('/\d+/');
})->with([['sr', 'sekundi'], ['en', 'seconds']]);

// ----------------------------------------------------------------------------------------------- other guest screens

it('renders forgot password and sends the neutral status back', function () {
    $xp = dom($this->get('/admin/forgot-password')->assertOk()->getContent());
    expect(attr($xp, '//form[@action="'.route('password.email').'"]', 'method'))->toBe('POST')
        ->and(attr($xp, '//input[@name="email"]', 'autocomplete'))->toBe('username')
        ->and(unlabelled_controls($xp))->toBe([]);

    $this->from('/admin/forgot-password')->post('/admin/forgot-password', ['email' => 'nobody@example.com'])->assertRedirect('/admin/forgot-password');
    $page = dom($this->get('/admin/forgot-password')->getContent());
    expect(text_of($page, '//*[@role="status"]'))->toBe(__('codeart::auth.reset_sent'));
});

it('renders the reset password screen from the link', function () {
    $xp = dom($this->get('/admin/reset-password/some-token?email=a%40example.com')->assertOk()->getContent());

    expect(attr($xp, '//form[@action="'.route('password.update').'"]', 'method'))->toBe('POST')
        ->and(attr($xp, '//input[@name="token"]', 'value'))->toBe('some-token')
        ->and(attr($xp, '//input[@name="email"]', 'value'))->toBe('a@example.com')
        ->and(attr($xp, '//input[@name="email"]', 'readonly'))->not->toBeNull()
        ->and(attr($xp, '//input[@name="password"]', 'autocomplete'))->toBe('new-password')
        ->and(attr($xp, '//input[@name="password_confirmation"]', 'autocomplete'))->toBe('new-password')
        ->and(unlabelled_controls($xp))->toBe([]);
});

it('renders the two-factor challenge with a single numeric code input and a recovery toggle', function () {
    $user = staff('editor');
    $this->actingAs($user, 'admin')->withSession(['auth.password_confirmed_at' => time()]);
    $this->postJson('/admin/user/two-factor-authentication')->assertOk();
    $this->postJson('/admin/user/confirmed-two-factor-authentication', ['code' => totp($user)])->assertOk();
    $this->flushSession();
    app('auth')->forgetGuards();
    $this->post('/admin/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])->assertRedirect(route('two-factor.login'));
    carrySession();

    $xp = dom($this->get('/admin/two-factor-challenge')->assertOk()->getContent());
    $code = '//form[@action="'.route('two-factor.login.store').'"]//input[@name="code"]';
    expect(attr($xp, $code, 'inputmode'))->toBe('numeric')
        ->and(attr($xp, $code, 'autocomplete'))->toBe('one-time-code')
        ->and(attr($xp, $code, 'maxlength'))->toBe('6')
        ->and(count_nodes($xp, '//input[@name="recovery_code"]'))->toBe(1)
        ->and(attr($xp, '//a[@href="'.route('two-factor.login', ['recovery' => 1]).'"]', 'href'))->not->toBeNull()
        ->and(unlabelled_controls($xp))->toBe([]);

    // the recovery variant is reachable without JS: the recovery panel is visible, the code panel hidden
    $recovery = dom($this->get('/admin/two-factor-challenge?recovery=1')->getContent());
    expect(attr($recovery, '//input[@name="recovery_code"]/ancestor::div[@hidden]', 'hidden'))->toBeNull()
        ->and(count_nodes($recovery, '//input[@name="code"]/ancestor::div[@hidden]'))->toBeGreaterThan(0);
});

// ------------------------------------------------------------------------------------------------- signed-in screens

it('renders the verify e-mail notice and resends', function () {
    $user = staff('editor', ['email_verified_at' => null]);
    $this->actingAs($user, 'admin');

    $xp = dom($this->get('/admin/email/verify')->assertOk()->getContent());
    expect(attr($xp, '//form[@action="'.route('verification.send').'"]', 'method'))->toBe('POST')
        ->and(attr($xp, '//form[@action="'.route('logout').'"]', 'method'))->toBe('POST');

    $this->from('/admin/email/verify')->post('/admin/email/verification-notification')->assertRedirect('/admin/email/verify');
    expect(text_of(dom($this->get('/admin/email/verify')->getContent()), '//*[@role="status"]'))->toBe(__('codeart::ui.status.verification-link-sent'));
});

it('sends an unverified user to the verify notice when they open the admin', function () {
    Route::middleware(['web', 'auth:admin', 'admin.access'])->get('/admin/probe', fn () => 'ok');
    $this->actingAs(staff('editor', ['email_verified_at' => null]), 'admin');

    $this->get('/admin/probe')->assertRedirect(route('verification.notice'));
});

it('renders the password confirmation screen', function () {
    $this->actingAs(staff('editor'), 'admin');

    $xp = dom($this->get('/admin/user/confirm-password')->assertOk()->getContent());
    expect(attr($xp, '//form[@action="'.route('password.confirm.store').'"]', 'method'))->toBe('POST')
        ->and(attr($xp, '//input[@name="password"]', 'autocomplete'))->toBe('current-password')
        ->and(unlabelled_controls($xp))->toBe([]);
});

it('shows the registration received screen after registering and waits for approval', function () {
    openRegistrationScreen();
    $bots = app(BotProtection::class);

    $this->post('/admin/register', [
        'name' => 'Visitor', 'email' => 'visitor@example.com', 'password' => 'Correct-horse-9', 'password_confirmation' => 'Correct-horse-9',
        $bots->tokenField() => $bots->issueToken(), $bots->honeypotField() => '',
    ])->assertRedirect(route('admin.registration.received'));

    $page = $this->get('/admin/registration-received')->assertOk()->getContent();
    expect($page)->toContain(__('codeart::ui.received_title'))->toContain(__('codeart::ui.received_approval'))->toContain(__('codeart::ui.received_verify'));
});

it('shows the themed 403 page to a signed-in user without access, and JSON stays JSON', function () {
    Route::middleware(['web', 'auth:admin', 'admin.access'])->get('/admin/probe', fn () => 'ok');
    $this->actingAs(staff('member'), 'admin');

    $response = $this->get('/admin/probe')->assertForbidden();
    $xp = dom($response->getContent());
    expect(text_of($xp, '//h1'))->toBe(__('codeart::ui.no_access_title'))
        ->and($response->getContent())->toContain(__('codeart::auth.denied.forbidden'))
        ->and(attr($xp, '//form[@action="'.route('logout').'"]', 'method'))->toBe('POST');

    $this->getJson('/admin/probe')->assertForbidden()->assertJson(['reason' => 'forbidden']);
});

// ------------------------------------------------------------------------------------------------------ invitation

it('renders the invitation form for a valid link', function () {
    $url = invitationLink();
    $xp = dom($this->get($url)->assertOk()->getContent());

    expect(attr($xp, '//form[.//input[@name="password"]]', 'action'))->toBe($url)
        ->and(attr($xp, '//input[@name="email"]', 'value'))->toBe('new@example.com')
        ->and(attr($xp, '//input[@name="password"]', 'autocomplete'))->toBe('new-password')
        ->and(attr($xp, '//input[@name="password_confirmation"]', 'autocomplete'))->toBe('new-password')
        ->and(count_nodes($xp, '//input[@name="locale"][@type="hidden"]'))->toBe(1)
        ->and(unlabelled_controls($xp))->toBe([]);

    // the form really works without JS: post it and land on the sign-in screen
    $this->post($url, ['password' => 'Brand-new-pass-7', 'password_confirmation' => 'Brand-new-pass-7', 'locale' => 'en'])->assertRedirect(route('login'));
    expect(AdminTestUser::query()->where('email', 'new@example.com')->value('locale'))->toBe('en');
});

it('shows a clear state for expired, used and unknown invitations', function () {
    $url = invitationLink();
    $token = basename((string) parse_url($url, PHP_URL_PATH));
    $invitation = UserInvitation::query()->firstOrFail();

    $state = fn (string $link, int $status) => attr(dom($this->get($link)->assertStatus($status)->getContent()), '//*[@data-state]', 'data-state');

    $invitation->forceFill(['expires_at' => now()->subMinute()])->save();
    expect($state($url, 410))->toBe('expired');

    $invitation->forceFill(['expires_at' => now()->addDay(), 'accepted_at' => now()])->save();
    expect($state($url, 410))->toBe('used');

    $unknown = URL::temporarySignedRoute('admin.invitation.show', now()->addDay(), ['token' => 'not-a-real-token']);
    expect($state($unknown, 410))->toBe('invalid')
        ->and($token)->not->toBe('');
});

it('shows the expired state for a link whose signature ran out, and invalid for a changed link', function () {
    $url = invitationLink();
    $this->travel(8)->days();
    expect(attr(dom($this->get($url)->assertForbidden()->getContent()), '//*[@data-state]', 'data-state'))->toBe('expired');
    $this->travelBack();

    $changed = str_replace('/invitation/', '/invitation/x', $url);
    expect(attr(dom($this->get($changed)->assertForbidden()->getContent()), '//*[@data-state]', 'data-state'))->toBe('invalid');
    $this->getJson($changed)->assertForbidden();
});

// ------------------------------------------------------------------------------------------------------- two-factor

it('walks through two-factor setup: QR only before the code, recovery codes only after a correct one', function () {
    $user = staff('editor');
    $this->actingAs($user, 'admin');

    // a password confirmation comes first
    $this->get('/admin/two-factor-setup')->assertRedirect(route('password.confirm'));
    $this->withSession(['auth.password_confirmed_at' => time()]);

    $xp = dom($this->get('/admin/two-factor-setup')->assertOk()->getContent());
    expect(attr($xp, '//form[@action="'.route('two-factor.enable').'"]', 'method'))->toBe('POST');

    $this->from('/admin/two-factor-setup')->post('/admin/user/two-factor-authentication')->assertRedirect('/admin/two-factor-setup');

    // secret exists: inline SVG QR, manual key, code form, and NO recovery codes yet
    $page = $this->get('/admin/two-factor-setup')->assertOk()->getContent();
    $xp = dom($page);
    $secret = decrypt($user->fresh()->two_factor_secret);
    expect(count_nodes($xp, '//*[@role="img"]//svg'))->toBe(1)
        ->and($page)->not->toContain('<img')
        ->and(text_of($xp, '//*[@id="two-factor-secret"]'))->toBe($secret)
        ->and(attr($xp, '//form[@action="'.route('two-factor.confirm').'"]//input[@name="code"]', 'autocomplete'))->toBe('one-time-code')
        ->and(count_nodes($xp, '//*[@x-ref="codes"]'))->toBe(0)
        ->and($page)->not->toContain(decrypt($user->fresh()->two_factor_recovery_codes));

    // a wrong code: error, still no recovery codes
    $this->from('/admin/two-factor-setup')->post('/admin/user/confirmed-two-factor-authentication', ['code' => '000000'])->assertRedirect('/admin/two-factor-setup');
    $xp = dom($this->get('/admin/two-factor-setup')->getContent());
    expect(attr($xp, '//input[@name="code"]', 'aria-invalid'))->toBe('true')
        ->and(count_nodes($xp, '//*[@x-ref="codes"]'))->toBe(0);

    // the right code: the recovery codes appear, once
    $this->from('/admin/two-factor-setup')->post('/admin/user/confirmed-two-factor-authentication', ['code' => totp($user)])->assertRedirect('/admin/two-factor-setup');
    $xp = dom($this->get('/admin/two-factor-setup')->getContent());
    $codes = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);
    $shown = array_map(fn (DOMNode $li) => trim($li->textContent), iterator_to_array($xp->query('//*[@x-ref="codes"]/li')));
    expect($shown)->toBe($codes)->toHaveCount(8)
        ->and(count_nodes($xp, '//*[@x-data="codeartRecoveryCodes(\'recovery-codes.txt\')"]//button[@type="button"]'))->toBe(2);

    $again = dom($this->get('/admin/two-factor-setup')->getContent());
    expect(count_nodes($again, '//*[@x-ref="codes"]'))->toBe(0)
        ->and(attr($again, '//form[@action="'.route('two-factor.regenerate-recovery-codes').'"]', 'method'))->toBe('POST');
});

it('says so when two-factor is switched off', function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    $this->actingAs(staff('editor'), 'admin');

    expect($this->get('/admin/two-factor-setup')->assertOk()->getContent())->toContain(__('codeart::ui.setup_off'));
});

// ------------------------------------------------------------------------------------------------------ social slot

it('renders the social slot only when it is provided', function () {
    $without = Blade::render('<x-codeart::auth-layout>BODY</x-codeart::auth-layout>');
    $with = Blade::render('<x-codeart::auth-layout>BODY<x-slot:social><a href="#social">SOCIAL</a></x-slot:social></x-codeart::auth-layout>');

    expect($without)->toContain('BODY')->not->toContain('SOCIAL')->not->toContain('role="separator"')
        ->and($with)->toContain('SOCIAL')->toContain('role="separator"');
});
