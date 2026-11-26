<?php

namespace Codeart\Core\ActivityLog;

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
}
