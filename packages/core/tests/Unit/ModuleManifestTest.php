<?php

use Codeart\Core\Modules\InvalidManifest;
use Codeart\Core\Modules\ModuleManifest;

function validManifest(array $override = []): array
{
    return array_replace([
        'name' => 'example',
        'package' => 'codeart/module-example',
        'title' => 'Example',
        'description' => 'Demo module.',
        'requires' => ['pages' => '^1.0'],
        'permissions' => ['example.view', 'example.edit'],
        'menu' => [['label' => 'Example', 'route' => 'example.index', 'permission' => 'example.view', 'icon' => 'cube', 'order' => 50]],
        'migrations' => 'database/migrations',
        'provider' => 'Codeart\\Example\\ExampleServiceProvider',
        'license' => 'proprietary',
    ], $override);
}

it('parses a valid manifest into typed values', function () {
    $m = ModuleManifest::fromArray(validManifest());

    expect($m->name)->toBe('example')
        ->and($m->package)->toBe('codeart/module-example')
        ->and($m->requires)->toBe(['pages' => '^1.0'])
        ->and($m->permissions)->toBe(['example.view', 'example.edit'])
        ->and($m->menu)->toHaveCount(1)
        ->and($m->menu[0]->permission)->toBe('example.view')
        ->and($m->menu[0]->module)->toBe('example')
        ->and($m->migrations)->toBe('database/migrations')
        ->and($m->license)->toBe('proprietary');
});

it('accepts a minimal manifest (optional fields default to empty)', function () {
    $m = ModuleManifest::fromArray([
        'name' => 'tiny', 'package' => 'codeart/module-tiny', 'title' => 'Tiny',
        'description' => 'x', 'provider' => 'A\\B',
    ]);

    expect($m->requires)->toBe([])->and($m->permissions)->toBe([])->and($m->menu)->toBe([])->and($m->migrations)->toBeNull();
});

it('reads a manifest from a file', function () {
    $m = ModuleManifest::fromFile(__DIR__.'/../Fixtures/modules/alpha/module.json');

    expect($m->name)->toBe('alpha')->and($m->menu)->toHaveCount(2);
});

it('rejects invalid manifests with a clear message', function (array $override, string $message) {
    expect(fn () => ModuleManifest::fromArray(validManifest($override)))
        ->toThrow(InvalidManifest::class, $message);
})->with([
    'missing name' => [['name' => null], '"name" is required'],
    'bad name' => [['name' => 'Bad Name'], 'lowercase slug'],
    'bad package' => [['package' => 'nonsense'], 'vendor/name'],
    'empty title' => [['title' => ' '], '"title" is required'],
    'no provider' => [['provider' => null], '"provider" is required'],
    'unknown field' => [['version' => '1.0.0'], 'unknown field "version"'],
    'requires as list' => [['requires' => ['pages']], '"requires" must map'],
    'permission without prefix' => [['permissions' => ['view']], 'starting with "example."'],
    'permission wrong module' => [['permissions' => ['other.view']], 'starting with "example."'],
    'menu not object' => [['menu' => ['x']], 'menu item #0 must be an object'],
    'menu missing label' => [['menu' => [['route' => 'a', 'icon' => 'i', 'order' => 1]]], '"label" is required'],
    'menu order string' => [['menu' => [['label' => 'a', 'route' => 'a', 'icon' => 'i', 'order' => '1']]], '"order" is required and must be an integer'],
    'menu undeclared permission' => [['menu' => [['label' => 'a', 'route' => 'a', 'icon' => 'i', 'order' => 1, 'permission' => 'example.nope']]], 'not declared'],
    'absolute migrations' => [['migrations' => '/etc'], 'relative path'],
    'migrations escape' => [['migrations' => '../x'], 'relative path'],
]);

it('rejects missing and malformed files', function () {
    expect(fn () => ModuleManifest::fromFile('/nope/module.json'))->toThrow(InvalidManifest::class, 'not found');

    $file = tempnam(sys_get_temp_dir(), 'mod');
    file_put_contents($file, '{broken');
    expect(fn () => ModuleManifest::fromFile($file))->toThrow(InvalidManifest::class, 'not valid JSON');
    unlink($file);
});
