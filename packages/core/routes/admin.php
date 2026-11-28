<?php

use Codeart\Core\Admin\Http\DashboardController;
use Codeart\Core\Admin\Http\LocalEnvironmentOnly;
use Codeart\Core\Admin\Http\UiShowcaseController;
use Codeart\Core\Confirmation\ConfirmController;
use Codeart\Core\Tables\TableController;
use Illuminate\Support\Facades\Route;

/*
 * Core's admin pages. Loaded under the admin path with the `web` group; every route here (and every module's admin
 * route) uses `auth:admin` + `admin.access`.
 */

Route::middleware(['auth:'.config('codeart.auth.guard', 'admin'), 'admin.access'])->group(function () {
    Route::get('/', DashboardController::class)->name('admin.dashboard');

    // Bulk actions and CSV export of every registered table (TableRegistry); permissions are checked in the controller.
    Route::post('tables/{table}/bulk', [TableController::class, 'bulk'])->name('codeart.tables.bulk');
    // Confirmation step of `<x-codeart::confirm>`; GET only returns to the page the confirmation came from.
    Route::post('confirm', [ConfirmController::class, 'show'])->name('codeart.confirm');
    Route::get('confirm', [ConfirmController::class, 'back']);
    Route::get('tables/{table}/export', [TableController::class, 'export'])->name('codeart.tables.export');
});

/*
 * Component showcase: local environment only (404 anywhere else, checked before anything else so the page's existence is
 * not revealed), then the usual admin guard; the controller requires `manage-modules` (403 for other staff). No menu item.
 */
Route::middleware([
    LocalEnvironmentOnly::class,
    'auth:'.config('codeart.auth.guard', 'admin'),
    'admin.access',
])->group(function () {
    Route::get('/_ui', [UiShowcaseController::class, 'show'])->name('codeart.ui-showcase');
    Route::post('/_ui', [UiShowcaseController::class, 'submit'])->name('codeart.ui-showcase.submit');
});
