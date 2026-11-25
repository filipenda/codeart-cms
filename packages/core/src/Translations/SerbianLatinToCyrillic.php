<?php

namespace Codeart\Core\Translations;

/**
 * Serbian Latin -> Cyrillic, pure PHP (no intl needed).
 *
 * Digraphs lj/nj/dž (all case forms) become one letter. A plain "dj" is NOT turned into ђ
 * (ambiguous: "nadjačati" is н-а-д-ј-а-ч-а-т-и; write đ for ђ). Known ambiguous words such as
 * "injekcija" (should be "инјекција", not "ињекција") cannot be solved by rules: the editor fixes
 * them by hand in the Cyrillic translation, and that manual edit is never overwritten.
 *
 * Left untouched: HTML tags (so attribute values too), script/style blocks, HTML entities,
 * URLs, e-mail addresses, placeholders (:name, %s, %1$s, {name}, {{ ... }}, {!! ... !!}).
 * Characters outside Serbian Latin (w q x y, digits, punctuation) pass through unchanged.
 */
class SerbianLatinToCyrillic implements Transliterator
{
    /** One outer capture group, no inner ones: preg_split then returns text and protected parts alternately. */
    private const PROTECTED = '~(
        <script\b[^>]*>.*?</script\s*>
      | <style\b[^>]*>.*?</style\s*>
      | <[^>]*>
      | &(?:\#\d+|\#x[0-9a-f]+|[a-z][a-z0-9]*);
      | (?:https?://|ftp://|www\.)[^\s<>"\']+
      | [a-z0-9._%+\-]+@[a-z0-9\-]+(?:\.[a-z0-9\-]+)*\.[a-z]{2,}
      | \{\{.*?\}\} | \{!!.*?!!\} | \{[^{}\s]*\}
      | (?<![\p{L}\p{N}]):[a-z_][a-z0-9_]*
      | %(?:\d+\$)?[-+0\#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX%]
    )~uxsi';

    private const MAP = [
        // Digraphs first (strtr also prefers the longest key, this is for readability).
        'Lj' => 'Љ', 'LJ' => 'Љ', 'lj' => 'љ',
        'Nj' => 'Њ', 'NJ' => 'Њ', 'nj' => 'њ',
        'Dž' => 'Џ', 'DŽ' => 'Џ', 'dž' => 'џ',
        'A' => 'А', 'B' => 'Б', 'C' => 'Ц', 'Č' => 'Ч', 'Ć' => 'Ћ', 'D' => 'Д', 'Đ' => 'Ђ',
        'E' => 'Е', 'F' => 'Ф', 'G' => 'Г', 'H' => 'Х', 'I' => 'И', 'J' => 'Ј', 'K' => 'К',
        'L' => 'Л', 'M' => 'М', 'N' => 'Н', 'O' => 'О', 'P' => 'П', 'R' => 'Р', 'S' => 'С',
        'Š' => 'Ш', 'T' => 'Т', 'U' => 'У', 'V' => 'В', 'Z' => 'З', 'Ž' => 'Ж',
        'a' => 'а', 'b' => 'б', 'c' => 'ц', 'č' => 'ч', 'ć' => 'ћ', 'd' => 'д', 'đ' => 'ђ',
        'e' => 'е', 'f' => 'ф', 'g' => 'г', 'h' => 'х', 'i' => 'и', 'j' => 'ј', 'k' => 'к',
        'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п', 'r' => 'р', 's' => 'с',
        'š' => 'ш', 't' => 'т', 'u' => 'у', 'v' => 'в', 'z' => 'з', 'ž' => 'ж',
    ];

    public function transliterate(string $text): string
    {
        $parts = preg_split(self::PROTECTED, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text; // invalid UTF-8: never mangle
        }

        $out = '';
        foreach ($parts as $i => $part) {
            $out .= $i % 2 === 1 ? $part : strtr($part, self::MAP);
        }

        return $out;
    }
}
