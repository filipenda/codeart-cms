<?php

// Monorepo-only test: the checker script lives in /tools at the repository root.
$script = __DIR__.'/../../../../tools/check-colors.php';

beforeEach(function () use ($script) {
    if (! is_file($script)) {
        $this->markTestSkipped('Not running inside the monorepo.');
    }
    require_once $script;

    $this->root = sys_get_temp_dir().'/codeart-colors-'.uniqid();
    mkdir($this->root.'/packages/core/resources/css', 0777, true);
    mkdir($this->root.'/apps/starter/resources/views', 0777, true);
});

afterEach(function () {
    if (isset($this->root)) {
        exec('rm -rf '.escapeshellarg($this->root));
    }
});

function put(string $root, string $path, string $content): void
{
    file_put_contents($root.'/'.$path, $content);
}

it('passes on clean files and allows tokens.css', function () {
    put($this->root, 'packages/core/resources/css/tokens.css', ':root{--color-primary:#c9f31d;}');
    put($this->root, 'apps/starter/resources/views/a.blade.php', '<a href="#top" class="bg-primary text-on-primary">&#123;</a>');

    expect(codeart_find_color_violations($this->root))->toBe([]);
});

it('flags hex, functional and palette colors', function (string $line) {
    put($this->root, 'apps/starter/resources/views/a.blade.php', $line);

    expect(codeart_find_color_violations($this->root))->toHaveCount(1);
})->with([
    'hex6' => ['<p style="color:#FF0000">x</p>'],
    'hex3' => ['<p style="color:#fff">x</p>'],
    'rgb' => ['<p style="color:rgb(1,2,3)">x</p>'],
    'hsl' => ['<p style="color: hsl(10 20% 30%)">x</p>'],
    'palette' => ['<div class="bg-lime-400">'],
    'palette variant' => ['<div class="hover:text-slate-700">'],
    'white' => ['<div class="bg-white">'],
]);

it('exits non-zero from the cli on violations', function () {
    put($this->root, 'apps/starter/resources/views/a.blade.php', '<div class="bg-red-500">');
    exec('php '.escapeshellarg(__DIR__.'/../../../../tools/check-colors.php').' '.escapeshellarg($this->root).' 2>&1', $out, $code);

    expect($code)->toBe(1)->and(implode("\n", $out))->toContain('a.blade.php:1');
});

it('passes on the real repository', function () {
    expect(codeart_find_color_violations(dirname(__DIR__, 4)))->toBe([]);
});
