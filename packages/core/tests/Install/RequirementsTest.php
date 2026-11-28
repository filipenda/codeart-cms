<?php

use Codeart\Core\Install\Requirements;

beforeEach(fn () => $this->withSession(['installer.key_ok' => true]));

function states(): array
{
    $list = [];
    foreach (app(Requirements::class)->check() as $item) {
        $list[$item->key.($item->replace['name'] ?? '')] = $item;
    }

    return $list;
}

it('is all green on a good server', function () {
    $this->fakeServer();

    expect(app(Requirements::class)->passes())->toBeTrue();
    $this->get('/install/requirements')->assertOk()->assertDontSee('data-state="bad"', false)->assertSee(route('install.database'));
});

it('turns each missing thing red and blocks the next screen', function (array $state, string $key) {
    $this->fakeServer($state);

    $item = states()[$key];
    expect($item->ok)->toBeFalse()->and($item->required)->toBeTrue()->and(app(Requirements::class)->passes())->toBeFalse();

    $this->get('/install/requirements')->assertOk()->assertSee('data-state="bad"', false)->assertSee(__('codeart::install.req.blocked'))->assertDontSee(route('install.database').'"', false);
    $this->get('/install/database')->assertRedirect(route('install.requirements'));
})->with([
    'old PHP' => [['php' => '8.3.9'], 'php'],
    'no mbstring' => [['missing' => ['mbstring']], 'extmbstring'],
    'no pdo_mysql' => [['missing' => ['pdo_mysql']], 'extpdo_mysql'],
    'no zip' => [['missing' => ['zip']], 'extzip'],
    'no curl' => [['missing' => ['curl']], 'extcurl'],
    'no image library' => [['missing' => ['gd', 'imagick']], 'image'],
    'storage read-only' => [['readonly' => ['storage']], 'storage'],
    'cache read-only' => [['readonly' => ['bootstrap/cache']], 'cache'],
    'env not writable' => [['readonly' => ['.env']], 'env'],
    'layout broken' => [['absent' => ['vendor/autoload.php']], 'layout'],
    'low memory' => [['memory' => 64 * 1024 * 1024], 'memory'],
]);

it('accepts one image library of the two', function () {
    $this->fakeServer(['missing' => ['gd']]);

    expect(states()['image']->ok)->toBeTrue();
});

it('only warns about debug mode', function () {
    $this->fakeServer(['debug' => true]);

    $debug = states()['debug'];
    expect($debug->ok)->toBeFalse()->and($debug->required)->toBeFalse()->and(app(Requirements::class)->passes())->toBeTrue();
    $this->get('/install/requirements')->assertSee('data-state="warn"', false)->assertSee(route('install.database'));
});

it('checks the real server too (PHP 8.4.1 minimum, the extensions the project needs)', function () {
    $items = app(Requirements::class)->check();

    expect(collect($items)->firstWhere('key', 'php')->ok)->toBeTrue()
        ->and(collect($items)->where('key', 'ext')->pluck('replace.name')->all())->toContain('pdo_mysql', 'mbstring', 'openssl', 'intl', 'fileinfo', 'zip', 'ctype', 'json', 'tokenizer', 'xml', 'curl');
});
