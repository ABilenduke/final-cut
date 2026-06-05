<?php

use App\Models\Auditorium;
use App\Models\BookingSeat;
use App\Models\LoyaltyAdjustment;
use App\Models\Seat;
use App\Models\User;

test('deleting a seat nulls booking_seats.seat_id but keeps the price/section snapshot', function () {
    $auditorium = Auditorium::factory()->create();
    $seat = Seat::factory()->create(['auditorium_id' => $auditorium->id]);

    $bookingSeat = BookingSeat::factory()->create([
        'seat_id' => $seat->id,
        'section' => 'Premium',
        'price' => 1500,
    ]);

    // Seat regeneration mass-deletes seats; the financial snapshot on a
    // (possibly terminal) booking must survive, with only the seat reference cleared.
    $seat->delete();

    $fresh = BookingSeat::find($bookingSeat->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->seat_id)->toBeNull()
        ->and($fresh->section)->toBe('Premium')
        ->and($fresh->price)->toBe(1500);
});

test('deleting a user nulls loyalty_adjustments.user_id but keeps the audit row', function () {
    $user = User::factory()->create();

    $adjustment = LoyaltyAdjustment::factory()->create([
        'user_id' => $user->id,
        'points_delta' => 250,
        'reason' => 'Goodwill credit',
    ]);

    // Losing attribution is acceptable; destroying the loyalty audit trail is not.
    $user->delete();

    $fresh = LoyaltyAdjustment::find($adjustment->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->user_id)->toBeNull()
        ->and($fresh->points_delta)->toBe(250)
        ->and($fresh->reason)->toBe('Goodwill credit');
});
