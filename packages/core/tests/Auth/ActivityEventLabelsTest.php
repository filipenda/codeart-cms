<?php

use Codeart\Core\ActivityLog\ActivityDescription;

/** Event keys used in the code (record('x.y'), logUser(..., 'x.y'), subscriber maps). */
function codeEventKeys(): array
{
    $keys = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = file_get_contents($file->getPathname());
        preg_match_all("/(?:record|logUser|userEvent)\((?:\\\$this->log, |\\\$log, )?'([a-z_]+\.[a-z_]+)'/", $code, $a);
        preg_match_all("/=> \['([a-z_]+\.[a-z_]+)', '/", $code, $b);
        $keys = [...$keys, ...$a[1], ...$b[1]];
    }

    // built dynamically in AuthEventSubscriber::permissionChange()
    return array_values(array_unique([...$keys, 'role.attached', 'role.detached', 'permission.attached', 'permission.detached']));
}

it('has a translation in every language for every activity event used in the code', function (string $locale) {
    $labels = ActivityDescription::labels($locale);
    $missing = array_values(array_filter(codeEventKeys(), fn (string $key): bool => ! isset($labels[$key]) || $labels[$key] === ''));

    expect(codeEventKeys())->not->toBeEmpty()
        ->and($missing)->toBe([], "Untranslated activity events ($locale): ".implode(', ', $missing));
})->with(['en', 'sr', 'sr-Cyrl']);

it('shows the translated event label in the table badge options', function () {
    app()->setLocale('sr');
    expect(ActivityDescription::label('media.uploaded'))->not->toBe('media.uploaded')
        ->and(ActivityDescription::label('nope.unknown'))->toBe('nope.unknown');
});
