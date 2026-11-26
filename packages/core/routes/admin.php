<?php

use Codeart\Core\Admin\Http\DashboardController;
use Illuminate\Support\Facades\Route;

/*
 * Core's admin pages. Loaded under the admin path with the `web` group; every route here (and every module's admin
 * route) uses `auth:admin` + `admin.access`.
 */

Route::middleware(['auth:'.config('codeart.auth.guard', 'admin'), 'admin.access'])->group(function () {
    Route::get('/', DashboardController::class)->name('admin.dashboard');
});
