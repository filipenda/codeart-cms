<?php

/** Images go through the one ImageProcessor service: no module, stub or other core code touches GD, Imagick or Intervention. */
function phpFilesUnder(string $dir): array
{
    $files = [];
    if (! is_dir($dir)) {
        return $files;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = (string) $file;
        if (preg_match('#/(vendor|node_modules)/#', $path) !== 1 && preg_match('/\.(php|stub)$/', $path) === 1) {
            $files[] = $path;
        }
    }

    return $files;
}

it('keeps GD, Imagick and Intervention inside core\'s Images folder', function () {
    $core = dirname(__DIR__, 3);
    $roots = [$core.'/src', $core.'/stubs', dirname($core).'/modules', dirname($core, 2).'/apps/starter/app'];
    $pattern = '/Intervention\\\\|\bimagecreate\w*\(|\bimagejpeg\(|\bimagepng\(|\bimagewebp\(|\bgetimagesize\(|\bnew\s+\\\\?Imagick\b|\bGdImage\b|\bgd_info\(/';
    $offenders = [];
    foreach ($roots as $root) {
        foreach (phpFilesUnder($root) as $file) {
            if (str_contains($file, '/src/Images/') || str_contains($file, '/Install/')) {
                continue; // the processor itself; the installer only probes for the extension
            }
            if (preg_match($pattern, (string) file_get_contents($file)) === 1) {
                $offenders[] = str_replace(dirname($core, 2).'/', '', $file);
            }
        }
    }

    expect($offenders)->toBe([]);
});
