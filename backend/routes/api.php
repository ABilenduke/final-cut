<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\FoodMenuController;
use App\Http\Controllers\Api\GiftCardController;
use App\Http\Controllers\Api\MovieController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\ShowtimeController;
use Illuminate\Support\Facades\Route;

// Movies (shared across locations)
Route::get('/movies', [MovieController::class, 'index']);
Route::get('/movies/{slug}', [MovieController::class, 'show']);

// Location-scoped resources
Route::prefix('locations/{location}')->group(function () {
    Route::get('/movies/{slug}/showtimes', [MovieController::class, 'showtimes']);
    Route::get('/showtimes/{id}', [ShowtimeController::class, 'show']);
    Route::get('/food-menu', [FoodMenuController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/confirm', [BookingController::class, 'confirm']);
});

// Bookings (not location-scoped — looked up by booking ID or confirmation code)
Route::get('/bookings/lookup', [BookingController::class, 'lookup']);
Route::get('/bookings/{id}', [BookingController::class, 'show']);

// Calendar
Route::get('/calendar/events', [CalendarEventController::class, 'index']);
Route::get('/calendar/events/{slug}', [CalendarEventController::class, 'show']);

// Auth
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Account (all auth-protected)
Route::middleware('auth:sanctum')->prefix('account')->group(function () {
    Route::get('/profile', [AccountController::class, 'profile']);
    Route::patch('/profile', [AccountController::class, 'updateProfile']);
    Route::get('/orders', [AccountController::class, 'orders']);
    Route::get('/bookings', [AccountController::class, 'bookings']);
    Route::get('/loyalty', [AccountController::class, 'loyalty']);
    Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
    Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
    Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy']);
});

// Gift Cards
Route::post('/gift-cards/purchase', [GiftCardController::class, 'purchase']);
Route::get('/gift-cards/balance', [GiftCardController::class, 'balance']);

// Contact / Rentals (rate-limited: 5 per minute)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/rentals/inquiry', [RentalController::class, 'store']);
    Route::post('/contact', [ContactController::class, 'store']);
});
