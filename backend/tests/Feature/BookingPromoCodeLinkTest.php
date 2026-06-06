<?php

use App\Models\Booking;
use App\Models\PromoCode;
use Illuminate\Support\Facades\Schema;

/**
 * The bookings.promo_code_id link that powers per_user_limit enforcement:
 * which promo a booking redeemed, so prior redemptions by the same customer
 * can be counted.
 */
test('bookings has a promo_code_id column', function (): void {
    expect(Schema::hasColumn('bookings', 'promo_code_id'))->toBeTrue();
});

test('a booking links to its promo code', function (): void {
    $promo = PromoCode::factory()->create();
    $booking = Booking::factory()->create(['promo_code_id' => $promo->id]);

    expect($booking->promo_code_id)->toBe($promo->id);
    expect($booking->promoCode)->toBeInstanceOf(PromoCode::class);
    expect($booking->promoCode->id)->toBe($promo->id);
});

test('a booking with no promo persists a null promo_code_id', function (): void {
    $booking = Booking::factory()->create();

    expect($booking->promo_code_id)->toBeNull();
});

test('hard-deleting a promo nulls the booking link but keeps the booking', function (): void {
    $promo = PromoCode::factory()->create();
    $booking = Booking::factory()->create(['promo_code_id' => $promo->id]);

    $promo->delete();

    expect(Booking::find($booking->id))->not->toBeNull();
    expect($booking->fresh()->promo_code_id)->toBeNull();
});
