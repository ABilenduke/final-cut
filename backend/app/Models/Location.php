<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'slug'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory, HasUuids;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function auditoriums(): HasMany
    {
        return $this->hasMany(Auditorium::class);
    }

    public function showtimes(): HasManyThrough
    {
        return $this->hasManyThrough(Showtime::class, Auditorium::class);
    }
}
