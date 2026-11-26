<?php

namespace Codeart\Core\Tests\Support;

use Codeart\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Fast, equivalent replacement for `migrate:fresh` before every test.
 *
 * The first test of a kind (and every change of kind) migrates from scratch and remembers which tables and which
 * `migrations` rows are the baseline. Later tests only undo what the previous test left behind: tables it created
 * (module tables, `users`, ...) are dropped, baseline tables that hold rows are truncated, extra `migrations` rows
 * are removed. The result is the same empty, fully migrated schema, without rebuilding ~30 tables each time.
 * Tests (not production code) only; any doubt falls back to a real `migrate:fresh`.
 */
final class TestDatabase
{
    /** @var array<string, true> table name => true */
    private static array $baseline = [];

    private static int $baselineMigrationId = 0;

    private static ?string $kind = null;

    /** Database name for this process: parallel workers (paratest sets TEST_TOKEN) each get their own, created on demand. */
    public static function name(): string
    {
        $name = getenv('DB_DATABASE') ?: 'test';
        $token = getenv('TEST_TOKEN') ?: ($_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? null);

        return $token ? $name.'_'.$token : $name;
    }

    public static function ensureExists(): void
    {
        static $done = false;
        if ($done || self::name() === (getenv('DB_DATABASE') ?: 'test')) {
            return;
        }
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s', getenv('DB_HOST') ?: 'db', getenv('DB_PORT') ?: '3306'),
            getenv('DB_USERNAME') ?: 'root',
            getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'root',
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.self::name().'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $done = true;
    }

    /** Leave the database fully migrated and empty. `$kind` names the set of migrations (they differ between test groups). */
    public static function reset(TestCase $test, string $kind): void
    {
        if (self::$kind === $kind && self::restore()) {
            return;
        }

        $test->artisan('migrate:fresh', ['--force' => true]);

        self::$kind = $kind;
        self::$baseline = array_fill_keys(array_keys(self::status()), true);
        self::$baselineMigrationId = (int) DB::table('migrations')->max('id');
    }

    /** @return array<string, object> tables of the current database only (Schema::getTables() would list every schema) */
    private static function status(): array
    {
        return collect(DB::select('SHOW TABLE STATUS'))->keyBy('Name')->all();
    }

    /** @return bool false when the schema cannot be trusted any more (a baseline table vanished): rebuild it */
    private static function restore(): bool
    {
        $status = self::status();
        if (array_diff_key(self::$baseline, $status) !== []) {
            return false;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($status as $name => $row) {
            if (! isset(self::$baseline[$name])) {
                DB::statement('DROP TABLE `'.$name.'`');
            } elseif ($name === 'migrations') {
                DB::table('migrations')->where('id', '>', self::$baselineMigrationId)->delete();
            } elseif ((int) $row->Auto_increment > 1 || DB::table($name)->exists()) {
                DB::statement('TRUNCATE TABLE `'.$name.'`');
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        return true;
    }
}
