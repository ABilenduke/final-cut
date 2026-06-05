<?php

use App\Models\BookingFoodItem;
use App\Models\MenuItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('deleting a menu item nulls the booking_food_items FK but keeps the price snapshot', function () {
    $menuItem = MenuItem::factory()->create();
    $item = BookingFoodItem::factory()->create([
        'menu_item_id' => $menuItem->id,
        'name' => 'Large Popcorn',
        'unit_price' => 999,
        'quantity' => 2,
        'total_price' => 1998,
    ]);

    $menuItem->delete();
    $item->refresh();

    // nullOnDelete: the back-reference is cleared, but the historical order
    // line (name + prices) is a snapshot and must survive menu cleanup.
    expect($item->menu_item_id)->toBeNull()
        ->and($item->name)->toBe('Large Popcorn')
        ->and($item->unit_price)->toBe(999)
        ->and($item->total_price)->toBe(1998);
});

test('a booking_food_item with a non-existent menu_item_id is rejected by the FK', function () {
    $item = BookingFoodItem::factory()->make([
        'menu_item_id' => '00000000-0000-0000-0000-000000000000',
    ]);

    // Savepoint so the FK violation rolls back without poisoning the outer
    // RefreshDatabase transaction.
    expect(fn () => DB::transaction(fn () => $item->save()))
        ->toThrow(QueryException::class);
});
