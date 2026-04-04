<?php

namespace App\Models;

use Database\Factories\AuditoriumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'total_seats'])]
class Auditorium extends Model
{
    /** @use HasFactory<AuditoriumFactory> */
    use HasFactory, HasUuids;

    protected $table = 'auditoriums';

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }
}
