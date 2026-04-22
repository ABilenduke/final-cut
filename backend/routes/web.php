<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

$notFound = static fn () => response()->json([
    'message' => 'Not Found',
], 404);

Route::any('/', $notFound)->withoutMiddleware([
    PreventRequestForgery::class,
]);

// Marked as a Laravel fallback so it only matches when no other route does.
// Without this, vendor routes registered after bootstrap/app.php's then:
// closure (Sanctum's csrf-cookie, Livewire, Filament export endpoints) would
// be swallowed by the `.*` catch-all under the primary domain.
Route::any('{fallbackPlaceholder}', $notFound)
    ->where('fallbackPlaceholder', '.*')
    ->fallback()
    ->withoutMiddleware([
        PreventRequestForgery::class,
    ]);
