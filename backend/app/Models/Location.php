<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'name', 'slug',
    'phone', 'email',
    'street', 'city', 'state', 'postal_code', 'country',
    'timezone', 'latitude', 'longitude',
])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }

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

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class)
            ->withPivot(['price_override', 'unavailable_at'])
            ->withTimestamps();
    }
}
