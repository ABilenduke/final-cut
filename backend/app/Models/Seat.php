<?php

namespace App\Models;

use App\Enums\SeatType;
use Database\Factories\SeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['auditorium_id', 'label', 'row', 'number', 'type'])]
class Seat extends Model
{
    /** @use HasFactory<SeatFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'type' => SeatType::class,
            'number' => 'integer',
        ];
    }

    public function auditorium(): BelongsTo
    {
        return $this->belongsTo(Auditorium::class);
    }
}
