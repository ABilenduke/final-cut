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

    protected function available(): Attribute
    {
        return Attribute::make(
            get: fn () => is_null($this->unavailable_at),
        );
    }

    public function scopeCurrentlyAvailable(Builder $query): Builder
    {
        return $query->whereNull('unavailable_at');
    }

    public function scopeCurrentlyUnavailable(Builder $query): Builder
    {
        return $query->whereNotNull('unavailable_at');
    }
}
