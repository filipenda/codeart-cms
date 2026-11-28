<?php

namespace Codeart\Core\Install;

use Codeart\Core\Auth\Actions\CreateAdminUser;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Auth\PasswordRules;
use Codeart\Core\Modules\Actions\EnableModule;
use Codeart\Core\Modules\InstalledModule;
use Codeart\Core\Modules\ModuleException;
use Codeart\Core\Modules\ModuleRegistry;
use Codeart\Core\Permissions\CreateDefaultRoles;
use Codeart\Core\Settings\Settings;
use DateTimeZone;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * The installation, step by step. Used by the web wizard and by `codeart:install`, so both give the same result.
 * Every run step can be repeated safely (a retry after a failure continues where it stopped).
 */
class InstallerService
{
    /** @var list<string> */
    public const array STEPS = ['migrate', 'roles', 'settings', 'admin', 'modules', 'storage', 'finish'];

    public function __construct(
        public readonly InstallPaths $paths,
        public readonly InstallState $state,
        public readonly InstallKey $key,
        private readonly EnvWriter $env,
        private readonly DatabaseTester $database,
        private readonly Requirements $requirements,
        private readonly ModuleRegistry $modules,
        private readonly PasswordRules $passwords,
    ) {}

    /** @return list<Requirement> */
    public function requirements(): array
    {
        return $this->requirements->check();
    }

    public function requirementsPass(): bool
    {
        return $this->requirements->passes();
    }

    // -- database ----------------------------------------------------------------------------------------------------

    /** @param array{host: string, port: int|string, database: string, username: string, password: string} $db */
    public function testDatabase(array $db): DatabaseResult
    {
        return $this->database->test($db['host'], (int) $db['port'], $db['database'], $db['username'], $db['password']);
    }

    /**
     * Writes .env (keeps an APP_KEY that already exists: sessions are signed with it).
     *
     * @param  array{host: string, port: int|string, database: string, username: string, password: string}  $db
     */
    public function writeEnvironment(array $db, string $url, string $siteName, string $locale = 'sr'): void
    {
        $existing = $this->env->read();
        $key = $existing['APP_KEY'] ?? '';
        if ($key === '') {
            $key = (string) config('app.key') !== '' ? (string) config('app.key') : 'base64:'.base64_encode(random_bytes(32));
        }

        $this->env->write([
            'APP_NAME' => $siteName,
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'APP_KEY' => $key,
            'APP_URL' => rtrim($url, '/'),
            'APP_LOCALE' => $locale,
            'APP_FALLBACK_LOCALE' => 'sr',
            'LOG_CHANNEL' => 'single',
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => 'mariadb',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (int) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'],
            // Shared hosting: files until the tables exist, no queue worker (jobs wait in the database, cron runs them), cache in files.
            'SESSION_DRIVER' => $existing['SESSION_DRIVER'] ?? 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'database',
            'MAIL_MAILER' => $existing['MAIL_MAILER'] ?? 'log',
        ]);
    }

    /** Point this process at the database written to .env (the web wizard runs the next requests with it already). */
    public function applyDatabase(): void
    {
        $values = $this->env->read();
        if (! isset($values['DB_DATABASE'])) {
            return;
        }
        $connection = (string) config('database.default');
        foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_DATABASE', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $key => $name) {
            if (isset($values[$name])) {
                config(["database.connections.{$connection}.{$key}" => $values[$name]]);
            }
        }
        DB::purge($connection);
    }

    // -- input -------------------------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function siteDefaults(string $url): array
    {
        return [
            'site_name' => '',
            'url' => $url,
            'timezone' => 'Europe/Belgrade',
            'locales' => ['sr', 'sr-Cyrl', 'en'],
            'default_locale' => 'sr',
            'admin_path' => 'admin',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> validated site data (throws ValidationException)
     */
    public function validateSite(array $input): array
    {
        $available = array_keys((array) config('codeart.locales.available', []));
        $input['locales'] = array_values((array) ($input['locales'] ?? []));
        $input['admin_path'] = strtolower(trim((string) ($input['admin_path'] ?? ''), '/ '));

        $data = Validator::make($input, [
            'site_name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:http,https', 'max:190'],
            'timezone' => ['required', 'string', 'in:'.implode(',', DateTimeZone::listIdentifiers())],
            'locales' => ['required', 'array', 'min:1'],
            'locales.*' => ['string', 'in:'.implode(',', $available)],
            'default_locale' => ['required', 'string', 'in:'.implode(',', $available)],
            'admin_path' => ['required', 'not_in:api,storage,vendor,livewire,up,build,assets,install,css,js,images,img,fonts', 'regex:/^[a-z0-9][a-z0-9_-]{0,63}$/'],
        ], [], __('codeart::install.attributes'))->after(function ($validator) use ($input): void {
            if (! in_array($input['default_locale'] ?? null, $input['locales'], true)) {
                $validator->errors()->add('default_locale', __('codeart::install.errors.default_locale'));
            }
            foreach ($input['locales'] as $locale) {
                $source = config("codeart.locales.available.{$locale}.derived_from");
                if (is_string($source) && ! in_array($source, $input['locales'], true)) {
                    $validator->errors()->add('locales', __('codeart::install.errors.derived_locale', ['locale' => $locale, 'source' => $source]));
                }
            }
        })->validate();

        $data['url'] = rtrim($data['url'], '/');

        return $data;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{first_name: string, last_name: string, email: string, password: string}
     */
    public function validateAdmin(array $input): array
    {
        /** @var array{first_name: string, last_name: string, email: string, password: string} */
        return Validator::make($input, [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:filter', 'max:190'],
            'password' => $this->passwords->rules(),
        ], [], __('codeart::install.attributes'))->validate();
    }

    /** A strong password that passes the configured rules (shown once, never stored by the installer in plain text). */
    public function generatePassword(): string
    {
        $sets = ['abcdefghijkmnopqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '-_.!*'];
        $all = implode('', $sets);
        $chars = array_map(fn (string $set): string => $set[random_int(0, strlen($set) - 1)], $sets);
        while (count($chars) < 20) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);

        return implode('', $chars);
    }

    /** @return array<string, InstalledModule> modules in the package that can be switched on */
    public function availableModules(): array
    {
        return $this->modules->installed();
    }

    // -- run ---------------------------------------------------------------------------------------------------------

    /**
     * Runs every step in order and stops at the first failure.
     *
     * @param  array{site: array<string, mixed>, admin: array{first_name: string, last_name: string, email: string, password: string}, modules: list<string>}  $input
     * @return list<StepResult>
     */
    public function run(array $input): array
    {
        $results = [];
        foreach (self::STEPS as $step) {
            try {
                $result = $this->{'step'.ucfirst($step)}($input);
            } catch (Throwable $e) {
                report($e);
                $result = new StepResult($step, false, 'failed', detail: class_basename($e));
            }
            $results[] = $result;
            if (! $result->ok) {
                break;
            }
        }

        return $results;
    }

    /** @param array<string, mixed> $input */
    private function stepMigrate(array $input): StepResult
    {
        $this->applyDatabase();
        Artisan::call('migrate', ['--force' => true]);

        return new StepResult('migrate', true);
    }

    /** @param array<string, mixed> $input */
    private function stepRoles(array $input): StepResult
    {
        app(CreateDefaultRoles::class)();

        return new StepResult('roles', true);
    }

    /** @param array<string, mixed> $input */
    private function stepSettings(array $input): StepResult
    {
        $site = $input['site'];
        $settings = app(Settings::class);
        $settings->flush();
        $settings->setMany([
            'site.timezone' => $site['timezone'],
            'locales.enabled' => array_values($site['locales']),
            'locales.default' => $site['default_locale'],
            'auth.admin_path' => $site['admin_path'],
            'mail.from_name' => $site['site_name'],
        ]);

        return new StepResult('settings', true);
    }

    /** @param array<string, mixed> $input */
    private function stepAdmin(array $input): StepResult
    {
        $admin = $input['admin'];
        $existing = Admin::findByEmail($admin['email']);
        if ($existing !== null && method_exists($existing, 'hasRole') && $existing->hasRole('super-admin')) {
            return new StepResult('admin', true); // an earlier try already made it
        }

        app(CreateAdminUser::class)(
            trim($admin['first_name'].' '.$admin['last_name']),
            $admin['email'],
            $admin['password'],
            ['super-admin'],
            null,
            ['email_verified_at' => now(), 'first_name' => $admin['first_name'], 'last_name' => $admin['last_name']],
        );

        return new StepResult('admin', true);
    }

    /** @param array<string, mixed> $input */
    private function stepModules(array $input): StepResult
    {
        foreach ($input['modules'] as $name) {
            try {
                $this->enableWithDependencies($name);
            } catch (ModuleException) {
                return new StepResult('modules', false, 'module_failed', detail: $name);
            }
        }

        return new StepResult('modules', true);
    }

    private function enableWithDependencies(string $name): void
    {
        $module = $this->modules->find($name) ?? throw new ModuleException("Module {$name} is not in this package.");
        foreach (array_keys($module->manifest->requires) as $required) {
            $this->enableWithDependencies($required);
        }
        app(EnableModule::class)($name);
    }

    /** @param array<string, mixed> $input */
    private function stepStorage(array $input): StepResult
    {
        try {
            $code = Artisan::call('storage:link');
        } catch (Throwable) {
            $code = 1;
        }

        // Some hosts forbid symbolic links; the site works, only uploaded images are not shown until support links the folder.
        return $code === 0 ? new StepResult('storage', true) : new StepResult('storage', true, 'storage_link', warning: true);
    }

    /** @param array<string, mixed> $input */
    private function stepFinish(array $input): StepResult
    {
        // From now on sessions live in the database (their table exists).
        $this->env->write(['SESSION_DRIVER' => 'database']);
        $this->state->markInstalled();
        $this->key->delete();
        is_file($temporary = $this->paths->storage().'/app/installer.key') && unlink($temporary);

        return new StepResult('finish', true);
    }

    /** The cron line for cPanel (the path is the application folder of this installation). */
    public function cronLine(): string
    {
        return '* * * * * php '.$this->paths->base.'/artisan schedule:run >> /dev/null 2>&1';
    }
}
