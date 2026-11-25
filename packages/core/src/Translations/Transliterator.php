<?php

namespace Codeart\Core\Translations;

/** Converts text from a source script to a derived one (e.g. Serbian Latin -> Cyrillic). */
interface Transliterator
{
    public function transliterate(string $text): string;
}
