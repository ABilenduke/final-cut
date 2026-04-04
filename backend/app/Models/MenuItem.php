<?php

namespace App\Models;

use App\Enums\MenuCategory;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'description', 'price', 'category', 'image_url',
    'allergens', 'dietary', 'available',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'category' => MenuCategory::class,
            'price' => 'integer',
            'allergens' => 'array',
            'dietary' => 'array',
            'available' => 'boolean',
        ];
    }
}
