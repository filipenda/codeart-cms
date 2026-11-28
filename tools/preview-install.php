<?php

/*
 * Dev only: open the REAL installer wizard in a browser without touching the dev .env or the dev database.
 *
 *   ddev composer preview:install            prepare a throwaway copy of apps/starter, start it, print URL + key
 *   ddev composer preview:install:clean    stop it, delete the folder and drop the temporary database
 *
 * Runs inside the DDEV web container. The copy lives in a temporary folder (vendor is linked, not copied), has its own
 * install.key and an empty temporary database schema; it is served by PHP's built-in server on container port 8098,
 * which `.ddev/config.yaml` exposes as http://<project>.ddev.site:8099. Nothing is written into the repository.
 */

const PORT_INSIDE = 8098;
const PORT_OUTSIDE = 8099;

$root = dirname(__DIR__);
$starter = $root.'/apps/starter';
$dir = sys_get_temp_dir().'/codeart-install-preview';
$pidFile = $dir.'/preview.pid';
$clean = in_array('--clean', array_slice($argv, 1), true);

$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbUser = 'root';
$dbPass = 'root'; // DDEV's well-known default for the local database container; never a real secret.

function out(string $line): void
{
    fwrite(STDOUT, $line.PHP_EOL);
}

function rootPdo(string $host, string $port, string $user, string $pass): PDO
{
    return new PDO(sprintf('mysql:host=%s;port=%s', $host, $port), $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function removeDir(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }
    if (! is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            removeDir($path.'/'.$entry);
        }
    }
    rmdir($path);
}

function copyDir(string $from, string $to, array $skip): void
{
    mkdir($to, 0775, true);
    foreach (scandir($from) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || in_array($entry, $skip, true) || is_link($from.'/'.$entry)) {
            continue;
        }
        is_dir($from.'/'.$entry)
            ? copyDir($from.'/'.$entry, $to.'/'.$entry, [])
            : copy($from.'/'.$entry, $to.'/'.$entry);
    }
}

function stopServer(string $pidFile): void
{
    if (is_file($pidFile)) {
        $pid = (int) file_get_contents($pidFile);
        if ($pid > 1 && function_exists('posix_kill')) {
            posix_kill($pid, SIGTERM);
        } elseif ($pid > 1) {
            exec('kill '.$pid.' 2>/dev/null');
        }
    }
}

if ($clean) {
    stopServer($pidFile);
    $dropped = [];
    try {
        $pdo = rootPdo($dbHost, $dbPort, $dbUser, $dbPass);
        foreach ($pdo->query("SHOW DATABASES LIKE 'codeart\\_preview\\_%'")->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $pdo->exec('DROP DATABASE `'.str_replace('`', '', (string) $name).'`');
            $dropped[] = $name;
        }
    } catch (PDOException $e) {
        out('Could not reach the database: '.$e->getMessage());
        exit(1);
    }
    removeDir($dir);
    out('Preview removed: folder '.$dir.($dropped === [] ? '' : ', database '.implode(', ', $dropped)).'.');
    exit(0);
}

if (is_dir($dir)) {
    out('A preview already exists. Run "ddev composer preview:install:clean" first.');
    exit(1);
}

// 1. The throwaway application: a copy of the starter (no vendor, no .env, no state), vendor linked.
copyDir($starter, $dir, ['vendor', 'node_modules', '.env', 'tests', 'storage']);
symlink($starter.'/vendor', $dir.'/vendor');
foreach (['app/public', 'framework/sessions', 'framework/cache/data', 'framework/views', 'logs'] as $folder) {
    mkdir($dir.'/storage/'.$folder, 0775, true);
}
if (! is_dir($dir.'/bootstrap/cache')) {
    mkdir($dir.'/bootstrap/cache', 0775, true);
}
foreach (glob($dir.'/bootstrap/cache/*.php') ?: [] as $stale) {
    unlink($stale);
}

// 2. Its own key, printed here and nowhere else.
$key = strtoupper(implode('-', str_split(bin2hex(random_bytes(12)), 6)));
file_put_contents($dir.'/install.key', $key);

// 3. The temporary, empty database schema (the wizard is told its name below).
$database = 'codeart_preview_'.bin2hex(random_bytes(3));
rootPdo($dbHost, $dbPort, $dbUser, $dbPass)->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

// 4. A minimal .env pointing at the preview URL (the wizard rewrites it).
$host = getenv('DDEV_HOSTNAME') ?: 'codeart-cms.ddev.site';
$url = 'http://'.explode(',', $host)[0].':'.PORT_OUTSIDE;
file_put_contents($dir.'/.env', implode("\n", [
    'APP_NAME="CodeArt preview"', 'APP_ENV=local', 'APP_DEBUG=false', 'APP_URL='.$url, 'SESSION_DRIVER=file',
    'DB_CONNECTION=mariadb', 'DB_HOST='.$dbHost, 'DB_PORT='.$dbPort, 'DB_DATABASE='.$database, 'DB_USERNAME='.$dbUser, 'DB_PASSWORD='.$dbPass,
])."\n");
chmod($dir.'/.env', 0600);

// 5. Serve it (php -S needs a router so that real files are served and everything else goes to index.php).
file_put_contents($dir.'/public/router.php', <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__.$path)) {
    return false;
}
require __DIR__.'/index.php';
PHP);
$command = sprintf(
    'cd %s && PHP_CLI_SERVER_WORKERS=4 nohup php -S 0.0.0.0:%d -t public public/router.php > %s 2>&1 & echo $!',
    escapeshellarg($dir),
    PORT_INSIDE,
    escapeshellarg($dir.'/server.log'),
);
file_put_contents($pidFile, trim((string) shell_exec($command)));
usleep(700_000);

out('');
out('Install wizard preview (throwaway copy; the dev .env and dev database are untouched)');
out('  URL           '.$url.'/install');
out('  Install key   '.$key);
out('  Database      host '.$dbHost.'  port '.$dbPort.'  name '.$database.'  user '.$dbUser.'  password '.$dbPass.' (DDEV default)');
out('  Remove it     ddev composer preview:install:clean');
