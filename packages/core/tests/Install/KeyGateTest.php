<?php

use Codeart\Core\Install\InstallState;
use Illuminate\Support\Facades\Route;

it('redirects every page to the wizard while not installed', function () {
    Route::middleware('web')->get('/home-page', fn () => 'home');

    $this->get('/home-page')->assertRedirect('/install');
    $this->get('/anything')->assertRedirect('/install');
    $this->get('/install')->assertOk()->assertSee('install.key');
});

it('refuses a wrong key and accepts the right one', function () {
    $this->post('/install', ['key' => 'nope'])->assertSessionHasErrors('key');
    $this->get('/install/requirements')->assertRedirect(route('install.key'));

    $this->post('/install', ['key' => 'KEY-FOR-THE-TEST-0123456789'])->assertRedirect(route('install.requirements'));
    $this->get('/install/requirements')->assertOk();
});

it('throttles wrong keys, even for the right key afterwards', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/install', ['key' => 'wrong-'.$i])->assertSessionHasErrors('key');
    }

    $this->post('/install', ['key' => 'KEY-FOR-THE-TEST-0123456789'])->assertSessionHasErrors('key');
    $this->get('/install/requirements')->assertRedirect(route('install.key'));
    $this->get('/install')->assertSee(__('codeart::install.key.locked', ['minutes' => 10]), false);
});

it('says so in plain words when install.key is missing, and never lets anything in', function () {
    unlink($this->dir.'/install.key');

    $this->get('/install')->assertOk()->assertSee(__('codeart::install.key.missing'));
    $this->post('/install', ['key' => ''])->assertSessionHasErrors('key');
    $this->post('/install', ['key' => 'anything'])->assertSessionHasErrors('key');
    $this->get('/install/requirements')->assertRedirect(route('install.key'));
});

it('is a 404 once the marker exists', function () {
    app(InstallState::class)->markInstalled();

    $this->get('/install')->assertNotFound();
    $this->post('/install', ['key' => 'KEY-FOR-THE-TEST-0123456789'])->assertNotFound();
    $this->get('/install/database')->assertNotFound();
});

it('is not there at all where the installer is switched off', function () {
    config(['codeart.install.enabled' => false]);

    expect(InstallState::enabled())->toBeFalse()
        ->and(app(InstallState::class)->pending())->toBeFalse();
});
