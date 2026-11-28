<?php

namespace Codeart\Core\Profile;

/** `name` is the composed full name; first and last name are the editable parts. */
final class PersonName
{
    public static function compose(string $first, string $last): string
    {
        return trim(preg_replace('/\s+/u', ' ', trim($first).' '.trim($last)) ?? '');
    }

    /**
     * Splits on the first space: "Ana Marija Petrović" -> ["Ana", "Marija Petrović"]; no space -> [name, ''].
     *
     * @return array{0: string, 1: string}
     */
    public static function split(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * What the form shows: the stored parts, or the split of `name` for users who never saved them.
     *
     * @return array{0: string, 1: string}
     */
    public static function partsOf(mixed $user): array
    {
        $first = $user->first_name ?? null;
        $last = $user->last_name ?? null;
        if ($first === null && $last === null) {
            return self::split((string) ($user->name ?? ''));
        }

        return [(string) $first, (string) $last];
    }
}
