<?php

namespace Codeart\Core\Tests\Support;

use Codeart\Core\Translations\Translatable;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use Translatable;

    protected $guarded = [];

    public function translatedAttributes(): array
    {
        return ['title', 'description', 'slug'];
    }
}
