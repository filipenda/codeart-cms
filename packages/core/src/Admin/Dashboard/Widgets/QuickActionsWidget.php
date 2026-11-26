<?php

namespace Codeart\Core\Admin\Dashboard\Widgets;

use Codeart\Core\Admin\Dashboard\QuickActions;
use Codeart\Core\Admin\Dashboard\WidgetData;
use Illuminate\Contracts\Auth\Authenticatable;

class QuickActionsWidget implements WidgetData
{
    public function __construct(private readonly QuickActions $actions) {}

    public function data(?Authenticatable $user): ?array
    {
        $actions = $this->actions->visible($user);

        return $actions === [] ? null : ['actions' => $actions];
    }
}
