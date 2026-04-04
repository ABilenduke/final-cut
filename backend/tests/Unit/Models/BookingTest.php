<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Support\Str;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a booking with UUID primary key', function () {
    $booking = Booking::factory()->create();
    expect($booking->id)->toBeString();
    expect(Str::isUuid($booking->id))->toBeTrue();
});

it('auto-generates confirmation code with CVF prefix', function () {
    $booking = Booking::factory()->create();
    expect($booking->confirmation_code)->toStartWith('CVF-');
    expect(strlen($booking->confirmation_code))->toBeGreaterThanOrEqual(10);
});

it('generates unique confirmation codes from sequential values', function () {
    $codes = collect(range(1, 10))->map(fn () => Booking::generateConfirmationCode());
    expect($codes->unique()->count())->toBe(10);
});

it('does not overwrite explicit confirmation code', function () {
    $booking = Booking::factory()->create(['confirmation_code' => 'CVF-CUSTOM']);
    expect($booking->confirmation_code)->toBe('CVF-CUSTOM');
});

it('enforces unique confirmation code at database level', function () {
    Booking::factory()->create(['confirmation_code' => 'CVF-ABC123']);
    Booking::factory()->create(['confirmation_code' => 'CVF-ABC123']);
})->throws(\Illuminate\Database\QueryException::class);

it('belongs to a showtime', function () {
    $booking = Booking::factory()->create();
    expect($booking->showtime)->toBeInstanceOf(Showtime::class);
});

it('optionally belongs to a user', function () {
    $booking = Booking::factory()->create();
    expect($booking->user)->toBeInstanceOf(User::class);

    $guestBooking = Booking::factory()->guest()->create();
    expect($guestBooking->user)->toBeNull();
    expect($guestBooking->guest_email)->not()->toBeNull();
});

it('casts status to BookingStatus enum', function () {
    $booking = Booking::factory()->create(['status' => 'confirmed']);
    expect($booking->status)->toBe(BookingStatus::Confirmed);
});

it('casts payment_method to PaymentMethod enum', function () {
    $booking = Booking::factory()->create(['payment_method' => 'card']);
    expect($booking->payment_method)->toBe(PaymentMethod::Card);
});

it('stores monetary values as integers (cents)', function () {
    $booking = Booking::factory()->create([
        'subtotal' => 3600,
        'discount' => 500,
        'total' => 3100,
    ]);
    expect($booking->subtotal)->toBe(3600);
    expect($booking->discount)->toBe(500);
    expect($booking->total)->toBe(3100);
});

it('has seats relationship', function () {
    $booking = Booking::factory()->create();
    expect($booking->seats())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('has food items relationship', function () {
    $booking = Booking::factory()->create();
    expect($booking->foodItems())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});
