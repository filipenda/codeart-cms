<?php

use Codeart\Core\Auth\Admin;
use Codeart\Core\Install\InstallerService;
use Codeart\Core\Modules\ModuleRecord;
use Codeart\Core\Settings\Settings;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->fakeServer();
    useModules(['alpha' => '1.0.0', 'beta' => '1.0.0']);
});

function assertInstalled(object $test, array $modules): void
{
    $users = DB::table('users')->get();
    expect($users)->toHaveCount(1)->and($users[0]->email)->toBe('pera@example.test');
    $user = Admin::findByEmail('pera@example.test');
    expect($user->hasRole('super-admin'))->toBeTrue()
        ->and($user->first_name)->toBe('Pera')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Role::pluck('name')->sort()->values()->all())->toBe(['admin', 'editor', 'member', 'super-admin'])
        ->and(ModuleRecord::query()->where('enabled', true)->pluck('name')->sort()->values()->all())->toBe($modules)
        ->and(file_exists($test->dir.'/storage/app/installed'))->toBeTrue()
        ->and(file_exists($test->dir.'/install.key'))->toBeFalse();

    $settings = app(Settings::class);
    $settings->flush();
    expect($settings->get('auth.admin_path'))->toBe('upravljanje')
        ->and($settings->get('site.timezone'))->toBe('Europe/Belgrade')
        ->and($settings->get('locales.default'))->toBe('sr');
}

it('installs through the wizard, end to end, in a temporary directory and database', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->message.json_encode($e->context);
    });
    Route::middleware('web')->get('/home-page', fn () => 'home');
    $this->get('/home-page')->assertRedirect('/install');

    walkWizard($this, ['beta']); // beta needs alpha: it comes along

    $response = $this->post('/install/run');
    $response->assertOk()->assertSee(__('codeart::install.done.title'))
        ->assertSee('* * * * * php '.$this->dir.'/artisan schedule:run', false)
        ->assertSee('https://example.test/upravljanje'.'', false)
        ->assertDontSee('Str0ng-Install-Pass');

    assertInstalled($this, ['alpha', 'beta']);

    // The .env was written in the temp directory only, with the session switched to the database at the end.
    $env = file_get_contents($this->dir.'/.env');
    expect($env)->toContain('SESSION_DRIVER=database', 'APP_ENV=production');

    // Locked: the wizard is gone, and an emptied database does not reopen it.
    $this->get('/install')->assertNotFound();
    $this->post('/install', ['key' => 'KEY-FOR-THE-TEST-0123456789'])->assertNotFound();
    $this->get('/home-page')->assertOk()->assertSee('home');
    DB::table('users')->delete();
    $this->get('/install/database')->assertNotFound();

    // No secret in any log line.
    $log = implode("\n", $logged);
    expect($log)->not->toContain('Str0ng-Install-Pass')->not->toContain(getenv('DB_PASSWORD') ?: 'root-secret-never');
});

it('does not create the account twice when the run is repeated', function () {
    walkWizard($this);

    $this->post('/install/run')->assertOk();
    $this->post('/install/run')->assertNotFound(); // locked after the first success

    expect(DB::table('users')->count())->toBe(1);
});

it('shows each failed step in plain words and a retry continues where it stopped', function () {
    // A migration that fails once (as a wrong privilege or a lost connection would).
    $folder = $this->dir.'/failing';
    mkdir($folder);
    file_put_contents($folder.'/2099_01_01_000000_fail_once.php', <<<PHP
        <?php
        use Illuminate\Database\Migrations\Migration;
        return new class extends Migration {
            public function up(): void
            {
                if (is_file('{$this->dir}/fail-now')) {
                    throw new RuntimeException('boom');
                }
            }
        };
        PHP);
    app('migrator')->path($folder);
    touch($this->dir.'/fail-now');

    walkWizard($this);
    $failed = $this->post('/install/run');
    $failed->assertOk()->assertSee('data-ok="0"', false)->assertSee(__('codeart::install.run.failed'))->assertSee(__('codeart::install.run.retry'));
    expect(file_exists($this->dir.'/storage/app/installed'))->toBeFalse()
        ->and(file_exists($this->dir.'/install.key'))->toBeTrue()
        ->and(DB::table('users')->count())->toBe(0);

    unlink($this->dir.'/fail-now');
    $this->post('/install/run')->assertOk()->assertSee(__('codeart::install.done.title'));

    expect(DB::table('users')->count())->toBe(1)
        ->and(file_exists($this->dir.'/storage/app/installed'))->toBeTrue();
});

it('lets only the modules of the package be chosen', function () {
    walkWizard($this, ['alpha', 'not-in-this-package']);
    $this->post('/install/run')->assertOk();

    assertInstalled($this, ['alpha']);
});

it('refuses weak or mismatching passwords and a wrong site form', function () {
    $this->withSession(['installer.key_ok' => true, 'installer.db_ok' => true, 'installer.site' => ['x' => 1]]);

    $this->post('/install/admin', ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.test', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    $this->post('/install/admin', ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.test', 'password' => 'Str0ng-Install-Pass', 'password_confirmation' => 'other'])->assertSessionHasErrors('password');
    $this->post('/install/site', ['site_name' => '', 'url' => 'not a url', 'timezone' => 'Mars/Base', 'locales' => ['sr-Cyrl'], 'default_locale' => 'en', 'admin_path' => 'api'])
        ->assertSessionHasErrors(['site_name', 'url', 'timezone', 'default_locale', 'admin_path']);
});

it('generates a password that passes the rules and shows it on the screen once', function () {
    $this->withSession(['installer.key_ok' => true, 'installer.db_ok' => true, 'installer.site' => ['x' => 1]]);

    $page = $this->get('/install/admin?generate=1')->assertOk()->assertSee('data-generated-password', false);
    $generated = app(InstallerService::class)->generatePassword();

    expect(fn () => app(InstallerService::class)->validateAdmin(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.test', 'password' => $generated]))->not->toThrow(Exception::class);
    // The password is in the form only (hidden field), not in the session before the form is posted.
    expect(session('installer.admin'))->toBeNull();
});

it('installs from the command line with the same result', function () {
    Artisan::call('codeart:install', [
        '--name' => 'Moj sajt', '--url' => 'https://example.test', '--timezone' => 'Europe/Belgrade',
        '--admin-path' => 'upravljanje',
        '--db-host' => $this->dbInput()['host'], '--db-port' => $this->dbInput()['port'], '--db-name' => $this->database,
        '--db-user' => $this->dbInput()['username'], '--db-password' => $this->dbInput()['password'],
        '--first-name' => 'Pera', '--last-name' => 'Perić', '--email' => 'pera@example.test',
        '--admin-password' => 'Str0ng-Install-Pass', '--modules' => 'beta', '--no-interaction' => true,
    ]);

    assertInstalled($this, ['alpha', 'beta']);
    expect(Artisan::output())->not->toContain('Str0ng-Install-Pass')
        ->and(substr(sprintf('%o', fileperms($this->dir.'/.env')), -4))->toBe('0600');
});

it('prints a generated password once in the CLI and accepts the secrets from the environment', function () {
    putenv('CODEART_INSTALL_DB_PASSWORD='.$this->dbInput()['password']);
    try {
        $this->artisan('codeart:install', [
            '--name' => 'Moj sajt', '--url' => 'https://example.test', '--admin-path' => 'upravljanje',
            '--db-host' => $this->dbInput()['host'], '--db-name' => $this->database, '--db-user' => $this->dbInput()['username'],
            '--first-name' => 'Pera', '--last-name' => 'Perić', '--email' => 'pera@example.test',
            '--generate-password' => true, '--no-interaction' => true,
        ])->expectsOutputToContain('Generated password')->assertSuccessful();
    } finally {
        putenv('CODEART_INSTALL_DB_PASSWORD');
    }

    assertInstalled($this, []);
});

it('refuses to install again from the CLI without --force', function () {
    walkWizard($this);
    $this->post('/install/run')->assertOk();

    $this->artisan('codeart:install', ['--no-interaction' => true])->assertFailed();
    expect(DB::table('users')->count())->toBe(1);
});

it('fails the CLI clearly when something is missing or wrong', function () {
    $this->artisan('codeart:install', ['--db-name' => $this->database, '--db-user' => 'root', '--db-password' => 'wrong-pass', '--no-interaction' => true])->assertFailed();
    expect(file_exists($this->dir.'/.env'))->toBeFalse();
});
