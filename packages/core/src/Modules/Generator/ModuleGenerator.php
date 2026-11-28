<?php

namespace Codeart\Core\Modules\Generator;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes a complete module from the stubs in `stubs/module` (docs/modules.md "Generate a module"). A stub is a file
 * whose name and content may contain `%%TOKENS%%`; lines `%%if flag%%` / `%%endif%%` keep or drop a block, and a first
 * line `%%file-if flag%%` drops the whole file. Nothing is overwritten: an existing target folder is an error.
 */
final class ModuleGenerator
{
    public function __construct(private readonly Filesystem $files) {}

    public static function stubPath(): string
    {
        return dirname(__DIR__, 3).'/stubs/module';
    }

    /**
     * @return list<string> files written, relative to the module folder
     */
    public function generate(ModuleSpec $spec, string $target, ?\DateTimeInterface $now = null): array
    {
        if ($this->files->exists($target) && ($this->files->isFile($target) || $this->files->files($target, true) !== [])) {
            throw new RuntimeException("{$target} already exists and is not empty. Nothing was written.");
        }

        $tokens = $this->tokens($spec, $now ?? now());
        $flags = ['publishable' => $spec->publishable, 'slug' => $spec->slug, 'widget' => $spec->widget, 'editor' => $spec->editors !== [], 'media' => $spec->medias !== []];
        $written = [];

        foreach ($this->files->allFiles(self::stubPath(), true) as $stub) {
            $content = $this->files->get($stub->getPathname());
            $lines = explode("\n", $content);
            if (preg_match('/^%%file-if (!?)(\w+)%%$/', $lines[0], $m) === 1) {
                if ($this->flag($flags, $m[2], $m[1] === '!') === false) {
                    continue;
                }
                array_shift($lines);
            }

            $relative = $this->replace(substr($stub->getRelativePathname(), 0, -strlen('.stub')), $tokens);
            $body = $this->replace($this->blocks($lines, $flags), $tokens);
            $path = $target.'/'.$relative;
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $body);
            $written[] = $relative;
        }
        sort($written);

        return $written;
    }

    /** @param  array<string, bool>  $flags */
    private function flag(array $flags, string $name, bool $negate): bool
    {
        return ($flags[$name] ?? false) !== $negate;
    }

    /**
     * @param  list<string>  $lines
     * @param  array<string, bool>  $flags
     */
    private function blocks(array $lines, array $flags): string
    {
        $out = [];
        $stack = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*%%if (!?)(\w+)%%\s*$/', $line, $m) === 1) {
                $stack[] = $this->flag($flags, $m[2], $m[1] === '!');

                continue;
            }
            if (preg_match('/^\s*%%endif%%\s*$/', $line) === 1) {
                array_pop($stack);

                continue;
            }
            if (! in_array(false, $stack, true)) {
                $out[] = $line;
            }
        }

        return implode("\n", $out);
    }

    /** @param  array<string, string>  $tokens */
    private function replace(string $text, array $tokens): string
    {
        $replaced = strtr($text, array_combine(array_map(fn (string $k): string => '%%'.$k.'%%', array_keys($tokens)), array_values($tokens)));
        if (preg_match('/%%(\w+)%%/', $replaced, $m) === 1) {
            throw new RuntimeException("Unknown stub token %%{$m[1]}%%.");
        }

        return $replaced;
    }

    /** @return array<string, string> */
    private function tokens(ModuleSpec $spec, \DateTimeInterface $now): array
    {
        $first = $spec->fields[0];
        $en = [];
        $sr = [];
        $columns = [];
        $rules = [];
        $form = [];
        foreach ($spec->fields as $i => $field) {
            $label = Str::headline($field);
            $en[] = "        '{$field}' => '{$label}',";
            $sr[] = "        '{$field}' => '{$label}',";
            $rich = in_array($field, $spec->editors, true);
            $columns[] = $i === 0 ? "            \$table->string('{$field}')->index();" : ($rich ? "            \$table->longText('{$field}')->nullable();" : "            \$table->text('{$field}')->nullable();");
            $rules[] = $i === 0 ? "            '{$field}' => ['required', 'string', 'max:255'],".'' : "            '{$field}' => ['nullable', 'string', 'max:".($rich ? '200000' : '65000')."'],";
            $form[] = $i === 0 ? "'{$field}' => 'text'" : "'{$field}' => '".($rich ? 'editor' : 'textarea')."'";
        }
        $mediaFields = [];
        $mediaColumns = [];
        $mediaCasts = [];
        $mediaMigration = [];
        foreach ($spec->medias as $media => $many) {
            $mediaFields[] = "'{$media}' => ['multiple' => ".($many ? 'true' : 'false').']';
            $mediaColumns[] = "'{$media}'";
            $mediaCasts[] = "'{$media}' => 'array'";
            $mediaMigration[] = "            \$table->json('{$media}')->nullable(); // media ids";
            $label = Str::headline($media);
            $en[] = "        '{$media}' => '{$label}',";
            $sr[] = "        '{$media}' => '{$label}',";
        }
        $perms = array_map(fn (string $p): string => json_encode($p, JSON_UNESCAPED_SLASHES) ?: '""', $spec->permissions());
        $grantsAdmin = array_map(fn (string $a): string => "'{$a}'", $spec->actions());
        $grantsEditor = array_map(fn (string $a): string => "'{$a}'", array_values(array_filter($spec->actions(), fn (string $a): bool => in_array($a, ['view', 'create', 'update'], true))));

        return [
            'NAME' => $spec->name,
            'SNAKE' => $spec->snake(),
            'STUDLY' => $spec->studly(),
            'MODEL' => $spec->model(),
            'NS' => $spec->namespace(),
            'NS_JSON' => str_replace('\\', '\\\\', $spec->namespace()),
            'PACKAGE' => $spec->package(),
            'TITLE' => $spec->title(),
            'TABLE' => $spec->table(),
            'TRANSLATIONS_TABLE' => $spec->translationsTable(),
            'FK' => $spec->foreignKey(),
            'DATE' => $now->format('Y_m_d_His'),
            'TITLE_FIELD' => $first,
            'MEDIA_FIELDS' => implode(', ', $mediaFields),
            'MEDIA_COLUMNS' => implode(', ', $mediaColumns),
            'MEDIA_CASTS' => implode(', ', $mediaCasts),
            'MEDIA_MIGRATION' => implode("\n", $mediaMigration),
            'MEDIA_FIELD' => array_key_first($spec->medias) ?? '',
            'MEDIA_MANY' => ($spec->medias[array_key_first($spec->medias)] ?? false) ? 'true' : 'false',
            'EDITOR_FIELD' => $spec->editors[0] ?? $first,
            'TRANSLATED' => implode(', ', array_map(fn (string $f): string => "'{$f}'", $spec->translatedAttributes())),
            'FORM_FIELDS' => implode(', ', $form),
            'COLUMNS' => implode("\n", $columns),
            'RULES' => implode("\n", $rules),
            'FIELD_LABELS_EN' => implode("\n", $en),
            'FIELD_LABELS_SR' => implode("\n", $sr),
            'PERMISSIONS_JSON' => implode(', ', $perms),
            'GRANTS_ADMIN' => implode(', ', $grantsAdmin),
            'GRANTS_EDITOR' => implode(', ', $grantsEditor),
            'PUBLIC_URL' => $spec->slug ? '/'.$spec->name.'/{slug}' : '/'.$spec->name.'/{id}',
            'PERMISSIONS_LIST' => implode(', ', array_map(fn (string $p): string => "'{$p}'", $spec->permissions())),
            'LOOKUP' => $spec->slug ? 'whereTranslatedSlug($slug)' : 'whereKey($id)',
            'WORKFLOW_TEXT' => $spec->publishable ? ' with the draft / published / scheduled workflow' : '',
            'WORKFLOW_TEXT_JSON' => $spec->publishable ? ', drafts, scheduled publishing and real-site preview' : '',
        ];
    }
}
