<?php

namespace App\Models;

use Database\Factories\ShowtimeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Movie $movie
 * @property Auditorium $auditorium
 */
#[Fillable([
    'movie_id', 'auditorium_id', 'start_time', 'end_time',
    'cancelled_at', 'cancellation_reason',
    'price_standard', 'price_premium', 'price_accessible',
])]
class Showtime extends Model
{
    /** @use HasFactory<ShowtimeFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'cancelled_at' => 'datetime',
            'price_standard' => 'integer',
            'price_premium' => 'integer',
            'price_accessible' => 'integer',
        ];
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function auditorium(): BelongsTo
    {
        return $this->belongsTo(Auditorium::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
