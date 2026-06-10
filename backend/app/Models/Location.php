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

/**
 * @property array|null $hours Weekly opening hours keyed by lowercase weekday: {monday: {open, close}|null, ...}.
 */
#[Fillable([
    'name', 'slug',
    'phone', 'email',
    'street', 'city', 'state', 'postal_code', 'country',
    'timezone', 'latitude', 'longitude',
    'hours',
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
            'hours' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Auditorium, $this> */
    public function auditoriums(): HasMany
    {
        return $this->hasMany(Auditorium::class);
    }

    /** @return HasManyThrough<Showtime, Auditorium, $this> */
    public function showtimes(): HasManyThrough
    {
        return $this->hasManyThrough(Showtime::class, Auditorium::class);
    }

    /** @return BelongsToMany<MenuItem, $this> */
    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class)
            ->using(LocationMenuItemPivot::class)
            ->withPivot(['price_override', 'unavailable_at'])
            ->withTimestamps();
    }
}
