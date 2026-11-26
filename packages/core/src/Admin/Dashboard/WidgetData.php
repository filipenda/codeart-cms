<?php

namespace Codeart\Core\Admin\Dashboard;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Data provider of a dashboard widget; resolved from the container (constructor injection works).
 * Return the array the widget's view receives as `$data`, or null to leave the widget out of the page.
 */
interface WidgetData
{
    /** @return array<string, mixed>|null */
    public function data(?Authenticatable $user): ?array;
}
