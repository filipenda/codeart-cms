<?php

use Codeart\Core\Auth\Http\AccessCheckController;
use Codeart\Core\Auth\Http\InvitationController;
use Codeart\Core\Auth\Http\LocaleController;
use Codeart\Core\Auth\Http\RegistrationReceivedController;
use Codeart\Core\Auth\Http\SessionsController;
use Codeart\Core\Auth\Http\SessionStateController;
use Codeart\Core\Auth\Http\TwoFactorSetupController;
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

    // Screens that only need a signed-in user (not yet access to the admin).
    Route::get('two-factor-setup', TwoFactorSetupController::class)->name('admin.two-factor.setup');
    Route::get('registration-received', RegistrationReceivedController::class)->name('admin.registration.received');
});

// Language switcher of the sign-in screens: validates against the enabled locales.
Route::post('locale', LocaleController::class)->middleware('throttle:30,1')->name('codeart.locale');

Route::middleware(['admin.invitation', 'throttle:20,1'])->group(function () {
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('admin.invitation.show');
    Route::post('invitation/{token}', [InvitationController::class, 'accept'])->name('admin.invitation.accept');
});
