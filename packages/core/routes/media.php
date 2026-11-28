<?php

use Codeart\Core\Media\Http\MediaController;
use Codeart\Core\Media\Http\MediaFolderController;
use Illuminate\Support\Facades\Route;

/*
 * Media library (core, always on). Under the admin path. Every route: signed in + may enter the admin; the controller then
 * checks the `media.*` permission (403). Deleting needs the server-issued confirmation token (`codeart.confirmed`).
 */
$guard = config('codeart.auth.guard', 'admin');

Route::middleware(['auth:'.$guard, 'admin.access'])->prefix('media')->group(function () {
    Route::get('/', [MediaController::class, 'index'])->name('admin.media.index');
    Route::get('items', [MediaController::class, 'items'])->middleware('throttle:240,1')->name('admin.media.items');
    Route::post('/', [MediaController::class, 'store'])->middleware('throttle:300,1')->name('admin.media.store');
    Route::put('settings', [MediaController::class, 'settings'])->middleware('throttle:20,1')->name('admin.media.settings');
    Route::post('bulk-move', [MediaController::class, 'bulkMove'])->middleware('throttle:60,1')->name('admin.media.bulk-move');
    Route::post('bulk-delete', [MediaController::class, 'bulkDestroy'])->middleware(['throttle:60,1', 'codeart.confirmed'])->name('admin.media.bulk-delete');

    Route::post('folders', [MediaFolderController::class, 'store'])->middleware('throttle:60,1')->name('admin.media.folders.store');
    Route::post('folders/delete', [MediaFolderController::class, 'destroy'])->middleware(['throttle:60,1', 'codeart.confirmed'])->name('admin.media.folders.destroy');
    Route::put('folders/{id}', [MediaFolderController::class, 'update'])->whereNumber('id')->middleware('throttle:60,1')->name('admin.media.folders.update');

    Route::get('{id}', [MediaController::class, 'show'])->whereNumber('id')->name('admin.media.show');
    Route::put('{id}', [MediaController::class, 'update'])->whereNumber('id')->middleware('throttle:120,1')->name('admin.media.update');
    Route::post('{id}/replace', [MediaController::class, 'replace'])->whereNumber('id')->middleware('throttle:60,1')->name('admin.media.replace');
});
