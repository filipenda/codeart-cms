<?php

use Codeart\Core\Modules\Generator\ModuleGenerator;
use Codeart\Core\Modules\Generator\ModuleSpec;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** A throwaway folder for generated modules; never inside the repository. */
function genDir(): string
{
    $dir = sys_get_temp_dir().'/codeart-gen-'.uniqid();
    mkdir($dir.'/modules', 0777, true);

    return $dir;
}

/** @param list<string> $files */
function migrationOf(array $files): string
{
    return (string) collect($files)->first(fn (string $f): bool => str_starts_with($f, 'database/migrations/'));
}

function cleanup(string $dir): void
{
    (new Filesystem)->deleteDirectory($dir);
}

it('generates every part of a module with no placeholder left', function () {
    $dir = genDir();
    $files = app(ModuleGenerator::class)->generate(new ModuleSpec('blog-posts', ['title', 'body'], slug: true), $dir.'/modules/blog-posts');

    expect($files)->toContain('module.json', 'composer.json', 'config/blog-posts.php', 'routes/web.php', 'README.md', 'phpunit.xml', 'phpstan.neon',
        'src/BlogPostsServiceProvider.php', 'src/Models/BlogPost.php', 'src/Models/BlogPostTranslation.php', 'src/BlogPostPolicy.php',
        'src/BlogPostsTable.php', 'src/BlogPostsSearch.php', 'src/BlogPostsWidget.php', 'src/Http/BlogPostsController.php',
        'resources/lang/sr/module.php', 'resources/lang/en/module.php', 'resources/views/index.blade.php', 'resources/views/form.blade.php',
        'tests/WorkflowTest.php', 'tests/ModuleTest.php', 'tests/TestCase.php');
    foreach ($files as $file) {
        expect(file_get_contents($dir.'/modules/blog-posts/'.$file))->not->toMatch('/%%\w+%%/', $file);
    }
    $manifest = json_decode((string) file_get_contents($dir.'/modules/blog-posts/module.json'), true);
    expect($manifest['name'])->toBe('blog-posts')->and($manifest['package'])->toBe('codeart/module-blog-posts')
        ->and($manifest['permissions'])->toBe(['blog-posts.view', 'blog-posts.create', 'blog-posts.update', 'blog-posts.publish', 'blog-posts.delete'])
        ->and($manifest['provider'])->toBe('Codeart\\BlogPosts\\BlogPostsServiceProvider');
    expect(file_get_contents($dir.'/modules/blog-posts/'.migrationOf($files)))
        ->toContain("'blog_posts_blog_posts'")->toContain("'blog_posts_blog_post_translations'")->toContain("unique(['locale', 'slug'])");
    cleanup($dir);
});

it('honours the options: slug, widget, publishable', function () {
    $dir = genDir();
    $plain = app(ModuleGenerator::class)->generate(new ModuleSpec('notes', ['title'], publishable: false, widget: false), $dir.'/modules/notes');

    expect($plain)->not->toContain('src/NotesWidget.php')->not->toContain('tests/WorkflowTest.php')->toContain('tests/NoWorkflowTest.php');
    $module = file_get_contents($dir.'/modules/notes/module.json');
    expect($module)->not->toContain('notes.publish');
    expect(file_get_contents($dir.'/modules/notes/routes/web.php'))->not->toContain('publish');
    expect(file_get_contents($dir.'/modules/notes/src/NotesServiceProvider.php'))->not->toContain('Widget')->not->toContain('Publishables');
    expect(file_get_contents($dir.'/modules/notes/'.migrationOf($plain)))->not->toContain("'slug'");
    cleanup($dir);
});

it('never overwrites an existing module and rejects bad names and fields', function () {
    $dir = genDir();
    app(ModuleGenerator::class)->generate(new ModuleSpec('pages'), $dir.'/modules/pages');

    expect(fn () => app(ModuleGenerator::class)->generate(new ModuleSpec('pages'), $dir.'/modules/pages'))->toThrow(RuntimeException::class, 'already exists')
        ->and(fn () => new ModuleSpec('Bad Name'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ModuleSpec('pages', ['title', 'status']))->toThrow(InvalidArgumentException::class, 'not allowed')
        ->and(fn () => new ModuleSpec('pages', ['Title']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ModuleSpec('pages', ['a', 'a']))->toThrow(InvalidArgumentException::class, 'unique')
        ->and(fn () => new ModuleSpec('pages', []))->toThrow(InvalidArgumentException::class);
    cleanup($dir);
});

it('runs as an artisan command, generates sr-Cyrl, and fails loudly on a bad name', function () {
    $dir = genDir();
    $this->artisan('make:module', ['name' => 'events', '--translatable' => 'title,summary', '--path' => $dir.'/modules/events', '--slug' => true])->assertSuccessful();

    $cyrillic = (string) file_get_contents($dir.'/modules/events/resources/lang/sr-Cyrl/module.php');
    expect($cyrillic)->toContain('Нова ставка')->and(file_get_contents($dir.'/modules/events/src/Models/Event.php'))->toContain("'title', 'summary', 'slug'");
    $this->artisan('make:module', ['name' => 'Bad Name', '--path' => $dir.'/modules/bad'])->assertFailed();
    $this->artisan('make:module', ['name' => 'events', '--path' => $dir.'/modules/events'])->assertFailed();
    cleanup($dir);
});

it('generates code that passes Pint, Larastan and its own tests, without edits', function (array $options) {
    $dir = genDir();
    $path = $dir.'/modules/'.$options['name'];
    $this->artisan('make:module', ['--path' => $path] + $options['args'])->assertSuccessful();
    symlink(dirname(__DIR__, 3).'/vendor', $path.'/vendor'); // what the command does in the monorepo
    $env = ['DB_DATABASE' => 'test_generated', 'TEST_TOKEN' => false, 'UNIQUE_TEST_TOKEN' => false, 'PARATEST' => false, 'LARAVEL_PARALLELISM' => false];

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

    unlink($path.'/vendor'); // a symlink: remove the link, never the target
    cleanup($dir);
})->with([
    'publishable with slug and widget' => [['name' => 'pages', 'args' => ['name' => 'pages', '--slug' => true]]],
    'plain, no workflow, no widget' => [['name' => 'notes', 'args' => ['name' => 'notes', '--publishable' => 'false', '--no-widget' => true, '--translatable' => 'title']]],
])->group('generator');
