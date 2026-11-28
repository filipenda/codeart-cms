<?php

namespace Codeart\Core\Console;

use Codeart\Core\Install\InstallerService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Installs the CMS from the command line, with the same service as the web wizard. Every input is a flag or an
 * environment variable (CODEART_INSTALL_<NAME>); what is missing is asked for when the terminal is interactive.
 */
class InstallCommand extends Command
{
    protected $signature = 'codeart:install
        {--name= : Site name}
        {--url= : Site address, e.g. https://example.com}
        {--timezone=Europe/Belgrade : Time zone}
        {--locales=sr,sr-Cyrl,en : Enabled content languages, comma separated}
        {--default-locale=sr : Default content language}
        {--admin-path=admin : Admin URL prefix}
        {--db-host=localhost}
        {--db-port=3306}
        {--db-name=}
        {--db-user=}
        {--db-password= : Prefer the environment variable CODEART_INSTALL_DB_PASSWORD (a flag stays in the shell history)}
        {--first-name=}
        {--last-name=}
        {--email=}
        {--admin-password= : Prefer CODEART_INSTALL_ADMIN_PASSWORD}
        {--generate-password : Make a password and print it once}
        {--modules= : Modules to enable, comma separated, or "all"}
        {--confirm-existing-db : The database already has tables and that is fine}
        {--force : Install again although the site is already installed}';

    protected $description = 'Install the CMS: .env, database, roles, settings, first super-admin and modules';

    public function handle(InstallerService $installer): int
    {
        if ($installer->state->installed()) {
            if (! $this->option('force')) {
                $this->components->error('This site is already installed. To install again (this changes the database you give) use --force.');

                return self::FAILURE;
            }
            $installer->state->clearMarker();
        }

        $requirements = $installer->requirements();
        foreach ($requirements as $item) {
            if ($item->required && ! $item->ok) {
                $this->components->error('Requirement not met: '.__('codeart::install.req.'.$item->key.'.title', $item->replace));
            }
        }
        if (! $installer->requirementsPass()) {
            return self::FAILURE;
        }

        $db = [
            'host' => (string) $this->value('db-host', 'localhost'),
            'port' => (int) $this->value('db-port', '3306'),
            'database' => (string) $this->value('db-name', required: 'Database name'),
            'username' => (string) $this->value('db-user', required: 'Database user'),
            'password' => (string) $this->value('db-password', secret: true, optional: true),
        ];
        $test = $installer->testDatabase($db);
        if (! $test->ok) {
            $this->components->error(__('codeart::install.db_error.'.$test->error));

            return self::FAILURE;
        }
        if ($test->tables > 0 && ! $this->option('confirm-existing-db')
            && ! ($this->input->isInteractive() && confirm(__('codeart::install.db.existing_confirm'), false))) {
            $this->components->error('The database is not empty. Use an empty database or add --confirm-existing-db.');

            return self::FAILURE;
        }

        $generated = null;
        try {
            $site = $installer->validateSite([
                'site_name' => $this->value('name', required: 'Site name'),
                'url' => $this->value('url', required: 'Site address'),
                'timezone' => $this->option('timezone'),
                'locales' => array_filter(array_map('trim', explode(',', (string) $this->option('locales')))),
                'default_locale' => $this->option('default-locale'),
                'admin_path' => $this->option('admin-path'),
            ]);
            if ($this->option('generate-password')) {
                $generated = $installer->generatePassword();
            }
            $admin = $installer->validateAdmin([
                'first_name' => $this->value('first-name', required: 'First name'),
                'last_name' => $this->value('last-name', required: 'Last name'),
                'email' => $this->value('email', required: 'E-mail'),
                'password' => $generated ?? $this->value('admin-password', secret: true, required: 'Password'),
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                $this->components->error($messages[0]);
            }

            return self::FAILURE;
        }

        $modules = $this->chosenModules($installer);
        if ($modules === null) {
            return self::FAILURE;
        }

        $installer->writeEnvironment($db, $site['url'], $site['site_name'], 'sr');
        $this->components->info('.env written.');

        $failed = false;
        foreach ($installer->run(['site' => $site, 'admin' => $admin, 'modules' => $modules]) as $result) {
            $label = __('codeart::install.run.'.$result->step);
            if ($result->ok) {
                $result->warning
                    ? $this->components->warn($label.' - '.__('codeart::install.run.'.$result->message))
                    : $this->components->info($label);

                continue;
            }
            $failed = true;
            $this->components->error($label.' - '.__('codeart::install.run.'.$result->message, ['module' => $result->detail ?? '']));
            $this->line('Fix the cause and run the same command again: every step can be repeated.');
        }
        if ($failed) {
            return self::FAILURE;
        }

        if ($generated !== null) {
            $this->components->warn('Generated password (shown only now, write it down): '.$generated);
        }
        $this->components->info('Installed. Admin: '.rtrim($site['url'], '/').'/'.$site['admin_path']);
        $this->line('Cron line: '.$installer->cronLine());

        return self::SUCCESS;
    }

    /** @return list<string>|null null = an unknown module was named */
    private function chosenModules(InstallerService $installer): ?array
    {
        $available = array_keys($installer->availableModules());
        $given = trim((string) $this->value('modules', optional: true));
        $names = $given === 'all' ? $available : array_values(array_filter(array_map('trim', explode(',', $given))));
        if ($unknown = array_diff($names, $available)) {
            $this->components->error('Not in this package: '.implode(', ', $unknown).'. Available: '.implode(', ', $available).'.');

            return null;
        }

        return $names;
    }

    /** Flag, then environment variable, then (interactive) a prompt. */
    private function value(string $option, ?string $required = null, bool $secret = false, bool $optional = false): ?string
    {
        $value = $this->option($option);
        if (! is_string($value) || $value === '') {
            $env = getenv('CODEART_INSTALL_'.strtoupper(str_replace('-', '_', $option)));
            $value = $env !== false ? $env : null;
        }
        if (($value === null || $value === '') && $this->input->isInteractive() && ! $optional) {
            $label = $required ?? $option;
            $value = $secret ? password($label) : text($label);
        }

        return $value === '' ? null : $value;
    }
}
