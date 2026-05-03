<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates the cross-location food-menu public cache when menu item data
 * or pivot relationships change.
 *
 * The cache version key is a simple integer stored under CACHE_VERSION_KEY.
 * The controller builds its remember-key by combining the base key with the
 * current version number. Incrementing the version effectively orphans the
 * old cache entry and forces a fresh DB query on next request.
 */
class MenuItemObserver
{
    public const string CACHE_VERSION_KEY = 'food_menu_public_version';

    public static function bumpVersion(): void
    {
        Cache::increment(self::CACHE_VERSION_KEY);
    }

    public function saved(MenuItem $menuItem): void
    {
        // Skip no-op saves (e.g. ::touch(), or a save() with no dirty columns).
        // Without this guard, every pure-read flow that calls save() would
        // orphan a still-valid cache entry on every request.
        // wasRecentlyCreated covers fresh inserts, where wasChanged() reports
        // no changed columns even though the row is brand new.
        if ($menuItem->wasRecentlyCreated || $menuItem->wasChanged()) {
            self::bumpVersion();
        }
    }

    public function deleted(MenuItem $menuItem): void
    {
        self::bumpVersion();
    }

    /**
     * Called by Eloquent when a pivot row is attached on the MenuItem → locations relation.
     * Registered via MenuItem::observe() + BelongsToMany::$pivotObservers in boot.
     */
    public function pivotAttached(MenuItem $menuItem, string $relationName, array $ids, array $attributes): void
    {
        if ($relationName === 'locations') {
            self::bumpVersion();
        }
    }

    public function pivotDetached(MenuItem $menuItem, string $relationName, array $ids): void
    {
        if ($relationName === 'locations') {
            self::bumpVersion();
        }
    }

    public function pivotUpdated(MenuItem $menuItem, string $relationName, array $ids, array $attributes): void
    {
        if ($relationName === 'locations') {
            self::bumpVersion();
        }
    }
}
