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

#[Fillable([
    'name', 'description', 'price', 'category', 'image_url',
    'allergens', 'dietary', 'unavailable_at',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, HasUuids;

    protected $appends = ['available'];

    protected function casts(): array
    {
        return [
            'category' => MenuCategory::class,
            'price' => 'integer',
            'allergens' => 'array',
            'dietary' => 'array',
            'unavailable_at' => 'datetime',
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

    public function scopeCurrentlyAvailable(Builder $query, ?Location $location = null): Builder
    {
        $query->whereNull('menu_items.unavailable_at');

        if ($location) {
            $query->select('menu_items.*')
                ->join('location_menu_item', function ($join) use ($location) {
                    $join->on('location_menu_item.menu_item_id', '=', 'menu_items.id')
                        ->where('location_menu_item.location_id', '=', $location->id);
                })
                ->whereNull('location_menu_item.unavailable_at');
        }

        return $query;
    }

    public function scopeCurrentlyUnavailable(Builder $query): Builder
    {
        return $query->whereNotNull('menu_items.unavailable_at');
    }
}
