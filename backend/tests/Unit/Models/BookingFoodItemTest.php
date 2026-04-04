<?php

use App\Models\Booking;
use App\Models\BookingFoodItem;
use Illuminate\Support\Str;

it('creates a booking food item with UUID primary key', function () {
    $item = BookingFoodItem::factory()->create();
    expect($item->id)->toBeString();
    expect(Str::isUuid($item->id))->toBeTrue();
});

it('belongs to a booking', function () {
    $item = BookingFoodItem::factory()->create();
    expect($item->booking)->toBeInstanceOf(Booking::class);
});

it('stores prices as integers (cents)', function () {
    $item = BookingFoodItem::factory()->create([
        'unit_price' => 799,
        'quantity' => 2,
        'total_price' => 1598,
    ]);
    expect($item->unit_price)->toBe(799);
    expect($item->total_price)->toBe(1598);
});
