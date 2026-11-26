<?php

namespace Codeart\Core\Admin\Dashboard\Widgets;

use Codeart\Core\Admin\Dashboard\WidgetData;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\Activitylog\Models\Activity;

/** The last 8 entries of the activity log, with who did it and how long ago. */
class RecentActivityWidget implements WidgetData
{
    public const int LIMIT = 8;

    public function data(?Authenticatable $user): array
    {
        $entries = Activity::query()->with('causer')->latest('created_at')->latest('id')->limit(self::LIMIT)->get();

        return ['entries' => $entries->map(fn (Activity $a): array => [
            'description' => (string) $a->description,
            'causer' => $a->causer->name ?? null,
            'when' => $a->created_at?->diffForHumans(),
            'at' => $a->created_at?->toIso8601String(),
        ])->all()];
    }
}
