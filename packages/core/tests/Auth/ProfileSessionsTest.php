<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Confirmation\ConfirmationTokens;
use Codeart\Core\Confirmation\RequireConfirmation;
use Codeart\Core\Profile\SessionHandle;
use Codeart\Core\Profile\UserAgent;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Tests\Support\AdminTestUser;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

const UA_CHROME_WIN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    config(['session.driver' => 'database']);
});

function sesRow(int $userId, string $id, ?string $ua = UA_CHROME_WIN, string $ip = '203.0.113.5', ?int $at = null): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'ip_address' => $ip, 'user_agent' => $ua, 'payload' => '', 'last_activity' => $at ?? time()]);
}

/** Signs in with a session whose id we know, and registers that session in the table (the "current" one). */
function sesLogin($test, AdminTestUser $user, bool $confirmed = false): string
{
    $test->withSession($confirmed ? ['auth.password_confirmed_at' => time()] : [])->actingAs($user, 'admin');
    $id = session()->getId();
    $test->withoutMiddleware(EncryptCookies::class)->withUnencryptedCookie(config('session.cookie'), $id); // the browser sends its session cookie
    sesRow($user->id, $id, UA_CHROME_WIN, '198.51.100.1', time());

    return $id;
}

function sesToken($test, AdminTestUser $user, string $method, string $path): string
{
    return app(ConfirmationTokens::class)->issue($user, RequireConfirmation::scope($method, $path));
}

dataset('agents', [
    'chrome windows' => [UA_CHROME_WIN, 'Chrome', 'Windows', 'desktop'],
    'edge' => [UA_CHROME_WIN.' Edg/126.0.0.0', 'Edge', 'Windows', 'desktop'],
    'opera' => [UA_CHROME_WIN.' OPR/112.0.0.0', 'Opera', 'Windows', 'desktop'],
    'firefox linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0', 'Firefox', 'Linux', 'desktop'],
    'safari macos' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', 'Safari', 'macOS', 'laptop'],
    'chrome android phone' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36', 'Chrome', 'Android', 'phone'],
    'chrome android tablet' => ['Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36', 'Chrome', 'Android', 'tablet'],
    'firefox android' => ['Mozilla/5.0 (Android 14; Mobile; rv:127.0) Gecko/127.0 Firefox/127.0', 'Firefox', 'Android', 'phone'],
    'safari iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'Safari', 'iOS', 'phone'],
    'safari ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', 'Safari', 'iOS', 'tablet'],
    'chrome ios' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/126.0.6478.153 Mobile/15E148 Safari/604.1', 'Chrome', 'iOS', 'phone'],
]);

it('reads browser, OS and device from real user-agent strings', function (string $agent, string $browser, string $os, string $device) {
    expect(UserAgent::parse($agent))->toBe(['browser' => $browser, 'os' => $os, 'device' => $device, 'known' => true]);
})->with('agents');

it('falls back for unknown or empty user agents', function () {
    foreach (['curl/8.0.1', '', null] as $agent) {
        $r = UserAgent::parse($agent);
        expect($r['known'])->toBeFalse()->and($r['browser'])->toBeNull()->and($r['os'])->toBeNull();
    }
});

it('draws the Sesije tab only with the database session driver', function () {
    $me = staff('editor');
    $xp = dom($this->actingAs($me, 'admin')->get('/admin/profile')->getContent());
    expect(count_nodes($xp, '//nav[@data-profile-tabs]//a'))->toBe(4)->and(count_nodes($xp, '//nav[@data-profile-tabs]//a[@data-tab="sessions"]'))->toBe(1);
    $this->actingAs($me, 'admin')->get('/admin/profile/sessions')->assertOk();

    foreach (['array', 'file', 'cookie'] as $driver) {
        config(['session.driver' => $driver]);
        $xp = dom($this->actingAs($me, 'admin')->get('/admin/profile')->getContent());
        expect(count_nodes($xp, '//nav[@data-profile-tabs]//a'))->toBe(3)->and(count_nodes($xp, '//a[@data-tab="sessions"]'))->toBe(0);
        $this->actingAs($me, 'admin')->get('/admin/profile/sessions')->assertNotFound();
    }
});

it('lists the current session first, then the others, without real ids and without anyone else\'s', function () {
    $me = staff('editor');
    $other = staff('editor');
    $current = sesLogin($this, $me);
    sesRow($me->id, 'older-session-id-0001', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', '203.0.113.9', time() - 7200);
    sesRow($me->id, 'newer-session-id-0002', 'curl/8.0', '203.0.113.10', time() - 600);
    sesRow($other->id, 'foreign-session-id-0003', UA_CHROME_WIN, '192.0.2.77');

    $html = $this->get('/admin/profile/sessions')->assertOk()->getContent();
    $xp = dom($html);
    $items = $xp->query('//li[@data-session]');
    expect($items->length)->toBe(3)
        ->and($items->item(0)->hasAttribute('data-session-current'))->toBeTrue()
        ->and(text_of($xp, '//li[@data-session-current]//*[@data-session-label]'))->toBe('Chrome · Windows')
        ->and(text_of($xp, '//li[@data-session-current]//*[@data-current-marker]'))->toBe('Trenutna sesija')
        ->and($xp->query('//li[@data-session-current]//*[@data-current-marker]/span')->item(0)->getAttribute('class'))->toContain('bg-success')
        ->and(text_of($xp, '(//li[@data-session])[2]//*[@data-session-ip]'))->toBe('203.0.113.10')
        ->and(text_of($xp, '(//li[@data-session])[2]//*[@data-session-label]'))->toBe('Nepoznat uređaj')
        ->and(attr($xp, '(//li[@data-session])[3]', 'data-device'))->toBe('phone')
        ->and(text_of($xp, '(//li[@data-session])[3]//*[@data-session-label]'))->toBe('Safari · iOS')
        ->and(count_nodes($xp, '//li[@data-session-current]//*[@data-end-session]'))->toBe(0)
        ->and(count_nodes($xp, '//li[@data-session]//*[@data-end-session]'))->toBe(2)
        ->and($html)->not->toContain($current)->not->toContain('older-session-id-0001')->not->toContain('newer-session-id-0002')
        ->not->toContain('foreign-session-id-0003')->not->toContain('192.0.2.77')
        ->and(count_nodes($xp, '//*[@data-end-others]'))->toBe(1);
});

it('shows the last activity in the user\'s display zone', function () {
    $me = staff('editor', ['timezone' => 'America/New_York']);
    sesLogin($this, $me);
    sesRow($me->id, 'zone-session-id-0004', UA_CHROME_WIN, '203.0.113.20', (int) strtotime('2026-01-15 12:00:00 UTC'));
    $xp = dom($this->get('/admin/profile/sessions')->getContent());
    expect(attr($xp, '(//li[@data-session])[2]//time', 'title'))->toBe('15.01.2026. 07:00');
});

it('ends only the user\'s own session, only with the confirmation and a fresh password', function () {
    $me = staff('editor');
    $other = staff('editor');
    sesLogin($this, $me, true);
    sesRow($me->id, 'mine-session-id-0001');
    sesRow($other->id, 'theirs-session-id-0002');
    $mine = SessionHandle::for('mine-session-id-0001', $me->id);
    $path = '/admin/profile/sessions/'.$mine;

    $this->delete($path)->assertRedirect(); // no token
    expect(DB::table('sessions')->where('id', 'mine-session-id-0001')->exists())->toBeTrue();

    $this->delete($path, ['confirmation' => sesToken($this, $me, 'DELETE', $path)])->assertRedirect(route('admin.profile.sessions'));
    expect(DB::table('sessions')->where('id', 'mine-session-id-0001')->exists())->toBeFalse()
        ->and(Activity::query()->where('event', 'session.revoked')->count())->toBe(1)
        ->and(Activity::query()->where('event', 'session.revoked')->first()->properties->toJson())->not->toContain('mine-session-id-0001');

    // someone else's session: a handle made for the other owner, or a guessed one, matches nothing of mine
    foreach ([SessionHandle::for('theirs-session-id-0002', $other->id), SessionHandle::for('theirs-session-id-0002', $me->id), str_repeat('a', 64)] as $handle) {
        $p = '/admin/profile/sessions/'.$handle;
        $this->delete($p, ['confirmation' => sesToken($this, $me, 'DELETE', $p)])->assertNotFound();
    }
    expect(DB::table('sessions')->where('id', 'theirs-session-id-0002')->exists())->toBeTrue();
});

it('asks for the password again before ending a session', function () {
    $me = staff('editor');
    sesLogin($this, $me, false);
    sesRow($me->id, 'mine-session-id-0001');
    $path = '/admin/profile/sessions/'.SessionHandle::for('mine-session-id-0001', $me->id);
    $this->delete($path, ['confirmation' => sesToken($this, $me, 'DELETE', $path)])->assertRedirect(route('password.confirm'));
    expect(DB::table('sessions')->where('id', 'mine-session-id-0001')->exists())->toBeTrue();
});

it('never ends the current session', function () {
    $me = staff('editor');
    $current = sesLogin($this, $me, true);
    $path = '/admin/profile/sessions/'.SessionHandle::for($current, $me->id);
    $this->delete($path, ['confirmation' => sesToken($this, $me, 'DELETE', $path)])->assertStatus(422);
    expect(DB::table('sessions')->where('id', $current)->exists())->toBeTrue();
});

it('ends all other sessions but not the current one or other people\'s', function () {
    $me = staff('editor', ['remember_token' => 'old-remember-token']);
    $other = staff('editor');
    $current = sesLogin($this, $me, true);
    sesRow($me->id, 'mine-session-id-0001');
    sesRow($me->id, 'mine-session-id-0002');
    sesRow($other->id, 'theirs-session-id-0003');
    $path = '/admin/profile/sessions';

    $this->delete($path)->assertRedirect(); // no token
    expect(DB::table('sessions')->where('user_id', $me->id)->count())->toBe(3);

    $this->delete($path, ['confirmation' => sesToken($this, $me, 'DELETE', $path)])->assertRedirect(route('admin.profile.sessions'));
    expect(DB::table('sessions')->where('user_id', $me->id)->pluck('id')->all())->toBe([$current])
        ->and(DB::table('sessions')->where('id', 'theirs-session-id-0003')->exists())->toBeTrue()
        ->and($me->fresh()->remember_token)->not->toBe('old-remember-token')
        ->and(Activity::query()->where('event', 'session.others_revoked')->first()->properties['sessions'])->toBe(2);
});

it('has the session texts in sr, en and sr-Cyrl', function () {
    foreach (['tab_sessions', 'ses_title', 'ses_current', 'ses_unknown', 'ses_end', 'ses_all', 'ses_tip'] as $key) {
        foreach (['sr', 'en', 'sr-Cyrl'] as $locale) {
            expect(__('codeart::profile.'.$key, [], $locale))->not->toBe('codeart::profile.'.$key);
        }
    }
    expect(__('codeart::profile.ses_current', [], 'sr'))->toBe('Trenutna sesija')->and(__('codeart::profile.ses_unknown', [], 'sr'))->toBe('Nepoznat uređaj');
});
