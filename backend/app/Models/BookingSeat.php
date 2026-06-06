<?php

namespace App\Models;

use Database\Factories\BookingSeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'showtime_id', 'seat_id', 'section', 'price'])]
class BookingSeat extends Model
{
    /** @use HasFactory<BookingSeatFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            // Trigger-maintained occupancy flag (migration
            // 2026_06_05_000000_add_booking_seats_occupancy_guard). Read-only —
            // intentionally NOT in #[Fillable]; the DB triggers own its value.
            'occupies_seat' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }
}
