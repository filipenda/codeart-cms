<?php

use Codeart\Core\Auth\Http\AccessCheckController;
use Codeart\Core\Auth\Http\InvitationController;
use Codeart\Core\Auth\Http\SessionsController;
use Codeart\Core\Auth\Http\SessionStateController;
use Illuminate\Support\Facades\Route;

/*
 * Core's own staff routes. Loaded under the admin path (setting auth.admin_path) with the `web` group.
 * Fortify adds login, logout, password reset, e-mail verification, two-factor and registration under
 * the same prefix; see docs/auth.md for every name.
 */

$guard = config('codeart.auth.guard', 'admin');

Route::middleware("auth:{$guard}")->group(function () {
    Route::get('session', SessionStateController::class)->name('admin.session');
    Route::get('access', AccessCheckController::class)->middleware('admin.access')->name('admin.access.check');

    Route::get('sessions', [SessionsController::class, 'index'])->name('admin.sessions.index');
    Route::delete('sessions/{id}', [SessionsController::class, 'destroy'])->name('admin.sessions.destroy');
    Route::delete('sessions', [SessionsController::class, 'destroyOthers'])->name('admin.sessions.destroy-others');
});

Route::middleware(['signed', 'throttle:20,1'])->group(function () {
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('admin.invitation.show');
    Route::post('invitation/{token}', [InvitationController::class, 'accept'])->name('admin.invitation.accept');
});
