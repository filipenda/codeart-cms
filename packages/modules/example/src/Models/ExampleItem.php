<?php

namespace Codeart\Example\Models;

use Codeart\Core\Translations\Translatable;
use Codeart\Example\Database\Factories\ExampleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $title current-locale value (fallback chain applies)
 * @property string|null $description
 * @property string|null $slug
 */
class ExampleItem extends Model
{
    /** @use HasFactory<ExampleItemFactory> */
    use HasFactory;

    use Translatable;

    public function translatedAttributes(): array
    {
        return ['title', 'description', 'slug'];
    }

    protected static function newFactory(): ExampleItemFactory
    {
        return ExampleItemFactory::new();
    }
}
