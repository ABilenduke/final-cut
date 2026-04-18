<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

$notFound = static fn () => response()->json([
    'message' => 'Not Found',
], 404);

Route::any('/', $notFound)->withoutMiddleware([
    PreventRequestForgery::class,
]);

Route::any('{fallbackPlaceholder}', $notFound)
    ->where('fallbackPlaceholder', '.*')
    ->withoutMiddleware([
        PreventRequestForgery::class,
    ]);
