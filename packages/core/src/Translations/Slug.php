<?php

namespace Codeart\Core\Translations;

use Illuminate\Support\Str;

/** Slugs are always lowercase ASCII Latin, in every locale. */
class Slug
{
    public static function make(string $text): string
    {
        // Serbian convention: đ -> dj (Str::slug alone would give "d").
        $slug = Str::slug(str_replace(['đ', 'Đ'], ['dj', 'Dj'], $text));

        return $slug !== '' ? $slug : 'item';
    }
}
