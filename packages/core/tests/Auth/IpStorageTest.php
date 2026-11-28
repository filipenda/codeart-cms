<?php

require_once __DIR__.'/../Support/dom.php';

use Codeart\Core\Auth\UserSessions;
use Codeart\Core\Privacy\IpPrivacy;
use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    app(Settings::class)->set('security.2fa.mode', 'off');
    config(['session.driver' => 'database']);
});

function ipMode(string $mode): void
{
    app(Settings::class)->set('security.ip_storage', $mode);
}

it('anonymizes IPv4 and IPv6', function () {
    expect(IpPrivacy::anonymize('203.0.113.77'))->toBe('203.0.113.0')
        ->and(IpPrivacy::anonymize('2001:db8:1234:5678:9abc:def0:1234:5678'))->toBe('2001:db8:1234::')
        ->and(IpPrivacy::anonymize(null))->toBeNull()
        ->and(IpPrivacy::anonymize('garbage'))->toBeNull();
});

it('defaults to full and rejects unknown values', function () {
    expect(app(Settings::class)->get('security.ip_storage'))->toBe('full');
    expect(fn () => app(Settings::class)->set('security.ip_storage', 'whatever'))->toThrow(InvalidSettingValue::class);
});

it('writes the activity log IP by mode', function (string $mode, ?string $expected) {
    ipMode($mode);
    $user = staff('editor');
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])->actingAs($user, 'admin')->put('/admin/profile', ['first_name' => 'A', 'last_name' => 'B']);
    $row = Activity::query()->where('event', 'profile.updated')->latest('id')->first();
    expect($row)->not->toBeNull()->and($row->ip_address)->toBe($expected);
})->with([['full', '203.0.113.77'], ['anonymized', '203.0.113.0'], ['off', null]]);

it('follows the mode in the session handler', function (string $mode, ?string $expected) {
    ipMode($mode);
    $user = staff('editor');
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])->actingAs($user, 'admin')->get('/admin/profile')->assertOk();
    $ips = DB::table('sessions')->pluck('ip_address')->all();
    foreach ($ips as $ip) {
        expect($ip)->toBe($expected);
    }
})->with([['full', '203.0.113.77'], ['anonymized', '203.0.113.0'], ['off', null]]);

it('shows the IP on the profile card and in the sessions list by mode', function (string $mode, bool $shown, ?string $text) {
    ipMode($mode);
    $user = staff('editor', ['last_login_ip' => '203.0.113.77']);
    DB::table('sessions')->insert(['id' => 'ip-session', 'user_id' => $user->id, 'ip_address' => '203.0.113.77', 'user_agent' => 'Mozilla/5.0', 'payload' => '', 'last_activity' => time()]);

    $card = dom($this->actingAs($user, 'admin')->get('/admin/profile')->getContent());
    $list = dom($this->get('/admin/profile/sessions')->getContent());

    expect($card->query('//*[@data-last-ip]')->length)->toBe($shown ? 1 : 0)
        ->and($list->query('//*[@data-session-ip]')->length)->toBe($shown ? $list->query('//li[@data-session]')->length : 0)
        ->and(collect(app(UserSessions::class)->list($user->id))->firstWhere('id', 'ip-session')['ip_address'])->toBe($text);
    if ($shown) {
        expect(trim($card->query('//*[@data-last-ip]')->item(0)->textContent))->toBe($text);
    }
})->with([['full', true, '203.0.113.77'], ['anonymized', true, '203.0.113.0'], ['off', false, null]]);

it('prune removes or anonymizes stored IPs in the rows that stay', function () {
    $user = staff('editor', ['last_login_ip' => '203.0.113.77']);
    activity()->log('x');
    Activity::query()->update(['ip_address' => '203.0.113.77']);
    DB::table('sessions')->insert(['id' => 'p1', 'user_id' => $user->id, 'ip_address' => '203.0.113.77', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

    ipMode('anonymized');
    $this->artisan('activity-log:prune')->assertSuccessful();
    expect(Activity::query()->value('ip_address'))->toBe('203.0.113.0')
        ->and(DB::table('sessions')->where('id', 'p1')->value('ip_address'))->toBe('203.0.113.0')
        ->and($user->fresh()->last_login_ip)->toBe('203.0.113.0');

    ipMode('off');
    $this->artisan('activity-log:prune')->assertSuccessful();
    expect(Activity::query()->value('ip_address'))->toBeNull()
        ->and(DB::table('sessions')->where('id', 'p1')->value('ip_address'))->toBeNull()
        ->and($user->fresh()->last_login_ip)->toBeNull();
});

it('keeps throttling on the raw IP whatever the mode', function () {
    ipMode('off');
    $user = staff('editor');
    app(Settings::class)->set('security.login.max_attempts', 2);
    foreach (range(1, 2) as $_) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/admin/login', ['email' => $user->email, 'password' => 'wrong']);
    }
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/admin/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->postJson('/admin/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(422);
});
