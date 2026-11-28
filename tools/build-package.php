<?php

/*
 * Builds the client ZIP: a ready-to-upload package for hosting without a terminal (cPanel File Manager / FTP).
 *
 *   php tools/build-package.php [--out=build] [--version=1.2.3]
 *
 * Needs on the build machine: PHP 8.4+ with the zip extension, Composer. No Node (assets are already built and committed).
 * The ZIP holds two folders for the client's home directory:
 *   cms/          the application (OUTSIDE the web root): vendor, config, storage, install.key, VERSION
 *   public_html/  what the web server shows: index.php, .htaccess, assets
 * public_html/index.php finds the application through ONE marked setting ($applicationPath).
 * No .env: the install wizard writes it. install.key is fresh and random for every build. The ZIP is a build artifact
 * (git-ignored), never committed. The last output lines are the file path and its SHA-256.
 */

$root = dirname(__DIR__);
$options = getopt('', ['out::', 'version::', 'keep-stage']);
$out = rtrim((string) ($options['out'] ?? $root.'/build'), '/');
$version = (string) ($options['version'] ?? trim((string) shell_exec('git -C '.escapeshellarg($root).' describe --tags --always 2>/dev/null')) ?: 'dev');
$version = preg_replace('/[^A-Za-z0-9._-]/', '-', $version) ?: 'dev';

function fail(string $message): never
{
    fwrite(STDERR, "build-package: {$message}\n");
    exit(1);
}

function say(string $message): void
{
    echo $message."\n";
}

function run(string $command, ?string $cwd = null, array $env = []): void
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env === [] ? null : array_merge(getenv(), $env));
    if (! is_resource($process)) {
        fail("cannot run: {$command}");
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    if (proc_close($process) !== 0) {
        fail("command failed: {$command}\n".$stdout.$stderr);
    }
}

function removeTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }
    if (! is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        $entry === '.' || $entry === '..' || removeTree($path.'/'.$entry);
    }
    rmdir($path);
}

/** @param list<string> $exclude names or relative paths (from $from) that are not copied */
function copyTree(string $from, string $to, array $exclude = [], string $relative = ''): void
{
    is_dir($to) || mkdir($to, 0755, true);
    foreach (scandir($from) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $rel = ltrim($relative.'/'.$entry, '/');
        if (in_array($entry, $exclude, true) || in_array($rel, $exclude, true)) {
            continue;
        }
        if (is_dir($from.'/'.$entry) && ! is_link($from.'/'.$entry)) {
            copyTree($from.'/'.$entry, $to.'/'.$entry, $exclude, $rel);
        } else {
            copy($from.'/'.$entry, $to.'/'.$entry);
        }
    }
}

if (! class_exists(ZipArchive::class)) {
    fail('the PHP zip extension is required.');
}
$composer = trim((string) shell_exec('command -v composer')) ?: fail('composer was not found in PATH.');

is_dir($out) || mkdir($out, 0755, true);
$stage = $out.'/stage-'.bin2hex(random_bytes(4));
mkdir($stage, 0755, true);

try {
    // 1. A clean copy that keeps the monorepo layout, so the path repositories (../../packages/...) still resolve.
    say('Copying sources...');
    $packageExclude = ['vendor', 'node_modules', 'tests', 'scripts', 'CLAUDE.md', 'package.json', 'package-lock.json', 'phpunit.xml', 'phpstan.neon', 'composer.lock', '.phpunit.cache', '.phpunit.result.cache', '.git'];
    copyTree($root.'/packages/core', $stage.'/packages/core', $packageExclude);
    foreach (glob($root.'/packages/modules/*', GLOB_ONLYDIR) ?: [] as $module) {
        copyTree($module, $stage.'/packages/modules/'.basename($module), $packageExclude);
    }
    $app = $stage.'/apps/starter';
    copyTree($root.'/apps/starter', $app, ['vendor', 'node_modules', 'tests', 'CLAUDE.md', 'phpunit.xml', 'phpstan.neon', '.env', '.env.example', '.phpunit.cache', '.phpunit.result.cache', '.git', '.gitignore', '.gitattributes', '.editorconfig', 'storage', 'public/vendor', 'public/storage', 'public/hot', 'bootstrap/cache']);

    // 2. Dependencies for production only; local packages are copied into vendor, never linked.
    say('Installing production dependencies (composer)...');
    run(escapeshellarg($composer).' install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist', $app, ['COMPOSER_MIRROR_PATH_REPOS' => '1', 'COMPOSER_NO_INTERACTION' => '1']);

    // The starter's composer.json asks for symlinks to the local packages (right for development, wrong for a ZIP): make them real copies.
    foreach (glob($app.'/vendor/*/*') ?: [] as $link) {
        if (is_link($link)) {
            $target = realpath($link) ?: fail("broken link in vendor: {$link}");
            unlink($link);
            copyTree($target, $link);
        }
    }

    // Drop what is only useful for developers from the vendor folders.
    foreach (glob($app.'/vendor/*/*', GLOB_ONLYDIR) ?: [] as $package) {
        foreach (['tests', 'Tests', 'test', 'docs', 'doc', '.github', '.git', 'examples'] as $dev) {
            removeTree($package.'/'.$dev);
        }
        foreach (glob($package.'/{phpunit*.xml*,.gitattributes,.gitignore,.editorconfig,CHANGELOG*,CLAUDE.md,AGENTS.md,UPGRADE*,CONTRIBUTING*,.php-cs-fixer*,phpstan*.neon*}', GLOB_BRACE) ?: [] as $file) {
            is_file($file) && unlink($file);
        }
    }

    // 3. Empty storage structure and the folders the framework needs.
    foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/testing', 'storage/logs', 'bootstrap/cache', 'public/vendor'] as $folder) {
        mkdir($app.'/'.$folder, 0775, true);
    }
    foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $folder) {
        file_put_contents($app.'/'.$folder.'/.gitignore', "*\n!.gitignore\n");
    }

    // 4. Package discovery and the built assets, as `composer install` scripts would do (run by hand: --no-scripts).
    say('Publishing assets...');
    $env = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'DB_CONNECTION' => 'mariadb', 'DB_HOST' => '127.0.0.1', 'DB_DATABASE' => 'none', 'CODEART_BUILD' => '1'];
    run(escapeshellarg(PHP_BINARY).' artisan vendor:publish --tag=codeart-assets --force', $app, $env);
    // Laravel regenerates these on the first request; stale copies must not travel.
    foreach (glob($app.'/bootstrap/cache/*.php') ?: [] as $cached) {
        unlink($cached);
    }
    is_file($app.'/public/vendor/codeart/app.css') || is_dir($app.'/public/vendor/codeart') || fail('the built assets were not published.');

    // 5. The two folders of the ZIP.
    $package = $stage.'/package';
    mkdir($package);
    rename($app.'/public', $package.'/public_html');
    rename($app, $package.'/cms');

    file_put_contents($package.'/public_html/index.php', <<<'PHP'
        <?php

        use Illuminate\Http\Request;

        // ---------------------------------------------------------------------------------------------
        // THE ONLY SETTING IN THIS FILE: where the application folder ("cms") is.
        // Default: next to this folder (home/cms and home/public_html). Absolute paths work too.
        $applicationPath = __DIR__.'/../cms';
        // ---------------------------------------------------------------------------------------------

        define('LARAVEL_START', microtime(true));

        $applicationPath = realpath($applicationPath);
        if ($applicationPath === false || ! is_file($applicationPath.'/vendor/autoload.php')) {
            http_response_code(500);
            exit('The application folder was not found. Open this file (public_html/index.php) and set $applicationPath to the folder "cms".');
        }

        if (file_exists($maintenance = $applicationPath.'/storage/framework/maintenance.php')) {
            require $maintenance;
        }

        require $applicationPath.'/vendor/autoload.php';

        $app = require_once $applicationPath.'/bootstrap/app.php';
        $app->usePublicPath(__DIR__);
        $app->handleRequest(Request::capture());
        PHP);
    file_put_contents($package.'/cms/.htaccess', "# The application folder must never be served.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");

    // 6. Install key (random, per build) and version. No .env on purpose.
    $key = strtoupper(implode('-', str_split(bin2hex(random_bytes(10)), 5)));
    file_put_contents($package.'/cms/install.key', $key."\n");
    file_put_contents($package.'/cms/VERSION', $version."\n");
    is_file($package.'/cms/.env') && unlink($package.'/cms/.env');

    // 7. ZIP (explicit permissions: folders 755, files 644).
    $zipPath = $out.'/codeart-cms-'.$version.'.zip';
    is_file($zipPath) && unlink($zipPath);
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE) === true || fail('cannot create '.$zipPath);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $name = substr($item->getPathname(), strlen($package) + 1);
        if ($item->isDir()) {
            $zip->addEmptyDir($name);
            $zip->setExternalAttributesName($name.'/', ZipArchive::OPSYS_UNIX, (0o40755) << 16);
        } else {
            $zip->addFile($item->getPathname(), $name);
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, (0o100644) << 16);
        }
    }
    $zip->close();

    $hash = hash_file('sha256', $zipPath);
    say('');
    say('ZIP:      '.$zipPath);
    say('Size:     '.number_format(filesize($zipPath) / 1024 / 1024, 1).' MB');
    say('Version:  '.$version);
    say('SHA-256:  '.$hash);
} finally {
    isset($options['keep-stage']) || removeTree($stage);
}
