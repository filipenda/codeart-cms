<?php

use Codeart\Example\Models\ExampleItem;
use Illuminate\Support\Facades\Route;

Route::get('/example', fn () => response()->json([
    'items' => ExampleItem::query()->orderBy('id')->get()->map->title->values(),
]))->middleware('can:example.view')->name('example.index');
