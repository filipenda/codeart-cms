<?php

namespace Codeart\Core\Tests;

use Codeart\Core\Install\Probe;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * The whole installation runs in a temporary directory against a throwaway database: never the dev .env or the dev database.
 */
abstract class InstallTestCase extends AuthTestCase
{
    public string $dir = '';

    public string $database = '';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->dir = sys_get_temp_dir().'/codeart-install-'.bin2hex(random_bytes(5));
        foreach (['storage/app', 'storage/framework/sessions', 'storage/framework/cache/data', 'bootstrap/cache', 'vendor'] as $folder) {
            mkdir($this->dir.'/'.$folder, 0775, true);
        }
        file_put_contents($this->dir.'/vendor/autoload.php', '<?php');
        file_put_contents($this->dir.'/bootstrap/app.php', '<?php');
        file_put_contents($this->dir.'/install.key', 'KEY-FOR-THE-TEST-0123456789');

        $this->database = 'codeart_inst_'.bin2hex(random_bytes(4));
        $this->rootPdo()->exec('CREATE DATABASE `'.$this->database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $app['config']->set('database.connections.mariadb.database', $this->database);
        $app['config']->set('codeart.install.enabled', true);
        $app['config']->set('codeart.install.base_path', $this->dir);
        $app['config']->set('session.files', $this->dir.'/storage/framework/sessions');
        $app['config']->set('cache.stores.file.path', $this->dir.'/storage/framework/cache/data');
        $app['config']->set('app.debug', false);
        $app['config']->set('app.name', 'Test site');
    }

    protected function tearDown(): void
    {
        $dir = $this->dir;
        $database = $this->database;
        if ($this->app !== null) {
            DB::disconnect();
            DB::purge();
        }
        parent::tearDown();
        $this->rootPdo()->exec('DROP DATABASE IF EXISTS `'.$database.'`');
        (new Filesystem)->deleteDirectory($dir);
    }

    protected function rootPdo(): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%s', getenv('DB_HOST') ?: 'db', getenv('DB_PORT') ?: '3306'),
            getenv('DB_USERNAME') ?: 'root',
            getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'root',
        );
    }

    /** @return array{host: string, port: int, database: string, username: string, password: string} */
    public function dbInput(array $override = []): array
    {
        return array_merge([
            'host' => getenv('DB_HOST') ?: 'db',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => $this->database,
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : 'root',
        ], $override);
    }

    /** Pretend the server looks like this (everything fine unless said otherwise). */
    public function fakeServer(array $state = []): void
    {
        $this->app->bind(Probe::class, fn () => new class($state, $this->dir) extends Probe
        {
            public function __construct(private array $state, private string $dir) {}

            public function phpVersion(): string
            {
                return $this->state['php'] ?? '8.5.0';
            }

            public function extensionLoaded(string $name): bool
            {
                return ! in_array($name, $this->state['missing'] ?? [], true);
            }

            public function writable(string $path): bool
            {
                return ! in_array(str_replace($this->dir.'/', '', $path), $this->state['readonly'] ?? [], true);
            }

            public function exists(string $path): bool
            {
                return ! in_array(str_replace($this->dir.'/', '', $path), $this->state['absent'] ?? [], true) && (file_exists($path) || str_ends_with($path, '.env'));
            }

            public function memoryLimit(): int
            {
                return $this->state['memory'] ?? -1;
            }

            public function debugOn(): bool
            {
                return $this->state['debug'] ?? false;
            }
        });
    }
}
