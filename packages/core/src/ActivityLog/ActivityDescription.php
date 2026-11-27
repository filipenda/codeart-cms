<?php

namespace Codeart\Core\ActivityLog;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

/** Turns a logged event into text in the current panel language; unknown events fall back to the stored description. */
final class ActivityDescription
{
    public static function for(?string $event, string $description): string
    {
        if ($event !== null && $event !== '') {
            $key = 'codeart::admin.activity.'.$event;
            if (Lang::has($key) && is_string($text = __($key))) {
                return $text;
            }
        }

        return $description;
    }

    /**
     * Every known event key => its text in the locale (`auth.login` => `Prijava`).
     *
     * @return array<string, string>
     */
    public static function labels(string $locale): array
    {
        $tree = Lang::get('codeart::admin.activity', [], $locale);
        $out = [];
        foreach (is_array($tree) ? Arr::dot($tree) : [] as $key => $text) {
            $out[(string) $key] = (string) $text;
        }

        return $out;
    }

    /** Text of one event key, or the key itself. */
    public static function label(?string $event): string
    {
        return $event === null || $event === '' ? '' : (self::for($event, $event));
    }
}
