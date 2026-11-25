<?php

use Illuminate\Support\Facades\Route;

Route::get('/alpha', fn () => 'alpha ok')->middleware('can:alpha.view')->name('alpha.index');
