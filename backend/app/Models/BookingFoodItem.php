<?php

namespace App\Models;

use Database\Factories\BookingFoodItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'menu_item_id', 'name', 'quantity', 'unit_price', 'total_price'])]
class BookingFoodItem extends Model
{
    /** @use HasFactory<BookingFoodItemFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total_price' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
