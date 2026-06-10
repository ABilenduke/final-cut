<?php

namespace App\Models;

use App\Enums\MenuCategory;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property int $price
 * @property MenuCategory $category
 * @property string|null $image_url
 * @property array|null $allergens
 * @property array|null $dietary
 * @property Carbon|null $unavailable_at
 * @property Carbon|null $featured_on_home_at Home food-teaser curation flag — several items may carry it.
 */
#[Fillable([
    'name', 'description', 'price', 'category', 'image_url',
    'allergens', 'dietary', 'unavailable_at', 'featured_on_home_at',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, HasUuids, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'price', 'category', 'image_url', 'allergens', 'dietary', 'unavailable_at', 'featured_on_home_at'])
            ->setDescriptionForEvent(fn (string $eventName) => $eventName)
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('admin');
    }

    protected $appends = ['available'];

    protected function casts(): array
    {
        return [
            'category' => MenuCategory::class,
            'price' => 'integer',
            'allergens' => 'array',
            'dietary' => 'array',
            'unavailable_at' => 'datetime',
            'featured_on_home_at' => 'datetime',
        ];
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)
            ->withPivot(['price_override', 'unavailable_at'])
            ->withTimestamps();
    }

    public function priceForLocation(): int
    {
        return $this->pivot?->price_override ?? $this->price;
    }

    protected function available(): Attribute
    {
        return Attribute::make(
            get: fn () => is_null($this->unavailable_at),
        );
    }

    public function scopeCurrentlyAvailable(Builder $query): Builder
    {
        return $query->whereNull('menu_items.unavailable_at');
    }

    public function scopeCurrentlyUnavailable(Builder $query): Builder
    {
        return $query->whereNotNull('menu_items.unavailable_at');
    }
}
