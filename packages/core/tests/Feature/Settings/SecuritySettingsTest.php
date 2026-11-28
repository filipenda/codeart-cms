<?php

use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;

it('has sensible defaults', function () {
    expect(app(Settings::class)->group('security'))->toBe([
        'security.2fa.mode' => 'required',
        'security.2fa.required_roles' => ['super-admin', 'admin'],
        'security.activity_log.retention_days' => 90,
        'security.ip_storage' => 'full',
        'security.login.decay_minutes' => 15,
        'security.login.max_attempts' => 5,
        'security.login.remember_days' => 30,
        'security.password.check_breached' => true,
        'security.password.min_length' => 10,
        'security.password.require_mixed_case' => true,
        'security.password.require_number' => true,
        'security.password.require_symbol' => false,
        'security.session.lifetime_minutes' => 120,
    ]);
});

it('enforces ranges', function (string $key, int $low, int $ok, int $high) {
    $settings = app(Settings::class);

    expect(fn () => $settings->set($key, $low))->toThrow(InvalidSettingValue::class)
        ->and(fn () => $settings->set($key, $high))->toThrow(InvalidSettingValue::class);
    $settings->set($key, $ok);
    expect($settings->get($key))->toBe($ok);
})->with([
    'min length' => ['security.password.min_length', 7, 8, 129],
    'session' => ['security.session.lifetime_minutes', 4, 5, 43201],
    'attempts' => ['security.login.max_attempts', 0, 1, 101],
    'decay' => ['security.login.decay_minutes', 0, 30, 1441],
    'retention' => ['security.activity_log.retention_days', 6, 365, 3651],
    'remember' => ['security.login.remember_days', 0, 14, 366],
]);

it('validates 2fa mode, roles and flags', function () {
    $settings = app(Settings::class);
    $settings->set('security.2fa.mode', 'required');
    $settings->set('security.2fa.required_roles', ['editor']);
    $settings->set('security.password.require_symbol', true);

    expect(fn () => $settings->set('security.2fa.mode', 'always'))->toThrow(InvalidSettingValue::class)
        ->and(fn () => $settings->set('security.2fa.required_roles', 'admin'))->toThrow(InvalidSettingValue::class)
        ->and(fn () => $settings->set('security.2fa.required_roles', [['admin']]))->toThrow(InvalidSettingValue::class)
        ->and(fn () => $settings->set('security.password.require_symbol', 'yes'))->toThrow(InvalidSettingValue::class)
        ->and($settings->get('security.2fa.mode'))->toBe('required')
        ->and($settings->get('security.2fa.required_roles'))->toBe(['editor']);
});
