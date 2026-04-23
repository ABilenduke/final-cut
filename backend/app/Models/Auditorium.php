<?php

namespace App\Models;

use Database\Factories\AuditoriumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['location_id', 'name', 'slug', 'cleanup_minutes', 'notes', 'total_seats'])]
class Auditorium extends Model
{
    /** @use HasFactory<AuditoriumFactory> */
    use HasFactory, HasUuids;

    protected $table = 'auditoriums';

    protected function casts(): array
    {
        return [
            'cleanup_minutes' => 'integer',
            'total_seats' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(AuditoriumSection::class)->orderBy('display_order');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }
}
