<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/example', fn () => response()->json([
    'items' => DB::table('example_items')->orderBy('id')->pluck('title'),
]))->middleware('can:example.view')->name('example.index');
