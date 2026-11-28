<?php

namespace Codeart\Core\Console;

use Codeart\Core\Modules\Generator\ModuleGenerator;
use Codeart\Core\Modules\Generator\ModuleSpec;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name? : Module name, a lowercase slug (pages, blog-posts); asked when missing}
        {--translatable=title,body : Translatable fields, comma separated; the first one is the title}
        {--publishable=true : Draft / published / scheduled workflow with revisions and preview (true|false)}
        {--slug : Add a translated, unique-per-locale slug}
        {--no-widget : No dashboard widget}
        {--path= : Folder that will hold the module (default: packages/modules/<name> in the monorepo)}';

    protected $description = 'Generate a complete CodeArt module (model, table, form, permissions, menu, tests ...)';

    public function handle(ModuleGenerator $generator): int
    {
        $name = $this->argument('name');
        $interactive = ! is_string($name) || $name === '';
        if ($interactive) {
            if (! $this->input->isInteractive()) {
                $this->components->error('Give the module name: php artisan make:module pages');

                return self::FAILURE;
            }
            $name = text('Module name (lowercase, e.g. pages or blog-posts)', required: true);
        }

        $fields = (string) $this->option('translatable');
        $slug = (bool) $this->option('slug');
        $publishable = filter_var($this->option('publishable'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        if ($interactive) {
            $fields = text('Translatable fields (comma separated, first = title)', default: $fields);
            $slug = confirm('Add a translated slug?', default: $slug);
            $publishable = confirm('Draft / publish workflow with preview?', default: $publishable);
        }

        try {
            $spec = ModuleSpec::fromOptions((string) $name, $fields, $slug, $publishable, ! $this->option('no-widget'));
            $target = rtrim((string) ($this->option('path') ?: $this->defaultPath($spec)), '/');
            $written = $generator->generate($spec, $target);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->linkMonorepoVendor($target);

        // Cyrillic UI strings are generated from the Latin ones, like core's.
        Artisan::call('lang:transliterate', ['--path' => $target.'/resources/lang']);

        $this->components->info(count($written)." files written to {$target}");
        $this->components->bulletList([
            'Add it to the app: composer require codeart/module-'.$spec->name.' (path repository), then php artisan module:enable '.$spec->name,
            'Public page address (client site): config/'.$spec->name.'.php `public_url` ('.($spec->slug ? '{slug}' : '{id}').' ...)',
            'Check it: cd '.$target.' && see README.md',
        ]);

        return self::SUCCESS;
    }

    /** In the monorepo a module has no vendor of its own: link core's (ignored by git), so pest, pint and phpstan run in the module folder. */
    private function linkMonorepoVendor(string $target): void
    {
        $core = dirname($target, 2).'/core/vendor';
        if (is_dir($core) && ! file_exists($target.'/vendor')) {
            @symlink($core, $target.'/vendor');
        }
    }

    private function defaultPath(ModuleSpec $spec): string
    {
        $monorepo = dirname(base_path(), 2).'/packages/modules';

        return (is_dir($monorepo) ? $monorepo : base_path('modules')).'/'.$spec->name;
    }
}
