<?php

namespace Codeart\Core\Install;

use PDO;
use PDOException;

/** Opens a plain PDO connection with what the person typed (not the app's own, which does not exist yet) and explains failures. */
class DatabaseTester
{
    public function test(string $host, int $port, string $database, string $user, string $password): DatabaseResult
    {
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database), $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $e) {
            return new DatabaseResult(false, $this->errorKey($e));
        }

        try {
            $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            // The installer must be able to create tables: try it with a throwaway one.
            $probe = 'codeart_install_probe_'.bin2hex(random_bytes(4));
            $pdo->exec("CREATE TABLE `{$probe}` (id INT)");
            $pdo->exec("DROP TABLE `{$probe}`");
        } catch (PDOException) {
            return new DatabaseResult(false, 'privileges');
        }

        return new DatabaseResult(true, null, $tables);
    }

    private function errorKey(PDOException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? $e->getCode());

        return match (true) {
            $code === 1045 => 'credentials',
            $code === 1049 => 'unknown_database',
            $code === 1044, $code === 1142 => 'privileges',
            in_array($code, [2002, 2003, 2005, 2006], true) => 'unreachable',
            default => 'other',
        };
    }
}
