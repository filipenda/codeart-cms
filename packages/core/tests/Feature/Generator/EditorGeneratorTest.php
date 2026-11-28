<?php

use Codeart\Core\Modules\Generator\ModuleGenerator;
use Codeart\Core\Modules\Generator\ModuleSpec;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

function editorGenDir(): string
{
    $dir = sys_get_temp_dir().'/codeart-gen-editor-'.uniqid();
    mkdir($dir.'/modules', 0777, true);

    return $dir;
}

it('wires rich-text fields into the model, rules, migration and tests', function () {
    $dir = editorGenDir();
    $files = app(ModuleGenerator::class)->generate(new ModuleSpec('articles', ['title', 'summary', 'body'], editors: ['body']), $dir.'/modules/articles');
    $root = $dir.'/modules/articles/';

    expect($files)->toContain('tests/EditorTest.php')
        ->and(file_get_contents($root.'src/Models/Article.php'))->toContain("'body' => 'editor'")->toContain("'summary' => 'textarea'")
        ->and(file_get_contents($root.'src/Http/ArticlesController.php'))->toContain("'body' => ['nullable', 'string', 'max:200000']")
        ->and(file_get_contents($root.collect($files)->first(fn (string $f): bool => str_starts_with($f, 'database/migrations/'))))->toContain("longText('body')");
    (new Filesystem)->deleteDirectory($dir);
});

it('has no editor test without the option and refuses bad editor fields', function () {
    $dir = editorGenDir();
    $files = app(ModuleGenerator::class)->generate(new ModuleSpec('plain'), $dir.'/modules/plain');

    expect($files)->not->toContain('tests/EditorTest.php')
        ->and(fn () => new ModuleSpec('x', ['title', 'body'], editors: ['nope']))->toThrow(InvalidArgumentException::class, '--editor')
        ->and(fn () => new ModuleSpec('x', ['title', 'body'], editors: ['title']))->toThrow(InvalidArgumentException::class, 'title');
    $this->artisan('make:module', ['name' => 'viaopt', '--path' => $dir.'/modules/viaopt', '--translatable' => 'title,body', '--editor' => 'body'])->assertSuccessful();
    expect(file_get_contents($dir.'/modules/viaopt/src/Models/Viaopt.php'))->toContain("'body' => 'editor'");
    $this->artisan('make:module', ['name' => 'bad', '--path' => $dir.'/modules/bad', '--editor' => 'ghost'])->assertFailed();
    (new Filesystem)->deleteDirectory($dir);
});

it('generates an editor module that passes Pint, Larastan and its own tests, without edits', function () {
    $dir = editorGenDir();
    $path = $dir.'/modules/stories';
    $this->artisan('make:module', ['name' => 'stories', '--path' => $path, '--translatable' => 'title,summary,body', '--editor' => 'body', '--slug' => true])->assertSuccessful();
    symlink(dirname(__DIR__, 3).'/vendor', $path.'/vendor');
    $env = ['DB_DATABASE' => 'test_generated_editor', 'TEST_TOKEN' => false, 'UNIQUE_TEST_TOKEN' => false, 'PARATEST' => false, 'LARAVEL_PARALLELISM' => false];
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

    unlink($path.'/vendor');
    (new Filesystem)->deleteDirectory($dir);
})->group('generator');
