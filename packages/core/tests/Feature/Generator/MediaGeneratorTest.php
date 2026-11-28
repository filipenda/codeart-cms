<?php

use Codeart\Core\Modules\Generator\ModuleGenerator;
use Codeart\Core\Modules\Generator\ModuleSpec;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function mediaGenDir(): string
{
    $dir = sys_get_temp_dir().'/codeart-gen-media-'.uniqid();
    mkdir($dir.'/modules', 0777, true);

    return $dir;
}

it('wires media fields into the model, migration and tests', function () {
    $dir = mediaGenDir();
    $spec = ModuleSpec::fromOptions('albums', 'title,body', false, true, true, '', 'cover,gallery:many');
    $files = app(ModuleGenerator::class)->generate($spec, $dir.'/modules/albums');
    $root = $dir.'/modules/albums/';
    $model = file_get_contents($root.'src/Models/Album.php');

    expect($files)->toContain('tests/MediaTest.php')
        ->and($model)->toContain('use HasMedia;')->toContain("'cover' => ['multiple' => false]")->toContain("'gallery' => ['multiple' => true]")->toContain("'cover' => 'array'")
        ->and(file_get_contents($root.collect($files)->first(fn (string $f): bool => str_starts_with($f, 'database/migrations/'))))->toContain("json('gallery')->nullable()")
        ->and(file_get_contents($root.'resources/lang/en/module.php'))->toContain("'cover' => 'Cover'");
    (new Filesystem)->deleteDirectory($dir);
});

it('has no media test without the option and refuses bad media fields', function () {
    $dir = mediaGenDir();
    $files = app(ModuleGenerator::class)->generate(new ModuleSpec('plain'), $dir.'/modules/plain');

    expect($files)->not->toContain('tests/MediaTest.php')
        ->and(file_get_contents($dir.'/modules/plain/src/Models/Plain.php'))->not->toContain('HasMedia')
        ->and(fn () => new ModuleSpec('x', ['title', 'body'], medias: ['title' => false]))->toThrow(InvalidArgumentException::class, '--media')
        ->and(fn () => new ModuleSpec('x', ['title', 'body'], medias: ['Bad Name' => false]))->toThrow(InvalidArgumentException::class, '--media');
    (new Filesystem)->deleteDirectory($dir);
});

it('generates a media + editor module that passes Pint, Larastan and its own tests, without edits', function () {
    $dir = mediaGenDir();
    $path = $dir.'/modules/galleries';
    $this->artisan('make:module', ['name' => 'galleries', '--path' => $path, '--translatable' => 'title,body', '--editor' => 'body', '--media' => 'cover,photos:many'])->assertSuccessful();
    symlink(dirname(__DIR__, 3).'/vendor', $path.'/vendor');
    $env = ['DB_DATABASE' => 'test_generated_media', 'TEST_TOKEN' => false, 'UNIQUE_TEST_TOKEN' => false, 'PARATEST' => false, 'LARAVEL_PARALLELISM' => false];
    $run = function (array $command) use ($path, $env): Process {
        $process = new Process($command, $path, $env, timeout: 600);
        $process->run();

        return $process;
    };

    $pint = $run([PHP_BINARY, 'vendor/bin/pint', '--test']);
    expect($pint->getExitCode())->toBe(0, 'pint: '.$pint->getOutput().$pint->getErrorOutput());
    $stan = $run([PHP_BINARY, 'vendor/bin/phpstan', 'analyse', '--memory-limit=1G', '--no-progress']);
    expect($stan->getExitCode())->toBe(0, 'larastan: '.$stan->getOutput().$stan->getErrorOutput());
    $pest = $run([PHP_BINARY, 'vendor/bin/pest']);
    expect($pest->getExitCode())->toBe(0, 'pest: '.$pest->getOutput().$pest->getErrorOutput());

    (new Filesystem)->deleteDirectory($dir);
});
