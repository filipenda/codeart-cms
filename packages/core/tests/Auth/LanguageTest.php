<?php

use Illuminate\Support\Arr;

const LANG_DIR = __DIR__.'/../../resources/lang';

/** @return array<string, string> dotted key => text */
function langLines(string $locale, string $file): array
{
    return Arr::dot(require LANG_DIR."/{$locale}/{$file}.php");
}

function langFiles(): array
{
    return array_map(fn ($f) => basename($f, '.php'), glob(LANG_DIR.'/sr/*.php'));
}

it('has the same files and keys in sr, sr-Cyrl and en', function () {
    foreach (['sr', 'sr-Cyrl', 'en'] as $locale) {
        expect(array_map(fn ($f) => basename($f, '.php'), glob(LANG_DIR."/{$locale}/*.php")))->toEqualCanonicalizing(langFiles());
    }

    foreach (langFiles() as $file) {
        $reference = array_keys(langLines('sr', $file));
        expect(array_keys(langLines('en', $file)))->toEqualCanonicalizing($reference, "en/{$file}")
            ->and(array_keys(langLines('sr-Cyrl', $file)))->toEqualCanonicalizing($reference, "sr-Cyrl/{$file}");
    }
});

it('has no empty text', function (string $locale) {
    foreach (langFiles() as $file) {
        foreach (langLines($locale, $file) as $key => $text) {
            expect(trim((string) $text))->not->toBe('', "{$locale}/{$file}.{$key}");
        }
    }
})->with(['sr', 'sr-Cyrl', 'en']);

it('keeps the placeholders the same in every language', function () {
    foreach (langFiles() as $file) {
        $sr = langLines('sr', $file);
        foreach (['en', 'sr-Cyrl'] as $locale) {
            foreach (langLines($locale, $file) as $key => $text) {
                preg_match_all('/:[a-z_]+/', (string) $text, $found);
                preg_match_all('/:[a-z_]+/', (string) $sr[$key], $expected);
                expect($found[0])->toEqualCanonicalizing($expected[0], "{$locale}/{$file}.{$key}");
            }
        }
    }
});

it('generates the sr-Cyrl files from sr, and they are up to date', function () {
    $this->artisan('lang:transliterate', ['--check' => true])->assertSuccessful();
});

it('reports sr-Cyrl files that are stale, and regenerates them', function () {
    $dir = sys_get_temp_dir().'/codeart-lang-'.uniqid();
    mkdir("{$dir}/sr", 0777, true);
    file_put_contents("{$dir}/sr/demo.php", "<?php\nreturn ['hello' => 'Zdravo :name, dobrodošli', 'nested' => ['a' => 'Lj', 'count' => 3]];\n");

    $this->artisan('lang:transliterate', ['--path' => $dir, '--check' => true])->assertFailed();
    $this->artisan('lang:transliterate', ['--path' => $dir])->assertSuccessful();
    $this->artisan('lang:transliterate', ['--path' => $dir, '--check' => true])->assertSuccessful();

    $generated = require "{$dir}/sr-Cyrl/demo.php";
    expect($generated['hello'])->toBe('Здраво :name, добродошли')->and($generated['nested'])->toBe(['a' => 'Љ', 'count' => 3]);
    exec('rm -rf '.escapeshellarg($dir));
});

it('shows Laravel\'s own messages instead of raw keys', function (string $locale, string $expected) {
    app()->setLocale($locale);
    app()->setFallbackLocale('xx'); // the test skeleton ships English files of its own; a real app has none

    expect(__('auth.failed'))->toBe($expected)
        ->and(__('passwords.sent'))->not->toBe('passwords.sent')
        ->and(__('validation.required', ['attribute' => 'x']))->not->toContain('validation.');
})->with([
    'sr' => ['sr', 'Uneti podaci se ne poklapaju sa našom evidencijom.'],
    'sr-Cyrl' => ['sr-Cyrl', 'Унети подаци се не поклапају са нашом евиденцијом.'],
    'en' => ['en', 'These credentials do not match our records.'],
]);

it('lets an app\'s own translation win over core\'s fallback', function () {
    app('translator')->addLines(['auth.failed' => 'App says no'], 'sr');
    app()->setLocale('sr');
    app()->setFallbackLocale('xx');

    expect(__('auth.failed'))->toBe('App says no');
});
