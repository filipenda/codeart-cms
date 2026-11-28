<?php

use Illuminate\Filesystem\Filesystem;

/*
 * Builds the client ZIP with tools/build-package.php (a few seconds, needs Composer's cache), checks what is inside and
 * starts the application from the unpacked ZIP in a different directory through public_html/index.php.
 * Everything happens in a temporary directory; no .env or database of the developer is touched.
 */

function buildRun(string $command, ?string $cwd = null): array
{
    // A clean environment: nothing of the test run (APP_KEY, DB_*, SESSION_DRIVER ...) leaks into the package.
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, ['PATH' => getenv('PATH'), 'HOME' => getenv('HOME') ?: sys_get_temp_dir(), 'COMPOSER_HOME' => getenv('COMPOSER_HOME') ?: (getenv('HOME') ?: sys_get_temp_dir()).'/.composer']);
    $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);

    return [proc_close($process), $out];
}

/** Run public_html/index.php for one GET request, the way a web server would (CLI, fresh process). */
function packageRequest(string $publicHtml, string $uri): string
{
    $runner = dirname($publicHtml).'/run-request.php';
    file_put_contents($runner, '<?php $_SERVER["REQUEST_URI"] = $argv[1]; $_SERVER["REQUEST_METHOD"] = "GET"; $_SERVER["HTTP_HOST"] = "localhost"; $_SERVER["SERVER_NAME"] = "localhost"; $_SERVER["SERVER_PORT"] = 80; $_SERVER["SCRIPT_NAME"] = "/index.php"; $_SERVER["PHP_SELF"] = "/index.php"; chdir($argv[2]); require $argv[2]."/index.php";');

    return buildRun(escapeshellarg(PHP_BINARY).' '.escapeshellarg($runner).' '.escapeshellarg($uri).' '.escapeshellarg($publicHtml))[1];
}

beforeAll(function () {
    $work = sys_get_temp_dir().'/codeart-build-test-'.bin2hex(random_bytes(4));
    mkdir($work);
    [$code, $output] = buildRun(escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__, 4).'/tools/build-package.php').' --out='.escapeshellarg($work.'/out').' --version=0.0.0-test');
    $GLOBALS['build'] = ['work' => $work, 'code' => $code, 'output' => $output, 'zip' => $work.'/out/codeart-cms-0.0.0-test.zip'];
    if ($code === 0) {
        $zip = new ZipArchive;
        $zip->open($GLOBALS['build']['zip']);
        $GLOBALS['build']['names'] = array_map(fn ($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
        mkdir($work.'/home');
        $zip->extractTo($work.'/home');
        $zip->close();
    }
});

afterAll(function () {
    $work = $GLOBALS['build']['work'] ?? null;
    if ($work !== null && str_starts_with($work, sys_get_temp_dir().'/codeart-build-test-')) {
        (new Filesystem)->deleteDirectory($work);
    }
});

it('builds a ZIP and prints its checksum', function () {
    expect($GLOBALS['build']['code'])->toBe(0, $GLOBALS['build']['output'])
        ->and($GLOBALS['build']['output'])->toContain('SHA-256:  '.hash_file('sha256', $GLOBALS['build']['zip']));
});

it('contains the application, vendor, assets, install.key and VERSION, and nothing for developers', function () {
    $names = $GLOBALS['build']['names'];
    $has = fn (string $name): bool => in_array($name, $names, true);
    $any = fn (string $pattern): bool => collect($names)->contains(fn ($n) => preg_match($pattern, $n) === 1);

    expect($has('cms/install.key'))->toBeTrue()
        ->and($has('cms/VERSION'))->toBeTrue()
        ->and($has('cms/vendor/autoload.php'))->toBeTrue()
        ->and($has('cms/vendor/codeart/core/src/CoreServiceProvider.php'))->toBeTrue()
        ->and($has('cms/vendor/codeart/module-example/module.json'))->toBeTrue()
        ->and($has('public_html/index.php'))->toBeTrue()
        ->and($has('public_html/.htaccess'))->toBeTrue()
        ->and($any('#^public_html/vendor/codeart/.+\.css$#'))->toBeTrue()
        ->and($has('cms/storage/app/public/.gitignore'))->toBeTrue()
        ->and($has('cms/bootstrap/cache/.gitignore'))->toBeTrue()
        // not in it:
        ->and($any('#(^|/)\.env(\..+)?$#'))->toBeFalse()
        ->and($any('#(^|/)\.git/#'))->toBeFalse()
        ->and($any('#(^|/)node_modules/#'))->toBeFalse()
        ->and($any('#^cms/(tests|docs)/#'))->toBeFalse()
        ->and($any('#^cms/vendor/[^/]+/[^/]+/(tests|Tests|docs)/#'))->toBeFalse()
        ->and($any('#(^|/)(CLAUDE\.md|phpunit\.xml|phpstan\.neon)$#'))->toBeFalse()
        ->and($any('#^cms/vendor/(pestphp|phpunit|mockery|larastan|laravel/pint)/#'))->toBeFalse()
        ->and($any('#\.log$#'))->toBeFalse();
});

it('has a fresh random install key that is not committed anywhere', function () {
    $key = trim((string) file_get_contents($GLOBALS['build']['work'].'/home/cms/install.key'));

    expect(strlen($key))->toBeGreaterThanOrEqual(20)
        ->and(trim((string) file_get_contents($GLOBALS['build']['work'].'/home/cms/VERSION')))->toBe('0.0.0-test')
        ->and(file_exists(dirname(__DIR__, 4).'/apps/starter/install.key'))->toBeFalse();
    $ignored = buildRun('git check-ignore -q build/x.zip', dirname(__DIR__, 4))[0];
    expect($ignored)->toBe(0);
});

it('starts from the unpacked ZIP and leads the first visitor to the installer', function () {
    $public = $GLOBALS['build']['work'].'/home/public_html';

    expect(packageRequest($public, '/'))->toContain('/install')
        ->and(packageRequest($public, '/install'))->toContain('install.key');
    expect(file_exists($GLOBALS['build']['work'].'/home/cms/.env'))->toBeFalse();
});

it('finds the application through the single path setting, wherever it is', function () {
    $work = $GLOBALS['build']['work'];
    mkdir($work.'/elsewhere/web', 0777, true);
    rename($work.'/home/cms', $work.'/elsewhere/my-application');
    rename($work.'/home/public_html', $work.'/elsewhere/web/public_html');
    $index = $work.'/elsewhere/web/public_html/index.php';

    // Not found: a plain message that says what to do.
    expect(packageRequest(dirname($index), '/'))->toContain('$applicationPath');

    $text = file_get_contents($index);
    expect($text)->toContain('$applicationPath = __DIR__.\'/../cms\';');
    file_put_contents($index, str_replace('__DIR__.\'/../cms\'', "'".$work."/elsewhere/my-application'", $text));

    expect(packageRequest(dirname($index), '/'))->toContain('/install');
});
