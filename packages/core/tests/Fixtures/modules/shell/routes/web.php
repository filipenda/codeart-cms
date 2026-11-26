<?php

use Illuminate\Support\Facades\Route;

Route::get('/shell-list', fn () => 'list')->name('shell.index');
Route::get('/shell-settings', fn () => 'settings')->name('shell.settings');
Route::get('/shell-new', fn () => 'new')->name('shell.create');
