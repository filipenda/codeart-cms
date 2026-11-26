<?php

/*
 * Runs only the Pest tests affected by the change (used by `composer check:fast`).
 *
 * Changed = committed on this branch since it left main, plus uncommitted and untracked files.
 * Per package (packages/core, apps/starter):
 *   - a changed test file runs itself;
 *   - a changed PHP class under src/ or app/ runs the test files that mention its class name;
 *   - anything else that can affect behaviour (views, CSS, lang, config, migrations, routes, test support, an
 *     unmapped class) runs the whole package suite, because it is cheaper than guessing wrong.
 * Docs and CI files are ignored. Usage: php tools/affected-tests.php [--dry]
 */

$root = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);

function sh(string $cmd): string
{
    return (string) shell_exec($cmd.' 2>/dev/null');
}

$base = trim(sh('git -C '.escapeshellarg($root).' merge-base HEAD main')) ?: trim(sh('git -C '.escapeshellarg($root).' merge-base HEAD origin/main')) ?: 'HEAD';
$changed = array_unique(array_filter(array_merge(
    explode("\n", sh('git -C '.escapeshellarg($root).' diff --name-only --diff-filter=d '.$base)),
    explode("\n", sh('git -C '.escapeshellarg($root).' ls-files --others --exclude-standard')),
)));

$packages = ['packages/core' => ['src'], 'apps/starter' => ['app']];
$exit = 0;

foreach ($packages as $package => $classDirs) {
    $files = array_values(array_filter($changed, fn ($f) => str_starts_with($f, $package.'/')));
    $files = array_map(fn ($f) => substr($f, strlen($package) + 1), $files);
    $files = array_values(array_filter($files, fn ($f) => ! str_starts_with($f, 'vendor/') && ! str_starts_with($f, 'node_modules/') && ! str_ends_with($f, '.md')));
    if ($files === []) {
        echo "[$package] nothing changed, no tests\n";

        continue;
    }

    $tests = [];
    $full = false;
    $testFiles = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$package/tests", FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_ends_with((string) $file, 'Test.php')) {
            $testFiles[(string) $file] = (string) file_get_contents((string) $file);
        }
    }

    foreach ($files as $file) {
        if (str_starts_with($file, 'tests/') && str_ends_with($file, 'Test.php')) {
            $tests[basename($file, '.php')] = true;
        } elseif (preg_match('#^('.implode('|', $classDirs).')/.*\.php$#', $file)) {
            $class = basename($file, '.php');
            $hits = array_filter($testFiles, fn ($content) => str_contains($content, $class));
            if ($hits === []) {
                $full = true;
            }
            foreach (array_keys($hits) as $hit) {
                $tests[basename($hit, '.php')] = true;
            }
        } else {
            $full = true; // views, css, lang, config, migrations, routes, tests/Pest.php, support classes ...
        }
    }

    $filter = $full ? '' : ' --filter '.escapeshellarg(implode('|', array_keys($tests)));
    if (! $full && $tests === []) {
        continue;
    }
    $pest = 'cd '.escapeshellarg("$root/$package").' && vendor/bin/pest --compact'.($package === 'packages/core' ? ' --parallel' : '').$filter;
    echo "[$package] ".($full ? 'full suite' : count($tests).' test file(s): '.implode(', ', array_keys($tests)))."\n";
    if ($dry) {
        continue;
    }
    passthru($pest, $code);
    $exit = max($exit, $code);
}

exit($exit);
