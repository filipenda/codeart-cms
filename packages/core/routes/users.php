<?php

use Codeart\Core\Profile\ProfileController;
use Codeart\Core\Users\Http\InvitationsController;
use Codeart\Core\Users\Http\UsersController;
use Illuminate\Support\Facades\Route;

/*
 * Users management (core, always on). Under the admin path. Every route: signed in + may enter the admin; the
 * controller/action then asks UserPolicy (403). Dangerous actions additionally need the server-issued confirmation token
 * (`codeart.confirmed`) AND a fresh password confirmation (`password.confirm`, checked first so the token is not spent).
 */
$guard = config('codeart.auth.guard', 'admin');
$dangerous = ['password.confirm', 'codeart.confirmed'];

// "Moj profil": every user who may enter the admin; always the signed-in user's own row.
Route::middleware(['auth:'.$guard, 'admin.access'])->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('admin.profile');
    Route::put('/', [ProfileController::class, 'update'])->middleware('throttle:30,1')->name('admin.profile.update');
    Route::put('password', [ProfileController::class, 'password'])->middleware('throttle:10,1')->name('admin.profile.password');
    Route::post('theme', [ProfileController::class, 'theme'])->middleware('throttle:60,1')->name('admin.profile.theme');
});

Route::middleware(['auth:'.$guard, 'admin.access'])->prefix('users')->group(function () use ($dangerous) {
    Route::get('/', [UsersController::class, 'index'])->name('admin.users.index');

    // Invitations: the only way to add a person. Sending is throttled per user.
    Route::get('invitations', [InvitationsController::class, 'index'])->name('admin.users.invitations');
    Route::get('invite', [InvitationsController::class, 'create'])->name('admin.users.invite');
    Route::post('invite', [InvitationsController::class, 'store'])->middleware('throttle:10,1')->name('admin.users.invite.store');
    Route::post('invitations/{id}/resend', [InvitationsController::class, 'resend'])->whereNumber('id')->middleware('throttle:10,1')->name('admin.users.invitations.resend');
    Route::delete('invitations/{id}', [InvitationsController::class, 'revoke'])->whereNumber('id')->middleware(['password.confirm', 'codeart.confirmed'])->name('admin.users.invitations.revoke');

    Route::middleware('throttle:60,1')->group(function () use ($dangerous) {
        Route::get('{id}', [UsersController::class, 'edit'])->whereNumber('id')->name('admin.users.edit');
        Route::put('{id}', [UsersController::class, 'update'])->whereNumber('id')->name('admin.users.update');
        Route::post('{id}/approve', [UsersController::class, 'approve'])->whereNumber('id')->name('admin.users.approve');
        Route::post('{id}/reject', [UsersController::class, 'reject'])->whereNumber('id')->name('admin.users.reject');
        Route::post('{id}/unblock', [UsersController::class, 'unblock'])->whereNumber('id')->name('admin.users.unblock');
        Route::post('{id}/restore', [UsersController::class, 'restore'])->whereNumber('id')->name('admin.users.restore');

        Route::middleware($dangerous)->whereNumber('id')->group(function () {
            Route::put('{id}/role', [UsersController::class, 'role'])->name('admin.users.role');
            Route::post('{id}/block', [UsersController::class, 'block'])->name('admin.users.block');
            Route::delete('{id}', [UsersController::class, 'destroy'])->name('admin.users.destroy');
            Route::delete('{id}/force', [UsersController::class, 'forceDestroy'])->name('admin.users.force-destroy');
            Route::post('{id}/reset-2fa', [UsersController::class, 'resetTwoFactor'])->name('admin.users.reset-2fa');
        });
    });
});
