<?php

/*
 * Fails when a color literal (hex/rgb/hsl/...) or a raw Tailwind palette color
 * appears outside the allowed files. Usage: php tools/check-colors.php [root]
 * Plain PHP, no dependencies (works anywhere, also in CI).
 */

const CODEART_COLOR_ALLOWED = [
    'packages/core/resources/css/tokens.css',
    'packages/core/config/codeart.php',
];

const CODEART_COLOR_SKIP_DIRS = ['vendor', 'node_modules', 'storage', '.git', '.idea', '.ddev', 'dist', 'build', 'cache', 'tests'];

const CODEART_COLOR_EXTENSIONS = ['php', 'css', 'js', 'ts', 'vue', 'html', 'svg'];

/**
 * @param  list<string>  $allowed  paths relative to $root
 * @return list<string> violations as "path:line: match"
 */
function codeart_find_color_violations(string $root, array $allowed = CODEART_COLOR_ALLOWED): array
{
    $root = rtrim($root, '/');
    $palette = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
    $patterns = [
        '/(?<![\w&#\/-])#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![\w-])/',
        '/\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\(/i',
        '/\b[a-z-]*:?(?:bg|text|border|ring|fill|stroke|from|via|to|divide|outline|decoration|accent|caret|shadow|placeholder)-(?:(?:'.$palette.')-\d{2,3}|white|black)\b/',
    ];

    $violations = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        fn (SplFileInfo $f): bool => ! ($f->isDir() && in_array($f->getFilename(), CODEART_COLOR_SKIP_DIRS, true)),
    ));

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), CODEART_COLOR_EXTENSIONS, true)) {
            continue;
        }
        $relative = ltrim(substr($file->getPathname(), strlen($root)), '/');
        if (in_array($relative, $allowed, true)) {
            continue;
        }
        foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [] as $i => $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $m) === 1) {
                    $violations[] = $relative.':'.($i + 1).': '.$m[0];
                }
            }
        }
    }

    sort($violations);

    return $violations;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $root = $argv[1] ?? dirname(__DIR__);
    $violations = codeart_find_color_violations($root);
    if ($violations === []) {
        echo "check:colors OK\n";
        exit(0);
    }
    fwrite(STDERR, "Color literals found outside tokens.css / config defaults:\n  ".implode("\n  ", $violations)."\n");
    exit(1);
}
