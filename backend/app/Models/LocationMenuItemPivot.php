<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\MenuItemObserver;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Custom pivot model for the location_menu_item table.
 *
 * Fires cache version bumps for the cross-location food-menu public endpoint
 * whenever a pivot row is created, updated, or deleted.
 *
 * Registered via `using(LocationMenuItemPivot::class)` on both
 * Location::menuItems() and MenuItem::locations() relations.
 */
class LocationMenuItemPivot extends Pivot
{
    protected static function booted(): void
    {
        static::saved(function (): void {
            MenuItemObserver::bumpVersion();
        });

        static::deleted(function (): void {
            MenuItemObserver::bumpVersion();
        });
    }
}
