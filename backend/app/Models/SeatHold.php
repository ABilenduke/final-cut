<?php

namespace App\Models;

use Database\Factories\SeatHoldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-showtime, per-seat hold placed by an in-flight checkout session.
 *
 * The customer checkout flow that creates these rows is not yet implemented
 * — PURCHASE_FLOW.md's MVP does not use server-side holds. This model and
 * table exist so `AuditoriumService::generateSeats` can honestly refuse
 * regeneration if any hold is live; the mechanism is ready for the flow
 * that will later populate it.
 *
 * @property Carbon $expires_at
 */
#[Fillable(['showtime_id', 'seat_id', 'expires_at'])]
class SeatHold extends Model
{
    /** @use HasFactory<SeatHoldFactory> */
    use HasFactory, HasUuids;

    protected $table = 'seat_holds';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Showtime, $this> */
    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    /** @return BelongsTo<Seat, $this> */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }
}
