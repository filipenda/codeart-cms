<?php

namespace Codeart\Core\Modules\Generator;

use Illuminate\Support\Str;
use InvalidArgumentException;

/** What `make:module` was asked for, validated, with every name derived from the module name in one place. */
final readonly class ModuleSpec
{
    /** Names that would collide with columns, relations or helpers the generated code already has. */
    private const array RESERVED_FIELDS = ['id', 'status', 'slug', 'locale', 'published_at', 'scheduled_at', 'draft_revision_id', 'created_at', 'updated_at', 'manual_fields', 'translations'];

    /** @param  list<string>  $fields translatable attributes; the first one is the title (a string, required) */
    public function __construct(
        public string $name,
        public array $fields = ['title', 'body'],
        public bool $slug = false,
        public bool $publishable = true,
        public bool $widget = true,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException("Module name \"{$name}\" must be a lowercase slug (letters, digits, single dashes), e.g. pages or blog-posts.");
        }
        if ($fields === []) {
            throw new InvalidArgumentException('At least one translatable field is needed (the first one is the title).');
        }
        foreach ($fields as $field) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $field) !== 1 || in_array($field, self::RESERVED_FIELDS, true)) {
                throw new InvalidArgumentException("Field \"{$field}\" is not allowed: use lowercase letters, digits and underscores, and not one of: ".implode(', ', self::RESERVED_FIELDS).'.');
            }
        }
        if (count(array_unique($fields)) !== count($fields)) {
            throw new InvalidArgumentException('Translatable fields must be unique.');
        }
    }

    /** @param  string  $fields comma separated list */
    public static function fromOptions(string $name, string $fields, bool $slug, bool $publishable, bool $widget): self
    {
        $list = array_values(array_filter(array_map('trim', explode(',', $fields)), fn (string $f): bool => $f !== ''));

        return new self(strtolower(trim($name)), $list === [] ? ['title', 'body'] : $list, $slug, $publishable, $widget);
    }

    /** `blog_posts` (tables, config-safe identifiers) */
    public function snake(): string
    {
        return str_replace('-', '_', $this->name);
    }

    /** `BlogPosts` (namespace and class prefix) */
    public function studly(): string
    {
        return Str::studly($this->name);
    }

    /** `BlogPost` (model class) */
    public function model(): string
    {
        return Str::singular($this->studly());
    }

    public function namespace(): string
    {
        return 'Codeart\\'.$this->studly();
    }

    public function package(): string
    {
        return 'codeart/module-'.$this->name;
    }

    public function title(): string
    {
        return Str::headline($this->name);
    }

    public function table(): string
    {
        return $this->snake().'_'.Str::snake(Str::plural($this->model()));
    }

    public function translationsTable(): string
    {
        return $this->snake().'_'.Str::snake($this->model()).'_translations';
    }

    public function foreignKey(): string
    {
        return Str::snake($this->model()).'_id';
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return array_map(fn (string $a): string => $this->name.'.'.$a, $this->actions());
    }

    /** @return list<string> */
    public function actions(): array
    {
        return $this->publishable ? ['view', 'create', 'update', 'publish', 'delete'] : ['view', 'create', 'update', 'delete'];
    }

    /** @return list<string> */
    public function translatedAttributes(): array
    {
        return $this->slug ? [...$this->fields, 'slug'] : $this->fields;
    }
}
