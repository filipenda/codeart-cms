<?php

namespace Codeart\Core\Admin\Http;

use Codeart\Core\Admin\Dashboard\WidgetRegistry;
use Codeart\Core\Auth\Admin;
use Illuminate\Contracts\View\View;

class DashboardController
{
    public function __invoke(WidgetRegistry $widgets): View
    {
        $user = Admin::guard()->user();

        return view('codeart::admin.dashboard', [
            'user' => $user,
            'widgets' => $widgets->render($user),
        ]);
    }
}
