<?php

use Codeart\Core\Install\Http\EnsureNotInstalled;
use Codeart\Core\Install\Http\InstallController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', EnsureNotInstalled::class])->prefix('install')->name('install.')->controller(InstallController::class)->group(function (): void {
    Route::get('/', 'key')->name('key');
    Route::post('/', 'storeKey')->name('key.store');
    Route::get('requirements', 'requirements')->name('requirements');
    Route::get('database', 'database')->name('database');
    Route::post('database', 'storeDatabase')->name('database.store');
    Route::get('site', 'site')->name('site');
    Route::post('site', 'storeSite')->name('site.store');
    Route::get('admin', 'admin')->name('admin');
    Route::post('admin', 'storeAdmin')->name('admin.store');
    Route::get('modules', 'modules')->name('modules');
    Route::post('modules', 'storeModules')->name('modules.store');
    Route::get('run', 'run')->name('run');
    Route::post('run', 'execute')->name('run.execute');
});
