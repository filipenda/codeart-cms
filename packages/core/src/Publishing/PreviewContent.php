<?php

namespace Codeart\Core\Publishing;

use Illuminate\Database\Eloquent\Model;

/** What a client site renders: the text attributes of one model in one locale, draft or published (`$isPreview` tells which). */
final readonly class PreviewContent
{
    /** @param  array<string, string|null>  $attributes */
    public function __construct(public Model $model, public array $attributes, public bool $isPreview) {}

    public function get(string $attribute, ?string $default = null): ?string
    {
        return $this->attributes[$attribute] ?? $default;
    }

    public function __get(string $attribute): ?string
    {
        return $this->get($attribute);
    }

    public function __isset(string $attribute): bool
    {
        return isset($this->attributes[$attribute]);
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
